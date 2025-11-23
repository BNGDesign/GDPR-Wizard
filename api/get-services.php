<?php
/**
 * API: Get Available Services
 * Returns active services with saved state (Data Persistence)
 */

define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Security.php';
require_once INCLUDES_PATH . '/FallbackData.php';

start_secure_session();

header('Content-Type: application/json');

$security = new Security();
$db = Database::getInstance();

// Rate limiting
$ip = $security->getClientIP();
if (!$security->checkRateLimit('api_services_' . $ip, 60, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

// Get language
$lang = $_GET['lang'] ?? 'en';
if (!in_array($lang, AVAILABLE_LANGUAGES)) {
    $lang = 'en';
}

// === PERSISTENCE LOGIC START ===
// Oturumdaki wizard verilerini al
$savedData = $_SESSION['wizard_data'] ?? [];
// Seçili servislerin listesi (örn: ['google_analytics', 'facebook_pixel'])
$savedServices = $savedData['services'] ?? []; 
if (!is_array($savedServices)) $savedServices = [];

// Form inputlarının değerlerini al (örn: service_google_analytics_id => 'UA-123')
$savedFields = []; 
foreach($savedData as $key => $val) {
    if(strpos($key, 'service_') === 0) {
        $savedFields[$key] = $val;
    }
}
// === PERSISTENCE LOGIC END ===

try {
    $grouped = [];
    $services = [];

    if ($db->isConnected()) {
        // SQL: Servisleri, Çevirilerini, Kategorilerini ve Kategori Çevirilerini Çek
        $sql = "SELECT
                    s.id,
                    s.service_key,
                    s.service_category AS raw_category_key,
                    s.icon_class,
                    s.is_active,
                    st.service_name,
                    st.service_description,
                    COALESCE(sct.category_name, s.service_category) as category_display_name
                FROM services s
                LEFT JOIN service_translations st ON s.id = st.service_id AND st.language_code = ?
                LEFT JOIN service_categories sc ON s.service_category = sc.category_key
                LEFT JOIN service_category_translations sct ON sc.id = sct.category_id AND sct.language_code = ?
                WHERE s.is_active = 1
                ORDER BY sc.sort_order ASC, s.sort_order ASC, st.service_name ASC";

        $services = $db->fetchAll($sql, [$lang, $lang]);

        foreach ($services as $service) {
            $categoryName = $service['category_display_name'] ?? 'Other';

            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [];
            }

            // Servis alanlarını çek
            $fieldsSql = "SELECT
                            sf.field_key,
                            sf.field_type,
                            sf.is_required,
                            sft.field_label,
                            sft.field_placeholder
                          FROM service_fields sf
                          LEFT JOIN service_field_translations sft ON sf.id = sft.field_id AND sft.language_code = ?
                          WHERE sf.service_id = ?
                          ORDER BY sf.sort_order";

            $fields = $db->fetchAll($fieldsSql, [$lang, $service['id']]);

            // Servis daha önce seçilmiş mi kontrol et
            $isSelected = in_array($service['service_key'], $savedServices);

            $grouped[$categoryName][] = [
                'key' => $service['service_key'],
                'name' => $service['service_name'] ?? $service['service_key'],
                'description' => $service['service_description'] ?? '',
                'icon' => $service['icon_class'] ?? 'fa-solid fa-circle-info',
                
                // Frontend için 'selected' bilgisini gönderiyoruz
                'selected' => $isSelected,

                'fields' => array_map(function($field) use ($service, $savedFields) {
                    // Input adını oluştur (Frontend ile uyumlu olmalı)
                    $inputName = 'service_' . $service['service_key'] . '_' . $field['field_key'];
                    
                    return [
                        'key' => $field['field_key'],
                        'label' => $field['field_label'],
                        'placeholder' => $field['field_placeholder'] ?? '',
                        'type' => $field['field_type'] ?? 'text',
                        'required' => (bool)$field['is_required'],
                        // Kayıtlı değeri frontend'e gönderiyoruz
                        'value' => $savedFields[$inputName] ?? ''
                    ];
                }, $fields)
            ];
        }
    }

    // Fallback logic
    if (!$db->isConnected() || empty($grouped)) {
        $grouped = FallbackData::getServices($lang);
        $services = array_merge(...array_values($grouped));
    }

    echo json_encode([
        'success' => true,
        'categories' => $grouped,
        'total' => count($services)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch services: ' . $e->getMessage()
    ]);
}