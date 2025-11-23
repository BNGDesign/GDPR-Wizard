<?php
/**
 * Edit Admin User
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
$userId = (int)($_GET['id'] ?? 0);
$user = $db->isConnected() ? $db->fetchOne('SELECT * FROM admin_users WHERE id = ?', [$userId]) : null;
if (!$user) {
    header('Location: users.php');
    exit;
}
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db->isConnected()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$security->verifyCSRFToken($csrf)) {
        $error = 'Invalid CSRF token.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        if (!$security->validateEmail($email)) {
            $error = 'Please provide a valid email.';
        } else {
            $updateData = [
                'email' => $email,
                'full_name' => $fullName,
                'is_active' => $isActive
            ];
            if (!empty($password)) {
                $updateData['password_hash'] = $security->hashPassword($password);
            }
            $db->update('admin_users', $updateData, ['id' => $userId]);
            $success = 'User updated successfully.';
            $security->logEvent('admin_user_updated', ['admin_id' => $_SESSION['admin_id'], 'target_id' => $userId]);
            $user = $db->fetchOne('SELECT * FROM admin_users WHERE id = ?', [$userId]);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Database connection is required.';
}
$page_title = 'Edit User';
include 'includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="bi bi-person-gear"></i> Edit User</h5>
                    <small class="text-muted">Update account details securely</small>
                </div>
                <a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!$db->isConnected()): ?><div class="alert alert-warning">Database offline.</div><?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo $user['is_active'] ? 'checked' : ''; ?> <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="isActive">Account Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>