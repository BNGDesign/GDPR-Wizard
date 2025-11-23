<?php
/**
 * Services Management Page
 * Add, Edit, Delete services dynamically
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    }
    
    // Add new service
    elseif (isset($_POST['add_service'])) {
        $serviceKey = $security->sanitize($_POST['service_key']);
        $category = $security->sanitize($_POST['category']);
        $iconClass = $security->sanitize($_POST['icon_class']);
        $sortOrder = (int)$_POST['sort_order'];
        
        try {
            $db->beginTransaction();
            
            // Insert service
            $serviceId = $db->insert('services', [
                'service_key' => $serviceKey,
                'service_category' => $category,
                'icon_class' => $iconClass,
                'sort_order' => $sortOrder,
                'is_active' => 1
            ]);
            
            // Insert translations
            foreach (AVAILABLE_LANGUAGES as $lang) {
                $db->insert('service_translations', [
                    'service_id' => $serviceId,
                    'language_code' => $lang,
                    'service_name' => $security->sanitize($_POST["name_{$lang}"]),
                    'service_description' => $security->sanitize($_POST["description_{$lang}"]),
                    'gdpr_text' => $_POST["gdpr_text_{$lang}"]
                ]);
            }
            
            $db->commit();
            $success = 'Service added successfully!';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to add service: ' . $e->getMessage();
        }
    }
    
    // Toggle service active status
    elseif (isset($_POST['toggle_status'])) {
        $serviceId = (int)$_POST['service_id'];
        $currentStatus = (int)$_POST['current_status'];
        $newStatus = $currentStatus === 1 ? 0 : 1;
        
        $db->update('services', ['is_active' => $newStatus], ['id' => $serviceId]);
        $success = 'Service status updated!';
    }
    
    // Delete service
    elseif (isset($_POST['delete_service'])) {
        $serviceId = (int)$_POST['service_id'];
        
        try {
            $db->beginTransaction();
            
            // Delete fields and translations (cascade)
            $db->delete('services', ['id' => $serviceId]);
            
            $db->commit();
            $success = 'Service deleted successfully!';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to delete service: ' . $e->getMessage();
        }
    }
}

// Get all services
$services = $db->fetchAll("
    SELECT 
        s.*,
        GROUP_CONCAT(DISTINCT st.service_name SEPARATOR ' | ') as names
    FROM services s
    LEFT JOIN service_translations st ON s.id = st.service_id
    GROUP BY s.id
    ORDER BY s.service_category, s.sort_order
");

// Get categories
$categories = $db->fetchAll("SELECT DISTINCT service_category FROM services ORDER BY service_category");

$page_title = 'Services Management';
$csrf_token = $security->generateCSRFToken();

include 'includes/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fa fad-check-circle"></i> <?php echo $success; ?>
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
                <h5><i class="bi bi-puzzle"></i> All Services</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="bi bi-plus-circle"></i> Add New Service
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th>Service Key</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Icon</th>
                                <th>Status</th>
                                <th>Order</th>
                                <th width="200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No services found. Add your first service!
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($services as $service): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?php echo $service['id']; ?></span></td>
                                <td><code><?php echo htmlspecialchars($service['service_key']); ?></code></td>
                                <td><?php echo htmlspecialchars(explode(' | ', $service['names'])[0]); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($service['service_category']); ?></span></td>
                                <td><i class="<?php echo htmlspecialchars($service['icon_class']); ?> fs-5"></i></td>
                                <td>
                                    <form method="POST" class="d-inline" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $service['is_active']; ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $service['is_active'] ? 'success' : 'secondary'; ?>">
                                            <i class="bi bi-<?php echo $service['is_active'] ? 'check-circle' : 'x-circle'; ?>"></i>
                                            <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td><?php echo $service['sort_order']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="service-edit.php?id=<?php echo $service['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="service-fields.php?id=<?php echo $service['id']; ?>" class="btn btn-outline-info">
                                            <i class="bi bi-list-ul"></i> Fields
                                        </a>
                                        <form method="POST" class="d-inline m-0" onsubmit="return confirm('Delete this service?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <button type="submit" name="delete_service" class="btn btn-outline-danger">
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
    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Service Key *</label>
                            <input type="text" class="form-control" name="service_key" required 
                                   placeholder="e.g. google_analytics" pattern="[a-z0-9_]+">
                            <small class="text-muted">Lowercase, numbers, and underscores only</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="Analytics">Analytics</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Social Media">Social Media</option>
                                <option value="Payment">Payment</option>
                                <option value="Hosting">Hosting</option>
                                <option value="CDN">CDN</option>
                                <option value="Email">Email</option>
                                <option value="Embed">Embed</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Icon Class</label>
                            <input type="text" class="form-control" name="icon_class" 
                                   value="bi-puzzle" placeholder="bi-puzzle">
                            <small class="text-muted">Bootstrap Icons class</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" value="0">
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
                                        <label class="form-label">Service Name *</label>
                                        <input type="text" class="form-control" name="name_<?php echo $lang; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description_<?php echo $lang; ?>" rows="2"></textarea>
                                    </div>
                                    
                                    <div class="mb-0">
                                        <label class="form-label">GDPR Text *</label>
                                        <textarea class="form-control" name="gdpr_text_<?php echo $lang; ?>" rows="4" required
                                                  placeholder="Enter the GDPR privacy policy text for this service..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_service" class="btn btn-primary">
                        <i class="bi bi-save"></i> Add Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>