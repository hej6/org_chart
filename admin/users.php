<?php
/**
 * Manage Users
 *
 * Allows administrators to add users, change a user's password,
 * handle "Forgot Password" requests, and archive or delete accounts.
 * Archive: copies the account into ArchivedUsers, then removes it.
 * Delete:  removes it with no record kept.
 * Either way the user's Credentials (ON DELETE CASCADE) and Contacts row go too.
 */
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
$success = $_SESSION['users_success'] ?? '';
unset($_SESSION['users_success']);
$_SESSION['users_token'] ??= bin2hex(random_bytes(32));

function usersEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function usersId($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

// Passwords: required, and at most 72 bytes (password_hash ignores anything longer)
function usersPasswordError($password, $confirm)
{
    if ($password === '') {
        return 'Enter a password.';
    }
    if (strlen($password) > 72) {
        return 'Passwords can be at most 72 characters.';
    }
    if ($password !== $confirm) {
        return 'The two passwords do not match.';
    }
    return '';
}

// Roles for the Add User form
$roles = [];
foreach ($db_connection->query('SELECT role_id, role_type FROM Roles ORDER BY role_id') as $row) {
    $roles[(int) $row['role_id']] = $row['role_type'];
}

// Add User form values (kept after an error so the admin does not retype everything)
$newUser = array_fill_keys(['first_name', 'last_name', 'email', 'phone', 'username', 'role_id'], '');
$newUser['role_id'] = '2';

// User whose password is being changed (?password=ID)
$passwordId = 0;

// All current users
$users = [];
foreach ($db_connection->query(
    'SELECT u.user_id, u.role_id, u.contact_id, u.creation_date,
            c.first_name, c.last_name, c.email, cr.username, r.role_type
     FROM Users u
     INNER JOIN Contacts c ON u.contact_id = c.contact_id
     INNER JOIN Credentials cr ON u.user_id = cr.user_id
     INNER JOIN Roles r ON u.role_id = r.role_id
     ORDER BY u.user_id'
) as $row) {
    $users[(int) $row['user_id']] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['token'] ?? '';
    $id = usersId($_POST['user_id'] ?? '');
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    try {
        if (!is_string($token) || !hash_equals($_SESSION['users_token'], $token)) {
            throw new InvalidArgumentException('Reload the page and try again.');
        }
        if (!in_array($action, ['add', 'password', 'dismiss', 'archive', 'delete'], true)) {
            throw new InvalidArgumentException('Invalid user action.');
        }

        if ($action === 'add') {
            // ---------- Add User ----------
            foreach (array_keys($newUser) as $key) {
                $newUser[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
            }
            $roleId = usersId($newUser['role_id']);

            if ($newUser['first_name'] === '' || $newUser['last_name'] === '' ||
                mb_strlen($newUser['first_name'], 'UTF-8') > 255 ||
                mb_strlen($newUser['last_name'], 'UTF-8') > 255) {
                throw new InvalidArgumentException('Enter a first and last name.');
            }
            if (strlen($newUser['email']) > 255 || !filter_var($newUser['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            if ($newUser['phone'] === '' || strlen($newUser['phone']) > 20) {
                throw new InvalidArgumentException('Enter a phone number of up to 20 characters.');
            }
            if ($newUser['username'] === '' || mb_strlen($newUser['username'], 'UTF-8') > 255) {
                throw new InvalidArgumentException('Enter a username.');
            }
            if ($roleId === false || !isset($roles[$roleId])) {
                throw new InvalidArgumentException('Choose a valid role.');
            }
            $passwordError = usersPasswordError($password, $confirm);
            if ($passwordError !== '') {
                throw new InvalidArgumentException($passwordError);
            }

            // Contacts, then Users, then Credentials, all or nothing
            $db_connection->begin_transaction();
            try {
                $stmt = $db_connection->prepare(
                    'INSERT INTO Contacts (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param('ssss', $newUser['first_name'], $newUser['last_name'],
                                  $newUser['email'], $newUser['phone']);
                $stmt->execute();
                $contactId = $stmt->insert_id;
                $stmt->close();

                $stmt = $db_connection->prepare('INSERT INTO Users (role_id, contact_id) VALUES (?, ?)');
                $stmt->bind_param('ii', $roleId, $contactId);
                $stmt->execute();
                $userId = $stmt->insert_id;
                $stmt->close();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db_connection->prepare(
                    'INSERT INTO Credentials (username, user_id, password_salted) VALUES (?, ?, ?)'
                );
                $stmt->bind_param('sis', $newUser['username'], $userId, $hash);
                $stmt->execute();
                $stmt->close();

                $db_connection->commit();
            } catch (mysqli_sql_exception $e) {
                $db_connection->rollback();
                if ((int) $e->getCode() === 1062) {
                    throw new InvalidArgumentException('That username is already taken.');
                }
                throw $e;
            }

            $_SESSION['users_success'] = 'User ' . $newUser['username'] . ' added as ' . $roles[$roleId] . '.';
            header('Location: users.php');
            exit;
        }

        if ($action === 'dismiss') {
            // ---------- Dismiss a password reset request ----------
            $requestId = usersId($_POST['request_id'] ?? '');
            $resolvedBy = (string) ($_SESSION['user_name'] ?? '');
            $stmt = $db_connection->prepare(
                "UPDATE PasswordResetRequests
                 SET resolved_at = CURRENT_TIMESTAMP, resolution = 'dismissed', resolved_by = ?
                 WHERE request_id = ? AND resolved_at IS NULL"
            );
            $stmt->bind_param('si', $resolvedBy, $requestId);
            $stmt->execute();
            $dismissed = $stmt->affected_rows;
            $stmt->close();

            $_SESSION['users_success'] = $dismissed
                ? 'Password reset request dismissed.'
                : 'That request was already handled.';
            header('Location: users.php');
            exit;
        }

        if ($id === false || !isset($users[$id])) {
            throw new InvalidArgumentException('That user no longer exists.');
        }

        if ($action === 'password') {
            // ---------- Change User Password ----------
            $passwordId = $id; // keep the form open if there is an error
            $passwordError = usersPasswordError($password, $confirm);
            if ($passwordError !== '') {
                throw new InvalidArgumentException($passwordError);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $resolvedBy = (string) ($_SESSION['user_name'] ?? '');

            // Change the password and close any pending reset request, together
            $db_connection->begin_transaction();
            try {
                $stmt = $db_connection->prepare('UPDATE Credentials SET password_salted = ? WHERE user_id = ?');
                $stmt->bind_param('si', $hash, $id);
                $stmt->execute();
                $stmt->close();

                $stmt = $db_connection->prepare(
                    "UPDATE PasswordResetRequests
                     SET resolved_at = CURRENT_TIMESTAMP, resolution = 'reset', resolved_by = ?
                     WHERE user_id = ? AND resolved_at IS NULL"
                );
                $stmt->bind_param('si', $resolvedBy, $id);
                $stmt->execute();
                $resolved = $stmt->affected_rows;
                $stmt->close();

                $db_connection->commit();
            } catch (mysqli_sql_exception $e) {
                $db_connection->rollback();
                throw $e;
            }

            $_SESSION['users_success'] = 'Password changed for ' . $users[$id]['username'] . '.'
                . ($resolved ? ' Their password reset request is marked as resolved.' : '');
            header('Location: users.php');
            exit;
        }

        // ---------- Archive / Delete ----------
        // An admin cannot remove their own account. Since only admins can use
        // this page, this also guarantees at least one admin always remains.
        if ($id === $currentUserId) {
            throw new InvalidArgumentException('You cannot archive or delete your own account.');
        }

        $contactId = (int) $users[$id]['contact_id'];

        // One transaction: the archive copy and all deletes succeed together or not at all
        $db_connection->begin_transaction();
        try {
            if ($action === 'archive') {
                $archivedBy = (string) ($_SESSION['user_name'] ?? '');
                $stmt = $db_connection->prepare(
                    'INSERT INTO ArchivedUsers
                     (user_id, username, first_name, last_name, email, role_type,
                      account_created, archived_by)
                     SELECT u.user_id, cr.username, c.first_name, c.last_name, c.email,
                            r.role_type, u.creation_date, ?
                     FROM Users u
                     INNER JOIN Contacts c ON u.contact_id = c.contact_id
                     INNER JOIN Credentials cr ON u.user_id = cr.user_id
                     INNER JOIN Roles r ON u.role_id = r.role_id
                     WHERE u.user_id = ?'
                );
                $stmt->bind_param('si', $archivedBy, $id);
                $stmt->execute();
                $stmt->close();
            }

            // Users must go before Contacts, because Users.contact_id references Contacts.
            // Deleting the user also deletes their Credentials (ON DELETE CASCADE).
            $stmt = $db_connection->prepare('DELETE FROM Users WHERE user_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            $stmt = $db_connection->prepare('DELETE FROM Contacts WHERE contact_id = ?');
            $stmt->bind_param('i', $contactId);
            $stmt->execute();
            $stmt->close();

            $db_connection->commit();
        } catch (mysqli_sql_exception $e) {
            $db_connection->rollback();
            throw $e;
        }

        $_SESSION['users_success'] = $action === 'archive' ? 'User archived.' : 'User deleted.';
        header('Location: users.php');
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        $error = 'The change could not be saved. Please try again.';
    }
} elseif (isset($_GET['password'])) {
    $requested = usersId($_GET['password']);
    if ($requested !== false && isset($users[$requested])) {
        $passwordId = $requested;
    } else {
        $error = 'User not found.';
    }
}

// Pending password reset requests, oldest first
$requests = $db_connection->query(
    'SELECT p.request_id, p.user_id, p.requested_at, cr.username,
            c.first_name, c.last_name, c.email, c.phone
     FROM PasswordResetRequests p
     INNER JOIN Users u ON p.user_id = u.user_id
     INNER JOIN Contacts c ON u.contact_id = c.contact_id
     INNER JOIN Credentials cr ON u.user_id = cr.user_id
     WHERE p.resolved_at IS NULL
     ORDER BY p.requested_at, p.request_id'
)->fetch_all(MYSQLI_ASSOC);

// Archived users, newest first
$archived = $db_connection->query(
    'SELECT * FROM ArchivedUsers ORDER BY archived_at DESC, archive_id DESC'
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
    <h1>Manage Users</h1>
    <p><a href="index.php">Back to dashboard</a></p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success" role="status"><?= usersEscape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-danger" role="alert"><?= usersEscape($error) ?></p>
    <?php endif; ?>

    <?php if ($passwordId): ?>
        <section class="mb-4">
            <h2>Change Password for <?= usersEscape($users[$passwordId]['username']) ?></h2>
            <form method="post" action="users.php">
                <input type="hidden" name="token" value="<?= usersEscape($_SESSION['users_token']) ?>">
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="user_id" value="<?= $passwordId ?>">
                <div class="mb-3">
                    <label class="form-label" for="new_password">New password</label>
                    <input class="form-control" id="new_password" name="password" type="password"
                           maxlength="72" autocomplete="new-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_confirm">Confirm new password</label>
                    <input class="form-control" id="new_confirm" name="confirm_password" type="password"
                           maxlength="72" autocomplete="new-password" required>
                </div>
                <button class="btn btn-primary" type="submit">Change Password</button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </form>
        </section>
    <?php endif; ?>

    <section class="mb-4">
        <h2>Password Reset Requests</h2>
        <p class="text-muted">From the Forgot Password page. Reset the password, then give the user
            their new password in person or by phone.</p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Requested</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$requests): ?>
                    <tr><td colspan="6">No pending requests.</td></tr>
                <?php endif; ?>
                <?php foreach ($requests as $req): ?>
                    <tr>
                        <th scope="row"><?= usersEscape($req['username']) ?></th>
                        <td><?= usersEscape($req['first_name'] . ' ' . $req['last_name']) ?></td>
                        <td><?= usersEscape($req['email']) ?></td>
                        <td><?= usersEscape($req['phone']) ?></td>
                        <td><?= usersEscape($req['requested_at']) ?></td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="users.php?password=<?= (int) $req['user_id'] ?>"
                               aria-label="Reset password for <?= usersEscape($req['username']) ?>">Reset Password</a>
                            <form method="post" action="users.php" class="d-inline"
                                  onsubmit="return confirm('Dismiss this request without changing the password?');">
                                <input type="hidden" name="token" value="<?= usersEscape($_SESSION['users_token']) ?>">
                                <input type="hidden" name="action" value="dismiss">
                                <input type="hidden" name="request_id" value="<?= (int) $req['request_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"
                                        aria-label="Dismiss request from <?= usersEscape($req['username']) ?>">Dismiss</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-4">
        <h2>Add User</h2>
        <form method="post" action="users.php">
            <input type="hidden" name="token" value="<?= usersEscape($_SESSION['users_token']) ?>">
            <input type="hidden" name="action" value="add">
            <?php foreach ([
                'first_name' => ['First name', 'text', 255],
                'last_name' => ['Last name', 'text', 255],
                'email' => ['Email', 'email', 255],
                'phone' => ['Phone', 'tel', 20],
                'username' => ['Username', 'text', 255],
            ] as $key => [$label, $type, $limit]): ?>
                <div class="mb-3">
                    <label class="form-label" for="add_<?= $key ?>"><?= $label ?></label>
                    <input class="form-control" id="add_<?= $key ?>" name="<?= $key ?>"
                           type="<?= $type ?>" maxlength="<?= $limit ?>"
                           value="<?= usersEscape($newUser[$key]) ?>" required>
                </div>
            <?php endforeach; ?>
            <div class="mb-3">
                <label class="form-label" for="add_password">Password</label>
                <input class="form-control" id="add_password" name="password" type="password"
                       maxlength="72" autocomplete="new-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="add_confirm">Confirm password</label>
                <input class="form-control" id="add_confirm" name="confirm_password" type="password"
                       maxlength="72" autocomplete="new-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="add_role_id">Role</label>
                <select class="form-select" id="add_role_id" name="role_id" required>
                    <?php foreach ($roles as $roleId => $roleType): ?>
                        <option value="<?= $roleId ?>" <?= (string) $newUser['role_id'] === (string) $roleId ? 'selected' : '' ?>>
                            <?= usersEscape($roleType) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Add User</button>
        </form>
    </section>

    <h2>Current Users</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Username</th>
                    <th scope="col">Role</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="6">No users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $id => $row): ?>
                <?php $name = $row['first_name'] . ' ' . $row['last_name']; ?>
                <tr>
                    <th scope="row"><?= $id ?></th>
                    <td><?= usersEscape($name) ?></td>
                    <td><?= usersEscape($row['email']) ?></td>
                    <td><?= usersEscape($row['username']) ?></td>
                    <td><?= usersEscape($row['role_type']) ?></td>
                    <td>
                        <a class="btn btn-sm btn-primary" href="users.php?password=<?= $id ?>"
                           aria-label="Change password for <?= usersEscape($row['username']) ?>">Change Password</a>
                        <?php if ($id === (int) ($_SESSION['user_id'] ?? 0)): ?>
                            <span class="text-muted">(you)</span>
                        <?php else: ?>
                            <form method="post" action="users.php" class="d-inline"
                                  onsubmit="return confirm('Delete this user? Their account and contact details are removed permanently, with no record kept.');">
                                <input type="hidden" name="token" value="<?= usersEscape($_SESSION['users_token']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                        aria-label="Delete <?= usersEscape($row['username']) ?>">Delete</button>
                            </form>
                            <form method="post" action="users.php" class="d-inline"
                                  onsubmit="return confirm('Archive this user? They can no longer log in, and they will be listed under Archived Users.');">
                                <input type="hidden" name="token" value="<?= usersEscape($_SESSION['users_token']) ?>">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="user_id" value="<?= $id ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"
                                        aria-label="Archive <?= usersEscape($row['username']) ?>">Archive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Archived Users</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Username</th>
                    <th scope="col">Role</th>
                    <th scope="col">Archived</th>
                    <th scope="col">Archived by</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$archived): ?>
                <tr><td colspan="6">No archived users.</td></tr>
            <?php endif; ?>
            <?php foreach ($archived as $row): ?>
                <tr>
                    <th scope="row"><?= usersEscape($row['first_name'] . ' ' . $row['last_name']) ?></th>
                    <td><?= usersEscape($row['email']) ?></td>
                    <td><?= usersEscape($row['username']) ?></td>
                    <td><?= usersEscape($row['role_type']) ?></td>
                    <td><?= usersEscape($row['archived_at']) ?></td>
                    <td><?= usersEscape($row['archived_by']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require_once ROOT_PATH . '/include/footer.php'; ?>
</body>
</html>
