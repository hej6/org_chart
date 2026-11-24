<?php
/**
 * Account page: view, edit, and deactivate (archive) an account
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

if ($account && $_SERVER['REQUEST_METHOD'] === 'POST') {

  // Query 2: save changes to the contact record (contact_id comes from the login, never the form)
  if (($_POST['action'] ?? '') === 'save') {
    $stmt = $db_connection->prepare(
      "UPDATE Contacts
       SET first_name = ?, last_name = ?, email = ?, phone = ?,
           street_1 = ?, street_2 = ?, city = ?, state_code = ?, post_code = ?
       WHERE contact_id = ?"
    );
    $stmt->bind_param("sssssssssi",
      $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
      $_POST['street_1'], $_POST['street_2'], $_POST['city'],
      $_POST['state_code'], $_POST['post_code'],
      $account['contact_id']
    );
    $stmt->execute();
    $stmt->close();
    $_SESSION['user_first'] = $_POST['first_name']; // keeps the header's Welcome text current
    $db_connection->close();
    header('Location: account.php?saved=1');
    exit;
  }

  // Queries 3-5: copy to ArchivedUsers, then remove the live rows (Credentials cascades from Users)
  if (($_POST['action'] ?? '') === 'deactivate') {
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
    <main role="main" class="container">

      <h1 class="mb-3">My Account</h1>

      <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Your information was saved.</div>
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

        <form method="post" class="card shadow-sm mb-4">
          <input type="hidden" name="action" value="save">
          <fieldset id="contactFields" disabled>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="first_name" class="form-label">First name</label>
                  <input type="text" class="form-control" id="first_name" name="first_name" maxlength="255" required value="<?php echo htmlspecialchars($account['first_name']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="last_name" class="form-label">Last name</label>
                  <input type="text" class="form-control" id="last_name" name="last_name" maxlength="255" required value="<?php echo htmlspecialchars($account['last_name']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="email" class="form-label">Email</label>
                  <input type="email" class="form-control" id="email" name="email" maxlength="255" required value="<?php echo htmlspecialchars($account['email']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="phone" class="form-label">Phone</label>
                  <input type="text" class="form-control" id="phone" name="phone" maxlength="20" required value="<?php echo htmlspecialchars($account['phone']); ?>">
                </div>
                <div class="col-md-6">
                  <label for="street_1" class="form-label">Street address</label>
                  <input type="text" class="form-control" id="street_1" name="street_1" maxlength="255" value="<?php echo htmlspecialchars($account['street_1'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label for="street_2" class="form-label">Apt, suite, etc.</label>
                  <input type="text" class="form-control" id="street_2" name="street_2" maxlength="255" value="<?php echo htmlspecialchars($account['street_2'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                  <label for="city" class="form-label">City</label>
                  <input type="text" class="form-control" id="city" name="city" maxlength="255" value="<?php echo htmlspecialchars($account['city'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label for="state_code" class="form-label">State</label>
                  <input type="text" class="form-control" id="state_code" name="state_code" maxlength="2" value="<?php echo htmlspecialchars($account['state_code'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                  <label for="post_code" class="form-label">ZIP</label>
                  <input type="text" class="form-control" id="post_code" name="post_code" maxlength="5" value="<?php echo htmlspecialchars($account['post_code'] ?? ''); ?>">
                </div>
              </div>
            </div>
          </fieldset>
          <div class="card-footer text-end">
            <button type="button" class="btn btn-outline-primary" id="editBtn">Edit</button>
            <a href="account.php" class="btn btn-secondary d-none" id="cancelBtn">Cancel</a>
            <button type="submit" class="btn btn-primary d-none" id="saveBtn">Save changes</button>
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
              <div class="modal-header">
                <h2 class="modal-title h5" id="deactivateModalLabel">Deactivate your account?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                You will be signed out and will not be able to log in again. An administrator can see that the account was deactivated.
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
    </script>
  </body>
</html>