<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Default response
$response = [
    'draw' => intval($_GET['draw']),
    'recordsTotal' => 0,
    'recordsFiltered' => 0,
    'data' => []
];

try {
    // Count total records
    $countQuery = "SELECT COUNT(*) as total FROM password_resets";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $response['recordsTotal'] = $totalRecords;
    
    // Build search query if necessary
    $searchValue = $_GET['search']['value'] ?? '';
    $searchQuery = '';
    $searchParams = [];
    
    if (!empty($searchValue)) {
        $searchQuery = " WHERE p.token LIKE ? OR u.username LIKE ?";
        $searchParams = ["%{$searchValue}%", "%{$searchValue}%"];
        
        // Count filtered records
        $filteredCountQuery = "SELECT COUNT(*) as total FROM password_resets p 
                              JOIN kullanicilar u ON p.user_id = u.id
                              {$searchQuery}";
        $filteredCountStmt = $db->prepare($filteredCountQuery);
        $filteredCountStmt->execute($searchParams);
        $filteredRecords = $filteredCountStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $response['recordsFiltered'] = $filteredRecords;
    } else {
        $response['recordsFiltered'] = $totalRecords;
    }
    
    // Order parameters
    $columnIndex = $_GET['order'][0]['column'] ?? 0;
    $columnName = $_GET['columns'][$columnIndex]['data'] ?? 'id';
    $columnSortOrder = $_GET['order'][0]['dir'] ?? 'desc';
    
    // Validate column name to prevent SQL injection
    $allowedColumns = ['id', 'username', 'token', 'expires_at', 'used', 'created_at'];
    if (!in_array($columnName, $allowedColumns)) {
        $columnName = 'id';
    }
    
    // Handle special case for username since it comes from kullanicilar table
    $orderBy = $columnName === 'username' ? 'u.username' : "p.{$columnName}";
    
    // Pagination
    $start = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 10;
    
    // Final query
    $query = "SELECT p.*, u.username, u.id as user_id 
              FROM password_resets p
              JOIN kullanicilar u ON p.user_id = u.id
              {$searchQuery}
              ORDER BY {$orderBy} {$columnSortOrder}
              LIMIT {$start}, {$length}";
    
    $stmt = $db->prepare($query);
    $stmt->execute($searchParams);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['data'] = $data;
    
} catch (PDOException $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 