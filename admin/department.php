<?php
/**
 * Department management page.
 * ADNINS ONLY
 * A department sets the organization, building, and college for all of its staff.
 */

require_once __DIR__ . '/../php/session.php';
require_once __DIR__ . '/../php/path.php';

// Stop unauthorized access before displaying or changing anything. Not necessary but nice to have.
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
$form = ['dept_name' => '', 'org_id' => '', 'building_id' => '', 'college_id' => ''];

// Protect forms against requests submitted from another website.
if (empty($_SESSION['department_token'])) {
    $_SESSION['department_token'] = bin2hex(random_bytes(32));
}

function escapeDepartment($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function departmentId($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

// Dropdown choices: key => [label, required, query]
$fields = [
    'org_id' => [
        'Organization', true,
        'SELECT org_id AS id, org_name AS label FROM Organization ORDER BY org_name'
    ],
    'building_id' => [
        'Building', false,
        'SELECT building_id AS id, building_name AS label FROM Building ORDER BY building_name'
    ],
    'college_id' => [
        'College', false,
        'SELECT college_id AS id, college_name AS label FROM College ORDER BY college_name'
    ],
];

$options = [];
foreach ($fields as $key => [$label, $required, $sql]) {
    $options[$key] = [];
    foreach ($db_connection->query($sql) as $row) {
        $options[$key][(int) $row['id']] = $row['label'];
    }
}

$success = $_SESSION['department_success'] ?? '';
unset($_SESSION['department_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $action = $_POST['action'] ?? '';
    $dept_id = departmentId($_POST['dept_id'] ?? '');

    foreach (array_keys($form) as $key) {
        $form[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }

    if ($action === 'update' && $dept_id !== false) {
        $edit_id = $dept_id;
    }

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
        $selected = departmentId($form[$key]);
        if ($selected === false || !isset($options[$key][$selected])) {
            $dropdown_error = "Choose a valid $label.";
            continue;
        }
        $ids[$key] = $selected;
    }

    if (!is_string($token) ||
        !hash_equals($_SESSION['department_token'], $token)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (!in_array($action, ['add', 'update', 'delete'], true)) {
        $error = 'Invalid action.';
    } elseif ($action !== 'add' && $dept_id === false) {
        $error = 'Please select a valid department.';
    } elseif (
        $action !== 'delete' &&
        ($form['dept_name'] === '' || mb_strlen($form['dept_name'], 'UTF-8') > 255)
    ) {
        $error = 'Enter a department name containing 1 to 255 characters.';
    } elseif ($action !== 'delete' && $dropdown_error !== '') {
        $error = $dropdown_error;
    } else {
        try {
            $name = $form['dept_name'];
            $org = $ids['org_id'];
            $building = $ids['building_id'];
            $college = $ids['college_id'];

            if ($action === 'add') {
                $stmt = $db_connection->prepare(
                    'INSERT INTO Department (dept_name, org_id, building_id, college_id)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param('siii', $name, $org, $building, $college);
                $stmt->execute();
                $stmt->close();

                $_SESSION['department_success'] = 'Department added.';
            } elseif ($action === 'update') {
                $stmt = $db_connection->prepare(
                    'UPDATE Department
                     SET dept_name = ?, org_id = ?, building_id = ?, college_id = ?
                     WHERE dept_id = ?'
                );
                $stmt->bind_param('siiii', $name, $org, $building, $college, $dept_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['department_success'] =
                    'Update completed. The list shows the current records.';
            } else {
                $stmt = $db_connection->prepare(
                    'DELETE FROM Department WHERE dept_id = ?'
                );
                $stmt->bind_param('i', $dept_id);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();

                $_SESSION['department_success'] = $deleted > 0
                    ? 'Department deleted.'
                    : 'That department no longer exists.';
            }

            // Redirect so refreshing does not repeat a database change.
            header('Location: department.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1451) {
                $error = 'This department still has staff members and cannot be deleted. Move or remove its staff first.';
            } elseif ((int) $e->getCode() === 1062) {
                $error = 'A department with that name already exists.';
            } elseif ((int) $e->getCode() === 1452) {
                $error = 'A selected record is no longer available. Reload and try again.';
            } else {
                error_log($e->getMessage());
                $error = 'The change could not be saved. Please try again.';
            }
        }
    }
}

// Load the selected department into the edit form.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $requested_id = departmentId($_GET['edit']);

    if ($requested_id === false) {
        $error = 'Invalid department ID.';
    } else {
        $stmt = $db_connection->prepare(
            'SELECT dept_id, dept_name, org_id, building_id, college_id
             FROM Department
             WHERE dept_id = ?'
        );
        $stmt->bind_param('i', $requested_id);
        $stmt->execute();
        $department = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($department) {
            $edit_id = (int) $department['dept_id'];
            foreach (array_keys($form) as $key) {
                $form[$key] = (string) ($department[$key] ?? '');
            }
        } else {
            $error = 'Department not found.';
        }
    }
}

// Departments with their organization, building, college, and staff count
$departments = $db_connection->query(
    'SELECT d.dept_id, d.dept_name, o.org_name, b.building_name, c.college_name,
            COUNT(s.staff_id) AS staff_count
     FROM Department d
     JOIN Organization o   ON d.org_id = o.org_id
     LEFT JOIN Building b  ON d.building_id = b.building_id
     LEFT JOIN College c   ON d.college_id = c.college_id
     LEFT JOIN Staff s     ON s.dept_id = d.dept_id
     GROUP BY d.dept_id, d.dept_name, o.org_name, b.building_name, c.college_name
     ORDER BY d.dept_name'
);
?>
<!doctype html>
<html lang="en">
<head>
    <?php require ROOT_PATH . '/include/head.php'; ?>
    <title>Manage Departments</title>
</head>
<body class="<?php echo escapeDepartment($page_name); ?>">
<?php require ROOT_PATH . '/include/header.php'; ?>

<main class="container" id="main-content" tabindex="-1">
    <h1>Manage Departments</h1>
    <p><a href="index.php">Back to dashboard</a></p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="status">
            <?php echo escapeDepartment($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo escapeDepartment($error); ?>
        </div>
    <?php endif; ?>

    <section class="mb-4">
        <h2><?php echo $edit_id > 0 ? 'Edit Department' : 'Add Department'; ?></h2>

        <form method="post" action="department.php">
            <input type="hidden" name="token"
                   value="<?php echo escapeDepartment($_SESSION['department_token']); ?>">

            <input type="hidden" name="action"
                   value="<?php echo $edit_id > 0 ? 'update' : 'add'; ?>">

            <input type="hidden" name="dept_id"
                   value="<?php echo $edit_id; ?>">

            <div class="mb-3">
                <label for="dept_name" class="form-label">
                    Department name
                </label>
                <input type="text"
                       class="form-control"
                       id="dept_name"
                       name="dept_name"
                       maxlength="255"
                       required
                       value="<?php echo escapeDepartment($form['dept_name']); ?>">
            </div>

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
                                <?php echo escapeDepartment($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">
                <?php echo $edit_id > 0 ? 'Save Changes' : 'Add Department'; ?>
            </button>

            <?php if ($edit_id > 0): ?>
                <a href="department.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>
    </section>

    <section>
        <h2>Existing Departments</h2>

        <div class="table-responsive">
            <table class="table">
                <caption>Departments available in the organizational chart system</caption>
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Department</th>
                        <th scope="col">Organization</th>
                        <th scope="col">Building</th>
                        <th scope="col">College</th>
                        <th scope="col">Staff</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($departments->num_rows === 0): ?>
                    <tr>
                        <td colspan="7">No departments found.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $departments->fetch_assoc()): ?>
                        <tr>
                            <th scope="row"><?php echo (int) $row['dept_id']; ?></th>
                            <td><?php echo escapeDepartment($row['dept_name']); ?></td>
                            <td><?php echo escapeDepartment($row['org_name']); ?></td>
                            <td><?php echo escapeDepartment($row['building_name'] ?? 'None'); ?></td>
                            <td><?php echo escapeDepartment($row['college_name'] ?? 'None'); ?></td>
                            <td><?php echo (int) $row['staff_count']; ?></td>
                            <td>
                                <a class="btn btn-sm btn-primary"
                                   href="department.php?edit=<?php echo (int) $row['dept_id']; ?>"
                                   aria-label="Edit <?php echo escapeDepartment($row['dept_name']); ?>">
                                    Edit
                                </a>

                                <form method="post"
                                      action="department.php"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this department?');">
                                    <input type="hidden" name="token"
                                           value="<?php echo escapeDepartment($_SESSION['department_token']); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="dept_id"
                                           value="<?php echo (int) $row['dept_id']; ?>">
                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            aria-label="Delete <?php echo escapeDepartment($row['dept_name']); ?>">
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
