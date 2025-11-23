<?php
/**
 * Admin Profile Overview
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
$currentUser = null;
if ($db->isConnected()) {
    $currentUser = $db->fetchOne('SELECT * FROM admin_users WHERE id = ?', [$_SESSION['admin_id']]);
}
$page_title = 'Profile';
include 'includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-circle"></i> My Profile</h5>
                <a href="profile-edit.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square"></i> Edit</a>
            </div>
            <div class="card-body">
                <?php if (!$db->isConnected()): ?>
                    <div class="alert alert-warning">Database not reachable. Displaying session values only.</div>
                <?php endif; ?>
                <dl class="row mb-0">
                    <dt class="col-4">Username</dt>
                    <dd class="col-8"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></dd>
                    <dt class="col-4">Full Name</dt>
                    <dd class="col-8"><?php echo htmlspecialchars($currentUser['full_name'] ?? ($_SESSION['admin_name'] ?? '')); ?></dd>
                    <dt class="col-4">Email</dt>
                    <dd class="col-8"><?php echo htmlspecialchars($currentUser['email'] ?? ($_SESSION['admin_email'] ?? '')); ?></dd>
                    <dt class="col-4">Last Login</dt>
                    <dd class="col-8"><?php echo isset($currentUser['last_login']) ? htmlspecialchars($currentUser['last_login']) : 'N/A'; ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>