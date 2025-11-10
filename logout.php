<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['admin_id'])) {
    // Log the logout
    $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, 'Logout', 'User logged out')");
    $stmt->execute([$_SESSION['admin_id']]);
    
    // Update last login time
    $stmt = $db->prepare("UPDATE administrators SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
}

// Destroy all session data
session_destroy();

// Redirect to login page
header("Location: login.php");
exit(); 