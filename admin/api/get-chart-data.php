<?php
/**
 * API: Get Chart Data for Dashboard
 */

define('BASE_PATH', dirname(dirname(__DIR__)));
define('INCLUDES_PATH', BASE_PATH . '/includes');

require_once INCLUDES_PATH . '/config.php';
require_once INCLUDES_PATH . '/Database.php';

start_secure_session();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$db = Database::getInstance();

$period = $_GET['period'] ?? '7days';

try {
    $labels = [];
    $values = [];
    
    if ($period === '7days') {
        // Get last 7 days data
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            $count = $db->fetchOne(
                "SELECT COUNT(*) as count FROM gdpr_documents WHERE DATE(created_at) = ?",
                [$date]
            );
            
            $values[] = (int)($count['count'] ?? 0);
        }
    }
    elseif ($period === '30days') {
        // Get last 30 days data
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M d', strtotime($date));
            
            $count = $db->fetchOne(
                "SELECT COUNT(*) as count FROM gdpr_documents WHERE DATE(created_at) = ?",
                [$date]
            );
            
            $values[] = (int)($count['count'] ?? 0);
        }
    }
    elseif ($period === '12months') {
        // Get last 12 months data
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $labels[] = date('M Y', strtotime($date . '-01'));
            
            $count = $db->fetchOne(
                "SELECT COUNT(*) as count FROM gdpr_documents WHERE DATE_FORMAT(created_at, '%Y-%m') = ?",
                [$date]
            );
            
            $values[] = (int)($count['count'] ?? 0);
        }
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'values' => $values
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch chart data'
    ]);
}