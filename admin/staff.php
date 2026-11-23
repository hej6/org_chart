<?php
require_once __DIR__ . '/../php/session.php';
require_once __DIR__ . '/../php/path.php';

if ((int) ($user_role ?? 0) !== 1) {
    $_SESSION['message'] = 'Access Denied';
    header('Location: ' . SRC_PATH . '/logout.php');
    exit;
}

require_once ROOT_PATH . '/php/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$page_name = 'admin';
$error = '';
$success = $_SESSION['staff_success'] ?? '';
unset($_SESSION['staff_success']);
$_SESSION['staff_token'] ??= bin2hex(random_bytes(32));

function staffEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staffId($value)
{
    return filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
}

$fields = [
    'job_id' => [
        'Job', true,
        'SELECT job_id AS id, job_title AS label FROM Job ORDER BY job_title'
    ],
    'rank_id' => [
        'Rank', true,
        'SELECT ranks_id AS id, ranks_title AS label FROM Ranks ORDER BY ranks_title'
    ],
    'supervisor_id' => [
        'Supervisor', false,
        "SELECT staff_id AS id, CONCAT(first_name, ' ', last_name) AS label
         FROM Staff ORDER BY last_name, first_name"
    ],
    'dept_id' => [
        'Department', true,
        'SELECT dept_id AS id, dept_name AS label FROM Department ORDER BY dept_name'
    ],
];

$options = [];
foreach ($fields as $key => [$label, $required, $sql]) {
    $options[$key] = [];
    foreach ($db_connection->query($sql) as $row) {
        $options[$key][(int) $row['id']] = $row['label'];
    }
}

$staff = [];
foreach ($db_connection->query(
    'SELECT * FROM Staff ORDER BY last_name, first_name, staff_id'
) as $row) {
    $staff[(int) $row['staff_id']] = $row;
}

