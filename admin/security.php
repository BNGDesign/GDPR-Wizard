<?php
/**
 * Security Center: logs and blocked IPs
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
        if ($action === 'block') {
            $ip = trim($_POST['ip_address'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $duration = (int)($_POST['duration'] ?? 0);
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $error = 'Please provide a valid IP address.';
            } else {
                $security->blockIP($ip, $reason, $duration > 0 ? $duration * 3600 : null);
                $success = 'IP blocked successfully.';
            }
        } elseif ($action === 'unblock') {
            $id = (int)($_POST['block_id'] ?? 0);
            $db->delete('blocked_ips', ['id' => $id]);
            $success = 'IP entry removed.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Database connection is required.';
}
$logs = $db->isConnected()
    ? $db->fetchAll('SELECT event_type, ip_address, user_agent, details, created_at FROM security_logs ORDER BY created_at DESC LIMIT 50')
    : [];
    $blocked = $db->isConnected()
    ? $db->fetchAll('SELECT * FROM blocked_ips ORDER BY created_at DESC')
    : [];
    $page_title = 'Security';
include 'includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-plus"></i> Block IP</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!$db->isConnected()): ?><div class="alert alert-warning">Offline mode. Changes disabled.</div><?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="block">
                    <div class="col-12">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="ip_address" class="form-control" placeholder="192.168.0.1" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="Multiple failed logins" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Duration (hours)</label>
                        <input type="number" name="duration" min="0" class="form-control" value="0" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                        <small class="text-muted">Leave 0 for permanent block.</small>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-danger" type="submit" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Block IP</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">Blocked IPs</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>Reason</th>
                                <th>Expires</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($blocked)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No blocked IPs.</td></tr>
                            <?php else: ?>
                                <?php foreach ($blocked as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                        <td><small><?php echo htmlspecialchars($row['reason']); ?></small></td>
                                        <td><small><?php echo $row['expires_at'] ?: 'Permanent'; ?></small></td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="unblock">
                                                <input type="hidden" name="block_id" value="<?php echo $row['id']; ?>">
                                                <button class="btn btn-sm btn-outline-secondary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Remove</button>
                                            </form>
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
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-activity"></i> Security Logs</h5>
                <span class="badge bg-<?php echo $db->isConnected() ? 'success' : 'danger'; ?>"><?php echo $db->isConnected() ? 'Live' : 'Offline'; ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>IP</th>
                                <th>Details</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No logs found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                                        <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($log['details']); ?></small></td>
                                        <td><small><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></small></td>
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