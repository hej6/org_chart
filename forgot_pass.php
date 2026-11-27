<?php
/**
 * Forgot Password Page
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026
 * @date      2026-02-24
 * @version   2.0
 *
 * There is no email, so this records a request for an administrator.
 * The admin resets the password on Manage Users and contacts the user.
 */

  /* Quick Paths */
  include_once (realpath(dirname(__FILE__).'/php/path.php'));
  include_once (realpath(dirname(__FILE__).'/php/config.php'));

  /* Start The Session */
  session_start();

  $submitted = false;
  $error = null;

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';

    if ($username === '' || mb_strlen($username, 'UTF-8') > 255) {
      $error = 'Enter your username.';
    } else {
      try {
        // Record a request only for a real account with no request already pending.
        // Nothing is shown either way, so this page cannot reveal which usernames exist.
        $stmt = $db_connection->prepare(
          "INSERT INTO PasswordResetRequests (user_id)
           SELECT cr.user_id
           FROM Credentials cr
           WHERE cr.username = ?
             AND NOT EXISTS (
               SELECT 1 FROM PasswordResetRequests p
               WHERE p.user_id = cr.user_id AND p.resolved_at IS NULL
             )");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->close();
        $submitted = true;
      } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
        $error = 'Your request could not be sent. Please try again later.';
      }
    }
  }

?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once "./include/head.php"; ?>
  </head>
  <body>
    <?php include_once "./include/header.php"; ?>
    <main role="main" class="container" id="main-content" tabindex="-1">
      <div class="row justify-content-sm-center">
        <div class="col-sm-4">
          <h1>Account Recovery</h1>
          <?php if ($submitted): ?>
            <div class="alert alert-success" role="status">
              Request received. If that account exists, an administrator will reset
              your password and contact you with a new one.
            </div>
          <?php else: ?>
            <p>Enter your username. An administrator will reset your password and contact you.</p>
            <form action="./forgot_pass.php" method="post">
              <div class="input-group mb-3">
                <input type="text" class="form-control" id="username" aria-label="Username" placeholder="Enter Username" name="username" maxlength="255" required>
              </div>
              <?php
                /* Error Message */
                if (isset($error)) {
                  // uses bootstrap alert style for error messages
                  echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($error) . '</div>';
                }
              ?>
              <button type="submit" class="btn btn-primary">Request Password Reset</button>
            </form>
          <?php endif; ?>
          <hr />
          <p class="text-center">
            <small>
              <a href="./create_account.php">Create An Account</a> | <a href="./">Login</a>
            </small>
          </p>
        </div>
      </div>
    </main>
    <?php include_once "./include/footer.php"; ?>
  </body>
</html>
