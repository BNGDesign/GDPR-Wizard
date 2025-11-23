<?php
/**
 * GDPR Wizard – Generate Final GDPR Document
 * Output: PDF / HTML / TXT
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Language.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/WizardSession.php';
require_once __DIR__ . '/../includes/GDPRGenerator.php';
require_once __DIR__ . '/../includes/WatermarkSystem.php';

start_secure_session();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

$security = new Security();
$lang      = new Language();
$session   = new WizardSession();
$db        = Database::getInstance();
$generator = new GDPRGenerator($db, $lang);
$watermark = new WatermarkSystem($db);

/* ----------------------------
   1) METHOD CHECK
-----------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* ----------------------------
   2) FORMAT CHECK
-----------------------------*/
$format = $_POST['format'] ?? 'html';
$validFormats = ['html', 'pdf', 'txt'];

if (!in_array($format, $validFormats)) {
    http_response_code(400);
    exit('Invalid format');
}

/* ----------------------------
   3) SESSION CHECK
-----------------------------*/
if (!isset($_SESSION['wizard_session_id'])) {
    http_response_code(403);
    exit('Session expired');
}

$wizardSessionId = $_SESSION['wizard_session_id'];

/* ----------------------------
   4) COLLECT ALL STEP DATA
-----------------------------*/
$wizardData = $session->getAllSteps($wizardSessionId);

if (!$wizardData || !is_array($wizardData)) {
    http_response_code(400);
    exit('Wizard data missing');
}

/* ----------------------------
   5) GENERATE DOCUMENT
-----------------------------*/
$htmlContent = $generator->generateFullDocument($wizardData, 'html');

if (!$htmlContent) {
    http_response_code(500);
    exit('Failed to generate document');
}

/* ----------------------------
   6) ADD WATERMARK
-----------------------------*/
$fingerprint = $watermark->generateFingerprint($wizardData);
$finalHTML   = $watermark->embedWatermark($htmlContent, $fingerprint);

/* ----------------------------
   7) SAVE DOCUMENT TO DATABASE
-----------------------------*/
$companyName = $wizardData['step1']['company_name'] ?? null;
$domain      = $wizardData['step1']['domain'] ?? null;

$db->insert("INSERT INTO gdpr_documents 
    (user_id, company_name, domain, document_format, wizard_data, generated_content, fingerprint) 
VALUES 
    (0, ?, ?, ?, ?, ?, ?)",
[
    $companyName,
    $domain,
    $format,
    json_encode($wizardData, JSON_UNESCAPED_UNICODE),
    $finalHTML,
    $fingerprint
]);

/* ----------------------------
   8) OUTPUT THE FILE
-----------------------------*/
$filename = "gdpr-privacy-policy." . $format;

/* HTML */
if ($format === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Disposition: attachment; filename=$filename");
    echo $finalHTML;
    exit;
}

/* TXT */
if ($format === 'txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    header("Content-Disposition: attachment; filename=$filename");
    echo strip_tags($finalHTML);
    exit;
}

/* PDF */
if ($format === 'pdf') {

    require_once __DIR__ . '/../libs/dompdf/autoload.inc.php';
    $dompdf = new Dompdf\Dompdf([
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true
    ]);

    $dompdf->loadHtml($finalHTML, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header("Content-Type: application/pdf");
    header("Content-Disposition: attachment; filename=$filename");

    echo $dompdf->output();
    exit;
}

exit('Unknown output error');
