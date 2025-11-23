<?php
/**
 * Hosting & CDN management
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
$languages = $db->isConnected()
    ? $db->fetchAll('SELECT code, name FROM languages WHERE is_active = 1 ORDER BY sort_order')
    : [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db->isConnected()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!$security->verifyCSRFToken($csrf)) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_hosting') {
            $key = trim($_POST['provider_key'] ?? '');
            $location = trim($_POST['server_location'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($key) {
                $db->insert('hosting_providers', [
                    'provider_key' => $key,
                    'server_location' => $location,
                    'is_active' => $isActive
                ]);
                $success = 'Hosting provider added.';
            } else {
                $error = 'Provider key required.';
            }
        } elseif ($action === 'add_cdn') {
            $key = trim($_POST['cdn_key'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($key) {
                $db->insert('cdn_providers', [
                    'provider_key' => $key,
                    'is_active' => $isActive
                ]);
                $success = 'CDN provider added.';
            } else {
                $error = 'CDN key required.';
            }
        } elseif ($action === 'save_hosting_translation') {
            $id = (int)($_POST['hosting_id'] ?? 0);
            $lang = trim($_POST['language_code'] ?? '');
            $name = trim($_POST['provider_name'] ?? '');
            $gdpr = trim($_POST['gdpr_text'] ?? '');
            if ($id && $lang && $name) {
                $db->query(
                    "INSERT INTO hosting_translations (hosting_id, language_code, provider_name, gdpr_text)
                     VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE provider_name = VALUES(provider_name), gdpr_text = VALUES(gdpr_text)",
                    [$id, $lang, $name, $gdpr]
                );
                $success = 'Hosting translation saved.';
            }
        } elseif ($action === 'save_cdn_translation') {
            $id = (int)($_POST['cdn_id'] ?? 0);
            $lang = trim($_POST['language_code'] ?? '');
            $name = trim($_POST['provider_name'] ?? '');
            $gdpr = trim($_POST['gdpr_text'] ?? '');
            if ($id && $lang && $name) {
                $db->query(
                    "INSERT INTO cdn_translations (cdn_id, language_code, provider_name, gdpr_text)
                     VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE provider_name = VALUES(provider_name), gdpr_text = VALUES(gdpr_text)",
                    [$id, $lang, $name, $gdpr]
                );
                $success = 'CDN translation saved.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Database connection is required.';
}
$hosting = $db->isConnected() ? $db->fetchAll('SELECT * FROM hosting_providers ORDER BY id DESC') : [];
$cdn = $db->isConnected() ? $db->fetchAll('SELECT * FROM cdn_providers ORDER BY id DESC') : [];
$page_title = 'Hosting & CDN';
include 'includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-server"></i> Add Hosting</h5></div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!$db->isConnected()): ?><div class="alert alert-warning">Offline mode.</div><?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_hosting">
                    <div class="col-12">
                        <label class="form-label">Provider Key</label>
                        <input type="text" name="provider_key" class="form-control" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Server Location</label>
                        <input type="text" name="server_location" class="form-control" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="hostActive" checked <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="hostActive">Active</label>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-cloud"></i> Add CDN</h5></div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_cdn">
                    <div class="col-12">
                        <label class="form-label">CDN Key</label>
                        <input type="text" name="cdn_key" class="form-control" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="cdnActive" checked <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="cdnActive">Active</label>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Hosting Providers</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Key</th><th>Location</th><th>Status</th><th>Translations</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hosting)): ?><tr><td colspan="4" class="text-center text-muted py-3">No hosting providers.</td></tr><?php endif; ?>
                            <?php foreach ($hosting as $host): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($host['provider_key']); ?></td>
                                    <td><small><?php echo htmlspecialchars($host['server_location']); ?></small></td>
                                    <td><span class="badge bg-<?php echo $host['is_active'] ? 'success' : 'danger'; ?>"><?php echo $host['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td>
                                        <?php foreach ($languages as $lang): ?>
                                            <?php $tr = $db->fetchOne('SELECT * FROM hosting_translations WHERE hosting_id = ? AND language_code = ?', [$host['id'], $lang['code']]); ?>
                                            <form method="post" class="border rounded p-2 mb-2">
                                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="save_hosting_translation">
                                                <input type="hidden" name="hosting_id" value="<?php echo $host['id']; ?>">
                                                <input type="hidden" name="language_code" value="<?php echo $lang['code']; ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong><?php echo htmlspecialchars($lang['name']); ?></strong>
                                                    <span class="badge bg-light text-dark"><?php echo $lang['code']; ?></span>
                                                </div>
                                                <input type="text" name="provider_name" class="form-control mb-2" placeholder="Name" value="<?php echo htmlspecialchars($tr['provider_name'] ?? ''); ?>" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                                                <textarea name="gdpr_text" class="form-control mb-2" rows="2" placeholder="GDPR text" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>><?php echo htmlspecialchars($tr['gdpr_text'] ?? ''); ?></textarea>
                                                <div class="text-end"><button class="btn btn-sm btn-outline-primary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save</button></div>
                                            </form>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="mb-0">CDN Providers</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Key</th><th>Status</th><th>Translations</th></tr></thead>
                        <tbody>
                            <?php if (empty($cdn)): ?><tr><td colspan="3" class="text-center text-muted py-3">No CDN providers.</td></tr><?php endif; ?>
                            <?php foreach ($cdn as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['provider_key']); ?></td>
                                    <td><span class="badge bg-<?php echo $row['is_active'] ? 'success' : 'danger'; ?>"><?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td>
                                        <?php foreach ($languages as $lang): ?>
                                            <?php $tr = $db->fetchOne('SELECT * FROM cdn_translations WHERE cdn_id = ? AND language_code = ?', [$row['id'], $lang['code']]); ?>
                                            <form method="post" class="border rounded p-2 mb-2">
                                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="save_cdn_translation">
                                                <input type="hidden" name="cdn_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="language_code" value="<?php echo $lang['code']; ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong><?php echo htmlspecialchars($lang['name']); ?></strong>
                                                    <span class="badge bg-light text-dark"><?php echo $lang['code']; ?></span>
                                                </div>
                                                <input type="text" name="provider_name" class="form-control mb-2" placeholder="Name" value="<?php echo htmlspecialchars($tr['provider_name'] ?? ''); ?>" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                                                <textarea name="gdpr_text" class="form-control mb-2" rows="2" placeholder="GDPR text" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>><?php echo htmlspecialchars($tr['gdpr_text'] ?? ''); ?></textarea>
                                                <div class="text-end"><button class="btn btn-sm btn-outline-primary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save</button></div>
                                            </form>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>