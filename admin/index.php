<?php
/**
 * Admin Dashboard - Main Page
 */

define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('LANG_PATH', BASE_PATH . '/languages');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Security.php';
require_once INCLUDES_PATH . '/Language.php';

start_secure_session();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$security = new Security();
$lang = new Language();

// Get current language
$currentLang = $_GET['lang'] ?? $_SESSION['wizard_lang'] ?? 'tr';
$lang->setLanguage($currentLang);
$_SESSION['wizard_lang'] = $currentLang;

// Get statistics
$stats = [
    'total_documents' => $db->fetchOne("SELECT COUNT(*) as count FROM gdpr_documents")['count'] ?? 0,
    'total_sessions' => $db->fetchOne("SELECT COUNT(DISTINCT session_id) as count FROM wizard_sessions")['count'] ?? 0,
    'active_services' => $db->fetchOne("SELECT COUNT(*) as count FROM services WHERE is_active = 1")['count'] ?? 0,
    'total_users' => $db->fetchOne("SELECT COUNT(DISTINCT user_id) as count FROM wizard_sessions WHERE user_id IS NOT NULL")['count'] ?? 0,
    'documents_today' => $db->fetchOne("SELECT COUNT(*) as count FROM gdpr_documents WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
    'watermark_tracks' => $db->fetchOne("SELECT COUNT(*) as count FROM watermark_tracking")['count'] ?? 0,
];

// Recent documents
$recent_documents = $db->fetchAll("
    SELECT id, company_name, domain, document_format, created_at 
    FROM gdpr_documents 
    ORDER BY created_at DESC 
    LIMIT 10
");

// Recent security logs
$recent_logs = $db->fetchAll("
    SELECT event_type, ip_address, created_at 
    FROM security_logs 
    ORDER BY created_at DESC 
    LIMIT 10
");

$page_title = 'Dashboard';
include 'includes/header.php';
?>

<div class="dashboard-stats">
    <div class="row g-4">
        <!-- Total Documents -->
        <div class="col-md-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="bi bi-file-text"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total_documents']); ?></div>
                    <div class="stat-label">Total Documents</div>
                </div>
            </div>
        </div>
        
        <!-- Active Sessions -->
        <div class="col-md-3">
            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
        </div>
        
        <!-- Active Services -->
        <div class="col-md-3">
            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <i class="bi bi-puzzle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['active_services']); ?></div>
                    <div class="stat-label">Active Services</div>
                </div>
            </div>
        </div>
        
        <!-- Today's Documents -->
        <div class="col-md-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['documents_today']); ?></div>
                    <div class="stat-label">Documents Today</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Recent Documents -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-file-text"></i> Recent Documents</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Company</th>
                                <th>Domain</th>
                                <th>Format</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_documents)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No documents yet</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_documents as $doc): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?php echo $doc['id']; ?></span></td>
                                <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($doc['domain']); ?></small></td>
                                <td>
                                    <span class="badge bg-<?php echo $doc['document_format'] === 'pdf' ? 'danger' : ($doc['document_format'] === 'html' ? 'primary' : 'secondary'); ?>">
                                        <?php echo strtoupper($doc['document_format']); ?>
                                    </span>
                                </td>
                                <td><small><?php echo date('M d, Y H:i', strtotime($doc['created_at'])); ?></small></td>
                                <td>
                                    <a href="documents.php?view=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
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
    
    <!-- Quick Stats & Activity -->
    <div class="col-md-4">
        <!-- Watermark Stats -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-shield-check"></i> Watermark Tracking</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Total Tracked</span>
                    <strong class="fs-4"><?php echo number_format($stats['watermark_tracks']); ?></strong>
                </div>
                <a href="watermark.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-arrow-right"></i> View Details
                </a>
            </div>
        </div>
        
        <!-- Recent Security Events -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-shield-exclamation"></i> Security Events</h5>
            </div>
            <div class="card-body p-0">
                <div class="activity-list">
                    <?php if (empty($recent_logs)): ?>
                    <div class="text-center text-muted py-3">No events</div>
                    <?php else: ?>
                    <?php foreach (array_slice($recent_logs, 0, 5) as $log): ?>
                    <div class="activity-item">
                        <div class="activity-icon bg-<?php echo strpos($log['event_type'], 'failed') !== false ? 'danger' : 'success'; ?>">
                            <i class="bi bi-<?php echo strpos($log['event_type'], 'failed') !== false ? 'x-circle' : 'check-circle'; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo str_replace('_', ' ', ucfirst($log['event_type'])); ?></div>
                            <div class="activity-meta">
                                <small><?php echo $log['ip_address']; ?> • <?php echo date('H:i', strtotime($log['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="security.php" class="text-decoration-none">View all logs <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Section -->
<div class="row g-4 mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-graph-up"></i> Document Generation Trend (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <canvas id="documentsChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Get last 7 days data
fetch('api/get-chart-data.php?period=7days')
    .then(response => response.json())
    .then(data => {
        const ctx = document.getElementById('documentsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Documents Generated',
                    data: data.values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>