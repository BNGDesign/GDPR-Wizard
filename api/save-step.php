<?php
/**
 * GDPR Wizard – Save Step Data
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/WizardSession.php';

start_secure_session();

header('Content-Type: application/json; charset=utf-8');

$security = new Security();
$db       = Database::getInstance();
$wizard   = new WizardSession();

/* -----------------------------------
   1) METHOD CHECK
------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

/* -----------------------------------
   2) SESSION CHECK
------------------------------------*/
if (!isset($_SESSION['wizard_session_id'])) {
    // create session if not exists
    $_SESSION['wizard_session_id'] = bin2hex(random_bytes(16));
}

$wizardSessionId = $_SESSION['wizard_session_id'];

/* -----------------------------------
   3) STEP NUMBER CHECK
------------------------------------*/
$step = isset($_POST['step']) ? intval($_POST['step']) : 0;

if ($step < 1 || $step > 7) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid step'
    ]);
    exit;
}

/* -----------------------------------
   4) COLLECT POST DATA
------------------------------------*/
$data = [];

foreach ($_POST as $key => $value) {
    if ($key === 'step') continue;
    if (is_array($value)) {
        $value = array_map('trim', $value);
        $data[$key] = $value;
    } else {
        $data[$key] = trim($value);
    }
}

/* -----------------------------------
   5) SAVE TO DATABASE
------------------------------------*/
$saveResult = $wizard->saveStep($wizardSessionId, $step, $data);

if (!$saveResult) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save step'
    ]);
    exit;
}

/* -----------------------------------
   6) FINAL RESPONSE
------------------------------------*/
echo json_encode([
    'success' => true,
    'message' => 'Step saved'
]);

exit;
