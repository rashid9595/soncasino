<?php
require_once __DIR__ . '/../includes/header.php';

// Check if user is admin
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz bulunmamaktadır.']);
    exit();
}

// Handle GET request (fetch bonus details)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM bonuslar WHERE id = ?");
    $stmt->execute([$id]);
    $bonus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bonus) {
        echo json_encode(['success' => true, 'data' => $bonus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bonus bulunamadı.']);
    }
    exit();
}

// Handle POST request (create/update/delete bonus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete operation
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM bonuslar WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bonus silinemedi.']);
        }
        exit();
    }

    // Create/Update operation
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $data = [
        'bonus_adi' => $_POST['bonus_adi'],
        'bonus_turu' => $_POST['bonus_turu'],
        'bonus_kategori' => $_POST['bonus_kategori'],
        'yuzde' => intval($_POST['yuzde']),
        'min_miktar' => floatval($_POST['min_miktar']),
        'max_miktar' => floatval($_POST['max_miktar']),
        'min_kayip_miktar' => floatval($_POST['min_kayip_miktar'] ?? 0),
        'min_yatirim_sarti' => floatval($_POST['min_yatirim_sarti'] ?? 0),
        'resim_url' => $_POST['resim_url'] ?? null,
        'tekrar_alinabilir' => isset($_POST['tekrar_alinabilir']) ? 1 : 0,
        'aktif' => isset($_POST['aktif']) ? 1 : 0
    ];

    if ($id) {
        // Update
        $sql = "UPDATE bonuslar SET 
                bonus_adi = :bonus_adi,
                bonus_turu = :bonus_turu,
                bonus_kategori = :bonus_kategori,
                yuzde = :yuzde,
                min_miktar = :min_miktar,
                max_miktar = :max_miktar,
                min_kayip_miktar = :min_kayip_miktar,
                min_yatirim_sarti = :min_yatirim_sarti,
                resim_url = :resim_url,
                tekrar_alinabilir = :tekrar_alinabilir,
                aktif = :aktif
                WHERE id = :id";
        $data['id'] = $id;
    } else {
        // Create
        $sql = "INSERT INTO bonuslar 
                (bonus_adi, bonus_turu, bonus_kategori, yuzde, min_miktar, max_miktar, 
                min_kayip_miktar, min_yatirim_sarti, resim_url, tekrar_alinabilir, aktif)
                VALUES 
                (:bonus_adi, :bonus_turu, :bonus_kategori, :yuzde, :min_miktar, :max_miktar,
                :min_kayip_miktar, :min_yatirim_sarti, :resim_url, :tekrar_alinabilir, :aktif)";
    }

    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($data)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'İşlem başarısız oldu.']);
    }
    exit();
}

// Invalid request method
echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
exit(); 