<?php
/**
 * GDPR Documents listing
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
$documents = $db->isConnected()
    ? $db->fetchAll('SELECT id, company_name, domain, document_format, created_at FROM gdpr_documents ORDER BY created_at DESC')
    : [];
    $page_title = 'Documents';
include 'includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="bi bi-file-text"></i> GDPR Documents</h5>
            <small class="text-muted">Generated outputs with watermark tracking</small>
        </div>
        <span class="badge bg-<?php echo $db->isConnected() ? 'success' : 'danger'; ?>"><?php echo $db->isConnected() ? 'Live' : 'Offline'; ?></span>
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No documents available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?php echo $doc['id']; ?></span></td>
                                <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($doc['domain']); ?></small></td>
                                <td><span class="badge bg-<?php echo $doc['document_format'] === 'pdf' ? 'danger' : 'primary'; ?>"><?php echo strtoupper($doc['document_format']); ?></span></td>
                                <td><small><?php echo date('Y-m-d H:i', strtotime($doc['created_at'])); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>