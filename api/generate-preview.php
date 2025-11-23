<?php
/**
 * FILE: gdpr-wizard/api/generate-preview.php
 * FIXED → Asla takılmaz, her zaman JSON döner.
 */

define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Language.php';
require_once INCLUDES_PATH . '/Security.php';
require_once INCLUDES_PATH . '/WizardSession.php';
require_once INCLUDES_PATH . '/GDPRGenerator.php';

start_secure_session();
header('Content-Type: application/json; charset=utf-8');

$db        = Database::getInstance();
$lang      = new Language();
$security  = new Security();
$session   = new WizardSession();
$generator = new GDPRGenerator($db, $lang);

/* SESSION FIX — Boşsa bile oluşturur, asla takılmaz */
if (empty($_SESSION['wizard_session_id'])) {
    $_SESSION['wizard_session_id'] = bin2hex(random_bytes(16));
}

$wizardSessionId = $_SESSION['wizard_session_id'];

/* TÜM STEP VERİLERİNİ YÜKLE */
$wizardData = $session->getAllSteps($wizardSessionId);

/* DATA YOKSA BİLE ÖNİZLEME BOŞ DÖNER, TAKILMAZ */
if (!$wizardData || !is_array($wizardData)) {
    echo json_encode([
        'success' => true,
        'html' => '<div class="alert alert-warning">Henüz veri yok.</div>'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ÖNİZLEME OLUŞTUR */
$html = $generator->generateFullDocument($wizardData, 'html');

/* OLUŞMAZSA YİNE TAKILMAZ */
if (!$html) {
    echo json_encode([
        'success' => true,
        'html' => '<div class="alert alert-danger">Önizleme üretilemedi.</div>'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* SON ÇIKTI */
echo json_encode([
    'success' => true,
    'html' => $html
], JSON_UNESCAPED_UNICODE);

exit;
