<?php
/**
 * User Index Page
 *
 * @author    Jackie Isabella <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', 0); // set to 1 to display errors, 0 to hide them

  /* Quick Paths */
  /* note the 2 after __FILE__, because it's 2 directories deep */
  include_once (realpath(dirname(__FILE__, 2).'/php/session.php')); // Session will be included in header.php
  include_once (realpath(dirname(__FILE__, 2).'/php/path.php')); // Path will be included in header.php
  // Session will be included in header.php

  /* Page Name */
  $page_name = "user";

?>
<?php
//======================================================================
// USER DASHBOARD PAGE
//======================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0); // set to 1 to display errors, 0 to hide them

  /* Quick Paths */
  /* note the 2 after __FILE__, because it's 2 directories deep */
  include_once (realpath(dirname(__FILE__, 2).'/php/session.php')); // Session will be included in header.php
  include_once (realpath(dirname(__FILE__, 2).'/php/path.php')); // Path will be included in header.php
  // Session will be included in header.php
  
  /* Check Role */
  include_once (ROOT_SRC_PATH .'/check_user.php'); // Check user role and redirect if not authorized

  $user_check = $_SESSION['login_user'];
  // Check user and get roll session from database

  /* Page Name */
  $page_name = "User ORG Chart Landing"; // Set page name for active link in header

// ****User landing with Banner and Cards for Staff, Colleges, Classifications, and Account Info******/
include_once (ROOT_PATH . '/php/config.php');
$staff_count   = $db_connection->query("SELECT COUNT(*) AS total FROM Staff")->fetch_assoc()['total'];
$dept_count    = $db_connection->query("SELECT COUNT(*) AS total FROM Department")->fetch_assoc()['total'];
$class_count   = $db_connection->query("SELECT COUNT(*) AS total FROM Classification")->fetch_assoc()['total'];
$div_count     = $db_connection->query("SELECT COUNT(*) AS total FROM Organization")->fetch_assoc()['total'];
$college_count = $db_connection->query("SELECT COUNT(*) AS total FROM College")->fetch_assoc()['total'];
?>
<!doctype html>
<html lang="en">
  <head>
  <?php include_once (ROOT_PATH . '/include/head.php'); // Include head.php ?>
  </head>
  <body class="<?php echo $page_name; ?>">

  <?php include_once (ROOT_PATH . '/include/header.php'); // Include header.php ?>
    <main role="main" class="container">

      <!-- Banner -->
      <div class="card text-bg-dark overflow-hidden shadow mb-4">
        <img src="/org_chart/img/banner.jpg" class="card-img card-img-banner" alt="Campus">
        <div class="card-img-overlay d-flex flex-column justify-content-center align-items-center text-center overlay-dark">
          <h1 class="display-4">Welcome, <?php echo htmlspecialchars($_SESSION['user_first']); ?></h1>
          <p class="display-6">Explore who's who at Wossamotta U </p>
        </div>
      </div>

      <!-- Cards -->
      <div class="row g-4">

        <div class="col-md-4">
          <a href="/org_chart/user/staff.php" class="text-decoration-none">
            <div class="card text-bg-dark overflow-hidden shadow">
              <img src="/org_chart/img/staff.jpg" class="card-img card-img-tile" alt="Staff Directory">
              <div class="card-img-overlay d-flex flex-column justify-content-end overlay-light">
                <div class="text-band">
                  <h4 class="card-title">Staff Directory</h4>
                  <p class="card-text mb-0"><?php echo $staff_count; ?> staff members</p>
                </div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-md-4">
          <a href="/org_chart/user/divisions.php" class="text-decoration-none">
            <div class="card text-bg-dark overflow-hidden shadow">
              <img src="/org_chart/img/colleges.jpg" class="card-img card-img-tile" alt="Divisions and Departments">
              <div class="card-img-overlay d-flex flex-column justify-content-end overlay-light">
                <div class="text-band">
                  <h4 class="card-title">Divisions &amp; Departments</h4>
                  <p class="card-text mb-0"><?php echo $div_count; ?> divisions, <?php echo $college_count; ?> colleges, <?php echo $dept_count; ?> departments</p>
                </div>
              </div>
            </div>
          </a>
        </div>

        <div class="col-md-4">
          <a href="/org_chart/user/classifications.php" class="text-decoration-none">
            <div class="card text-bg-dark overflow-hidden shadow">
              <img src="/org_chart/img/classifications.jpg" class="card-img card-img-tile" alt="Classifications and Ranks">
              <div class="card-img-overlay d-flex flex-column justify-content-end overlay-light">
                <div class="text-band">
                  <h4 class="card-title">Classifications &amp; Ranks</h4>
                  <p class="card-text mb-0"><?php echo $class_count; ?> classifications</p>
                </div>
              </div>
            </div>
          </a>
        </div>

      </div>
      <!-- Close the database connection -->
      <?php $db_connection->close(); ?>

    </main>
    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>