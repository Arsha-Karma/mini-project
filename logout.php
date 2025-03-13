<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
if (session_destroy()) {
    // Redirect to login page
    header("Location: login.php");
    exit();
} else {
    die("Failed to destroy the session.");
}
?>