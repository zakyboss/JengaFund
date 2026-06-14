<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();
// Redirect to login page
header("Location: ../pages/login.php?logout=success");
exit();

?>