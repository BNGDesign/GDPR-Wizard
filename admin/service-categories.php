<?php
/**
 * Service Categories Management (Multi-Language)
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

// Eğer config dosyasında yoksa varsayılan dilleri tanımlayalım
if (!defined('AVAILABLE_LANGUAGES')) {
    define('AVAILABLE_LANGUAGES', ['en', 'tr', 'de']);
}

$db = Database::getInstance();
$security = new Security();

$success = '';
$error = '';

/* ==================================
   FORM ACTION İŞLEME
================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db->isConnected()) {

    $csrf = $_POST['csrf_token'] ?? '';

    if (!$security->verifyCSRFToken($csrf)) {
        $error = 'Invalid CSRF token.';
    } else {

        $action = $_POST['action'] ?? '';

        /* === CREATE === */
        if ($action === 'create') {
            $key   = trim($_POST['category_key'] ?? '');
            $order = intval($_POST['sort_order'] ?? 0);
            
            // Ana tablo için varsayılan isim (Genelde ilk dil veya İngilizce)
            $defaultLang = AVAILABLE_LANGUAGES[0];
            $defaultName = trim($_POST['category_name_' . $defaultLang] ?? '');

            if (!$key || !$defaultName) {
                $error = 'Category key and at least the main language name are required.';
            } else {
                try {
                    $db->beginTransaction();

                    // 1. Ana Tabloya Ekle
                    $categoryId = $db->insert('service_categories', [
                        'category_key'  => $key,
                        'category_name' => $defaultName, // Fallback isim
                        'sort_order'    => $order,
                        'is_active'     => 1
                    ]);

                    // 2. Çeviri Tablosuna Ekle (Döngü ile)
                    foreach (AVAILABLE_LANGUAGES as $lang) {
                        $langName = trim($_POST['category_name_' . $lang] ?? '');
                        if ($langName) {
                            $db->insert('service_category_translations', [
                                'category_id'   => $categoryId,
                                'language_code' => $lang,
                                'category_name' => $langName
                            ]);
                        }
                    }

                    $db->commit();
                    $success = 'Category created successfully with translations.';

                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }

        /* === UPDATE === */
        elseif ($action === 'update') {
            $id    = (int)($_POST['id'] ?? 0);
            $key   = trim($_POST['category_key'] ?? '');
            $order = intval($_POST['sort_order'] ?? 0);
            $active = isset($_POST['is_active']) ? 1 : 0;
            
            // Ana isim güncellemesi için
            $defaultLang = AVAILABLE_LANGUAGES[0];
            $defaultName = trim($_POST['category_name_' . $defaultLang] ?? '');

            if ($id > 0 && $key && $defaultName) {
                try {
                    $db->beginTransaction();

                    // 1. Ana Tabloyu Güncelle
                    $db->update(
                        'service_categories',
                        [
                            'category_key'  => $key,
                            'category_name' => $defaultName,
                            'sort_order'    => $order,
                            'is_active'     => $active
                        ],
                        ['id' => $id]
                    );

                    // 2. Çevirileri Güncelle (INSERT ... ON DUPLICATE KEY UPDATE mantığı)
                    foreach (AVAILABLE_LANGUAGES as $lang) {
                        $langName = trim($_POST['category_name_' . $lang] ?? '');
                        
                        // SQL Enjeksiyonuna karşı prepare kullanımı Database sınıfında vardır varsayımıyla:
                        $query = "INSERT INTO service_category_translations (category_id, language_code, category_name) 
                                  VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE category_name = VALUES(category_name)";
                        
                        $db->query($query, [$id, $lang, $langName]);
                    }

                    $db->commit();
                    $success = 'Category updated successfully.';

                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Update failed: ' . $e->getMessage();
                }
            } else {
                $error = 'Missing required fields.';
            }
        }

        /* === DELETE === */
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // Foreign key constraint (ON DELETE CASCADE) varsa çeviriler de otomatik silinir.
                $db->delete('service_categories', ['id' => $id]);
                $success = 'Category deleted.';
            }
        }
    }
}

// Kategorileri ve Çevirileri Çekme
// GROUP_CONCAT ile çevirileri tek satırda birleştiriyoruz: "en:Name|tr:İsim|de:Name" formatında
$categories = [];
if ($db->isConnected()) {
    $sql = "SELECT 
                sc.*,
                GROUP_CONCAT(CONCAT(sct.language_code, ':', sct.category_name) SEPARATOR '||') as translations_packed
            FROM service_categories sc
            LEFT JOIN service_category_translations sct ON sc.id = sct.category_id
            GROUP BY sc.id
            ORDER BY sc.sort_order ASC, sc.id ASC";
    $categories = $db->fetchAll($sql);
}

$page_title = 'Service Categories';
$csrf_token = $security->generateCSRFToken();

include 'includes/header.php';
?>

