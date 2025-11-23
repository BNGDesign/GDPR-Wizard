<?php
/**
 * Edit current admin profile
 */
 define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Security.php';
start_secure_session();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
    }
$db = Database::getInstance();
$security = new Security();
$user = $db->isConnected() ? $db->fetchOne('SELECT * FROM admin_users WHERE id = ?', [$_SESSION['admin_id']]) : null;
if (!$user) {
    $user = [
        'username' => $_SESSION['admin_username'],
        'email' => $_SESSION['admin_email'] ?? '',
        'full_name' => $_SESSION['admin_name'] ?? '',
        'is_active' => 1
    ];
    }
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db->isConnected()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$security->verifyCSRFToken($csrf)) {
        $error = 'Invalid CSRF token.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$security->validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $updateData = [
                'full_name' => $fullName,
                'email' => $email
            ];
            if (!empty($password)) {
                $updateData['password_hash'] = $security->hashPassword($password);
            }
            $db->update('admin_users', $updateData, ['id' => $_SESSION['admin_id']]);
            $success = 'Profile updated successfully.';
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_name'] = $fullName;
            $security->logEvent('admin_profile_updated', ['admin_id' => $_SESSION['admin_id']]);
        }
    }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Database connection is required.';
    }
$page_title = 'Edit Profile';
include 'includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person"></i> Edit Profile</h5>
                <a href="profile.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!$db->isConnected()): ?><div class="alert alert-warning">Database offline.</div><?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
<?php include 'includes/footer.php'; ?>