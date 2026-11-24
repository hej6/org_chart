<?php
/**
 * Header Include File
 *
 * @author    Jackie Isabella <
 * @copyright 2026 
 * @date      2026-11-24
 * @version   3.0
 */
// Make sure BASE_URL is defined, so the links below work from any folder
if (!defined('BASE_URL')) {
    include_once __DIR__ . '/../php/path.php';
}
if (!isset($_SESSION)) {
    // Only include session if not already started
    include_once (realpath(dirname(__FILE__).'/path.php'));
    // Include session file
    include_once (ROOT_PATH . '/php/session.php');
}
?>
<?php
// Header Section
?>
<header class="mb-5">
<?php
// navigation bar
?>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>/">   
    <i class="bi bi-claude"></i>  Basic Login</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarText">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php
          /* Show Home link only when user is NOT logged in */
          if (!isset($_SESSION['user_name'])) {
            echo '<li class="nav-item">';
            echo '<a class="nav-link active" aria-current="page" href="' . BASE_URL . '/">Home</a>';
            echo '</li>';
          }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 1) { // Only display when user is logged in
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/admin/index.php">Admin Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="#">New Page</a></li>';
          }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) {
            echo '<li class="nav-item"><a class="nav-link" href="/org_chart/user/index.php">ORG Chart Home</a></li>';
            echo '<li class="nav-item dropdown">';
            echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Browse</a>';
            echo '<ul class="dropdown-menu">';
            echo '<li><a class="dropdown-item" href="/org_chart/user/staff.php">Staff Directory</a></li>';
            echo '<li><a class="dropdown-item" href="/org_chart/user/divisions.php">Divisions, Colleges &amp; Departments</a></li>';
            echo '<li><a class="dropdown-item" href="/org_chart/user/classifications.php">Classifications &amp; Ranks</a></li>';
            echo '</ul>';
            echo '</li>';
            echo '<li class="nav-item"><a class="nav-link" href="/org_chart/user/account.php">My Account</a></li>';
         }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 3) { // Only display when user is logged in
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/guest/index.php">Guest Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="#">New Page</a></li>';
          }
        ?>
      </ul>
      <span class="navbar-text">
        <?php
            /* User Information */
            if (isset($_SESSION['user_first']) && isset($_SESSION['user_type'])) {
                echo 'Welcome ' . $_SESSION['user_first'] . ' (' . $_SESSION['user_type'] . ')';
                echo ' | <a href="' . BASE_URL . '/account.php">My Account</a>';
                echo ' | <a href="' . BASE_URL . '/php/logout.php">Logout</a>';
            } else {
                echo 'Not Logged In';
            }
            ?>
      </span>
    </div>
  </div>
</nav>
</header>