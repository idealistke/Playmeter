<?php
// includes/auth_check.php
// This file checks if user is logged in

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Optional: Check user role for admin-only pages
function require_admin() {
    if($_SESSION['role'] != 'admin') {
        header("Location: index.php?error=unauthorized");
        exit();
    }
}

// Optional: Log user activity
function log_activity($db, $user_id, $action) {
    // You can create an activity log table if needed
    $query = "INSERT INTO activity_logs (user_id, action, ip_address) 
              VALUES ($user_id, '$action', '{$_SERVER['REMOTE_ADDR']}')";
    $db->exec($query);
}
?>