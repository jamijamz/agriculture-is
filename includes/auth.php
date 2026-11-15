<?php
/**
 * AgriInfo System - Authentication & Session Security Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensures the user is logged in. 
 * Redirects to login.php if the session is missing or unauthenticated.
 */
function require_login() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // Automatically resolve relative path whether included from root or modules folder
        $loginPath = file_exists('login.php') ? 'login.php' : '../login.php';
        header("Location: $loginPath");
        exit();
    }
}

/**
 * Ensures the user is logged in AND possesses Administrator privileges.
 */
function require_admin() {
    require_login();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
        $_SESSION['error'] = "Access denied. Administrator privileges required.";
        $dashboardPath = file_exists('dashboard.php') ? 'dashboard.php' : '../dashboard.php';
        header("Location: $dashboardPath");
        exit();
    }
}
?>