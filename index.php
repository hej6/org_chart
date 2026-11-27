<?php
/**
 * Login Page
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026
 * @date      2026-02-24
 * @version   1.0
 */

/* Quick Paths */
include_once __DIR__ . '/php/path.php';

/* Page Name */
$page_name = 'home';

/* Start The Session */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once __DIR__ . '/include/head.php'; ?>
  </head>
  <body>
    <?php include_once __DIR__ . '/include/header.php'; ?>

    <main role="main" class="container" id="main-content" tabindex="-1">
      <div class="row justify-content-sm-center">
        <div class="col-sm-4">
          <h1>Login</h1>

          <form action="./php/authenticate.php" method="post">
            <div class="input-group mb-3">
              <label for="login-username" class="visually-hidden">
                Username
              </label>
              <input
                type="text"
                class="form-control"
                id="login-username"
                name="username"
                placeholder="Username"
                autocomplete="username"
                required>
            </div>

            <div class="input-group mb-3">
              <label for="login-password" class="visually-hidden">
                Password
              </label>
              <input
                type="password"
                class="form-control"
                id="login-password"
                name="password"
                placeholder="Password"
                autocomplete="current-password"
                required>
            </div>

            <?php include_once ROOT_PATH . '/php/error_rpt.php'; ?>

            <button type="submit" class="btn btn-primary">
              Log In
            </button>
          </form>

          <hr>

          <p class="text-center">
            <small>
              <a href="./create_account.php">Create An Account</a>
              |
              <a href="./forgot_pass.php">Forgot Password</a>
            </small>
          </p>
        </div>
      </div>
    </main>

    <?php include_once __DIR__ . '/include/footer.php'; ?>
  </body>
</html>