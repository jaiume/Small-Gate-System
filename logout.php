<?php
session_start();

// Log out the user by destroying the session
session_destroy();

// Redirect to the admin login page
header("Location: admin_login.php");
exit();
?>
