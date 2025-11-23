<?php
/**
 * Service Fields Management
 * Manage dynamic fields for each service
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

$serviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$serviceId) {
    header('Location: services.php');
    exit;
}

// Get service info
$service = $db->fetchOne("SELECT * FROM services WHERE id = ?", [$serviceId]);
if (!$service) {
    header('Location: services.php');
    exit;
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    }
    
    // Add new field
    elseif (isset($_POST['add_field'])) {
        $fieldKey = $security->sanitize($_POST['field_key']);
        $fieldType = $security->sanitize($_POST['field_type']);
        $isRequired = isset($_POST['is_required']) ? 1 : 0;
        $sortOrder = (int)$_POST['sort_order'];
        
        try {
            $db->beginTransaction();
            
            // Insert field
            $fieldId = $db->insert('service_fields', [
                'service_id' => $serviceId,
                'field_key' => $fieldKey,
                'field_type' => $fieldType,
                'is_required' => $isRequired,
                'sort_order' => $sortOrder
            ]);
            
            // Insert field translations
            foreach (AVAILABLE_LANGUAGES as $lang) {
                $db->insert('service_field_translations', [
                    'field_id' => $fieldId,
                    'language_code' => $lang,
                    'field_label' => $security->sanitize($_POST["label_{$lang}"]),
                    'field_placeholder' => $security->sanitize($_POST["placeholder_{$lang}"])
                ]);
            }
            
            $db->commit();
            $success = 'Field added successfully!';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to add field: ' . $e->getMessage();
        }
    }
    
    // Delete field
    elseif (isset($_POST['delete_field'])) {
        $fieldId = (int)$_POST['field_id'];
        
        try {
            $db->delete('service_fields', ['id' => $fieldId]);
            $success = 'Field deleted successfully!';
        } catch (Exception $e) {
            $error = 'Failed to delete field';
        }
    }
}

// Get all fields for this service
$fields = $db->fetchAll("
    SELECT 
        sf.*,
        GROUP_CONCAT(DISTINCT sft.field_label SEPARATOR ' | ') as labels
    FROM service_fields sf
    LEFT JOIN service_field_translations sft ON sf.id = sft.field_id
    WHERE sf.service_id = ?
    GROUP BY sf.id
    ORDER BY sf.sort_order
", [$serviceId]);

$page_title = 'Service Fields: ' . $service['service_key'];
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

<div class="row g-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>
                    <i class="bi bi-list-ul"></i> Fields for 
                    <code><?php echo htmlspecialchars($service['service_key']); ?></code>
                </h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                    <i class="bi bi-plus-circle"></i> Add Field
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th>Field Key</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Order</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fields)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No fields defined. Add fields that users need to fill for this service.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?php echo $field['id']; ?></span></td>
                                <td><code><?php echo htmlspecialchars($field['field_key']); ?></code></td>
                                <td><?php echo htmlspecialchars(explode(' | ', $field['labels'])[0]); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($field['field_type']); ?></span></td>
                                <td>
                                    <?php if ($field['is_required']): ?>
                                    <span class="badge bg-danger">Required</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Optional</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $field['sort_order']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="service-field-edit.php?id=<?php echo $field['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline m-0" onsubmit="return confirm('Delete this field?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                            <button type="submit" name="delete_field" class="btn btn-outline-danger">
                                                <i class="bi bi-trash"></i>
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
        
        <!-- Example Preview -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="bi bi-eye"></i> Preview</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">This is how the fields will appear in the frontend wizard:</p>
                
                <div class="service-card" style="border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px;">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div style="width: 48px; height: 48px; background: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="<?php echo htmlspecialchars($service['icon_class']); ?> fs-4"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($service['service_key']); ?></h5>
                            <small class="text-muted">Select this service to show these fields</small>
                        </div>
                    </div>
                    
                    <?php if (!empty($fields)): ?>
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 15px; margin-top: 15px;">
                        <div class="row g-3">
                            <?php foreach ($fields as $field): ?>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <?php echo htmlspecialchars(explode(' | ', $field['labels'])[0]); ?>
                                    <?php if ($field['is_required']): ?>
                                    <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>
                                <input type="<?php echo htmlspecialchars($field['field_type']); ?>" 
                                       class="form-control" 
                                       placeholder="Example: user input here"
                                       <?php echo $field['is_required'] ? 'required' : ''; ?>>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> No fields defined yet. This service won't require any additional input from users.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Field Modal -->
<div class="modal fade" id="addFieldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Field</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Field Key *</label>
                            <input type="text" class="form-control" name="field_key" required 
                                   placeholder="e.g. tracking_id" pattern="[a-z0-9_]+">
                            <small class="text-muted">Lowercase, numbers, and underscores only</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Field Type *</label>
                            <select class="form-select" name="field_type" required>
                                <option value="text">Text</option>
                                <option value="email">Email</option>
                                <option value="url">URL</option>
                                <option value="number">Number</option>
                                <option value="tel">Phone</option>
                                <option value="textarea">Textarea</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" value="0">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label d-block">Options</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_required" id="isRequired">
                                <label class="form-check-label" for="isRequired">
                                    Required Field
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <hr>
                            <h6>Translations</h6>
                        </div>
                        
                        <?php foreach (AVAILABLE_LANGUAGES as $lang): ?>
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="text-uppercase"><?php echo $lang; ?></h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Field Label *</label>
                                        <input type="text" class="form-control" name="label_<?php echo $lang; ?>" required
                                               placeholder="e.g. Tracking ID">
                                    </div>
                                    
                                    <div class="mb-0">
                                        <label class="form-label">Placeholder Text</label>
                                        <input type="text" class="form-control" name="placeholder_<?php echo $lang; ?>"
                                               placeholder="e.g. Enter your tracking ID">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_field" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Field
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>