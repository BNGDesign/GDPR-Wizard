<?php
/**
 * GDPR reusable blocks manager
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
if ($db->isConnected()) {
    $db->query("CREATE TABLE IF NOT EXISTS gdpr_blocks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        block_key VARCHAR(100) NOT NULL,
        language_code VARCHAR(10) NOT NULL DEFAULT 'en',
        title VARCHAR(255) NOT NULL,
        content TEXT,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY block_lang (block_key, language_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}
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
        $blockKey = trim($_POST['block_key'] ?? '');
        $language = trim($_POST['language_code'] ?? DEFAULT_LANGUAGE);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($blockKey && $title) {
            $db->query(
                "INSERT INTO gdpr_blocks (block_key, language_code, title, content, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), sort_order = VALUES(sort_order), is_active = VALUES(is_active)",
                [$blockKey, $language, $title, $content, $sortOrder, $isActive]
            );
            $success = 'Block saved successfully.';
        } else {
            $error = 'Block key and title are required.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = 'Database connection is required.';
}
$blocks = $db->isConnected()
    ? $db->fetchAll('SELECT * FROM gdpr_blocks ORDER BY sort_order, block_key')
    : [];
    $page_title = 'GDPR Blocks';
include 'includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Add Block</h5>
            </div>
            <div class="card-body">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if (!$db->isConnected()): ?><div class="alert alert-warning">Offline mode.</div><?php endif; ?>
                <form method="post" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <div class="col-12">
                        <label class="form-label">Block Key</label>
                        <input type="text" name="block_key" class="form-control" placeholder="privacy_intro" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Language</label>
                        <select name="language_code" class="form-select" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?php echo $lang['code']; ?>"><?php echo htmlspecialchars($lang['name']); ?> (<?php echo $lang['code']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="4" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>></textarea>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="blockActive" checked <?php echo !$db->isConnected() ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="blockActive">Active</label>
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
                <h5 class="mb-0">Existing Blocks</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Key</th><th>Language</th><th>Title</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($blocks)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No GDPR blocks saved.</td></tr>
                            <?php else: ?>
                                <?php foreach ($blocks as $block): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($block['block_key']); ?></td>
                                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($block['language_code']); ?></span></td>
                                        <td><?php echo htmlspecialchars($block['title']); ?></td>
                                        <td><span class="badge bg-<?php echo $block['is_active'] ? 'success' : 'danger'; ?>"><?php echo $block['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
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