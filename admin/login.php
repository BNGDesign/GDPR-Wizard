<?php
/**
 * Admin Login Page
 */

define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('LANG_PATH', BASE_PATH . '/languages');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Security.php';
require_once INCLUDES_PATH . '/Language.php';

start_secure_session();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$security = new Security();
$lang = new Language();

// Get current language
$currentLang = $_GET['lang'] ?? $_SESSION['wizard_lang'] ?? 'de';
$lang->setLanguage($currentLang);
$_SESSION['wizard_lang'] = $currentLang;

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $security->sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!$security->verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    }
    // Rate limiting
    elseif (!$security->checkRateLimit('admin_login_' . $security->getClientIP(), 5, 300)) {
        $error = 'Too many login attempts. Please try again in 5 minutes.';
        $security->logEvent('rate_limit_exceeded', ['type' => 'admin_login']);
    }
    // Check if IP is blocked
    elseif ($security->isIPBlocked()) {
        $error = 'Your IP address has been blocked.';
        $security->logEvent('blocked_ip_attempt', ['type' => 'admin_login']);
    }
    // Validate input
    elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    }
    // Authenticate
    else {
        $sql = "SELECT * FROM admin_users WHERE username = ? AND is_active = 1";
        $user = $db->fetchOne($sql, [$username]);
        
        if ($user && $security->verifyPassword($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_name'] = $user['full_name'];
            
            // Update last login
            $db->update('admin_users', [
                'last_login' => date('Y-m-d H:i:s')
            ], [
                'id' => $user['id']
            ]);
            
            // Log successful login
            $security->logEvent('admin_login_success', [
                'admin_id' => $user['id'],
                'username' => $username
            ]);
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            
            // Log failed login
            $security->logEvent('admin_login_failed', [
                'username' => $username
            ]);
        }
    }
}



// Generate CSRF token
$csrf_token = $security->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - GDPR Wizard</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/solid.css">
    <link rel="stylesheet" href="../assets/css/regular.css">
    <link rel="stylesheet" href="../assets/css/light.css">
    <link rel="stylesheet" href="../assets/css/thin.css">
    <link rel="stylesheet" href="../assets/css/duotone.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <img src="../assets/img/gdpr-login.webp" />
            <h1><?php echo $lang->get('gdpr_wizard_management_system'); ?></h1>
            <h2><?php echo $lang->get('admin_panel'); ?></h2>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fad fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fad fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label class="form-label" for="username"></label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fad fa-user"></i>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="<?php echo $lang->get('username'); ?>" required autofocus>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="form-label" for="password"></label>
                    <div class="input-group position-relative">
                        <span class="input-group-text">
                            <i class="fad fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo $lang->get('password'); ?>" required>
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fad fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="login" class="btn btn-login">
                        <i class="fad fa-arrow-right-to-bracket"></i> <?php echo $lang->get('login_admin_button'); ?>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="login-footer">
            <i class="fad fa-info-circle"></i> <?php echo $lang->get('default_credentials'); ?> 
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Auto-focus on username field
window.onload = function() {
    document.getElementById('username').focus();
}
</script>
<!-- Footer Info - Fixed Bottom -->
<div class="all_rights_reserved">
    <a href="https://detailwebdesign.com.tr" target="_blank">
        © 2025 Detail Web Design · Alper İlhan · All Rights Reserved
    </a>
</div>

</body>
</html>