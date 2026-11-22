<?php

/**
 * Authentication Script
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */

// Include necessary configuration and path files
include_once (realpath(dirname(__FILE__).'/path.php'));
include_once (realpath(dirname(__FILE__).'/config.php'));

//-----------------------------------------------------
// Authenticate
//-----------------------------------------------------
if (isset($_POST['submit'])) {
  // Validate input
  if (empty($_POST['username']) || empty($_POST['password'])) {
    // Handle empty input
    $error = "Username or Password is empty!";
    return $error;
  }
} else {
/* Check the Username and Password */
  if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Define variables
    // The prepared statement below protects against SQL injection,
    // so the username is passed through unchanged.
    $username = $_POST['username'];
    $password = $_POST['password'];
    // Look up the user by username only; the password is checked in PHP
    // because each password_hash() result has its own salt.
    $select_users = $db_connection->prepare(
        "SELECT u.user_id, u.role_id, cr.username, cr.password_salted
         FROM Users u
         JOIN Credentials cr ON u.user_id = cr.user_id
         WHERE cr.username = ? LIMIT 1");
    $select_users->bind_param("s", $username);
    $select_users->execute();
    $select_users->bind_result($user_id, $user_role, $username, $password_hash);
    $select_users->store_result();

    // Exactly one matching user, and the password matches its hash
    if($select_users->num_rows == 1 && $select_users->fetch()
       && password_verify($password, $password_hash)) {
      // Successful login
      session_start(); 
      # This area needs to send users and admins to there own directory
      $_SESSION['user_id'] = $user_id;
      $_SESSION['login_user']=$username;
      $_SESSION['user_role'] = $user_role;
      // Redirect based on user role
      if ($_SESSION['user_role'] == 1) {
        header("location: " . BASE_URL . "/admin");
      } elseif ($_SESSION['user_role'] == 2) {
        header("location:" . BASE_URL . "/user");
      } elseif ($_SESSION['user_role'] == 3) {
        header("location:" . BASE_URL . "/guest");
      } else {
        $_SESSION['error'] = "Login Failed";
        
      }
  } else {
    // Invalid login
    $_SESSION['message'] = "Username or Password did not match!";
    // Redirect to logout
    header("location: " . SRC_PATH . "/logout.php"); 
    exit();
  }
  // close the statement
  $select_users->close();
} else {
  // Direct access without POST data
  header("location: " . SRC_PATH . "/logout.php"); 
}
}