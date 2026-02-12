<?php
require_once 'conn.php';

// Clear session data
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to home page
header("Location: home.php");
exit();
?>
