<?php
/**
 * WebSocket olmadan gerçek zamanlı veri sağlayan AJAX endpoint'i
 * Bu dosya, WebSocket sunucusu olmadığında veya port kısıtlamaları
 * olduğunda kullanılabilecek bir alternatiftir.
 */

session_start();
require_once '../config/database.php';

// Kullanıcı kimlik doğrulaması
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['2fa_verified'])) {
    http_response_code(403);
    echo json_encode([
        'type' => 'error',
        'message' => 'Yetkisiz erişim'
    ]);
    exit();
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'type' => 'error',
        'message' => 'Sadece POST istekleri kabul edilir'
    ]);
    exit();
}

// İstek verilerini al
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['type'])) {
    http_response_code(400);
    echo json_encode([
        'type' => 'error',
        'message' => 'Geçersiz istek formatı'
    ]);
    exit();
}

header('Content-Type: application/json');

// Filtre isteklerini işle
if ($data['type'] === 'filter') {
    try {
        // Filtreleri doğrula
        if (!isset($data['filters']) || !is_array($data['filters'])) {
            throw new Exception('Geçersiz filtre formatı');
        }

        $filters = $data['filters'];
        
        // Varsayılan değerleri ayarla
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        $userId = isset($filters['user_id']) && $filters['user_id'] ? (int)$filters['user_id'] : 0;
        $transactionType = $filters['transaction_type'] ?? '';
        $gameId = $filters['game_id'] ?? '';
        $provider = $filters['provider'] ?? '';
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // WHERE şartlarını oluştur
        $whereConditions = [];
        $params = [];

        // Tarih aralığı şartı
        $whereConditions[] = "t.created_at BETWEEN ? AND ?";
        $params[] = $startDate . ' 00:00:00';
        $params[] = $endDate . ' 23:59:59';

        // Kullanıcı ID şartı
        if ($userId > 0) {
            $whereConditions[] = "t.user_id = ?";
            $params[] = $userId;
        }

        // İşlem tipi şartı
        if (!empty($transactionType)) {
            $whereConditions[] = "t.type = ?";
            $params[] = $transactionType;
        }

        // Oyun ID şartı
        if (!empty($gameId)) {
            $whereConditions[] = "t.game = ?";
            $params[] = $gameId;
        }

        // Sağlayıcı şartı
        if (!empty($provider)) {
            $whereConditions[] = "t.providers = ?";
            $params[] = $provider;
        }

        // WHERE şartını oluştur
        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        // Toplam kayıt sayısını hesapla
        $countQuery = "SELECT COUNT(*) FROM transactions t $whereClause";
        $stmt = $db->prepare($countQuery);
        $stmt->execute($params);
        $totalRecords = $stmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // İşlemleri sorgula
        $query = "
            SELECT 
                t.*,
                k.username,
                g.game_name,
                g.provider_game,
                g.game_type 
            FROM 
                transactions t
            LEFT JOIN 
                kullanicilar k ON t.user_id = k.id
            LEFT JOIN 
                games g ON t.game = g.game_code
            $whereClause
            ORDER BY t.created_at DESC
            LIMIT $offset, $limit
        ";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // JSON için işlem verilerini formatla
        foreach ($transactions as &$transaction) {
            // Tarihi okunabilir formata çevir
            $transaction['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($transaction['created_at']));
            
            // Statü ekle
            $transaction['status'] = $transaction['type'] === 'win' ? 'win' : 'lose';
        }

        // Bahis toplamını hesapla
        $betTotalQuery = "
            SELECT SUM(amount) as total
            FROM transactions t
            " . ($whereClause ? $whereClause . " AND" : "WHERE") . " t.type = 'bet'
        ";
        $betParams = $params;
        
        $stmt = $db->prepare($betTotalQuery);
        $stmt->execute($betParams);
        $betTotal = $stmt->fetchColumn() ?: 0;

        // Kazanç toplamını hesapla
        $winTotalQuery = "
            SELECT SUM(type_money) as total
            FROM transactions t
            " . ($whereClause ? $whereClause . " AND" : "WHERE") . " t.type = 'win'
        ";
        $winParams = $params;
        
        $stmt = $db->prepare($winTotalQuery);
        $stmt->execute($winParams);
        $winTotal = $stmt->fetchColumn() ?: 0;

        // İstatistik verilerini hazırla
        $stats = [
            'total_records' => (int)$totalRecords,
            'bet_total' => (float)$betTotal,
            'win_total' => (float)$winTotal
        ];

        // Sayfalama bilgisini hazırla
        $pagination = [
            'total_records' => (int)$totalRecords,
            'total_pages' => (int)$totalPages,
            'current_page' => (int)$page,
            'limit' => (int)$limit,
            'showing_start' => ($page - 1) * $limit + 1,
            'showing_end' => min($page * $limit, $totalRecords)
        ];

        // Yanıtı oluştur
        echo json_encode([
            'type' => 'transactions',
            'transactions' => $transactions,
            'stats' => $stats,
            'pagination' => $pagination,
            'timestamp' => time()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'type' => 'error',
            'message' => 'Sunucu hatası: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'type' => 'error',
        'message' => 'Bilinmeyen istek tipi: ' . $data['type']
    ]);
} 