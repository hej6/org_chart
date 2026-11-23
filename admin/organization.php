<?php
/**
 * Organization management page.
 * Admins only
 */

require_once __DIR__ . '/../php/session.php';
require_once __DIR__ . '/../php/path.php';

// Stop unauthorized access
if (!isset($user_role) || (int) $user_role !== 1) {
    $_SESSION['message'] = 'Access Denied';
    header('Location: ' . SRC_PATH . '/logout.php');
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once ROOT_PATH . '/php/config.php';

$page_name = 'admin';
$error = '';
$org_name = '';
$edit_id = 0;

// Protect forms against requests submitted from another website.
if (empty($_SESSION['organization_token'])) {
    $_SESSION['organization_token'] = bin2hex(random_bytes(32));
}

function escapeOrganization($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$success = $_SESSION['organization_success'] ?? '';
unset($_SESSION['organization_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $action = $_POST['action'] ?? '';

    $org_id = filter_var(
        $_POST['org_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $org_name = is_string($_POST['org_name'] ?? null)
        ? trim($_POST['org_name'])
        : '';

    if ($action === 'update' && $org_id !== false) {
        $edit_id = $org_id;
    }

    if (!is_string($token) ||
        !hash_equals($_SESSION['organization_token'], $token)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (!in_array($action, ['add', 'update', 'delete'], true)) {
        $error = 'Invalid action.';
    } elseif ($action !== 'add' && $org_id === false) {
        $error = 'Please select a valid organization.';
    } elseif (
        $action !== 'delete' &&
        ($org_name === '' || mb_strlen($org_name, 'UTF-8') > 50)
    ) {
        $error = 'Enter an organization name containing 1 to 50 characters.';
    } else {
        try {
            if ($action === 'add') {
                $stmt = $db_connection->prepare(
                    'INSERT INTO Organization (org_name) VALUES (?)'
                );
                $stmt->bind_param('s', $org_name);
                $stmt->execute();
                $stmt->close();

                $_SESSION['organization_success'] = 'Organization added.';
            } elseif ($action === 'update') {
                $stmt = $db_connection->prepare(
                    'UPDATE Organization
                     SET org_name = ?
                     WHERE org_id = ?'
                );
                $stmt->bind_param('si', $org_name, $org_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['organization_success'] =
                    'Update completed. The list shows the current records.';
            } else {
                $stmt = $db_connection->prepare(
                    'DELETE FROM Organization WHERE org_id = ?'
                );
                $stmt->bind_param('i', $org_id);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();

                $_SESSION['organization_success'] = $deleted > 0
                    ? 'Organization deleted.'
                    : 'That organization no longer exists.';
            }

            // Redirect so refreshing does not repeat a database change.
            header('Location: organization.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1451) {
                $error = 'This organization is used by another record and cannot be deleted.';
            } elseif ((int) $e->getCode() === 1062) {
                $error = 'An organization with that name already exists.';
            } else {
                error_log($e->getMessage());
                $error = 'The change could not be saved. Please try again.';
            }
        }
    }
}

// Load the selected organization into the edit form.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $requested_id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($requested_id === false) {
        $error = 'Invalid organization ID.';
    } else {
        $stmt = $db_connection->prepare(
            'SELECT org_id, org_name
             FROM Organization
             WHERE org_id = ?'
        );
        $stmt->bind_param('i', $requested_id);
        $stmt->execute();
        $organization = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($organization) {
            $edit_id = (int) $organization['org_id'];
            $org_name = $organization['org_name'];
        } else {
            $error = 'Organization not found.';
        }
    }
}

$organizations = $db_connection->query(
    'SELECT org_id, org_name
     FROM Organization
     ORDER BY org_name'
);
?>
<!doctype html>
<html lang="en">
<head>
    <?php require ROOT_PATH . '/include/head.php'; ?>
    <title>Manage Organizations</title>
</head>
<body class="<?php echo escapeOrganization($page_name); ?>">
<?php require ROOT_PATH . '/include/header.php'; ?>

<main class="container">
    <h1>Manage Organizations</h1>
    <p><a href="index.php">Back to dashboard</a></p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?php echo escapeOrganization($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo escapeOrganization($error); ?>
        </div>
    <?php endif; ?>

    <section class="mb-4">
        <h2><?php echo $edit_id > 0 ? 'Edit Organization' : 'Add Organization'; ?></h2>

        <form method="post" action="organization.php">
            <input type="hidden" name="token"
                   value="<?php echo escapeOrganization($_SESSION['organization_token']); ?>">

            <input type="hidden" name="action"
                   value="<?php echo $edit_id > 0 ? 'update' : 'add'; ?>">

            <input type="hidden" name="org_id"
                   value="<?php echo $edit_id; ?>">

            <div class="mb-3">
                <label for="org_name" class="form-label">
                    Organization name
                </label>
                <input type="text"
                       class="form-control"
                       id="org_name"
                       name="org_name"
                       maxlength="50"
                       required
                       value="<?php echo escapeOrganization($org_name); ?>">
            </div>

            <button type="submit" class="btn btn-primary">
                <?php echo $edit_id > 0 ? 'Save Changes' : 'Add Organization'; ?>
            </button>

            <?php if ($edit_id > 0): ?>
                <a href="organization.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Existing Organizations</h2>

        <div class="table-responsive">
            <table class="table">
                <caption>Organizations available in the organizational chart system</caption>
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Organization name</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($organizations->num_rows === 0): ?>
                    <tr>
                        <td colspan="3">No organizations found.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $organizations->fetch_assoc()): ?>
                        <tr>
                            <th scope="row">
                                <?php echo (int) $row['org_id']; ?>
                            </th>
                            <td>
                                <?php echo escapeOrganization($row['org_name']); ?>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-primary"
                                   href="organization.php?edit=<?php echo (int) $row['org_id']; ?>"
                                   aria-label="Edit <?php echo escapeOrganization($row['org_name']); ?>">
                                    Edit
                                </a>

                                <form method="post"
                                      action="organization.php"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this organization?');">
                                    <input type="hidden" name="token"
                                           value="<?php echo escapeOrganization($_SESSION['organization_token']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="org_id"
                                           value="<?php echo (int) $row['org_id']; ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            aria-label="Delete <?php echo escapeOrganization($row['org_name']); ?>">
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