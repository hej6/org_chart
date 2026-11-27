<?php
/**
 * Classification management page.
 * ADMINS only
 */

require_once __DIR__ . '/../php/session.php';
require_once __DIR__ . '/../php/path.php';

// Security
if (!isset($user_role) || (int) $user_role !== 1) {
    $_SESSION['message'] = 'Access Denied';
    header('Location: ' . SRC_PATH . '/logout.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once ROOT_PATH . '/php/config.php';

$page_name = 'admin';
$error = '';
$class_name = '';
$edit_id = 0;

// Protect forms against requests submitted from another website.
if (empty($_SESSION['classification_token'])) {
    $_SESSION['classification_token'] = bin2hex(random_bytes(32));
}

function escapeClassification($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$success = $_SESSION['classification_success'] ?? '';
unset($_SESSION['classification_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $action = $_POST['action'] ?? '';

    $class_id = filter_var(
        $_POST['class_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $class_name = is_string($_POST['class_name'] ?? null)
        ? trim($_POST['class_name'])
        : '';

    if ($action === 'update' && $class_id !== false) {
        $edit_id = $class_id;
    }

    if (!is_string($token) ||
        !hash_equals($_SESSION['classification_token'], $token)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (!in_array($action, ['add', 'update', 'delete'], true)) {
        $error = 'Invalid action.';
    } elseif ($action !== 'add' && $class_id === false) {
        $error = 'Please select a valid classification.';
    } elseif (
        $action !== 'delete' &&
        ($class_name === '' || mb_strlen($class_name, 'UTF-8') > 100)
    ) {
        $error = 'Enter a classification name containing 1 to 100 characters.';
    } else {
        try {
            if ($action === 'add') {
                $stmt = $db_connection->prepare(
                    'INSERT INTO Classification (class_name) VALUES (?)'
                );
                $stmt->bind_param('s', $class_name);
                $stmt->execute();
                $stmt->close();

                $_SESSION['classification_success'] = 'Classification added.';
            } elseif ($action === 'update') {
                $stmt = $db_connection->prepare(
                    'UPDATE Classification
                     SET class_name = ?
                     WHERE class_id = ?'
                );
                $stmt->bind_param('si', $class_name, $class_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['classification_success'] =
                    'Update completed. The list shows the current records.';
            } else {
                $stmt = $db_connection->prepare(
                    'DELETE FROM Classification WHERE class_id = ?'
                );
                $stmt->bind_param('i', $class_id);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();

                $_SESSION['classification_success'] = $deleted > 0
                    ? 'Classification deleted.'
                    : 'That classification no longer exists.';
            }

            // Redirect
            header('Location: classification.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1451) {
                $error = 'This classification is used by another record and cannot be deleted.';
            } elseif ((int) $e->getCode() === 1062) {
                $error = 'A classification with that name already exists.';
            } else {
                error_log($e->getMessage());
                $error = 'The change could not be saved. Please try again.';
            }
        }
    }
}

// Load the selected classification into the edit form.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $requested_id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($requested_id === false) {
        $error = 'Invalid classification ID.';
    } else {
        $stmt = $db_connection->prepare(
            'SELECT class_id, class_name
             FROM Classification
             WHERE class_id = ?'
        );
        $stmt->bind_param('i', $requested_id);
        $stmt->execute();
        $classification = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($classification) {
            $edit_id = (int) $classification['class_id'];
            $class_name = $classification['class_name'];
        } else {
            $error = 'Classification not found.';
        }
    }
}

$classifications = $db_connection->query(
    'SELECT class_id, class_name
     FROM Classification
     ORDER BY class_name'
);
?>
<!doctype html>
<html lang="en">
<head>
    <?php require ROOT_PATH . '/include/head.php'; ?>
    <title>Manage Classifications</title>
</head>
<body class="<?php echo escapeClassification($page_name); ?>">
<?php require ROOT_PATH . '/include/header.php'; ?>

<main class="container" id="main-content" tabindex="-1">
    <h1>Manage Classifications</h1>
    <p><a href="index.php">Back to dashboard</a></p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?php echo escapeClassification($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo escapeClassification($error); ?>
        </div>
    <?php endif; ?>

    <section class="mb-4">
        <h2><?php echo $edit_id > 0 ? 'Edit Classification' : 'Add Classification'; ?></h2>

        <form method="post" action="classification.php">
            <input type="hidden" name="token"
                   value="<?php echo escapeClassification($_SESSION['classification_token']); ?>">

            <input type="hidden" name="action"
                   value="<?php echo $edit_id > 0 ? 'update' : 'add'; ?>">

            <input type="hidden" name="class_id"
                   value="<?php echo $edit_id; ?>">

            <div class="mb-3">
                <label for="class_name" class="form-label">
                    Classification name
                </label>
                <input type="text"
                       class="form-control"
                       id="class_name"
                       name="class_name"
                       maxlength="100"
                       required
                       value="<?php echo escapeClassification($class_name); ?>">
            </div>

            <button type="submit" class="btn btn-primary">
                <?php echo $edit_id > 0 ? 'Save Changes' : 'Add Classification'; ?>
            </button>

            <?php if ($edit_id > 0): ?>
                <a href="classification.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Existing Classifications</h2>

        <div class="table-responsive">
            <table class="table">
                <caption>Classifications available in the organizational chart system</caption>
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Classification name</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($classifications->num_rows === 0): ?>
                    <tr>
                        <td colspan="3">No classifications found.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $classifications->fetch_assoc()): ?>
                        <tr>
                            <th scope="row">
                                <?php echo (int) $row['class_id']; ?>
                            </th>
                            <td>
                                <?php echo escapeClassification($row['class_name']); ?>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-primary"
                                   href="classification.php?edit=<?php echo (int) $row['class_id']; ?>"
                                   aria-label="Edit <?php echo escapeClassification($row['class_name']); ?>">
                                    Edit
                                </a>

                                <form method="post"
                                      action="classification.php"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this classification?');">
                                    <input type="hidden" name="token"
                                           value="<?php echo escapeClassification($_SESSION['classification_token']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="class_id"
                                           value="<?php echo (int) $row['class_id']; ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            aria-label="Delete <?php echo escapeClassification($row['class_name']); ?>">
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