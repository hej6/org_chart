<?php
/**
 * Account page: view, edit, and deactivate (archive) an account,
 * and change password.
 * Changing the email, changing the password, and deactivating all
 * require the current password. Usernames cannot be changed.
 *
 * @author    Jackie Isabella <isabellaj2@southernct.edu>
 * @copyright 2026 
 * @date      2026-11-24
 * @version   1.0
 */
include_once (realpath(dirname(__FILE__).'/../php/path.php'));
include_once (ROOT_PATH . '/php/session.php');
include_once (ROOT_PATH . '/php/check_user.php');

$page_name = "My Account"; // Set page name for active link in header

include_once (ROOT_PATH . '/php/config.php');

// Query 1: the logged-in user's account, contact info, and role
$stmt = $db_connection->prepare(
  "SELECT u.user_id, u.contact_id, u.creation_date, cr.username, r.role_type,
          c.first_name, c.last_name, c.email, c.phone,
          c.street_1, c.street_2, c.city, c.state_code, c.post_code
   FROM Users u
   INNER JOIN Contacts c     ON u.contact_id = c.contact_id
   INNER JOIN Credentials cr ON u.user_id = cr.user_id
   INNER JOIN Roles r        ON u.role_id = r.role_id
   WHERE cr.username = ?"
);
$stmt->bind_param("s", $user_check);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';
$open_form = '';   // 'save' or 'password': which form to show again after an error
$form = [];        // contact form values (the saved values, or what was typed after an error)
$contact_fields = ['first_name', 'last_name', 'email', 'phone', 'street_1', 'street_2', 'city', 'state_code', 'post_code'];
if ($account) {
  foreach ($contact_fields as $key) {
    $form[$key] = (string) ($account[$key] ?? '');
  }
}

// Token that proves a form was submitted from this page, not from another website
$_SESSION['account_token'] ??= bin2hex(random_bytes(32));

