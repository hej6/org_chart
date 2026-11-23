<?php
/**
 * Rank management page.
 * ADMINS only
 */

require_once __DIR__ . '/../php/session.php';
require_once __DIR__ . '/../php/path.php';

if (!isset($user_role) || (int) $user_role !== 1) {
    $_SESSION['message'] = 'Access Denied';
    header('Location: ' . SRC_PATH . '/logout.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once ROOT_PATH . '/php/config.php';

$page_name = 'admin';
$error = '';
$edit_id = 0;
$form = ['ranks_title' => '', 'min_salary' => '', 'max_salary' => '', 'class_id' => ''];

// Protect forms against requests submitted from another website.
if (empty($_SESSION['ranks_token'])) {
    $_SESSION['ranks_token'] = bin2hex(random_bytes(32));
}

function escapeRank($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rankId($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

// Dropdown choices: key => [label, required, query]
$fields = [
    'class_id' => [
        'Classification', true,
        'SELECT class_id AS id, class_name AS label FROM Classification ORDER BY class_name'
    ],
];

// A salary: 0 to 999999.99 (the DECIMAL(8,2) range), at most 2 decimal places
function rankSalary($value)
{
    $value = str_replace([',', '$'], '', $value);
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $value)) {
        return false;
    }
    return $value;
}

$options = [];
foreach ($fields as $key => [$label, $required, $sql]) {
    $options[$key] = [];
    foreach ($db_connection->query($sql) as $row) {
        $options[$key][(int) $row['id']] = $row['label'];
    }
}

$success = $_SESSION['ranks_success'] ?? '';
unset($_SESSION['ranks_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $action = $_POST['action'] ?? '';
    $ranks_id = rankId($_POST['ranks_id'] ?? '');

    foreach (array_keys($form) as $key) {
        $form[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }

    if ($action === 'update' && $ranks_id !== false) {
        $edit_id = $ranks_id;
    }

    $min = rankSalary($form['min_salary']);
    $max = rankSalary($form['max_salary']);

    // Check each dropdown: required ones must be chosen, and any choice must exist.
    $ids = [];
    $dropdown_error = '';
    foreach ($fields as $key => [$label, $required]) {
        if ($form[$key] === '') {
            $ids[$key] = null;
            if ($required) {
                $dropdown_error = "Choose the $label.";
            }
            continue;
        }
        $selected = rankId($form[$key]);
        if ($selected === false || !isset($options[$key][$selected])) {
            $dropdown_error = "Choose a valid $label.";
            continue;
        }
        $ids[$key] = $selected;
    }

    if (!is_string($token) ||
        !hash_equals($_SESSION['ranks_token'], $token)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (!in_array($action, ['add', 'update', 'delete'], true)) {
        $error = 'Invalid action.';
    } elseif ($action !== 'add' && $ranks_id === false) {
        $error = 'Please select a valid rank.';
    } elseif (
        $action !== 'delete' &&
        ($form['ranks_title'] === '' || mb_strlen($form['ranks_title'], 'UTF-8') > 100)
    ) {
        $error = 'Enter a rank title containing 1 to 100 characters.';
    } elseif ($action !== 'delete' && ($min === false || $max === false)) {
        $error = 'Enter salaries as amounts from 0 to 999999.99, for example 45000 or 45000.50.';
    } elseif ($action !== 'delete' && (float) $min > (float) $max) {
        $error = 'The minimum salary cannot be higher than the maximum salary.';
    } elseif ($action !== 'delete' && $dropdown_error !== '') {
        $error = $dropdown_error;
    } else {
        try {
            $title = $form['ranks_title'];
            $class = $ids['class_id'];

            // Salaries are bound as strings so DECIMAL values stay exact
            if ($action === 'add') {
                $stmt = $db_connection->prepare(
                    'INSERT INTO Ranks (ranks_title, min_salary, max_salary, class_id)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param('sssi', $title, $min, $max, $class);
                $stmt->execute();
                $stmt->close();

                $_SESSION['ranks_success'] = 'Rank added.';
            } elseif ($action === 'update') {
                $stmt = $db_connection->prepare(
                    'UPDATE Ranks
                     SET ranks_title = ?, min_salary = ?, max_salary = ?, class_id = ?
                     WHERE ranks_id = ?'
                );
                $stmt->bind_param('sssii', $title, $min, $max, $class, $ranks_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['ranks_success'] =
                    'Update completed. The list shows the current records.';
            } else {
                $stmt = $db_connection->prepare(
                    'DELETE FROM Ranks WHERE ranks_id = ?'
                );
                $stmt->bind_param('i', $ranks_id);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();

                $_SESSION['ranks_success'] = $deleted > 0
                    ? 'Rank deleted.'
                    : 'That rank no longer exists.';
            }

            // Redirect so refreshing does not repeat a database change.
            header('Location: ranks.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1451) {
                $error = 'This rank is still assigned to staff members and cannot be deleted. Change their rank first.';
            } elseif ((int) $e->getCode() === 4025) {
                $error = 'The minimum salary cannot be higher than the maximum salary.';
            } elseif ((int) $e->getCode() === 1452) {
                $error = 'A selected record is no longer available. Reload and try again.';
            } else {
                error_log($e->getMessage());
                $error = 'The change could not be saved. Please try again.';
            }
        }
    }
}

// Load the selected rank into the edit form.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $requested_id = rankId($_GET['edit']);

    if ($requested_id === false) {
        $error = 'Invalid rank ID.';
    } else {
        $stmt = $db_connection->prepare(
            'SELECT ranks_id, ranks_title, min_salary, max_salary, class_id
             FROM Ranks
             WHERE ranks_id = ?'
        );
        $stmt->bind_param('i', $requested_id);
        $stmt->execute();
        $rank = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($rank) {
            $edit_id = (int) $rank['ranks_id'];
            foreach (array_keys($form) as $key) {
                $form[$key] = (string) ($rank[$key] ?? '');
            }
        } else {
            $error = 'Rank not found.';
        }
    }
}

// Ranks with their classification and staff count
$ranks = $db_connection->query(
    'SELECT r.ranks_id, r.ranks_title, r.min_salary, r.max_salary, cl.class_name,
            COUNT(s.staff_id) AS staff_count
     FROM Ranks r
     JOIN Classification cl ON r.class_id = cl.class_id
     LEFT JOIN Staff s      ON s.rank_id = r.ranks_id
     GROUP BY r.ranks_id, r.ranks_title, r.min_salary, r.max_salary, cl.class_name
     ORDER BY r.ranks_id'
);
?>
<!doctype html>
<html lang="en">
<head>
    <?php require ROOT_PATH . '/include/head.php'; ?>
    <title>Manage Ranks</title>
</head>
<body class="<?php echo escapeRank($page_name); ?>">
<?php require ROOT_PATH . '/include/header.php'; ?>

<main class="container">
    <h1>Manage Ranks</h1>
    <p><a href="index.php">Back to dashboard</a></p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?php echo escapeRank($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo escapeRank($error); ?>
        </div>
    <?php endif; ?>

    <section class="mb-4">
        <h2><?php echo $edit_id > 0 ? 'Edit Rank' : 'Add Rank'; ?></h2>

        <form method="post" action="ranks.php">
            <input type="hidden" name="token"
                   value="<?php echo escapeRank($_SESSION['ranks_token']); ?>">

            <input type="hidden" name="action"
                   value="<?php echo $edit_id > 0 ? 'update' : 'add'; ?>">

            <input type="hidden" name="ranks_id"
                   value="<?php echo $edit_id; ?>">

            <div class="mb-3">
                <label for="ranks_title" class="form-label">
                    Rank title
                </label>
                <input type="text"
                       class="form-control"
                       id="ranks_title"
                       name="ranks_title"
                       maxlength="100"
                       required
                       value="<?php echo escapeRank($form['ranks_title']); ?>">
            </div>

            <?php foreach (['min_salary' => 'Minimum salary', 'max_salary' => 'Maximum salary'] as $key => $label): ?>
                <div class="mb-3">
                    <label for="<?php echo $key; ?>" class="form-label"><?php echo $label; ?></label>
                    <input type="number"
                           class="form-control"
                           id="<?php echo $key; ?>"
                           name="<?php echo $key; ?>"
                           min="0" max="999999.99" step="0.01"
                           required
                           value="<?php echo escapeRank($form[$key]); ?>">
                </div>
            <?php endforeach; ?>

            <?php foreach ($fields as $key => [$label, $required]): ?>
                <div class="mb-3">
                    <label for="<?php echo $key; ?>" class="form-label">
                        <?php echo $label; ?><?php echo $required ? '' : ' (optional)'; ?>
                    </label>
                    <select class="form-select" id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                            <?php echo $required ? 'required' : ''; ?>>
                        <option value=""><?php echo $required ? 'Select an option' : 'None'; ?></option>
                        <?php foreach ($options[$key] as $value => $text): ?>
                            <option value="<?php echo $value; ?>"
                                <?php echo (string) $form[$key] === (string) $value ? 'selected' : ''; ?>>
                                <?php echo escapeRank($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">
                <?php echo $edit_id > 0 ? 'Save Changes' : 'Add Rank'; ?>
            </button>

            <?php if ($edit_id > 0): ?>
                <a href="ranks.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Existing Ranks</h2>

        <div class="table-responsive">
            <table class="table">
                <caption>Ranks available in the organizational chart system</caption>
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Rank</th>
                        <th scope="col">Classification</th>
                        <th scope="col">Minimum salary</th>
                        <th scope="col">Maximum salary</th>
                        <th scope="col">Staff</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($ranks->num_rows === 0): ?>
                    <tr>
                        <td colspan="7">No ranks found.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $ranks->fetch_assoc()): ?>
                        <tr>
                            <th scope="row"><?php echo (int) $row['ranks_id']; ?></th>
                            <td><?php echo escapeRank($row['ranks_title']); ?></td>
                            <td><?php echo escapeRank($row['class_name']); ?></td>
                            <td>$<?php echo number_format((float) $row['min_salary'], 2); ?></td>
                            <td>$<?php echo number_format((float) $row['max_salary'], 2); ?></td>
                            <td><?php echo (int) $row['staff_count']; ?></td>
                            <td>
                                <a class="btn btn-sm btn-primary"
                                   href="ranks.php?edit=<?php echo (int) $row['ranks_id']; ?>"
                                   aria-label="Edit rank <?php echo escapeRank($row['ranks_title']); ?>">
                                    Edit
                                </a>

                                <form method="post"
                                      action="ranks.php"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this rank?');">
                                    <input type="hidden" name="token"
                                           value="<?php echo escapeRank($_SESSION['ranks_token']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="ranks_id"
                                           value="<?php echo (int) $row['ranks_id']; ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            aria-label="Delete rank <?php echo escapeRank($row['ranks_title']); ?>">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require ROOT_PATH . '/include/footer.php'; ?>
</body>
</html>