<div class="row g-4">

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Add Category</h5></div>
            <div class="card-body">

                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">

                    <div class="mb-3">
                        <label class="form-label">Category Key *</label>
                        <input type="text" name="category_key" class="form-control" placeholder="e.g. analytics" required>
                        <small class="text-muted">Unique key (a-z, 0-9, _)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>

                    <hr>
                    <h6 class="mb-3">Translations</h6>

                    <?php foreach (AVAILABLE_LANGUAGES as $lang): ?>
                    <div class="mb-3">
                        <label class="form-label text-uppercase"><i class="bi bi-translate"></i> Name (<?php echo $lang; ?>)</label>
                        <input type="text" name="category_name_<?php echo $lang; ?>" class="form-control" required>
                    </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary w-100">Create Category</button>
                </form>

            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Category List</h5>
                <span class="badge bg-<?php echo $db->isConnected() ? 'success' : 'danger'; ?>">
                    <?php echo $db->isConnected() ? 'DB Connected' : 'DB Offline'; ?>
                </span>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Key</th>
                                <th>Name (Default)</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th width="130" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php if (empty($categories)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No categories found.</td></tr>
                        <?php else: ?>

                            <?php foreach ($categories as $cat): 
                                // Çevirileri diziye dönüştürelim (JSON olarak JS'e aktarmak için)
                                $transMap = [];
                                if(!empty($cat['translations_packed'])){
                                    $pairs = explode('||', $cat['translations_packed']);
                                    foreach($pairs as $pair){
                                        $parts = explode(':', $pair, 2);
                                        if(count($parts) === 2){
                                            $transMap[$parts[0]] = $parts[1];
                                        }
                                    }
                                }
                                $transJson = htmlspecialchars(json_encode($transMap), ENT_QUOTES, 'UTF-8');
                            ?>
                                <tr>
                                    <td><span class="badge bg-secondary">#<?php echo $cat['id']; ?></span></td>
                                    <td><code><?php echo htmlspecialchars($cat['category_key']); ?></code></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cat['category_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php 
                                            // Önizleme olarak diğer dilleri göster
                                            foreach($transMap as $l => $n) {
                                                if($n != $cat['category_name']) echo "<span class='me-1 badge bg-light text-dark'>$l: $n</span>";
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td><?php echo $cat['sort_order']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $cat['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $cat['is_active'] ? 'Active' : 'Passive'; ?>
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary"
                                                data-id="<?php echo $cat['id']; ?>"
                                                data-key="<?php echo htmlspecialchars($cat['category_key']); ?>"
                                                data-order="<?php echo $cat['sort_order']; ?>"
                                                data-active="<?php echo $cat['is_active']; ?>"
                                                data-translations="<?php echo $transJson; ?>"
                                                onclick="openEditModal(this)">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <form method="post" class="ms-1" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Delete this category? Associated services may be affected.');">
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


<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">
                    <label class="form-label">Category Key</label>
                    <input type="text" name="category_key" id="edit_key" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="edit_order" class="form-control">
                </div>

                <div class="mb-3 form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_active">
                    <label class="form-check-label" for="edit_active">Active Status</label>
                </div>

                <hr>
                <h6 class="mb-3">Translations</h6>

                <?php foreach (AVAILABLE_LANGUAGES as $lang): ?>
                <div class="mb-3">
                    <label class="form-label text-uppercase">Name (<?php echo $lang; ?>)</label>
                    <input type="text" name="category_name_<?php echo $lang; ?>" id="edit_name_<?php echo $lang; ?>" class="form-control" required>
                </div>
                <?php endforeach; ?>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal nesnesini tanımla (Bootstrap 5)
var editModalElement = document.getElementById('editModal');
var editModal = null;

// Butona tıklandığında çalışacak fonksiyon
function openEditModal(btn) {
    if (!editModal) {
        editModal = new bootstrap.Modal(editModalElement);
    }

    // Butonun data özelliklerinden verileri al
    var id = btn.getAttribute('data-id');
    var key = btn.getAttribute('data-key');
    var order = btn.getAttribute('data-order');
    var active = btn.getAttribute('data-active');
    
    // JSON olarak gelen çevirileri parse et
    var translations = JSON.parse(btn.getAttribute('data-translations'));

    // Form alanlarını doldur
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_key').value = key;
    document.getElementById('edit_order').value = order;
    document.getElementById('edit_active').checked = (active == "1");

    // Dil alanlarını doldur
    // Önce hepsini temizle
    <?php foreach (AVAILABLE_LANGUAGES as $lang): ?>
        document.getElementById('edit_name_<?php echo $lang; ?>').value = '';
    <?php endforeach; ?>

    // Varsa değerleri yaz
    for (var lang in translations) {
        var input = document.getElementById('edit_name_' + lang);
        if (input) {
            input.value = translations[lang];
        }
    }

    // Modalı göster
    editModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>