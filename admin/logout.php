<?php
/**
 * Admin Logout
 */

define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Security.php';

start_secure_session();

$security = new Security();

// Log logout event
if (isset($_SESSION['admin_id'])) {
    $security->logEvent('admin_logout', [
        'admin_id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? ''
    ]);
}

// Destroy session
session_unset();
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// Redirect to login
header('Location: login.php');
exit;