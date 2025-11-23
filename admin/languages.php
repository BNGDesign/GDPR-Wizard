<?php
/**
 * Advanced Languages & Translation Manager
 * FULL VERSION: Add, Edit, Delete, Translate
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

// --- İŞLEM YÖNETİMİ (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Kontrolü
    if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik tokeni geçersiz!';
    }
    
    // 1. DİL EKLEME
    elseif (isset($_POST['add_language'])) {
        $langCode = strtolower(trim($_POST['language_code']));
        $langName = trim($_POST['language_name']);
        
        if (empty($langCode) || empty($langName)) {
            $error = 'Lütfen dil kodu ve adını boş bırakmayın.';
        } else {
            try {
                $sql = "INSERT INTO languages (language_code, language_name, is_active, is_default) VALUES (?, ?, 1, 0)";
                $db->query($sql, [$langCode, $langName]);
                header("Location: languages.php?success=" . urlencode("Dil eklendi: $langName"));
                exit;
            } catch (Exception $e) {
                $error = 'Hata: ' . $e->getMessage();
            }
        }
    }

    // 2. DİL DÜZENLEME (YENİ)
    elseif (isset($_POST['edit_language'])) {
        $langId = (int)$_POST['language_id'];
        $langName = trim($_POST['language_name']);
        // Dil kodu değiştirilirse tüm çeviriler bozulabilir, bu yüzden genelde sadece isim değiştirilir.
        // Ama istersen kod değişimini de açabiliriz. Şimdilik sadece isim.
        
        try {
            $db->query("UPDATE languages SET language_name = ? WHERE id = ?", [$langName, $langId]);
            $success = 'Dil bilgileri güncellendi!';
        } catch (Exception $e) {
            $error = 'Güncelleme hatası: ' . $e->getMessage();
        }
    }

    // 3. DİL SİLME (YENİ)
    elseif (isset($_POST['delete_language'])) {
        $langId = (int)$_POST['language_id'];
        
        // Varsayılan dil silinemez kontrolü
        $lang = $db->fetchOne("SELECT is_default, language_code FROM languages WHERE id = ?", [$langId]);
        
        if ($lang && $lang['is_default']) {
            $error = 'Varsayılan dil silinemez! Önce başka bir dili varsayılan yapın.';
        } else {
            try {
                $db->beginTransaction();
                // 1. Çevirileri sil (Cascade yoksa manuel silmek gerekir)
                $db->query("DELETE FROM translations WHERE language_code = ?", [$lang['language_code']]);
                // 2. Dili sil
                $db->query("DELETE FROM languages WHERE id = ?", [$langId]);
                $db->commit();
                $success = 'Dil ve tüm çevirileri silindi!';
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Silme hatası: ' . $e->getMessage();
            }
        }
    }
    
    // 4. DİL DURUMU (Aktif/Pasif)
    elseif (isset($_POST['toggle_language'])) {
        $langId = (int)$_POST['language_id'];
        $newStatus = (int)$_POST['current_status'] === 1 ? 0 : 1;
        $db->query("UPDATE languages SET is_active = ? WHERE id = ?", [$newStatus, $langId]);
        $success = 'Dil durumu güncellendi!';
    }
    
    // 5. VARSAYILAN YAPMA
    elseif (isset($_POST['set_default'])) {
        $langId = (int)$_POST['language_id'];
        $db->beginTransaction();
        $db->query("UPDATE languages SET is_default = 0");
        $db->query("UPDATE languages SET is_default = 1 WHERE id = ?", [$langId]);
        $db->commit();
        $success = 'Varsayılan dil değiştirildi!';
    }

    // 6. ÇEVİRİ KAYDETME
    elseif (isset($_POST['save_key_translations'])) {
        $key = trim($_POST['translation_key']);
        $translations = $_POST['trans'] ?? []; 
        if (!empty($key)) {
            $db->beginTransaction();
            foreach ($translations as $langCode => $value) {
                $val = trim($value);
                $db->query("INSERT INTO translations (language_code, translation_key, translation_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE translation_value = VALUES(translation_value)", [$langCode, $key, $val]);
            }
            $db->commit();
            $success = 'Çeviriler kaydedildi!';
        }
    }
    
    // 7. ANAHTAR SİLME
    elseif (isset($_POST['delete_key'])) {
        $key = $_POST['key_to_delete'];
        $db->query("DELETE FROM translations WHERE translation_key = ?", [$key]);
        header("Location: languages.php?success=" . urlencode("Anahtar silindi."));
        exit;
    }
}

// --- VERİLERİ ÇEKME ---
try {
    $allLanguages = $db->fetchAll("SELECT * FROM languages ORDER BY is_default DESC, language_name ASC");
} catch (Exception $e) {
    $allLanguages = [];
    $error = "Veritabanı hatası: " . $e->getMessage();
}

$activeLanguages = array_filter($allLanguages, fn($l) => $l['is_active'] == 1);
$allKeys = $db->fetchAll("SELECT DISTINCT translation_key FROM translations ORDER BY translation_key ASC");

$editKey = $_GET['edit_key'] ?? '';
$isNewKey = ($editKey === 'new');
$currentTranslations = [];

if ($editKey && !$isNewKey) {
    $rows = $db->fetchAll("SELECT language_code, translation_value FROM translations WHERE translation_key = ?", [$editKey]);
    foreach ($rows as $row) $currentTranslations[$row['language_code']] = $row['translation_value'];
}

$page_title = 'Dil Yönetimi';
$csrf_token = $security->generateCSRFToken();
include 'includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@6.6.6/css/flag-icons.min.css"/>

<div class="container-fluid p-0">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-globe"></i> Dil Yönetimi</h4>
            <p class="text-muted small mb-0">Sistem dillerini ve çevirilerini yönetin</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLanguageModal">
            <i class="bi bi-plus-lg"></i> Yeni Dil Ekle
        </button>
    </div>

    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?php echo $success ?: htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-1 mb-5">
        <?php foreach ($allLanguages as $lang): 
            $flagCode = strtolower($lang['language_code']);
            if ($flagCode == 'en') $flagCode = 'gb'; 
            $isActive = $lang['is_active'];
            $opacity = $isActive ? '1' : '0.7';
            $cardBorder = $isActive ? ($lang['is_default'] ? 'border-primary' : 'border-success') : 'border-secondary';
        ?>
        <div class="col-md-6 col-lg-2">
            <div class="card h-100 shadow-sm <?php echo $cardBorder; ?>" style="opacity: <?php echo $opacity; ?>">
                <div class="card-body p-1">
                    <div class="d-flex align-items-center mb-3">
                        <span class="fi fi-<?php echo $flagCode; ?> fs-1 me-3 shadow-sm rounded"></span>
                        <div>
                            <div class="mb-0 fw-bold"><small class="bg-dark text-light border rounded small p-1"><?php echo strtoupper($lang['language_code']); ?></small> <?php echo htmlspecialchars($lang['language_name']); ?></div>
                            
                            <?php if ($lang['is_default']): ?>
                                <span class="badge bg-primary">Varsayılan</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-1 mt-3">
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1" 
                                onclick="openEditModal('<?php echo $lang['id']; ?>', '<?php echo htmlspecialchars($lang['language_name']); ?>', '<?php echo $lang['language_code']; ?>')">
                            <i class="fad fa-pencil"></i>
                        </button>

                        <form method="POST" class="flex-grow-1">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="language_id" value="<?php echo $lang['id']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $lang['is_active']; ?>">
                            <button type="submit" name="toggle_language" class="btn btn-sm w-100 btn-outline-<?php echo $isActive ? 'warning' : 'success'; ?>" title="<?php echo $isActive ? 'Pasif Yap' : 'Aktif Yap'; ?>">
                                <i class="fad fa-<?php echo $isActive ? 'pause' : 'play'; ?>"></i> <?php/* echo $isActive ? 'Pasif' : 'Aktif'; */?>
                            </button>
                        </form>
                        <?php if (!$lang['is_default'] && $isActive): ?>
                        <form method="POST" class="flex-grow-1">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="language_id" value="<?php echo $lang['id']; ?>">
                            <button type="submit" name="set_default" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fad fa-star"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if (!$lang['is_default']): ?>
                        <form method="POST" class="flex-grow-1" onsubmit="return confirm('Bu dili ve bağlı TÜM çevirileri silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="language_id" value="<?php echo $lang['id']; ?>">
                            <button type="submit" name="delete_language" class="btn btn-sm btn-outline-danger w-100">
                                <i class="fad fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-translate"></i> Çeviri Editörü</h5>
        <a href="?edit_key=new" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-lg"></i> Yeni Anahtar Ekle
        </a>
    </div>

    <div class="row g-0 border rounded overflow-hidden shadow-sm" style="min-height: 600px; background: #fff;">
        <div class="col-md-3 border-end bg-light d-flex flex-column">
            <div class="p-3 border-bottom bg-white">
                <input type="text" id="searchKey" class="form-control" placeholder="Anahtar ara...">
            </div>
            <div class="list-group list-group-flush overflow-auto" style="height: 600px;" id="keyList">
                <?php foreach ($allKeys as $k): 
                    $activeClass = ($editKey === $k['translation_key']) ? 'active' : '';
                ?>
                    <a href="?edit_key=<?php echo urlencode($k['translation_key']); ?>" class="list-group-item list-group-item-action py-2 px-3 small <?php echo $activeClass; ?>" data-key="<?php echo strtolower($k['translation_key']); ?>">
                        <?php echo htmlspecialchars($k['translation_key']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="col-md-9 bg-white d-flex flex-column">
            <?php if ($editKey): ?>
                <div class="p-4 h-100 overflow-auto">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <div class="w-75">
                                <label class="form-label text-muted small fw-bold">ANAHTAR (KEY)</label>
                                <?php if ($isNewKey): ?>
                                    <input type="text" name="translation_key" class="form-control font-monospace fw-bold" placeholder="örn: welcome_message" required autofocus>
                                <?php else: ?>
                                    <input type="text" name="translation_key" class="form-control-plaintext font-monospace fw-bold fs-5" value="<?php echo htmlspecialchars($editKey); ?>" readonly>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isNewKey): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteKeyModal"><i class="bi bi-trash"></i> Sil</button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row g-3">
                            <?php foreach ($activeLanguages as $lang): 
                                $val = $currentTranslations[$lang['language_code']] ?? '';
                                $flagCode = strtolower($lang['language_code']);
                                if ($flagCode == 'en') $flagCode = 'gb';
                            ?>
                            <div class="col-12">
                                <label class="form-label d-flex align-items-center mb-1">
                                    <span class="fi fi-<?php echo $flagCode; ?> me-2"></span>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($lang['language_name']); ?></span>
                                </label>
                                <textarea name="trans[<?php echo $lang['language_code']; ?>]" class="form-control" rows="2"><?php echo htmlspecialchars($val); ?></textarea>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" name="save_key_translations" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Kaydet</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted p-5">
                    <h4>Bir Çeviri Anahtarı Seçin</h4>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addLanguageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="modal-header"><h5 class="modal-title">Yeni Dil Ekle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label>Dil Kodu (ISO)</label><input type="text" name="language_code" class="form-control" placeholder="fr" required maxlength="5"></div>
                    <div class="mb-3"><label>Dil Adı</label><input type="text" name="language_name" class="form-control" placeholder="Français" required></div>
                </div>
                <div class="modal-footer"><button type="submit" name="add_language" class="btn btn-primary">Ekle</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editLanguageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="language_id" id="editLangId">
                <div class="modal-header"><h5 class="modal-title">Dili Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Dil Kodu</label>
                        <input type="text" id="editLangCode" class="form-control" disabled>
                        <small class="text-muted">Dil kodu değiştirilemez.</small>
                    </div>
                    <div class="mb-3">
                        <label>Dil Adı</label>
                        <input type="text" name="language_name" id="editLangName" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="edit_language" class="btn btn-primary">Güncelle</button></div>
            </form>
        </div>
    </div>
</div>

<?php if ($editKey && !$isNewKey): ?>
<div class="modal fade" id="deleteKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="key_to_delete" value="<?php echo htmlspecialchars($editKey); ?>">
            <div class="modal-header"><h5 class="modal-title text-danger">Silme Onayı</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p><strong><?php echo htmlspecialchars($editKey); ?></strong> anahtarını silmek istediğinize emin misiniz?</p></div>
            <div class="modal-footer"><button type="submit" name="delete_key" class="btn btn-danger">Evet, Sil</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Dil Düzenleme Modalını Aç
function openEditModal(id, name, code) {
    document.getElementById('editLangId').value = id;
    document.getElementById('editLangName').value = name;
    document.getElementById('editLangCode').value = code;
    new bootstrap.Modal(document.getElementById('editLanguageModal')).show();
}

// Arama Fonksiyonu
document.getElementById('searchKey').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#keyList a').forEach(item => {
        const text = item.getAttribute('data-key');
        item.classList.toggle('d-none', !text.includes(filter));
    });
});
</script>

<?php include 'includes/footer.php'; ?>