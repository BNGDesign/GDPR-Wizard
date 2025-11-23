<?php
/**
 * Edit individual service field
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
$fieldId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$fieldId) {
    header('Location: services.php');
    exit;
}
if (!$db->isConnected()) {
    $page_title = 'Edit Service Field';
    include 'includes/header.php';
    echo '<div class="alert alert-danger">Database bağlantısı kurulamadı. Lütfen ayarları kontrol edin.</div>';
    include 'includes/footer.php';
    exit;
}
$field = $db->fetchOne('SELECT * FROM service_fields WHERE id = ?', [$fieldId]);
if (!$field) {
    header('Location: services.php');
    exit;
}
$service = $db->fetchOne('SELECT id, service_key FROM services WHERE id = ?', [$field['service_id']]);
$translations = $db->fetchAll('SELECT language_code, field_label, field_placeholder FROM service_field_translations WHERE field_id = ?', [$fieldId]);
$translationMap = [];
foreach ($translations as $row) {
    $translationMap[$row['language_code']] = $row;
}
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $fieldKey = $security->sanitize($_POST['field_key']);
        $fieldType = $security->sanitize($_POST['field_type']);
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $sortOrder = (int)$_POST['sort_order'];
        try {
            $db->beginTransaction();
            $db->update('service_fields', [
                'field_key' => $fieldKey,
                'field_type' => $fieldType,
                'is_required' => $isRequired,
                'sort_order' => $sortOrder
            ], [
                'id' => $fieldId
            ]);
            foreach (AVAILABLE_LANGUAGES as $lang) {
                $label = $security->sanitize($_POST['label_' . $lang] ?? '');
                $placeholder = $security->sanitize($_POST['placeholder_' . $lang] ?? '');
                $db->query(
                    "INSERT INTO service_field_translations (field_id, language_code, field_label, field_placeholder) VALUES (?, ?, ?, ?)" .
                    " ON DUPLICATE KEY UPDATE field_label = VALUES(field_label), field_placeholder = VALUES(field_placeholder)",
                    [$fieldId, $lang, $label, $placeholder]
                );
            }
            $db->commit();
            $success = 'Field updated successfully!';
            $field = $db->fetchOne('SELECT * FROM service_fields WHERE id = ?', [$fieldId]);
            $translations = $db->fetchAll('SELECT language_code, field_label, field_placeholder FROM service_field_translations WHERE field_id = ?', [$fieldId]);
            $translationMap = [];
            foreach ($translations as $row) {
                $translationMap[$row['language_code']] = $row;
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to update field: ' . $e->getMessage();
        }
    }
}
$page_title = 'Edit Field #' . $fieldId;
$csrf_token = $security->generateCSRFToken();
include 'includes/header.php';
?>
<div class="mb-3 d-flex justify-content-between align-items-center">
    <div>
        <a href="service-fields.php?id=<?php echo (int)$field['service_id']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Fields
        </a>
    </div>
    <?php if ($service): ?>
    <div class="text-muted">
        <i class="bi bi-puzzle"></i> Service: <code><?php echo htmlspecialchars($service['service_key']); ?></code>
    </div>
    <?php endif; ?>
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
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-pencil-square"></i> Field Details</h5>
    </div>
    <div class="card-body">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Field Key</label>
                    <input type="text" name="field_key" class="form-control" value="<?php echo htmlspecialchars($field['field_key']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Field Type</label>
                    <select name="field_type" class="form-select">
                        <?php
                        $types = ['text', 'email', 'url', 'number', 'phone', 'textarea'];
                        foreach ($types as $type) {
                            $selected = $field['field_type'] === $type ? 'selected' : '';
                            echo "<option value='{$type}' {$selected}>" . ucfirst($type) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?php echo (int)$field['sort_order']; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_required" id="isRequired" <?php echo $field['is_required'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isRequired">Required</label>
                    </div>
                </div>
            </div>
            <hr>
            <h6 class="mb-3">Translations</h6>
            <?php foreach (AVAILABLE_LANGUAGES as $lang):
                $row = $translationMap[$lang] ?? ['field_label' => '', 'field_placeholder' => ''];
            ?>
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong><?php echo strtoupper($lang); ?></strong>
                    <span class="text-muted small">Field content</span>
                </div>
                <div class="mb-2">
                    <label class="form-label">Label (<?php echo strtoupper($lang); ?>)</label>
                    <input type="text" name="label_<?php echo $lang; ?>" class="form-control" value="<?php echo htmlspecialchars($row['field_label']); ?>" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">Placeholder (<?php echo strtoupper($lang); ?>)</label>
                    <input type="text" name="placeholder_<?php echo $lang; ?>" class="form-control" value="<?php echo htmlspecialchars($row['field_placeholder']); ?>">
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