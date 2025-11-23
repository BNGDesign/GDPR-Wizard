<?php
/**
 * Data Types management
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
        if ($action === 'create_type') {
            $key = trim($_POST['data_type_key'] ?? '');
            $icon = trim($_POST['icon_class'] ?? 'bi-database');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $sort = (int)($_POST['sort_order'] ?? 0);
            if ($key) {
                $db->insert('data_types', [
                    'data_type_key' => $key,
                    'icon_class' => $icon,
                    'is_active' => $isActive,
                    'sort_order' => $sort,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $success = 'Data type added.';
            } else {
                $error = 'Key is required.';
            }
        } elseif ($action === 'save_translation') {
            $typeId = (int)($_POST['data_type_id'] ?? 0);
            $lang = trim($_POST['language_code'] ?? '');
            $name = trim($_POST['data_type_name'] ?? '');
            $description = trim($_POST['data_type_description'] ?? '');
            $gdpr = trim($_POST['gdpr_text'] ?? '');
            if ($typeId && $lang && $name) {
                $db->query(
                    "INSERT INTO data_type_translations (data_type_id, language_code, data_type_name, data_type_description, gdpr_text)
                     VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE data_type_name = VALUES(data_type_name), data_type_description = VALUES(data_type_description), gdpr_text = VALUES(gdpr_text)",
                    [$typeId, $lang, $name, $description, $gdpr]
                );
                $success = 'Translation saved.';
            } else {
                $error = 'All translation fields are required.';
            }
        }
    }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Database connection is required.';
}
$dataTypes = $db->isConnected()
    ? $db->fetchAll('SELECT * FROM data_types ORDER BY sort_order, id DESC')
    : [];
    $page_title = 'Data Types';
include 'includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-database-add"></i> Add Data Type</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!$db->isConnected()): ?><div class="alert alert-warning">Database offline.</div><?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="create_type">
                    <div class="col-12">
                        <label class="form-label">Key</label>
                        <input type="text" name="data_type_key" class="form-control" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Icon (Bootstrap Icon class)</label>
                        <input type="text" name="icon_class" class="form-control" value="bi-database" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="typeActive" checked <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="typeActive">Active</label>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" type="submit" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Data Types & Translations</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Status</th>
                                <th>Translations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dataTypes)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No data types found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($dataTypes as $type): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($type['data_type_key']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($type['icon_class']); ?></small>
                                        </td>
                                        <td><span class="badge bg-<?php echo $type['is_active'] ? 'success' : 'danger'; ?>"><?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                        <td>
                                            <?php foreach ($languages as $lang): ?>
                                                <?php $tr = $db->fetchOne('SELECT * FROM data_type_translations WHERE data_type_id = ? AND language_code = ?', [$type['id'], $lang['code']]); ?>
                                                <form method="post" class="border rounded p-2 mb-2">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="save_translation">
                                                    <input type="hidden" name="data_type_id" value="<?php echo $type['id']; ?>">
                                                    <input type="hidden" name="language_code" value="<?php echo $lang['code']; ?>">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <strong><?php echo htmlspecialchars($lang['name']); ?></strong>
                                                        <span class="badge bg-light text-dark"><?php echo $lang['code']; ?></span>
                                                    </div>
                                                    <input type="text" name="data_type_name" class="form-control mb-2" placeholder="Name" value="<?php echo htmlspecialchars($tr['data_type_name'] ?? ''); ?>" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                                                    <textarea name="data_type_description" class="form-control mb-2" rows="2" placeholder="Description" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>><?php echo htmlspecialchars($tr['data_type_description'] ?? ''); ?></textarea>
                                                    <textarea name="gdpr_text" class="form-control mb-2" rows="2" placeholder="GDPR text" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>><?php echo htmlspecialchars($tr['gdpr_text'] ?? ''); ?></textarea>
                                                    <div class="text-end">
                                                        <button class="btn btn-sm btn-outline-primary" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>Save</button>
                                                    </div>
                                                </form>
                                            <?php endforeach; ?>
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
