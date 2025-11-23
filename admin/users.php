<?php
/**
 * Admin Users Management
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
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db->isConnected()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$security->verifyCSRFToken($csrf)) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if (!$username || !$password || !$email) {
                $error = 'Username, email and password are required.';
            } elseif (!$security->validateEmail($email)) {
                $error = 'Invalid email address.';
            } else {
                $existing = $db->fetchOne('SELECT id FROM admin_users WHERE username = ?', [$username]);
                if ($existing) {
                    $error = 'Username already exists.';
                } else {
                    $db->insert('admin_users', [
                        'username' => $username,
                        'email' => $email,
                        'full_name' => $fullName,
                        'password_hash' => $security->hashPassword($password),
                        'is_active' => $isActive,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    $success = 'User created successfully.';
                    $security->logEvent('admin_user_created', ['admin_id' => $_SESSION['admin_id'], 'username' => $username]);
                }
            }
        } elseif ($action === 'toggle') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $user = $db->fetchOne('SELECT is_active FROM admin_users WHERE id = ?', [$userId]);
            if ($user) {
                $db->update('admin_users', ['is_active' => $user['is_active'] ? 0 : 1], ['id' => $userId]);
                $success = 'User status updated.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Database connection is required for this action.';
}
$users = $db->isConnected()
    ? $db->fetchAll('SELECT id, username, email, full_name, is_active, last_login, created_at FROM admin_users ORDER BY created_at DESC')
    : [];
    $page_title = 'Users';
include 'includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-plus"></i> Add Admin User</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!$db->isConnected()): ?><div class="alert alert-warning">Database offline. User changes disabled.</div><?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="userActive" checked <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                        <label class="form-check-label" for="userActive">Active</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Create User</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Admin Users</h5>
                <span class="badge bg-<?php echo $db->isConnected() ? 'success' : 'danger'; ?>"><?php echo $db->isConnected() ? 'Live' : 'Offline'; ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No admin users found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">#<?php echo $user['id']; ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($user['email']); ?></small></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Suspended'; ?></span>
                                        </td>
                                        <td><small><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></small></td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a class="btn btn-sm btn-outline-primary" href="users-edit.php?id=<?php echo $user['id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="post" class="ms-1">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                                                        <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>