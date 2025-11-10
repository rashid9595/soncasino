<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$theme = $input['theme'] ?? null;

if (!$theme) {
    echo json_encode(['success' => false, 'error' => 'Theme parameter is required']);
    exit();
}

try {
    // Check if setting exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'active_theme'");
    $stmt->execute();
    $exists = $stmt->fetchColumn() > 0;
    
    if ($exists) {
        // Update existing setting
        $stmt = $db->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'active_theme'");
        $stmt->execute([$theme]);
    } else {
        // Insert new setting
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['active_theme', $theme]);
    }
    
    // Update session
    $_SESSION['active_theme'] = $theme;
    
    echo json_encode(['success' => true, 'theme' => $theme]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