// The current password, needed for email changes, password changes, and deactivating
function accountCheckPassword($db_connection, $user_id, $password)
{
  $stmt = $db_connection->prepare("SELECT password_salted FROM Credentials WHERE user_id = ?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $stmt->bind_result($hash);
  $found = $stmt->fetch();
  $stmt->close();
  return $found && password_verify((string) $password, $hash);
}

if ($account && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['token'] ?? '';
  $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
  $open_form = $action;

  if (!is_string($token) || !hash_equals($_SESSION['account_token'], $token)) {
    $error = 'Your form expired. Reload the page and try again.';
    $action = '';
  }

  // Query 2: save changes to the contact record (contact_id comes from the login, never the form)
  if ($action === 'save') {
    foreach ($contact_fields as $key) {
      $form[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $form['state_code'] = strtoupper($form['state_code']);
    $email_changed = strcasecmp($form['email'], (string) $account['email']) !== 0;

    // Same rules as sign-up
    if ($form['first_name'] === '' || $form['last_name'] === '' ||
        mb_strlen($form['first_name'], 'UTF-8') > 255 || mb_strlen($form['last_name'], 'UTF-8') > 255) {
      $error = 'Enter your first and last name.';
    } elseif (strlen($form['email']) > 255 || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
      $error = 'Enter a valid email address.';
    } elseif ($form['phone'] === '' || strlen($form['phone']) > 20) {
      $error = 'Enter a phone number of up to 20 characters.';
    } elseif (mb_strlen($form['street_1'], 'UTF-8') > 255 || mb_strlen($form['street_2'], 'UTF-8') > 255 ||
              mb_strlen($form['city'], 'UTF-8') > 255) {
      $error = 'Address lines and city can be at most 255 characters.';
    } elseif ($form['state_code'] !== '' && !preg_match('/^[A-Z]{2}$/', $form['state_code'])) {
      $error = 'State must be a 2-letter code, for example CT.';
    } elseif ($form['post_code'] !== '' && !preg_match('/^[0-9]{5}$/', $form['post_code'])) {
      $error = 'ZIP code must be 5 digits.';
    } elseif ($email_changed && !accountCheckPassword($db_connection, $account['user_id'], $current)) {
      // A new email needs the current password, so someone at an unlocked computer cannot take over the account
      $error = 'To change your email, enter your current password.';
    } else {
      // Optional address fields are stored as NULL when left blank
      $values = $form;
      foreach (['street_1', 'street_2', 'city', 'state_code', 'post_code'] as $key) {
        $values[$key] = $values[$key] === '' ? null : $values[$key];
      }
      try {
        $stmt = $db_connection->prepare(
          "UPDATE Contacts
           SET first_name = ?, last_name = ?, email = ?, phone = ?,
               street_1 = ?, street_2 = ?, city = ?, state_code = ?, post_code = ?
           WHERE contact_id = ?"
        );
        $stmt->bind_param("sssssssssi",
          $values['first_name'], $values['last_name'], $values['email'], $values['phone'],
          $values['street_1'], $values['street_2'], $values['city'],
          $values['state_code'], $values['post_code'],
          $account['contact_id']
        );
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_first'] = $values['first_name']; // keeps the header's Welcome text current
        $db_connection->close();
        header('Location: account.php?saved=1');
        exit;
      } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        $error = 'Your changes could not be saved. Please try again.';
      }
    }
  }

  // Query 6: change the password (stored as a password_hash, never as plain text)
  if ($action === 'password') {
    $password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!accountCheckPassword($db_connection, $account['user_id'], $current)) {
      $error = 'Your current password is incorrect.';
    } elseif ($password === '') {
      $error = 'Enter a new password.';
    } elseif (strlen($password) > 72) {
      $error = 'Passwords can be at most 72 characters.';
    } elseif ($password !== $confirm) {
      $error = 'The two new passwords do not match.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $db_connection->prepare("UPDATE Credentials SET password_salted = ? WHERE user_id = ?");
      $stmt->bind_param("si", $hash, $account['user_id']);
      $stmt->execute();
      $stmt->close();
      $db_connection->close();
      header('Location: account.php?password_changed=1');
      exit;
    }
  }

  // Queries 3-5: copy to ArchivedUsers, then remove the live rows (Credentials cascades from Users)
  if ($action === 'deactivate' && !accountCheckPassword($db_connection, $account['user_id'], $current)) {
    $error = 'Your account was not deactivated: the password was incorrect.';
  } elseif ($action === 'deactivate') {
    try {
      $db_connection->begin_transaction();

      $stmt = $db_connection->prepare(
        "INSERT INTO ArchivedUsers
           (user_id, username, first_name, last_name, email, role_type, account_created, archived_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
      );
      $stmt->bind_param("isssssss",
        $account['user_id'], $account['username'], $account['first_name'],
        $account['last_name'], $account['email'], $account['role_type'],
        $account['creation_date'], $account['username']
      );
      $stmt->execute();
      $stmt->close();

      $stmt = $db_connection->prepare("DELETE FROM Users WHERE user_id = ?");
      $stmt->bind_param("i", $account['user_id']);
      $stmt->execute();
      $stmt->close();

      $stmt = $db_connection->prepare("DELETE FROM Contacts WHERE contact_id = ?");
      $stmt->bind_param("i", $account['contact_id']);
      $stmt->execute();
      $stmt->close();

      $db_connection->commit();
      $db_connection->close();
      header('Location: /org_chart/php/logout.php');
      exit;
    } catch (Exception $e) {
      $db_connection->rollback();
      $error = 'Your account could not be deactivated. Nothing was changed.';
    }
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
  <?php include_once (ROOT_PATH . '/include/head.php'); // Include head.php ?>
  </head>
  <body class="<?php echo $page_name; ?>">

  <?php include_once (ROOT_PATH . '/include/header.php'); // Include header.php ?>
    <main class="container" id="main-content" tabindex="-1">

      <h1 class="mb-3">My Account</h1>

      <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Your information was saved.</div>
      <?php endif; ?>
      <?php if (isset($_GET['password_changed'])): ?>
        <div class="alert alert-success">Your password was changed.</div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if ($account): ?>
        <div class="card shadow-sm mb-4">
          <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>Username:</strong> <?php echo htmlspecialchars($account['username']); ?></li>
            <li class="list-group-item"><strong>Role:</strong> <?php echo htmlspecialchars($account['role_type']); ?></li>
          </ul>
        </div>

        <?php $editing = ($open_form === 'save' && $error !== ''); // keep the form unlocked after an error ?>
        <form method="post" class="card shadow-sm mb-4">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['account_token']); ?>">
          <fieldset id="contactFields" <?php echo $editing ? '' : 'disabled'; ?>>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="first_name" class="form-label">First name</label>
                  <input type="text" class="form-control" id="first_name" name="first_name" maxlength="255" required value="<?php echo htmlspecialchars($form['first_name']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="last_name" class="form-label">Last name</label>
                  <input type="text" class="form-control" id="last_name" name="last_name" maxlength="255" required value="<?php echo htmlspecialchars($form['last_name']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="email" class="form-label">Email</label>
                  <input type="email" class="form-control" id="email" name="email" maxlength="255" required data-original="<?php echo htmlspecialchars($account['email']); ?>" value="<?php echo htmlspecialchars($form['email']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="phone" class="form-label">Phone</label>
                  <input type="text" class="form-control" id="phone" name="phone" maxlength="20" required value="<?php echo htmlspecialchars($form['phone']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="street_1" class="form-label">Street address</label>
                  <input type="text" class="form-control" id="street_1" name="street_1" maxlength="255" value="<?php echo htmlspecialchars($form['street_1']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="street_2" class="form-label">Apt, suite, etc.</label>
                  <input type="text" class="form-control" id="street_2" name="street_2" maxlength="255" value="<?php echo htmlspecialchars($form['street_2']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="city" class="form-label">City</label>
                  <input type="text" class="form-control" id="city" name="city" maxlength="255" value="<?php echo htmlspecialchars($form['city']); ?>">
                </div>
                <div class="col-md-3">
                  <label for="state_code" class="form-label">State</label>
                  <input type="text" class="form-control" id="state_code" name="state_code" maxlength="2" pattern="[A-Za-z]{2}" title="2-letter state code" value="<?php echo htmlspecialchars($form['state_code']); ?>">
                </div>
                <div class="col-md-3">
                  <label for="post_code" class="form-label">ZIP</label>
                  <input type="text" class="form-control" id="post_code" name="post_code" maxlength="5" pattern="[0-9]{5}" inputmode="numeric" title="5-digit ZIP code" value="<?php echo htmlspecialchars($form['post_code']); ?>">
                </div>
                <div class="col-md-6 <?php echo ($editing && $form['email'] !== $account['email']) ? '' : 'd-none'; ?>" id="emailPasswordRow">
                  <label for="email_current_password" class="form-label">Current password (needed to change your email)</label>
                  <input type="password" class="form-control" id="email_current_password" name="current_password" autocomplete="current-password">
                </div>
              </div>
            </div>
          </fieldset>
          <div class="card-footer text-end">
            <button type="button" class="btn btn-outline-primary <?php echo $editing ? 'd-none' : ''; ?>" id="editBtn">Edit</button>
            <a href="account.php" class="btn btn-secondary <?php echo $editing ? '' : 'd-none'; ?>" id="cancelBtn">Cancel</a>
            <button type="submit" class="btn btn-primary <?php echo $editing ? '' : 'd-none'; ?>" id="saveBtn">Save changes</button>
          </div>
        </form>

        <!-- Change password -->
        <form method="post" class="card shadow-sm mb-4">
          <input type="hidden" name="action" value="password">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['account_token']); ?>">
          <div class="card-body">
            <h2 class="h5 card-title">Change password</h2>
            <div class="row g-3">
              <div class="col-md-4">
                <label for="password_current" class="form-label">Current password</label>
                <input type="password" class="form-control" id="password_current" name="current_password" autocomplete="current-password" required>
              </div>
              <div class="col-md-4">
                <label for="new_password" class="form-label">New password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" maxlength="72" autocomplete="new-password" required>
              </div>
              <div class="col-md-4">
                <label for="confirm_password" class="form-label">Confirm new password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" maxlength="72" autocomplete="new-password" required>
              </div>
            </div>
          </div>
          <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Change password</button>
          </div>
        </form>

        <!-- Deactivate account -->
        <div class="card border-danger shadow-sm mb-5">
            <div class="card-body text-center">
            <button type="button" class="btn btn-outline-danger mb-3" data-bs-toggle="modal" data-bs-target="#deactivateModal">Deactivate my account</button>
            <p class="mb-0">Deactivating removes your login and contact record and signs you out.</p>
          </div>
        </div>

        <!-- Confirmation pop-up -->
        <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <form method="post" class="modal-content">
              <input type="hidden" name="action" value="deactivate">
              <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['account_token']); ?>">
              <div class="modal-header">
                <h2 class="modal-title h5" id="deactivateModalLabel">Deactivate your account?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <p>You will be signed out and will not be able to log in again. An administrator can see that the account was deactivated.</p>
                <label for="deactivate_password" class="form-label">Enter your password to confirm</label>
                <input type="password" class="form-control" id="deactivate_password" name="current_password" autocomplete="current-password" required>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Yes, deactivate it</button>
              </div>
            </form>
          </div>
        </div>
      <?php else: ?>
        <p>No account found.</p>
      <?php endif; ?>

    </main>
    <?php $db_connection->close(); ?>
    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
    <script>
      // Edit button: unlock the fields and swap the buttons
      document.getElementById('editBtn').addEventListener('click', function () {
        document.getElementById('contactFields').disabled = false;
        document.getElementById('editBtn').classList.add('d-none');
        document.getElementById('cancelBtn').classList.remove('d-none');
        document.getElementById('saveBtn').classList.remove('d-none');
        document.getElementById('first_name').focus();
      });

      // Changing the email needs the current password: show that field only when the email changes
      document.getElementById('email').addEventListener('input', function () {
        var changed = this.value.trim().toLowerCase() !== this.dataset.original.toLowerCase();
        document.getElementById('emailPasswordRow').classList.toggle('d-none', !changed);
      });
    </script>
  </body>
</html>