$editId = 0;
$restoreId = 0;      // archive_id being restored, if any
$restoreNotes = [];  // fields that could not be matched when restoring
$form = array_fill_keys(
    array_merge(['first_name', 'last_name', 'email'], array_keys($fields)),
    ''
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['token'] ?? '';
    $id = staffId($_POST['staff_id'] ?? '');

    try {
        if (!is_string($token) || !hash_equals($_SESSION['staff_token'], $token)) {
            throw new InvalidArgumentException('Reload the page and try again.');
        }

        if (!in_array($action, ['add', 'update', 'delete', 'archive', 'restore'], true)) {
            throw new InvalidArgumentException('Invalid staff action.');
        }

        if (in_array($action, ['update', 'delete', 'archive'], true) &&
            ($id === false || !isset($staff[$id]))) {
            throw new InvalidArgumentException('That staff record no longer exists.');
        }

        // Restore: the archived record must still exist
        $archiveRow = null;
        if ($action === 'restore') {
            $restoreId = staffId($_POST['archive_id'] ?? '') ?: 0;
            $stmt = $db_connection->prepare('SELECT * FROM ArchivedStaff WHERE archive_id = ?');
            $stmt->bind_param('i', $restoreId);
            $stmt->execute();
            $archiveRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$archiveRow) {
                $restoreId = 0;
                throw new InvalidArgumentException('That archived record no longer exists.');
            }
        }

        if ($action === 'delete') {
            $stmt = $db_connection->prepare('DELETE FROM Staff WHERE staff_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $_SESSION['staff_success'] = $stmt->affected_rows
                ? 'Staff member deleted.'
                : 'That staff record no longer exists.';
        } elseif ($action === 'archive') {
            // Copy the staff member into ArchivedStaff, then remove them from Staff.
            // One transaction, so they can never end up in both tables or neither.
            $db_connection->begin_transaction();
            try {
                $archivedBy = (string) ($_SESSION['user_name'] ?? '');
                $stmt = $db_connection->prepare(
                    'INSERT INTO ArchivedStaff
                     (staff_id, first_name, last_name, email, job_title, ranks_title,
                      class_name, dept_name, org_name, college_name, supervisor_name,
                      job_id, rank_id, dept_id, supervisor_id, archived_by)
                     SELECT v.staff_id, v.first_name, v.last_name, v.email, v.job_title,
                            v.ranks_title, v.class_name, v.dept_name, v.org_name,
                            v.college_name, v.supervisor_name,
                            v.job_id, v.ranks_id, v.dept_id, s.supervisor_id, ?
                     FROM vw_staff_directory v
                     JOIN Staff s ON s.staff_id = v.staff_id
                     WHERE v.staff_id = ?'
                );
                $stmt->bind_param('si', $archivedBy, $id);
                $stmt->execute();
                $stmt->close();

                // Fails with error 1451 if they still supervise someone
                $stmt = $db_connection->prepare('DELETE FROM Staff WHERE staff_id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $db_connection->commit();
            } catch (mysqli_sql_exception $e) {
                $db_connection->rollback();
                throw $e;
            }
            $_SESSION['staff_success'] = 'Staff member archived.';
        } else {
            $editId = $action === 'update' ? $id : 0;

            foreach (array_keys($form) as $key) {
                $value = $_POST[$key] ?? '';
                $form[$key] = is_string($value) ? trim($value) : '';
            }

            $first = $form['first_name'];
            $last = $form['last_name'];
            $email = $form['email'];

            if ($first === '' || $last === '' ||
                mb_strlen($first, 'UTF-8') > 50 ||
                mb_strlen($last, 'UTF-8') > 50) {
                throw new InvalidArgumentException(
                    'Enter a first and last name of up to 50 characters each.'
                );
            }

            if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }

            $ids = [];
            foreach ($fields as $key => [$label, $required]) {
                if (!$required && $form[$key] === '') {
                    $ids[$key] = null;
                    continue;
                }

                $selected = staffId($form[$key]);
                if ($selected === false || !isset($options[$key][$selected])) {
                    throw new InvalidArgumentException("Choose a valid option for $label.");
                }

                $ids[$key] = $selected;
            }

            $supervisor = $ids['supervisor_id'];
            $job = $ids['job_id'];
            $rank = $ids['rank_id'];
            $department = $ids['dept_id'];

            // Follow the reporting chain to prevent a circular assignment.
            $visited = [];
            for ($current = $supervisor; $current !== null;
                 $current = $staff[$current]['supervisor_id'] ?? null) {
                $current = (int) $current;
                if ($current === $editId || isset($visited[$current])) {
                    throw new InvalidArgumentException(
                        'Choose a supervisor whose reporting chain does not loop back.'
                    );
                }
                $visited[$current] = true;
            }

            if ($action === 'restore') {
                // Put them back with their original staff_id (AUTO_INCREMENT never
                // reuses IDs, so it is normally still free), then remove the archive row.
                $originalId = (int) $archiveRow['staff_id'];
                $newId = isset($staff[$originalId]) ? null : $originalId;
                $db_connection->begin_transaction();
                try {
                    $stmt = $db_connection->prepare(
                        'INSERT INTO Staff
                         (staff_id, first_name, last_name, email, supervisor_id, job_id,
                          rank_id, dept_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param(
                        'isssiiii', $newId, $first, $last, $email, $supervisor,
                        $job, $rank, $department
                    );
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $db_connection->prepare('DELETE FROM ArchivedStaff WHERE archive_id = ?');
                    $stmt->bind_param('i', $restoreId);
                    $stmt->execute();
                    $db_connection->commit();
                } catch (mysqli_sql_exception $e) {
                    $db_connection->rollback();
                    throw $e;
                }
            } elseif ($action === 'add') {
                $stmt = $db_connection->prepare(
                    'INSERT INTO Staff
                     (first_name, last_name, email, supervisor_id, job_id,
                      rank_id, dept_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'sssiiii', $first, $last, $email, $supervisor,
                    $job, $rank, $department
                );
            } else {
                $stmt = $db_connection->prepare(
                    'UPDATE Staff SET first_name = ?, last_name = ?, email = ?,
                     supervisor_id = ?, job_id = ?, rank_id = ?,
                     dept_id = ? WHERE staff_id = ?'
                );
                $stmt->bind_param(
                    'sssiiiii', $first, $last, $email, $supervisor,
                    $job, $rank, $department, $id
                );
            }

            if ($action !== 'restore') {
                $stmt->execute();
            }
            $_SESSION['staff_success'] = [
                'add' => 'Staff member added.',
                'update' => 'Staff update completed.',
                'restore' => 'Staff member restored to the org chart.',
            ][$action];
        }

        $stmt->close();
        header('Location: staff.php');
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() === 1451) {
            $error = 'This staff member is still referenced by another record. Reassign their direct reports before deleting or archiving them.';
        } elseif ((int) $e->getCode() === 1062) {
            $error = 'Another staff member already uses that email address.';
        } elseif ((int) $e->getCode() === 1452) {
            $error = 'A selected record is no longer available. Reload and try again.';
        } else {
            error_log($e->getMessage());
            $error = 'The staff record could not be saved.';
        }
    }
} elseif (isset($_GET['edit'])) {
    $id = staffId($_GET['edit']);
    if ($id !== false && isset($staff[$id])) {
        $editId = $id;
        $form = array_replace($form, array_intersect_key($staff[$id], $form));
    } else {
        $error = 'Staff member not found.';
    }
} elseif (isset($_GET['restore'])) {
    // Pre-fill the form from an archived record. Match each choice by its
    // original ID first, then by name; anything unmatched is left for the admin.
    $restoreId = staffId($_GET['restore']) ?: 0;
    $stmt = $db_connection->prepare('SELECT * FROM ArchivedStaff WHERE archive_id = ?');
    $stmt->bind_param('i', $restoreId);
    $stmt->execute();
    $archiveRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$archiveRow) {
        $restoreId = 0;
        $error = 'Archived record not found.';
    } else {
        $form['first_name'] = $archiveRow['first_name'];
        $form['last_name'] = $archiveRow['last_name'];
        $form['email'] = $archiveRow['email'];

        $matches = [
            // form field => [original ID, original name]
            'job_id' => [$archiveRow['job_id'], $archiveRow['job_title']],
            'rank_id' => [$archiveRow['rank_id'], $archiveRow['ranks_title']],
            'dept_id' => [$archiveRow['dept_id'], $archiveRow['dept_name']],
            'supervisor_id' => [$archiveRow['supervisor_id'], $archiveRow['supervisor_name']],
        ];
        foreach ($matches as $key => [$oldId, $oldName]) {
            if ($oldId === null && $oldName === null) {
                continue; // nothing to match (e.g. they had no supervisor)
            }
            if ($oldId !== null && isset($options[$key][(int) $oldId])) {
                $form[$key] = (string) $oldId;
            } elseif ($oldName !== null && ($found = array_search($oldName, $options[$key], true)) !== false) {
                $form[$key] = (string) $found;
            } else {
                $restoreNotes[] = $fields[$key][0] . ' (was ' . ($oldName ?? 'unknown') . ')';
            }
        }
    }
}

// Archived staff, newest first
$archived = $db_connection->query(
    'SELECT * FROM ArchivedStaff ORDER BY archived_at DESC, archive_id DESC'
)->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <?php require_once ROOT_PATH . '/include/head.php'; ?>
</head>
<body class="admin">
<?php require_once ROOT_PATH . '/include/header.php'; ?>

<main class="container">
    <h1>Manage Staff</h1>
    <p><a href="index.php">Back to dashboard</a></p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success" role="status"><?= staffEscape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-danger" role="alert"><?= staffEscape($error) ?></p>
    <?php endif; ?>

    <h2><?= $restoreId ? 'Restore Staff Member' : ($editId ? 'Edit Staff Member' : 'Add Staff Member') ?></h2>
    <?php if ($restoreId && $restoreNotes): ?>
        <p class="alert alert-warning" role="status">
            These no longer exist, so choose a replacement:
            <?= staffEscape(implode(', ', $restoreNotes)) ?>
        </p>
    <?php elseif ($restoreId): ?>
        <p class="text-muted">Check the details below, then restore them to the org chart.</p>
    <?php endif; ?>
    <form method="post" action="staff.php" class="mb-4">
        <input type="hidden" name="token" value="<?= staffEscape($_SESSION['staff_token']) ?>">
        <input type="hidden" name="action" value="<?= $restoreId ? 'restore' : ($editId ? 'update' : 'add') ?>">
        <input type="hidden" name="staff_id" value="<?= $editId ?>">
        <input type="hidden" name="archive_id" value="<?= $restoreId ?>">

        <?php foreach ([
            'first_name' => ['First name', 'text', 50],
            'last_name' => ['Last name', 'text', 50],
            'email' => ['Email', 'email', 255],
        ] as $key => [$label, $type, $limit]): ?>
            <div class="mb-3">
                <label class="form-label" for="<?= $key ?>"><?= $label ?></label>
                <input class="form-control" id="<?= $key ?>" name="<?= $key ?>"
                       type="<?= $type ?>" maxlength="<?= $limit ?>"
                       value="<?= staffEscape($form[$key]) ?>" required>
            </div>
        <?php endforeach; ?>

        <?php foreach ($fields as $key => [$label, $required]): ?>
            <div class="mb-3">
                <label class="form-label" for="<?= $key ?>">
                    <?= $label ?><?= $required ? '' : ' (optional)' ?>
                </label>
                <select class="form-select" id="<?= $key ?>" name="<?= $key ?>"
                        <?= $required ? 'required' : '' ?>>
                    <option value=""><?= $required ? 'Select an option' : 'None' ?></option>
                    <?php foreach ($options[$key] as $value => $text): ?>
                        <?php if ($key === 'supervisor_id' && $value === $editId) continue; ?>
                        <option value="<?= $value ?>"
                            <?= (string) $form[$key] === (string) $value ? 'selected' : '' ?>>
                            <?= staffEscape($text) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>

        <button class="btn btn-primary" type="submit">
            <?= $restoreId ? 'Restore Staff Member' : ($editId ? 'Save Changes' : 'Add Staff Member') ?>
        </button>
        <?php if ($editId || $restoreId): ?>
            <a href="staff.php">Cancel</a>
        <?php endif; ?>
    </form>

    <h2>Existing Staff</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <?php foreach ($fields as [$label]): ?>
                        <th scope="col"><?= $label ?></th>
                    <?php endforeach; ?>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$staff): ?>
                <tr><td colspan="7">No staff members found.</td></tr>
            <?php endif; ?>
            <?php foreach ($staff as $id => $row): ?>
                <?php $name = $row['first_name'] . ' ' . $row['last_name']; ?>
                <tr>
                    <th scope="row"><?= staffEscape($name) ?></th>
                    <td><?= staffEscape($row['email']) ?></td>
                    <?php foreach ($fields as $key => $field): ?>
                        <td><?= staffEscape($options[$key][$row[$key]] ?? 'None') ?></td>
                    <?php endforeach; ?>
                    <td>
                        <a href="staff.php?edit=<?= $id ?>"
                           aria-label="Edit <?= staffEscape($name) ?>">Edit</a>
                        <form method="post" action="staff.php" class="d-inline"
                              onsubmit="return confirm('Delete this staff member?');">
                            <input type="hidden" name="token"
                                   value="<?= staffEscape($_SESSION['staff_token']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="staff_id" value="<?= $id ?>">
                            <button type="submit" class="btn btn-sm btn-danger"
                                    aria-label="Delete <?= staffEscape($name) ?>">Delete</button>
                        </form>
                        <form method="post" action="staff.php" class="d-inline"
                              onsubmit="return confirm('Archive this staff member? They will be removed from the org chart and listed under Archived Staff.');">
                            <input type="hidden" name="token"
                                   value="<?= staffEscape($_SESSION['staff_token']) ?>">
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="staff_id" value="<?= $id ?>">
                            <button type="submit" class="btn btn-sm btn-secondary"
                                    aria-label="Archive <?= staffEscape($name) ?>">Archive</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Archived Staff</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Job</th>
                    <th scope="col">Department</th>
                    <th scope="col">Supervisor</th>
                    <th scope="col">Archived</th>
                    <th scope="col">Archived by</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$archived): ?>
                <tr><td colspan="8">No archived staff.</td></tr>
            <?php endif; ?>
            <?php foreach ($archived as $row): ?>
                <tr>
                    <th scope="row"><?= staffEscape($row['first_name'] . ' ' . $row['last_name']) ?></th>
                    <td><?= staffEscape($row['email']) ?></td>
                    <td><?= staffEscape($row['job_title']) ?></td>
                    <td><?= staffEscape($row['dept_name']) ?></td>
                    <td><?= staffEscape($row['supervisor_name'] ?? 'None') ?></td>
                    <td><?= staffEscape($row['archived_at']) ?></td>
                    <td><?= staffEscape($row['archived_by']) ?></td>
                    <td>
                        <a class="btn btn-sm btn-primary"
                           href="staff.php?restore=<?= (int) $row['archive_id'] ?>"
                           aria-label="Restore <?= staffEscape($row['first_name'] . ' ' . $row['last_name']) ?>">Restore</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once ROOT_PATH . '/include/footer.php'; ?>
</body>
</html>