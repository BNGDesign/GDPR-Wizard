<?php
/**
 * Settings Page (FIXED VERSION)
 */

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Security.php';

$db = Database::getInstance();
$security = new Security();

$success = '';
$error = '';

// Get current settings
$settings = [
    'site_name' => '',
    'admin_email' => '',
    'default_language' => 'en',
    'watermark_enabled' => true
];

// Load from config or database
$settingsFromDB = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settingsFromDB as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get available languages
$languages = $db->fetchAll("SELECT * FROM languages WHERE is_active = 1 ORDER BY language_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            $db->beginTransaction();
            
            // Update each setting
            $settingsToSave = [
                'site_name' => $security->sanitize($_POST['site_name']),
                'admin_email' => $security->sanitize($_POST['admin_email']),
                'default_language' => $security->sanitize($_POST['default_language']),
                'watermark_enabled' => isset($_POST['watermark_enabled']) ? '1' : '0'
            ];
            
            foreach ($settingsToSave as $key => $value) {
                $existing = $db->fetchOne(
                    "SELECT id FROM system_settings WHERE setting_key = ?",
                    [$key]
                );
                
                if ($existing) {
                    $db->update('system_settings', [
                        'setting_value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], [
                        'id' => $existing['id']
                    ]);
                } else {
                    $db->insert('system_settings', [
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            // Update default language in languages table
            $db->query("UPDATE languages SET is_default = 0");
            $db->query("UPDATE languages SET is_default = 1 WHERE language_code = ?", [$settingsToSave['default_language']]);
            
            $db->commit();
            $success = 'Settings saved successfully!';
            
            // Reload settings
            $settings = $settingsToSave;
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
}

// Create system_settings table if not exists
$db->query("
    CREATE TABLE IF NOT EXISTS system_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$page_title = 'Settings';
$csrf_token = $security->generateCSRFToken();

include 'includes/header.php';
?>

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

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    
    <div class="row g-4">
        <!-- General Settings -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-gear"></i> General Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Site Name</label>
                        <input type="text" class="form-control" name="site_name" 
                               value="<?php echo htmlspecialchars($settings['site_name']); ?>" 
                               placeholder="GDPR Wizard">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Email</label>
                        <input type="email" class="form-control" name="admin_email" 
                               value="<?php echo htmlspecialchars($settings['admin_email']); ?>" 
                               placeholder="admin@example.com">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Default Language</label>
                        <select class="form-select" name="default_language">
                            <?php foreach ($languages as $lang): ?>
                            <option value="<?php echo $lang['language_code']; ?>" 
                                    <?php echo $settings['default_language'] === $lang['language_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang['language_name']); ?> (<?php echo $lang['language_code']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="watermark_enabled" 
                               id="watermarkEnabled" <?php echo $settings['watermark_enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="watermarkEnabled">
                            Enable Watermark System
                        </label>
                    </div>
                </div>
            </div>
            

        </div>
        
        <!-- Security Info -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check"></i> Security</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i> CSRF Protection Active
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i> SQL Injection Protected
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i> XSS Prevention
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i> Rate Limiting Active
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Current Values -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Current Values</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td><strong>Site Name</strong></td>
                            <td><?php echo htmlspecialchars($settings['site_name'] ?: 'Not set'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Admin Email</strong></td>
                            <td><?php echo htmlspecialchars($settings['admin_email'] ?: 'Not set'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Default Lang</strong></td>
                            <td><?php echo strtoupper($settings['default_language']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Watermark</strong></td>
                            <td>
                                <span class="badge bg-<?php echo $settings['watermark_enabled'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $settings['watermark_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save"></i> Save Settings
        </button>
    </div>
</form>

<script>
// No WordPress functions needed
</script>

<?php include 'includes/footer.php'; ?>