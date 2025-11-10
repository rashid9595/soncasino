<?php
session_start();
require_once "../config/database.php";

// Security check
if (!isset($_SESSION["admin_id"]) || !isset($_SESSION["2fa_verified"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

header("Content-Type: application/json");

try {
    // Get deposit transactions with user information
    $depositStmt = $db->prepare("
        SELECT p.*, u.username 
        FROM parayatir p
        LEFT JOIN kullanicilar u ON p.user_id = u.id
        ORDER BY p.tarih DESC
        LIMIT 100
    ");
    
    $depositStmt->execute();
    $deposits = $depositStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(["deposits" => $deposits]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} 