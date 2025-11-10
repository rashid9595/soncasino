<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['2fa_verified'])) {
    echo json_encode(['status' => 'error', 'message' => 'Oturum doğrulaması başarısız.']);
    exit();
}

// Check if user has permission to edit users
$stmt = $db->prepare("
    SELECT ap.* 
    FROM admin_permissions ap 
    WHERE ap.role_id = ? AND ap.menu_item = 'kullanicilar'
");
$stmt->execute([$_SESSION['role_id']]);
$permissions = $stmt->fetch(PDO::FETCH_ASSOC);

$canEdit = $permissions['can_edit'] ?? 0;
$isAdmin = ($_SESSION['role_id'] == 1);

if (!$canEdit && !$isAdmin) {
    echo json_encode(['status' => 'error', 'message' => 'Kullanıcı bilgilerini düzenleme yetkiniz yok.']);
    exit();
}

// Get POST data
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$field = isset($_POST['field']) ? $_POST['field'] : '';
$value = isset($_POST['value']) ? $_POST['value'] : '';

// Valid fields that can be updated
$validFields = [
    'firstName' => 'Ad',
    'surname' => 'Soyad',
    'gender' => 'Cinsiyet',
    'birthDay' => 'Doğum Günü',
    'birthMonth' => 'Doğum Ayı',
    'birthYear' => 'Doğum Yılı',
    'email' => 'E-posta',
    'phone' => 'Telefon',
    'identity' => 'Kimlik No',
    'cityName' => 'Şehir',
    'twofactor' => 'İki Faktörlü Kimlik Doğrulama',
    'secret_key' => '2FA Secret Key'
];

// Check if user ID and field are provided
if ($userId <= 0 || !array_key_exists($field, $validFields)) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek.']);
    exit();
}

// Validate fields based on type
if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz e-posta adresi.']);
    exit();
}

if ($field === 'phone' && !preg_match('/^[0-9]{10,11}$/', $value)) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz telefon numarası. 10-11 rakamdan oluşmalıdır.']);
    exit();
}

if ($field === 'identity' && !empty($value) && !preg_match('/^[0-9]{11}$/', $value)) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz kimlik numarası. 11 rakamdan oluşmalıdır.']);
    exit();
}

if (($field === 'birthDay' && (!empty($value) && ($value < 1 || $value > 31))) || 
    ($field === 'birthMonth' && (!empty($value) && ($value < 1 || $value > 12))) || 
    ($field === 'birthYear' && (!empty($value) && ($value < 1900 || $value > date('Y'))))) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz doğum tarihi değeri.']);
    exit();
}

if ($field === 'gender' && !in_array($value, ['0', '1', '2', '3'])) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz cinsiyet değeri.']);
    exit();
}

try {
    // Check if user exists
    $checkStmt = $db->prepare("SELECT id FROM kullanicilar WHERE id = ?");
    $checkStmt->execute([$userId]);
    if ($checkStmt->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Kullanıcı bulunamadı.']);
        exit();
    }
    
    // Update the field
    $updateStmt = $db->prepare("UPDATE kullanicilar SET {$field} = ? WHERE id = ?");
    $updateStmt->execute([$value, $userId]);
    
    // Log the activity
    $fieldName = $validFields[$field];
    $activityStmt = $db->prepare("
        INSERT INTO activity_logs (admin_id, action, description, created_at) 
        VALUES (?, 'update', ?, NOW())
    ");
    $activityStmt->execute([
        $_SESSION['admin_id'], 
        "Kullanıcı bilgisi güncellendi: ID {$userId} - {$fieldName}: {$value}"
    ]);
    
    echo json_encode([
        'status' => 'success', 
        'message' => $fieldName . ' bilgisi başarıyla güncellendi.',
        'field' => $field,
        'value' => $value
    ]);
    
} catch (PDOException $e) {
    error_log("Error updating user info: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı işlemi sırasında bir hata oluştu.']);
} 