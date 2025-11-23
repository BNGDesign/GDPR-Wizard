<?php
/**
 * GDPR Wizard Frontend System
 * Main Entry Point - Data Persistence Complete
 */

session_start();

// Config
define('BASE_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('LANG_PATH', BASE_PATH . '/languages');
define('ASSETS_PATH', '/assets');

// Autoload
require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Language.php';
require_once INCLUDES_PATH . '/WizardSession.php';
require_once INCLUDES_PATH . '/Security.php';

// Initialize
$db = Database::getInstance();
$security = new Security();
$lang = new Language();
$wizard = new WizardSession();

// Varsayılan dil config.php'den gelir (tr)
$langCode = DEFAULT_LANGUAGE;

// 1. URL'de ?lang=fr var mı?
if (isset($_GET['lang']) && !empty($_GET['lang'])) {
    $requestedLang = strip_tags($_GET['lang']);
    
    // Veritabanına sor: Bu dil aktif mi?
    $activeLangs = array_column($lang->getAvailableLanguages(), 'language_code');

    if (in_array($requestedLang, $activeLangs)) {
        $langCode = $requestedLang;
        $_SESSION['wizard_lang'] = $langCode; // Seçimi hatırla
    }
} 
// 2. Yoksa Session'a bak (Daha önce seçilmiş mi?)
elseif (isset($_SESSION['wizard_lang'])) {
    $langCode = $_SESSION['wizard_lang'];
}

// 3. Son kararı sisteme yükle
$lang->setLanguage($langCode);
$currentLang = $lang->getCurrentLanguage();

// Get current step
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($currentStep < 1) $currentStep = 1;
if ($currentStep > 7) $currentStep = 7;

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userData = $isLoggedIn ? ['id' => $_SESSION['user_id'], 'email' => $_SESSION['user_email'] ?? ''] : null;

// === VERİLERİ ÇEKME (PERSISTENCE) ===
// WizardSession tarafından kaydedilen verileri alıyoruz.
// Genellikle session içinde 'wizard_data' anahtarında tutulur.
$savedData = $_SESSION['wizard_data'] ?? [];

?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lang->get('wizard_title'); ?> - GDPR Wizard</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="assets/css/solid.css">
    <link rel="stylesheet" href="assets/css/regular.css">
    <link rel="stylesheet" href="assets/css/light.css">
    <link rel="stylesheet" href="assets/css/thin.css">
    <link rel="stylesheet" href="assets/css/duotone.css">
    <link rel="stylesheet" href="assets/css/brands.css">
    <link rel="stylesheet" href="assets/css/frontend.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@6.6.6/css/flag-icons.min.css"/>
</head>
<body>

<style>
    .language-switcher-wrapper {
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 99999;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 15px;
    }

    .lang-flag-btn {
        display: block;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        text-decoration: none;
        overflow: hidden; /* Yuvarlak kesim için */
        position: relative;
    }

    .lang-flag-btn:hover {
        transform: scale(1.2);
    }

    /* Bayrak İkonunun Kendisi */
    .lang-flag-icon {
        width: 100% !important;
        height: 100% !important;
        display: block;
        background-size: cover;
        background-position: center;
    }

    /* Aktif Dil (Seçili) */
    .lang-active {
        opacity: 1;
    }

    /* Pasif Dil (Seçili Olmayan) */
    .lang-passive {
        opacity: 0.5;
        border: 1px solid #e0e0e0;
        filter: grayscale(100%); /* Siyah beyaz */
    }
    
    .lang-passive:hover {
        opacity: 1;
        filter: grayscale(0%); /* Üzerine gelince renklen */
    }
</style>

<div class="language-switcher-wrapper">
    
    <input type="hidden" id="langSwitch" value="<?php echo $currentLang; ?>">

    <?php
    // 1. SADECE Veritabanından Çek (Manuel dizi kaldırıldı)
    $langs = $lang->getAvailableLanguages();

    // 2. Bayrak İkon Haritası (Görsel düzeltme - Veri değil, sadece ikon eşleşmesi)
    $flagMap = [
        'en' => 'gb', // İngilizce -> Büyük Britanya Bayrağı
        'ar' => 'sa', 
        'ja' => 'jp', 
        'zh' => 'cn'
    ];

    if (!empty($langs)): 
        foreach ($langs as $l):
            $code = $l['language_code'];
            
            // Bayrak kodunu bul (Haritada varsa onu, yoksa kendisini kullan)
            $flag = isset($flagMap[$code]) ? $flagMap[$code] : strtolower($code);
            
            // Aktif dil kontrolü
            $isActive = ($currentLang == $code);
            $activeClass = $isActive ? 'lang-active' : 'lang-passive';
    ?>
        <a href="javascript:void(0);" 
           class="lang-flag-btn <?php echo $activeClass; ?>"
           onclick="setLanguage('<?php echo $code; ?>')"
           title="<?php echo htmlspecialchars($l['language_name']); ?>">
            
            <span class="fi fi-<?php echo $flag; ?> fis lang-flag-icon"></span>
        </a>
    <?php 
        endforeach; 
    endif; 
    ?>

</div>

<script>
function setLanguage(code) {
    // 1. URL'i güncelle (Sayfayı yenile ve dili değiştir)
    // wizard.js bazen yeni dilleri yakalayamayabilir, bu yüzden en garanti yol reload etmektir.
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('lang', code);
    window.location.href = currentUrl.toString();
}
</script>

<div class="wizard-container">
    
    <div class="wizard-header">
        <h1><?php echo $lang->get('wizard_title'); ?></h1>
        <p><?php echo $lang->get('wizard_subtitle'); ?></p>
    </div>
    
    <div class="progress-wrapper">
        <div class="step-indicators">
            <?php for($i = 1; $i <= 7; $i++): ?>
            <div class="step-indicator <?php echo $i === $currentStep ? 'active' : ($i < $currentStep ? 'completed' : ''); ?>">
                <div class="step-circle">
                    <?php if($i < $currentStep): ?>
                        <i class="far fa-check"></i>
                    <?php else: ?>
                        <?php echo $i; ?>
                    <?php endif; ?>
                </div>
                <div class="step-label"><?php echo $lang->get('step_' . $i . '_title'); ?></div>
            </div>
            <?php endfor; ?>
        </div>
        <div class="progress">
            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo (($currentStep - 1) / 6) * 100; ?>%"></div>
        </div>
    </div>
    
    <div class="wizard-body">
        <form id="wizardForm" method="post">
            
            <div class="step-content <?php echo $currentStep === 1 ? 'active' : ''; ?>" data-step="1">
                <div class="form-section">
                    <h3><i class="fad fa-building"></i> <?php echo $lang->get('step1_company_info'); ?></h3>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_company_name'); ?> *</label>
                            <input type="text" class="form-control" name="company_name" required 
                                   value="<?php echo htmlspecialchars($savedData['company_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_website_name'); ?> *</label>
                            <input type="text" class="form-control" name="website_name" required 
                                   value="<?php echo htmlspecialchars($savedData['website_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_domain'); ?> *</label>
                            <input type="url" class="form-control" name="domain" placeholder="https://example.com" required 
                                   value="<?php echo htmlspecialchars($savedData['domain'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
    <label class="form-label"><?php echo $lang->get('field_country'); ?> *</label>
    
    <?php
    // Ülke Listesi ve Bayrak Kodları (ISO kodu küçük harf olmalı bayrak için)
    $countries = [
        'DE' => ['flag' => 'de', 'lang_key' => 'country_DE'],
        'TR' => ['flag' => 'tr', 'lang_key' => 'country_TR'],
        'US' => ['flag' => 'us', 'lang_key' => 'country_US'],
        'GB' => ['flag' => 'gb', 'lang_key' => 'country_GB'],
        'FR' => ['flag' => 'fr', 'lang_key' => 'country_FR'],
    ];

    // Kayıtlı veri var mı?
    $savedCountryCode = $savedData['country'] ?? '';
    
    // Seçili ülkenin ismini ve bayrağını bul (Başlangıç görünümü için)
    $selectedCountryName = $lang->get('select_country');
    $selectedCountryFlag = '<i class="fa-duotone fa-solid fa-earth-europe"></i>';
    
    if($savedCountryCode && isset($countries[$savedCountryCode])) {
        $selectedCountryName = $lang->get($countries[$savedCountryCode]['lang_key']);
        $selectedCountryFlag = '<span class="fi fi-' . $countries[$savedCountryCode]['flag'] . ' me-2"></span>';
    }
    ?>

    <input type="hidden" name="country" id="countryInput" value="<?php echo htmlspecialchars($savedCountryCode); ?>" required>

    <div class="dropdown w-100">
        <button class="btn btn-outline-secondary w-100 text-start d-flex align-items-center justify-content-between dropdown-toggle" 
                type="button" 
                id="countryDropdownBtn" 
                data-bs-toggle="dropdown" 
                aria-expanded="false">
            <span>
                <?php echo $selectedCountryFlag . $selectedCountryName; ?>
            </span>
        </button>
        
        <ul class="dropdown-menu w-100" aria-labelledby="countryDropdownBtn">
            <?php foreach($countries as $code => $data): 
                $countryName = $lang->get($data['lang_key']);
                $isActive = ($savedCountryCode === $code) ? 'active' : '';
            ?>
            <li>
                <a class="dropdown-item d-flex align-items-center <?php echo $isActive; ?>" 
                   href="#" 
                   onclick="selectCountry('<?php echo $code; ?>', '<?php echo $data['flag']; ?>', '<?php echo htmlspecialchars($countryName, ENT_QUOTES); ?>', event)">
                    <span class="fi fi-<?php echo $data['flag']; ?> me-3 fs-5"></span>
                    <?php echo $countryName; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fad fa-server"></i> <?php echo $lang->get('step1_hosting_info'); ?></h3>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_hosting_provider'); ?> *</label>
                            <select class="form-select" name="hosting_provider" required>
                                <option value=""><?php echo $lang->get('select_option'); ?></option>
                                <?php
                                $hosts = ['aws', 'google_cloud', 'azure', 'hetzner', 'digitalocean'];
                                foreach($hosts as $host):
                                    $selected = ($savedData['hosting_provider'] ?? '') === $host ? 'selected' : '';
                                ?>
                                <option value="<?php echo $host; ?>" <?php echo $selected; ?>><?php echo ucfirst($host); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_cdn_provider'); ?></label>
                            <select class="form-select" name="cdn_provider">
                                <option value=""><?php echo $lang->get('no_cdn'); ?></option>
                                <?php
                                $cdns = ['cloudflare', 'fastly', 'akamai', 'cloudfront'];
                                foreach($cdns as $cdn):
                                    $selected = ($savedData['cdn_provider'] ?? '') === $cdn ? 'selected' : '';
                                ?>
                                <option value="<?php echo $cdn; ?>" <?php echo $selected; ?>><?php echo ucfirst($cdn); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-content <?php echo $currentStep === 2 ? 'active' : ''; ?>" data-step="2">
                <div class="form-section">
                    <h3><i class="fad fa-database"></i> <?php echo $lang->get('step2_data_types'); ?></h3>
                    <p class="text-muted"><?php echo $lang->get('step2_description'); ?></p>
                    
                    <div class="data-types-grid">
                        <?php
                        $savedTypes = $savedData['data_types'] ?? [];
                        if(!is_array($savedTypes)) $savedTypes = [];

                        $dataTypes = [
                            'ip_address' => ['icon' => 'fad fa-network-wired', 'name' => 'IP Address', 'desc' => 'User IP addresses for security'],
                            'cookies' => ['icon' => 'fad fa-cookie', 'name' => 'Cookies', 'desc' => 'Browser cookies and tracking'],
                            'form_data' => ['icon' => 'fad fa-file-alt', 'name' => 'Form Data', 'desc' => 'Contact forms, registration'],
                            'user_accounts' => ['icon' => 'fad fa-user', 'name' => 'User Accounts', 'desc' => 'Registration and login data'],
                            'payment_data' => ['icon' => 'fad fa-credit-card', 'name' => 'Payment Data', 'desc' => 'E-commerce transactions'],
                            'analytics_data' => ['icon' => 'fad fa-chart-line', 'name' => 'Analytics Data', 'desc' => 'Usage statistics and behavior'],
                            'device_info' => ['icon' => 'fad fa-mobile', 'name' => 'Device Information', 'desc' => 'Browser, OS, device type'],
                            'location_data' => ['icon' => 'fad fa-map-marker-alt', 'name' => 'Location Data', 'desc' => 'GPS or IP-based location'],
                        ];
                        
                        foreach($dataTypes as $key => $data):
                            $isChecked = in_array($key, $savedTypes) ? 'checked' : '';
                            $cardClass = in_array($key, $savedTypes) ? 'checked' : '';
                        ?>
                        <div class="checkbox-card <?php echo $cardClass; ?>" data-checkbox="data_type_<?php echo $key; ?>">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="data_types[]" value="<?php echo $key; ?>" id="data_<?php echo $key; ?>" <?php echo $isChecked; ?>>
                                <label class="form-check-label w-100" for="data_<?php echo $key; ?>">
                                    <div class="d-flex align-items-start gap-3">
                                        <i class="<?php echo $data['icon']; ?> fs-3 text-primary"></i>
                                        <div>
                                            <strong><?php echo $data['name']; ?></strong>
                                            <div class="small text-muted"><?php echo $data['desc']; ?></div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="step-content <?php echo $currentStep === 3 ? 'active' : ''; ?>" data-step="3">
                <div class="form-section">
                    <h3><i class="fad fa-puzzle"></i> <?php echo $lang->get('step3_services'); ?></h3>
                    <p class="text-muted"><?php echo $lang->get('step3_description'); ?></p>
                    
                    <div id="servicesContainer">
                        </div>
                </div>
            </div>
            
            <div class="step-content <?php echo $currentStep === 4 ? 'active' : ''; ?>" data-step="4">
                <div class="form-section">
                    <h3><i class="fad fa-shield-check"></i> <?php echo $lang->get('step4_legal_basis'); ?></h3>
                    
                    <?php
                    $savedLegal = $savedData['legal_basis'] ?? [];
                    if(!is_array($savedLegal)) $savedLegal = [];

                    $legalBases = [
                        'consent' => ['title' => 'Consent (Art. 6(1)(a) GDPR)', 'desc' => 'User has given explicit consent'],
                        'contract' => ['title' => 'Contract (Art. 6(1)(b) GDPR)', 'desc' => 'Processing necessary for contract'],
                        'legal_obligation' => ['title' => 'Legal Obligation (Art. 6(1)(c) GDPR)', 'desc' => 'Required by law'],
                        'vital_interests' => ['title' => 'Vital Interests (Art. 6(1)(d) GDPR)', 'desc' => 'Protection of vital interests'],
                        'public_interest' => ['title' => 'Public Interest (Art. 6(1)(e) GDPR)', 'desc' => 'Public interest or official authority'],
                        'legitimate_interests' => ['title' => 'Legitimate Interests (Art. 6(1)(f) GDPR)', 'desc' => 'Legitimate interests of controller'],
                    ];
                    
                    foreach($legalBases as $key => $basis):
                        $isChecked = in_array($key, $savedLegal) ? 'checked' : '';
                        $cardClass = in_array($key, $savedLegal) ? 'checked' : '';
                    ?>
                    <div class="checkbox-card <?php echo $cardClass; ?>" data-checkbox="legal_<?php echo $key; ?>">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="legal_basis[]" value="<?php echo $key; ?>" id="legal_<?php echo $key; ?>" <?php echo $isChecked; ?>>
                            <label class="form-check-label w-100" for="legal_<?php echo $key; ?>">
                                <strong><?php echo $basis['title']; ?></strong>
                                <div class="small text-muted"><?php echo $basis['desc']; ?></div>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="step-content <?php echo $currentStep === 5 ? 'active' : ''; ?>" data-step="5">
                <div class="form-section">
                    <h3><i class="fad fa-person-badge"></i> <?php echo $lang->get('step5_controller'); ?></h3>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_controller_name'); ?> *</label>
                            <input type="text" class="form-control" name="controller_name" required 
                                   value="<?php echo htmlspecialchars($savedData['controller_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_controller_email'); ?> *</label>
                            <input type="email" class="form-control" name="controller_email" required 
                                   value="<?php echo htmlspecialchars($savedData['controller_email'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label"><?php echo $lang->get('field_controller_address'); ?> *</label>
                            <textarea class="form-control" name="controller_address" rows="3" required><?php echo htmlspecialchars($savedData['controller_address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_controller_phone'); ?></label>
                            <input type="tel" class="form-control" name="controller_phone" 
                                   value="<?php echo htmlspecialchars($savedData['controller_phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $lang->get('field_dpo_email'); ?></label>
                            <input type="email" class="form-control" name="dpo_email" 
                                   value="<?php echo htmlspecialchars($savedData['dpo_email'] ?? ''); ?>">
                            <div class="form-text"><?php echo $lang->get('dpo_optional'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="step-content <?php echo $currentStep === 6 ? 'active' : ''; ?>" data-step="6">
                <div class="form-section">
                    <h3><i class="fad fa-eye"></i> <?php echo $lang->get('step6_preview'); ?></h3>
                    
                    <div class="preview-box" id="previewContent">
                        <div class="text-center text-muted py-5">
                            <i class="fad fa-hourglass-split fs-1"></i>
                            <p><?php echo $lang->get('preview_loading'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="step-content <?php echo $currentStep === 7 ? 'active' : ''; ?>" data-step="7">
                <?php if($isLoggedIn): ?>
                <div class="form-section">
                    <h3><i class="fad fa-download"></i> <?php echo $lang->get('step7_download'); ?></h3>
                    
                    <div class="alert alert-success alert-custom">
                        <i class="fad fa-check-circle fs-3"></i>
                        <h5><?php echo $lang->get('gdpr_ready'); ?></h5>
                        <p><?php echo $lang->get('download_description'); ?></p>
                    </div>
                    
                    <div class="row g-3 mt-3">
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-danger w-100 py-3" id="downloadPDF">
                                <i class="fad fa-file-pdf fs-3 d-block mb-2"></i>
                                <strong>Download PDF</strong>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100 py-3" id="downloadHTML">
                                <i class="fad fa-file-code fs-3 d-block mb-2"></i>
                                <strong>Download HTML</strong>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-secondary w-100 py-3" id="downloadTXT">
                                <i class="fad fa-file-text fs-3 d-block mb-2"></i>
                                <strong>Download TXT</strong>
                            </button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="login-required">
                    <i class="fad fa-lock"></i>
                    <h3>Login Required</h3>
                    <p class="text-muted">You need to be logged in to download your GDPR document.</p>
                    <a href="login.php" class="btn btn-wizard-primary mt-3">
                        <i class="fad fa-sign-in-alt"></i> Login or Register
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="wizard-navigation">
                <button type="button" class="btn btn-wizard-secondary" id="prevBtn" <?php echo $currentStep === 1 ? 'style="visibility:hidden"' : ''; ?>>
                    <i class="fad fa-arrow-left"></i> <?php echo $lang->get('btn_previous'); ?>
                </button>
                
                <button type="button" class="btn btn-wizard-primary" id="nextBtn">
                    <?php echo $currentStep === 7 ? $lang->get('btn_finish') : $lang->get('btn_next'); ?> <i class="fad fa-arrow-right"></i>
                </button>
            </div>
            
        </form>
    </div>
    
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/wizard.js"></script>
<script>
function selectCountry(code, flag, name, event) {
    if(event) event.preventDefault();
    
    // 1. Gizli input'u güncelle (PHP bu değeri okuyacak)
    document.getElementById('countryInput').value = code;
    
    // 2. Buton üzerindeki metni ve bayrağı güncelle
    const btnContent = `<span class="fi fi-${flag} me-2"></span> ${name}`;
    const btn = document.getElementById('countryDropdownBtn');
    btn.querySelector('span').innerHTML = btnContent;
    
    // 3. Dropdown içindeki aktif sınıfını güncelle
    document.querySelectorAll('.dropdown-item').forEach(item => item.classList.remove('active'));
    if(event && event.target) {
        // Tıklanan öğeye active class ekle (UX için)
        const activeLink = event.target.closest('a');
        if(activeLink) activeLink.classList.add('active');
    }
}
</script>
</body>
</html>