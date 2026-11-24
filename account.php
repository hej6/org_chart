<?php
/**
 * My Account Page
 *
 * Lets any logged-in user (admin, user, or guest) change their own
 * contact details, email address, and password.
 * Email and password changes require the current password; contact details do not.
 * Usernames cannot be changed.
 */

  /* Quick Paths */
  include_once (realpath(dirname(__FILE__).'/php/path.php'));
  include_once (realpath(dirname(__FILE__).'/php/session.php'));

  // Not logged in: send to the login page
  if (!isset($user_id)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
  }

  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  /* Page Name */
  $page_name = 'account';
  $error = '';
  $open_form = ''; // which form to keep filled in after an error
  $new_email = '';

  $success = $_SESSION['account_success'] ?? '';
  unset($_SESSION['account_success']);
  $_SESSION['account_token'] ??= bin2hex(random_bytes(32));

  function accountEscape($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }

  // Current account details
  $stmt = $db_connection->prepare(
    'SELECT c.contact_id, c.first_name, c.last_name, c.email, c.phone,
            c.street_1, c.street_2, c.city, c.state_code, c.post_code,
            cr.username, cr.password_salted, r.role_type
     FROM Users u
     INNER JOIN Contacts c ON u.contact_id = c.contact_id
     INNER JOIN Credentials cr ON u.user_id = cr.user_id
     INNER JOIN Roles r ON u.role_id = r.role_id
     WHERE u.user_id = ?');
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $account = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // Contact Details form, pre-filled with the current values
  $detail_fields = ['first_name', 'last_name', 'phone', 'street_1', 'street_2', 'city', 'state_code', 'post_code'];
  $details = [];
  foreach ($detail_fields as $key) {
    $details[$key] = (string) ($account[$key] ?? '');
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['token'] ?? '';
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $open_form = $action;

    try {
      if (!is_string($token) || !hash_equals($_SESSION['account_token'], $token)) {
        throw new InvalidArgumentException('Your form expired. Reload the page and try again.');
      }
      if (!in_array($action, ['details', 'email', 'password'], true)) {
        throw new InvalidArgumentException('Invalid action.');
      }
      // Email and password changes need the current password, so someone at
      // an unlocked computer cannot take over the account
      if ($action !== 'details' && !password_verify($current, $account['password_salted'])) {
        throw new InvalidArgumentException('Your current password is incorrect.');
      }

      if ($action === 'details') {
        foreach ($detail_fields as $key) {
          $details[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        }
        $details['state_code'] = strtoupper($details['state_code']);

        // Same rules as sign-up
        if ($details['first_name'] === '' || $details['last_name'] === '' ||
            mb_strlen($details['first_name'], 'UTF-8') > 255 ||
            mb_strlen($details['last_name'], 'UTF-8') > 255) {
          throw new InvalidArgumentException('Enter your first and last name.');
        }
        if ($details['phone'] === '' || strlen($details['phone']) > 20) {
          throw new InvalidArgumentException('Enter a phone number of up to 20 characters.');
        }
        foreach (['street_1', 'street_2', 'city'] as $key) {
          if (mb_strlen($details[$key], 'UTF-8') > 255) {
            throw new InvalidArgumentException('Address lines and city can be at most 255 characters.');
          }
        }
        if ($details['state_code'] !== '' && !preg_match('/^[A-Z]{2}$/', $details['state_code'])) {
          throw new InvalidArgumentException('State must be a 2-letter code, for example CT.');
        }
        if ($details['post_code'] !== '' && !preg_match('/^[0-9]{5}$/', $details['post_code'])) {
          throw new InvalidArgumentException('Zip code must be 5 digits.');
        }

        // Optional fields are stored as NULL when left blank
        $values = $details;
        foreach (['street_1', 'street_2', 'city', 'state_code', 'post_code'] as $key) {
          $values[$key] = $values[$key] === '' ? null : $values[$key];
        }
        $stmt = $db_connection->prepare(
          'UPDATE Contacts
           SET first_name = ?, last_name = ?, phone = ?, street_1 = ?, street_2 = ?,
               city = ?, state_code = ?, post_code = ?
           WHERE contact_id = ?');
        $stmt->bind_param('ssssssssi', $values['first_name'], $values['last_name'], $values['phone'],
                          $values['street_1'], $values['street_2'], $values['city'],
                          $values['state_code'], $values['post_code'], $account['contact_id']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['account_success'] = 'Your contact details were updated.';
      } elseif ($action === 'email') {
        $new_email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        if (strlen($new_email) > 255 || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
          throw new InvalidArgumentException('Enter a valid email address.');
        }
        $stmt = $db_connection->prepare('UPDATE Contacts SET email = ? WHERE contact_id = ?');
        $stmt->bind_param('si', $new_email, $account['contact_id']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['account_success'] = 'Your email address was updated.';
      } else {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
        if ($password === '') {
          throw new InvalidArgumentException('Enter a new password.');
        }
        if (strlen($password) > 72) {
          throw new InvalidArgumentException('Passwords can be at most 72 characters.');
        }
        if ($password !== $confirm) {
          throw new InvalidArgumentException('The two new passwords do not match.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db_connection->prepare('UPDATE Credentials SET password_salted = ? WHERE user_id = ?');
        $stmt->bind_param('si', $hash, $user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['account_success'] = 'Your password was changed.';
      }

      // Redirect so refreshing does not resubmit the form
      header('Location: ' . BASE_URL . '/account.php');
      exit;
    } catch (InvalidArgumentException $e) {
      $error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
      error_log($e->getMessage());
      $error = 'The change could not be saved. Please try again.';
    }
  }

?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once ROOT_PATH . '/include/head.php'; ?>
  </head>
  <body class="<?php echo accountEscape($page_name); ?>">
    <?php include_once ROOT_PATH . '/include/header.php'; ?>
    <main role="main" class="container">
      <div class="row justify-content-sm-center">
        <div class="col-md-6">
          <h1>My Account</h1>

          <?php if ($success !== ''): ?>
            <div class="alert alert-success" role="status"><?php echo accountEscape($success); ?></div>
          <?php endif; ?>
          <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo accountEscape($error); ?></div>
          <?php endif; ?>

          <table class="table mb-4">
            <tbody>
              <tr><th scope="row">Name</th><td><?php echo accountEscape($account['first_name'] . ' ' . $account['last_name']); ?></td></tr>
              <tr><th scope="row">Username</th><td><?php echo accountEscape($account['username']); ?></td></tr>
              <tr><th scope="row">Email</th><td><?php echo accountEscape($account['email']); ?></td></tr>
              <tr><th scope="row">Phone</th><td><?php echo accountEscape($account['phone']); ?></td></tr>
              <?php
                // Address on one line, skipping blank parts
                $address = implode(', ', array_filter([
                  $account['street_1'], $account['street_2'], $account['city'],
                  trim(($account['state_code'] ?? '') . ' ' . ($account['post_code'] ?? '')),
                ], fn($part) => $part !== null && $part !== ''));
              ?>
              <tr><th scope="row">Address</th><td><?php echo $address !== '' ? accountEscape($address) : '<span class="text-muted">Not provided</span>'; ?></td></tr>
              <tr><th scope="row">Role</th><td><?php echo accountEscape($account['role_type']); ?></td></tr>
            </tbody>
          </table>

          <section class="mb-4">
            <h2>Contact Details</h2>
            <form method="post" action="account.php">
              <input type="hidden" name="token" value="<?php echo accountEscape($_SESSION['account_token']); ?>">
              <input type="hidden" name="action" value="details">
              <?php foreach ([
                'first_name' => ['First name', 'text', 255, true, ''],
                'last_name' => ['Last name', 'text', 255, true, ''],
                'phone' => ['Phone', 'tel', 20, true, ''],
                'street_1' => ['Street', 'text', 255, false, ''],
                'street_2' => ['Street line 2', 'text', 255, false, ''],
                'city' => ['City', 'text', 255, false, ''],
                'state_code' => ['State (e.g. CT)', 'text', 2, false, 'pattern="[A-Za-z]{2}" title="2-letter state code"'],
                'post_code' => ['Zip code', 'text', 5, false, 'pattern="[0-9]{5}" inputmode="numeric" title="5-digit zip code"'],
              ] as $key => [$label, $type, $limit, $required, $extra]): ?>
                <div class="mb-3">
                  <label class="form-label" for="<?php echo $key; ?>">
                    <?php echo $label; ?><?php echo $required ? '' : ' (optional)'; ?>
                  </label>
                  <input class="form-control" id="<?php echo $key; ?>" name="<?php echo $key; ?>"
                         type="<?php echo $type; ?>" maxlength="<?php echo $limit; ?>" <?php echo $extra; ?>
                         <?php echo $required ? 'required' : ''; ?>
                         value="<?php echo accountEscape($details[$key]); ?>">
                </div>
              <?php endforeach; ?>
              <button class="btn btn-primary" type="submit">Save Contact Details</button>
            </form>
          </section>

          <section class="mb-4">
            <h2>Change Email</h2>
            <form method="post" action="account.php">
              <input type="hidden" name="token" value="<?php echo accountEscape($_SESSION['account_token']); ?>">
              <input type="hidden" name="action" value="email">
              <div class="mb-3">
                <label class="form-label" for="email">New email</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="255" required
                       value="<?php echo accountEscape($open_form === 'email' ? $new_email : ''); ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="email_current_password">Current password</label>
                <input class="form-control" id="email_current_password" name="current_password" type="password"
                       autocomplete="current-password" required>
              </div>
              <button class="btn btn-primary" type="submit">Change Email</button>
            </form>
          </section>

          <section class="mb-4">
            <h2>Change Password</h2>
            <form method="post" action="account.php">
              <input type="hidden" name="token" value="<?php echo accountEscape($_SESSION['account_token']); ?>">
              <input type="hidden" name="action" value="password">
              <div class="mb-3">
                <label class="form-label" for="password_current_password">Current password</label>
                <input class="form-control" id="password_current_password" name="current_password" type="password"
                       autocomplete="current-password" required>
              </div>
              <div class="mb-3">
                <label class="form-label" for="password">New password</label>
                <input class="form-control" id="password" name="password" type="password"
                       maxlength="72" autocomplete="new-password" required>
              </div>
              <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm new password</label>
                <input class="form-control" id="confirm_password" name="confirm_password" type="password"
                       maxlength="72" autocomplete="new-password" required>
              </div>
              <button class="btn btn-primary" type="submit">Change Password</button>
            </form>
          </section>
        </div>
      </div>
    </main>
    <?php include_once ROOT_PATH . '/include/footer.php'; ?>
  </body>
</html>
