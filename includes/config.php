<?php
/**
 * GDPR Wizard Configuration File
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gdpr');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Security
define('SECRET_KEY', 'Dq6mThJH9dh5Jfp1qrDIXpV3yhxSX89L'); 
define('SESSION_LIFETIME', 3600); 
define('CSRF_TOKEN_NAME', 'gdpr_csrf_token');
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_PERIOD', 3600); 

// Watermark Settings
define('WATERMARK_ENABLED', true);
define('FINGERPRINT_ENABLED', true);
define('ZERO_WIDTH_WATERMARK', true);

// Paths
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('TEMP_PATH', BASE_PATH . '/temp');
define('LOG_PATH', BASE_PATH . '/logs');

// File Generation
define('PDF_ENGINE', 'mpdf'); 
define('MAX_GENERATION_TIME', 300); 

// Default Language
define('DEFAULT_LANGUAGE', 'tr'); 

// Admin Settings
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_LOGIN_URL', '/admin/login.php');

// Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Europe/Berlin');

// Session Configuration
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
}

if (!function_exists('start_secure_session')) {
    function start_secure_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }
}

// Create necessary directories
$directories = [UPLOAD_PATH, TEMP_PATH, LOG_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}