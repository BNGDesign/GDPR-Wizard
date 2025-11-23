<?php
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
$serviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$serviceId) {
    header('Location: services.php');
    exit;
}
if (!$db->isConnected()) {
    $page_title = 'Service Edit';
    include 'includes/header.php';
    echo '<div class="alert alert-danger">Database bağlantısı kurulamadı. Lütfen ayarları kontrol edin.</div>';
    include 'includes/footer.php';
    exit;
}
$service = $db->fetchOne('SELECT * FROM services WHERE id = ?', [$serviceId]);
if (!$service) {
    header('Location: services.php');
    exit;
}
$translations = $db->fetchAll('SELECT language_code, service_name, service_description, gdpr_text FROM service_translations WHERE service_id = ?', [$serviceId]);
$translationMap = [];
foreach ($translations as $row) {
    $translationMap[$row['language_code']] = $row;
}

/* === KATEGORİLERİ YÜKLE (BUNUN YÜZÜNDEN BOŞ GELİYORDU) === */
$categories = $db->fetchAll("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $serviceKey = $security->sanitize($_POST['service_key']);
        $category = $security->sanitize($_POST['service_category']);
        $iconClass = $security->sanitize($_POST['icon_class']);
        $sortOrder = (int)$_POST['sort_order'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        try {
            $db->beginTransaction();
            $db->update('services', [
                'service_key' => $serviceKey,
                'service_category' => $category,
                'icon_class' => $iconClass,
                'sort_order' => $sortOrder,
                'is_active' => $isActive
            ], [
                'id' => $serviceId
            ]);
            foreach (AVAILABLE_LANGUAGES as $lang) {
                $name = $security->sanitize($_POST['name_' . $lang] ?? '');
                $description = $security->sanitize($_POST['description_' . $lang] ?? '');
                $gdprText = $_POST['gdpr_text_' . $lang] ?? '';
                $db->query(
                    "INSERT INTO service_translations (service_id, language_code, service_name, service_description, gdpr_text) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE service_name = VALUES(service_name), service_description = VALUES(service_description), gdpr_text = VALUES(gdpr_text)",
                    [$serviceId, $lang, $name, $description, $gdprText]
                );
            }
            $db->commit();
            $success = 'Service updated successfully!';
            $service = $db->fetchOne('SELECT * FROM services WHERE id = ?', [$serviceId]);
            $translations = $db->fetchAll('SELECT language_code, service_name, service_description, gdpr_text FROM service_translations WHERE service_id = ?', [$serviceId]);
            $translationMap = [];
            foreach ($translations as $row) {
                $translationMap[$row['language_code']] = $row;
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to update service: ' . $e->getMessage();
        }
    }
}
$page_title = 'Edit Service #' . $serviceId;
$csrf_token = $security->generateCSRFToken();
include 'includes/header.php';
?>
<div class="mb-3">
    <a href="services.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Services
    </a>
</div>
<?php if ($success): ?>
    <div class="alert alert-success">
    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
</div>
<?php endif; ?>
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-pencil-square"></i> Service Details</h5>
    </div>
    <div class="card-body">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Service Key</label>
                    <input type="text" name="service_key" class="form-control" value="<?php echo htmlspecialchars($service['service_key']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="service_category" class="form-control" required>
                        <option value="">— Select Category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category_key']); ?>"
                                <?php echo ($service['service_category'] === $cat['category_key']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Icon Class</label>
                    <input type="text" name="icon_class" class="form-control" value="<?php echo htmlspecialchars($service['icon_class']); ?>" placeholder="bi-puzzle">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?php echo (int)$service['sort_order']; ?>">
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
            </div>

            <hr>

            <h6 class="mb-3">Translations</h6>

            <?php foreach (AVAILABLE_LANGUAGES as $lang):
                $row = $translationMap[$lang] ?? ['service_name' => '', 'service_description' => '', 'gdpr_text' => ''];
            ?>
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong><?php echo strtoupper($lang); ?></strong>
                    <span class="text-muted small">Service content</span>
                </div>

                <div class="mb-2">
                    <label class="form-label">Name (<?php echo strtoupper($lang); ?>)</label>
                    <input type="text" name="name_<?php echo $lang; ?>" class="form-control" value="<?php echo htmlspecialchars($row['service_name']); ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Description (<?php echo strtoupper($lang); ?>)</label>
                    <textarea name="description_<?php echo $lang; ?>" class="form-control" rows="2" required><?php echo htmlspecialchars($row['service_description']); ?></textarea>
                </div>

                <div class="mb-0">
                    <label class="form-label">GDPR Text (<?php echo strtoupper($lang); ?>)</label>
                    <textarea name="gdpr_text_<?php echo $lang; ?>" class="form-control" rows="4" required><?php echo htmlspecialchars($row['gdpr_text']); ?></textarea>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Changes
                </button>
            </div>

        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
