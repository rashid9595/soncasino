<?php
require_once __DIR__ . '/../includes/header.php';

// Check if user is admin
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Yetkiniz bulunmamaktadır.']);
    exit();
}

// Handle GET request (fetch promotion details)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM promotions WHERE id = ?");
    $stmt->execute([$id]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($promotion) {
        echo json_encode(['success' => true, 'data' => $promotion]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Promosyon bulunamadı.']);
    }
    exit();
}

// Handle POST request (create/update/delete promotion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete operation
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM promotions WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Promosyon silinemedi.']);
        }
        exit();
    }

    // Create/Update operation
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $data = [
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'image_url' => $_POST['image_url'],
        'category' => $_POST['category'],
        'rules' => $_POST['rules'],
        'terms_conditions' => $_POST['terms_conditions'],
        'status' => isset($_POST['status']) ? 1 : 0
    ];

    if ($id) {
        // Update
        $sql = "UPDATE promotions SET 
                title = :title,
                description = :description,
                image_url = :image_url,
                category = :category,
                rules = :rules,
                terms_conditions = :terms_conditions,
                status = :status
                WHERE id = :id";
        $data['id'] = $id;
    } else {
        // Create
        $sql = "INSERT INTO promotions 
                (title, description, image_url, category, rules, terms_conditions, status)
                VALUES 
                (:title, :description, :image_url, :category, :rules, :terms_conditions, :status)";
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