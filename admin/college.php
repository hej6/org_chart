<?php
/**
 * College management page.
 * ADMIN only
 */

require_once __DIR__ . '/../php/session.php';
require_once __DIR__ . '/../php/path.php';

// Stop unauthorized access before displaying or changing anything
if (!isset($user_role) || (int) $user_role !== 1) {
    $_SESSION['message'] = 'Access Denied';
    header('Location: ' . SRC_PATH . '/logout.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once ROOT_PATH . '/php/config.php';

$page_name = 'admin';
$error = '';
$college_name = '';
$edit_id = 0;

// Protect forms against requests submitted from another website. Not necessary but nice to have.
if (empty($_SESSION['college_token'])) {
    $_SESSION['college_token'] = bin2hex(random_bytes(32));
}

function escapeCollege($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$success = $_SESSION['college_success'] ?? '';
unset($_SESSION['college_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $action = $_POST['action'] ?? '';

    $college_id = filter_var(
        $_POST['college_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $college_name = is_string($_POST['college_name'] ?? null)
        ? trim($_POST['college_name'])
        : '';

    if ($action === 'update' && $college_id !== false) {
        $edit_id = $college_id;
    }

    if (!is_string($token) ||
        !hash_equals($_SESSION['college_token'], $token)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (!in_array($action, ['add', 'update', 'delete'], true)) {
        $error = 'Invalid action.';
    } elseif ($action !== 'add' && $college_id === false) {
        $error = 'Please select a valid college.';
    } elseif (
        $action !== 'delete' &&
        ($college_name === '' || mb_strlen($college_name, 'UTF-8') > 50)
    ) {
        $error = 'Enter a college name containing 1 to 50 characters.';
    } else {
        try {
            if ($action === 'add') {
                $stmt = $db_connection->prepare(
                    'INSERT INTO College (college_name) VALUES (?)'
                );
                $stmt->bind_param('s', $college_name);
                $stmt->execute();
                $stmt->close();

                $_SESSION['college_success'] = 'College added.';
            } elseif ($action === 'update') {
                $stmt = $db_connection->prepare(
                    'UPDATE College
                     SET college_name = ?
                     WHERE college_id = ?'
                );
                $stmt->bind_param('si', $college_name, $college_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['college_success'] =
                    'Update completed. The list shows the current records.';
            } else {
                $stmt = $db_connection->prepare(
                    'DELETE FROM College WHERE college_id = ?'
                );
                $stmt->bind_param('i', $college_id);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();

                $_SESSION['college_success'] = $deleted > 0
                    ? 'College deleted.'
                    : 'That college no longer exists.';
            }

            // Redirect so refreshing does not repeat a database change.
            header('Location: college.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1451) {
                $error = 'This college is used by another record and cannot be deleted.';
            } elseif ((int) $e->getCode() === 1062) {
                $error = 'A college with that name already exists.';
            } else {
                error_log($e->getMessage());
                $error = 'The change could not be saved. Please try again.';
            }
        }
    }
}

// Load the selected college into the edit form.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $requested_id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($requested_id === false) {
        $error = 'Invalid college ID.';
    } else {
        $stmt = $db_connection->prepare(
            'SELECT college_id, college_name
             FROM College
             WHERE college_id = ?'
        );
        $stmt->bind_param('i', $requested_id);
        $stmt->execute();
        $college = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($college) {
            $edit_id = (int) $college['college_id'];
            $college_name = $college['college_name'];
        } else {
            $error = 'College not found.';
        }
    }
}

$colleges = $db_connection->query(
    'SELECT college_id, college_name
     FROM College
     ORDER BY college_name'
);
?>
<!doctype html>
<html lang="en">
<head>
    <?php require ROOT_PATH . '/include/head.php'; ?>
    <title>Manage Colleges</title>
</head>
<body class="<?php echo escapeCollege($page_name); ?>">
<?php require ROOT_PATH . '/include/header.php'; ?>

<main class="container">
    <h1>Manage Colleges</h1>
    <p><a href="index.php">Back to dashboard</a></p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?php echo escapeCollege($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo escapeCollege($error); ?>
        </div>
    <?php endif; ?>

    <section class="mb-4">
        <h2><?php echo $edit_id > 0 ? 'Edit College' : 'Add College'; ?></h2>

        <form method="post" action="college.php">
            <input type="hidden" name="token"
                   value="<?php echo escapeCollege($_SESSION['college_token']); ?>">

            <input type="hidden" name="action"
                   value="<?php echo $edit_id > 0 ? 'update' : 'add'; ?>">

            <input type="hidden" name="college_id"
                   value="<?php echo $edit_id; ?>">

            <div class="mb-3">
                <label for="college_name" class="form-label">
                    College name
                </label>
                <input type="text"
                       class="form-control"
                       id="college_name"
                       name="college_name"
                       maxlength="50"
                       required
                       value="<?php echo escapeCollege($college_name); ?>">
            </div>

            <button type="submit" class="btn btn-primary">
                <?php echo $edit_id > 0 ? 'Save Changes' : 'Add College'; ?>
            </button>

            <?php if ($edit_id > 0): ?>
                <a href="college.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Existing Colleges</h2>

        <div class="table-responsive">
            <table class="table">
                <caption>Colleges available in the organizational chart system</caption>
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">College name</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($colleges->num_rows === 0): ?>
                    <tr>
                        <td colspan="3">No colleges found.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $colleges->fetch_assoc()): ?>
                        <tr>
                            <th scope="row">
                                <?php echo (int) $row['college_id']; ?>
                            </th>
                            <td>
                                <?php echo escapeCollege($row['college_name']); ?>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-primary"
                                   href="college.php?edit=<?php echo (int) $row['college_id']; ?>"
                                   aria-label="Edit <?php echo escapeCollege($row['college_name']); ?>">
                                    Edit
                                </a>

                                <form method="post"
                                      action="college.php"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this college?');">
                                    <input type="hidden" name="token"
                                           value="<?php echo escapeCollege($_SESSION['college_token']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="college_id"
                                           value="<?php echo (int) $row['college_id']; ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            aria-label="Delete <?php echo escapeCollege($row['college_name']); ?>">
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