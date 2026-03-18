<?php
session_start();

// SmartHRM System - Main Entry Point
// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

// Redirect to dashboard if authenticated
header('Location: dashboard.php');
exit();
?>