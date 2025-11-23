<?php
/**
 * Watermark Tracking & Verification System
 */

define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Security.php';
require_once INCLUDES_PATH . '/WatermarkSystem.php';

start_secure_session();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$security = new Security();
$watermark = new WatermarkSystem();

// Get statistics
$stats = $watermark->getStatistics();

// Get recent watermark entries
$recent_watermarks = $db->fetchAll("
    SELECT 
        w.*,
        g.company_name,
        g.domain
    FROM watermark_tracking w
    LEFT JOIN gdpr_documents g ON w.fingerprint = g.fingerprint
    ORDER BY w.created_at DESC
    LIMIT 50
");

// Get crawler detections
$crawler_detections = $db->fetchAll("
    SELECT 
        cd.*,
        w.user_id,
        g.company_name,
        g.domain
    FROM crawler_detections cd
    LEFT JOIN watermark_tracking w ON cd.fingerprint = w.fingerprint
    LEFT JOIN gdpr_documents g ON cd.fingerprint = g.fingerprint
    ORDER BY cd.detected_at DESC
    LIMIT 20
");

$page_title = 'Watermark Tracking';
$csrf_token = $security->generateCSRFToken();

include 'includes/header.php';
?>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="bi bi-fingerprint"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['total_documents'] ?? 0); ?></div>
                <div class="stat-label">Total Tracked</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                <div class="stat-label">Unique Users</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['unique_fingerprints'] ?? 0); ?></div>
                <div class="stat-label">Unique Fingerprints</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format(count($crawler_detections)); ?></div>
                <div class="stat-label">Theft Detections</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Watermark Tracker -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-shield-check"></i> Watermark Tracking Log</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fingerprint</th>
                                <th>Company</th>
                                <th>Domain</th>
                                <th>IP Address</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_watermarks)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No watermarks tracked yet
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_watermarks as $wm): ?>
                            <tr>
                                <td>
                                    <code class="small" title="<?php echo htmlspecialchars($wm['fingerprint']); ?>">
                                        <?php echo substr($wm['fingerprint'], 0, 8); ?>...
                                    </code>
                                </td>
                                <td><?php echo htmlspecialchars($wm['company_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($wm['domain'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td><small><?php echo htmlspecialchars($wm['ip_address']); ?></small></td>
                                <td><small><?php echo date('M d, H:i', strtotime($wm['created_at'])); ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="viewWatermarkDetails('<?php echo htmlspecialchars($wm['fingerprint']); ?>')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Verification Tool -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="bi bi-search"></i> Verify Document</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Paste document content or fingerprint to verify authenticity</p>
                
                <form id="verifyForm">
                    <div class="mb-3">
                        <label class="form-label">Document Content or Fingerprint</label>
                        <textarea class="form-control" id="verifyContent" rows="5" 
                                  placeholder="Paste document HTML or fingerprint hash..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Verify
                    </button>
                </form>
                
                <div id="verifyResult" class="mt-3" style="display: none;"></div>
            </div>
        </div>
    </div>
    
    <!-- Theft Detections -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Theft Detections</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                    <?php if (empty($crawler_detections)): ?>
                    <div class="list-group-item text-center text-muted py-4">
                        <i class="bi bi-shield-check fs-1"></i>
                        <p class="mb-0 mt-2">No theft detected</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($crawler_detections as $detection): ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-exclamation-circle text-danger fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($detection['company_name'] ?? 'Unknown'); ?></h6>
                                <p class="mb-1 small text-muted">
                                    Detected on: 
                                    <a href="<?php echo htmlspecialchars($detection['detected_url']); ?>" 
                                       target="_blank" class="text-decoration-none">
                                        <?php echo htmlspecialchars($detection['detected_url']); ?>
                                    </a>
                                </p>
                                <small class="text-muted">
                                    <?php echo date('M d, Y H:i', strtotime($detection['detected_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Watermark Layers Info -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="bi bi-layers"></i> Watermark Layers</h5>
            </div>
            <div class="card-body">
                <div class="watermark-layers">
                    <div class="layer-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="layer-badge bg-primary">1</div>
                            <div class="ms-3">
                                <strong>HTML Fingerprint</strong>
                                <div class="small text-muted">SHA256 hash in comment</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="layer-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="layer-badge bg-success">2</div>
                            <div class="ms-3">
                                <strong>Word Variation</strong>
                                <div class="small text-muted">Micro pattern changes</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="layer-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="layer-badge bg-info">3</div>
                            <div class="ms-3">
                                <strong>Zero-Width Unicode</strong>
                                <div class="small text-muted">Invisible characters</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="layer-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="layer-badge bg-warning">4</div>
                            <div class="ms-3">
                                <strong>Crypto Spacing</strong>
                                <div class="small text-muted">CSS letter-spacing</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="layer-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="layer-badge bg-danger">5</div>
                            <div class="ms-3">
                                <strong>Database Tracking</strong>
                                <div class="small text-muted">Full metadata log</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="layer-item mb-3">
                        <div class="d-flex align-items-center">
                            <div class="layer-badge bg-secondary">6</div>
                            <div class="ms-3">
                                <strong>DIFF Tracking</strong>
                                <div class="small text-muted">Duplicate detection</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="layer-item">
                        <div class="d-flex align-items-center">
                            <div class="layer-badge bg-dark">7</div>
                            <div class="ms-3">
                                <strong>Domain Verification</strong>
                                <div class="small text-muted">Token endpoint</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.layer-badge {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}
</style>

<script>
// Verify document
document.getElementById('verifyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const content = document.getElementById('verifyContent').value;
    const resultDiv = document.getElementById('verifyResult');
    
    if (!content) {
        alert('Please enter content to verify');
        return;
    }
    
    fetch('api/verify-watermark.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ content: content })
    })
    .then(response => response.json())
    .then(data => {
        resultDiv.style.display = 'block';
        
        if (data.authentic) {
            resultDiv.className = 'alert alert-success mt-3';
            resultDiv.innerHTML = `
                <h5><i class="bi bi-check-circle"></i> Document Verified!</h5>
                <p><strong>Fingerprint:</strong> <code>${data.fingerprint}</code></p>
                <p><strong>Created:</strong> ${data.created_at}</p>
                <p><strong>IP Address:</strong> ${data.ip_address}</p>
            `;
        } else {
            resultDiv.className = 'alert alert-danger mt-3';
            resultDiv.innerHTML = `
                <h5><i class="bi bi-x-circle"></i> Verification Failed</h5>
                <p>${data.reason}</p>
            `;
        }
    })
    .catch(error => {
        resultDiv.style.display = 'block';
        resultDiv.className = 'alert alert-danger mt-3';
        resultDiv.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Error verifying document';
    });
});

function viewWatermarkDetails(fingerprint) {
    window.open('watermark-details.php?fp=' + fingerprint, '_blank');
}
</script>

<?php include 'includes/footer.php'; ?>