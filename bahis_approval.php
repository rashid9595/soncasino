<?php
require_once 'config/database.php';

// Session kontrolü
session_start();
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1; // Admin ID için varsayılan değer
}

// Page title
$pageTitle = "Bahis Onay Sistemi";

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && isset($_POST['bet_id'])) {
            $action = $_POST['action'];
            $betId = $_POST['bet_id'];
            $adminNote = $_POST['admin_note'] ?? '';
            
            // Update bet status based on action
            if ($action === 'approve_won') {
                // Mark bet as won
                $updateStmt = $db->prepare("UPDATE bahisler SET status = 'won', updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$betId]);
                
                // Get bet info for balance update
                $betStmt = $db->prepare("SELECT user_id, potential_winnings FROM bahisler WHERE id = ?");
                $betStmt->execute([$betId]);
                $bet = $betStmt->fetch(PDO::FETCH_ASSOC);
                
                // Update user balance
                if ($bet) {
                    $updateBalanceStmt = $db->prepare("UPDATE kullanicilar SET ana_bakiye = ana_bakiye + ? WHERE id = ?");
                    $updateBalanceStmt->execute([$bet['potential_winnings'], $bet['user_id']]);
                    
                    // Log transaction
                    $logStmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'approve_won', ?, ?)");
                    $logStmt->execute([$_SESSION['admin_id'], "Bahis kazandı olarak onaylandı. Kullanıcıya {$bet['potential_winnings']} TL ödeme yapıldı.", $_SERVER['REMOTE_ADDR']]);
                }
                
                $successMessage = "Bahis kazandı olarak işaretlendi ve kullanıcıya ödeme yapıldı!";
            } elseif ($action === 'approve_lost') {
                // Mark bet as lost
                $updateStmt = $db->prepare("UPDATE bahisler SET status = 'lost', updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$betId]);
                
                // Log action
                $logStmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'approve_lost', ?, ?)");
                $logStmt->execute([$_SESSION['admin_id'], "Bahis kaybetti olarak onaylandı.", $_SERVER['REMOTE_ADDR']]);
                
                $successMessage = "Bahis kaybetti olarak işaretlendi!";
            } elseif ($action === 'cancel') {
                // Mark bet as canceled
                $updateStmt = $db->prepare("UPDATE bahisler SET status = 'canceled', updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$betId]);
                
                // Get bet info for refund
                $betStmt = $db->prepare("SELECT user_id, amount FROM bahisler WHERE id = ?");
                $betStmt->execute([$betId]);
                $bet = $betStmt->fetch(PDO::FETCH_ASSOC);
                
                // Refund amount to user balance
                if ($bet) {
                    $updateBalanceStmt = $db->prepare("UPDATE kullanicilar SET ana_bakiye = ana_bakiye + ? WHERE id = ?");
                    $updateBalanceStmt->execute([$bet['amount'], $bet['user_id']]);
                    
                    // Log transaction
                    $logStmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'cancel_bet', ?, ?)");
                    $logStmt->execute([$_SESSION['admin_id'], "Bahis iptal edildi. Kullanıcıya {$bet['amount']} TL iade yapıldı. Not: {$adminNote}", $_SERVER['REMOTE_ADDR']]);
                }
                
                $successMessage = "Bahis iptal edildi ve yatırılan miktar kullanıcıya iade edildi!";
            }
        }
    } catch (PDOException $e) {
        $errorMessage = "Veritabanı hatası: " . $e->getMessage();
    }
}

// Get statistics
try {
    // Total bets
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bahisler");
    $stmt->execute();
    $totalBets = $stmt->fetch()['total'];
    
    // Active bets (pending)
    $stmt = $db->prepare("SELECT COUNT(*) as active FROM bahisler WHERE status = 'active'");
    $stmt->execute();
    $activeBets = $stmt->fetch()['active'];
    
    // Won bets
    $stmt = $db->prepare("SELECT COUNT(*) as won, SUM(potential_winnings) as total_winnings FROM bahisler WHERE status = 'won'");
    $stmt->execute();
    $wonData = $stmt->fetch();
    $wonBets = $wonData['won'];
    $totalWinnings = $wonData['total_winnings'] ?: 0;
    
    // Lost bets
    $stmt = $db->prepare("SELECT COUNT(*) as lost, SUM(amount) as total_lost FROM bahisler WHERE status = 'lost'");
    $stmt->execute();
    $lostData = $stmt->fetch();
    $lostBets = $lostData['lost'];
    $totalLost = $lostData['total_lost'] ?: 0;
    
    // Canceled bets
    $stmt = $db->prepare("SELECT COUNT(*) as canceled, SUM(amount) as total_refunded FROM bahisler WHERE status = 'canceled'");
    $stmt->execute();
    $canceledData = $stmt->fetch();
    $canceledBets = $canceledData['canceled'];
    $totalRefunded = $canceledData['total_refunded'] ?: 0;
    
    // Today's bets
    $stmt = $db->prepare("SELECT COUNT(*) as today FROM bahisler WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayBets = $stmt->fetch()['today'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalBets = $activeBets = $wonBets = $lostBets = $canceledBets = $todayBets = 0;
    $totalWinnings = $totalLost = $totalRefunded = 0;
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter setup
$status = isset($_GET['status']) ? $_GET['status'] : 'active';
$statusFilter = '';

// Get total bet count for pagination
$countQuery = "SELECT COUNT(*) FROM bahisler b";
if ($status !== 'all') {
    $countQuery .= " WHERE b.status = :status";
}
$countStmt = $db->prepare($countQuery);
if ($status !== 'all') {
    $countStmt->bindValue(':status', $status);
}
$countStmt->execute();
$filteredBets = $countStmt->fetchColumn();
$totalPages = ceil($filteredBets / $perPage);

// Get bets with pagination
$query = "SELECT b.*, k.username 
          FROM bahisler b 
          LEFT JOIN kullanicilar k ON b.user_id = k.id";
          
if ($status !== 'all') {
    $query .= " WHERE b.status = :status";
}

$query .= " ORDER BY b.created_at DESC LIMIT :offset, :perPage";
$stmt = $db->prepare($query);

// Bind parameters with their types
if ($status !== 'all') {
    $stmt->bindValue(':status', $status);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);

// Execute the prepared statement
$stmt->execute();
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process bet data to extract detailed information
foreach ($bets as $key => $bet) {
    $selectionsArray = [];
    
    // Bet data'dan bahis bilgilerini çıkart
    $betData = json_decode($bet['bet_data'], true);
    
    if (isset($betData['bets']) && is_array($betData['bets'])) {
        foreach ($betData['bets'] as $betInfo) {
            // Maç başlığını al
            $matchTitle = isset($betInfo['match_title']) ? htmlspecialchars($betInfo['match_title']) : 'Bilinmiyor';
            
            // Bahis tarihini ayarla (varsa), yoksa oluşturma tarihini kullan
            $matchDate = isset($bet['created_at']) ? date('d.m.Y H:i', strtotime($bet['created_at'])) : '-';
            
            // Bahis türü ve seçim bilgisini al
            $marketName = isset($betInfo['market_name']) ? htmlspecialchars($betInfo['market_name']) : 'Bilinmiyor';
            $pick = isset($betInfo['pick']) ? htmlspecialchars($betInfo['pick']) : 'Bilinmiyor';
            $selectionText = $marketName . ': ' . $pick;
            
            // Oranı al
            $odds = isset($betInfo['price']) ? number_format($betInfo['price'], 2) : '0.00';
            
            // Statü bilgisini ayarla (hepsi aktif ise beklemede olarak ayarla)
            $status = '<span class="badge" style="background-color: #000000; color: #fff; font-weight: bold;">Beklemede</span>';
            if ($bet['status'] == 'won') {
                $status = '<span class="badge badge-success" style="background-color: #28a745; color: #fff; font-weight: bold;">Kazandı</span>';
            } elseif ($bet['status'] == 'lost') {
                $status = '<span class="badge badge-danger" style="background-color: #dc3545; color: #fff; font-weight: bold;">Kaybetti</span>';
            } elseif ($bet['status'] == 'canceled') {
                $status = '<span class="badge badge-secondary" style="background-color: #6c757d; color: #fff; font-weight: bold;">İptal</span>';
            }
            
            // Bahis tipine göre bilgileri çıkart
            $betType = isset($betData['type']) ? $betData['type'] : 0;
            $betTypeText = ($betType == 1) ? "Tekli Bahis" : (($betType == 2) ? "Kombine Bahis" : "Bilinmeyen Bahis");
            
            $selectionsArray[] = [
                'match' => $matchTitle,
                'date' => $matchDate,
                'selection' => $selectionText,
                'odds' => $odds,
                'status' => $status,
                'betType' => $betTypeText
            ];
        }
    }
    
    $bets[$key]['selections_array'] = $selectionsArray;
}

// Status definitions for display
$statusLabels = [
    'active' => '<span class="badge" style="background-color: #000000; color: #fff; font-weight: bold;">Beklemede</span>',
    'won' => '<span class="badge badge-success" style="background-color: #28a745; color: #fff; font-weight: bold;">Kazandı</span>',
    'lost' => '<span class="badge badge-danger" style="background-color: #dc3545; color: #fff; font-weight: bold;">Kaybetti</span>',
    'canceled' => '<span class="badge badge-secondary" style="background-color: #6c757d; color: #fff; font-weight: bold;">İptal Edildi</span>'
];

// Start output buffering for page content
ob_start();
?>

<style>
    :root {
        /* Corporate Blue Color Palette */
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --secondary-blue: #60a5fa;
        --accent-blue: #93c5fd;
        --light-blue: #dbeafe;
        --ultra-light-blue: #eff6ff;
        
        /* Corporate Whites and Grays */
        --white: #ffffff;
        --light-gray: #f8fafc;
        --medium-gray: #e2e8f0;
        --dark-gray: #64748b;
        --text-gray: #475569;
        
        /* Status Colors */
        --success-green: #059669;
        --error-red: #dc2626;
        --warning-orange: #d97706;
        --info-blue: var(--primary-blue-light);
        
        /* Corporate Gradients - Dark to Light Blue Theme */
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        --secondary-gradient: linear-gradient(135deg, #1e40af 0%, #60a5fa 100%);
        --tertiary-gradient: linear-gradient(135deg, #1e3a8a 0%, #93c5fd 100%);
        --quaternary-gradient: linear-gradient(135deg, #1e40af 0%, #dbeafe 100%);
        --light-gradient: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        --corporate-gradient: linear-gradient(135deg, #1e3a8a 0%, #60a5fa 50%, #dbeafe 100%);
        
        /* Corporate Theme */
        --bg-primary: var(--light-gray);
        --bg-secondary: var(--ultra-light-blue);
        --card-bg: var(--white);
        --card-border: var(--medium-gray);
        --text-primary: var(--text-gray);
        --text-secondary: var(--dark-gray);
        --text-heading: var(--primary-blue-dark);
        
        /* Corporate Shadows */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
        --shadow-blue: 0 0 20px rgba(30, 64, 175, 0.2);
        --shadow-success: 0 0 20px rgba(5, 150, 105, 0.2);
        --shadow-warning: 0 0 20px rgba(217, 119, 6, 0.2);
        --shadow-danger: 0 0 20px rgba(220, 38, 38, 0.2);
        
        /* Layout */
        --border-radius: 8px;
        --border-radius-lg: 12px;
        --border-radius-sm: 6px;
    }

    body {
        background: var(--bg-primary);
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: var(--text-primary);
        line-height: 1.6;
    }

    @keyframes fadeIn {
        from { 
            opacity: 0; 
            transform: translateY(20px);
        }
        to { 
            opacity: 1; 
            transform: translateY(0);
        }
    }

    .dashboard-header {
        margin-bottom: 2rem;
        position: relative;
        padding: 2rem;
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-md);
        border-top: 4px solid var(--primary-blue-dark);
    }
    
    .greeting {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.8rem;
        color: var(--text-heading);
        position: relative;
        font-family: 'Inter', sans-serif;
        letter-spacing: -0.5px;
    }
    
    .dashboard-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
        margin-bottom: 0;
        font-weight: 400;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        position: relative;
        border: 1px solid var(--card-border);
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .stat-card.total::after {
        background: var(--primary-gradient);
    }
    
    .stat-card.active::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.won::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.lost::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.canceled::after {
        background: var(--primary-gradient);
    }

    .stat-card.today::after {
        background: var(--secondary-gradient);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        position: relative;
        color: white;
    }
    
    .stat-card.total .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.active .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.won .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.lost .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.canceled .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.today .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-heading);
        font-family: 'Inter', monospace;
    }
    
    .stat-title {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bets-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .bets-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .bets-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .bets-header h6 {
        margin: 0;
        color: var(--text-heading);
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .bets-header h6 i {
        color: var(--primary-blue-light);
        margin-right: 0.5rem;
    }

    .bets-body {
        padding: 2rem;
    }

    .filters-section {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        border: 1px solid var(--card-border);
    }

    .filter-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .filter-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-group label {
        font-weight: 600;
        color: var(--text-heading);
        margin: 0;
    }

    .filter-group select {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
        border-radius: var(--border-radius);
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }

    .filter-group select:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        outline: none;
    }

    .total-info {
        background: var(--primary-gradient);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        box-shadow: var(--shadow-sm);
    }

    .table-responsive {
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .table {
        background: var(--card-bg);
        margin: 0;
    }

    .table thead th {
        background: var(--ultra-light-blue);
        border-bottom: 2px solid var(--card-border);
        color: var(--text-heading);
        font-weight: 600;
        padding: 1rem;
        text-align: left;
    }

    .table tbody td {
        padding: 1rem;
        border-bottom: 1px solid var(--card-border);
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: var(--bg-secondary);
    }

    .badge {
        display: inline-block;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
        font-weight: 600;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: var(--border-radius-sm);
        box-shadow: var(--shadow-sm);
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .btn-success {
        background: var(--success-green);
        color: var(--white);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-success);
        color: var(--white);
    }

    .btn-danger {
        background: var(--error-red);
        color: var(--white);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-danger);
        color: var(--white);
    }

    .btn-warning {
        background: var(--warning-orange);
        color: var(--white);
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-warning);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    .alert {
        border-radius: var(--border-radius);
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
        animation: fadeIn 0.5s ease;
    }

    .alert-success {
        background: #dcfce7;
        border-left: 4px solid var(--success-green);
        color: #166534;
    }

    .alert-danger {
        background: #fee2e2;
        border-left: 4px solid var(--error-red);
        color: #7f1d1d;
    }

    .alert-info {
        background: var(--light-blue);
        border-left: 4px solid var(--info-blue);
        color: var(--primary-blue-dark);
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .page-item {
        list-style: none;
    }

    .page-link {
        padding: 0.75rem 1rem;
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
        text-decoration: none;
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .page-link:hover {
        background: var(--bg-secondary);
        border-color: var(--primary-blue-light);
        color: var(--primary-blue-dark);
    }

    .page-item.active .page-link {
        background: var(--primary-gradient);
        border-color: var(--primary-blue-light);
        color: white;
    }

    .page-item.disabled .page-link {
        background: var(--light-gray);
        color: var(--text-secondary);
        cursor: not-allowed;
    }

    .text-muted {
        color: var(--text-secondary) !important;
    }

    .animate-fade-in {
        animation: fadeIn 0.8s ease forwards;
    }

    /* Modal styles */
    .modal-backdrop {
        z-index: 1040;
    }
    
    .modal {
        z-index: 1050;
    }
    
    /* Dropdown styles */
    .show > .dropdown-menu {
        display: block !important;
    }
    
    .dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 1000;
        display: none;
        min-width: 10rem;
        padding: 0.5rem 0;
        margin: 0.125rem 0 0;
        font-size: 1rem;
        color: var(--text-primary);
        text-align: left;
        list-style: none;
        background-color: var(--card-bg);
        background-clip: padding-box;
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
    }

    /* Container responsive design */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem;
        }
        
        .greeting {
            font-size: 1.5rem;
        }
        
        .stat-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .bets-body {
            padding: 1.5rem;
        }
        
        .filter-row {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }
        
        .filter-group {
            justify-content: space-between;
        }
        
        .table-responsive {
            font-size: 0.9rem;
        }
        
        .table thead th,
        .table tbody td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-check-circle-fill me-2"></i>
        Bahis Onay Sistemi
    </h1>
    <p class="dashboard-subtitle">Kullanıcı bahislerini onaylayın, reddedin veya iptal edin.</p>
</div>

<?php if (isset($successMessage)): ?>
<div class="alert alert-success animate-fade-in">
    <i class="bi bi-check-circle-fill me-2"></i>
    <?php echo $successMessage; ?>
</div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
<div class="alert alert-danger animate-fade-in">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?php echo $errorMessage; ?>
</div>
<?php endif; ?>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-ticket-detailed"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalBets); ?></div>
        <div class="stat-title">Toplam Bahis</div>
    </div>
    
    <div class="stat-card active">
        <div class="stat-icon">
            <i class="bi bi-clock"></i>
        </div>
        <div class="stat-number"><?php echo number_format($activeBets); ?></div>
        <div class="stat-title">Bekleyen Bahis</div>
    </div>
    
    <div class="stat-card won">
        <div class="stat-icon">
            <i class="bi bi-trophy"></i>
        </div>
        <div class="stat-number"><?php echo number_format($wonBets); ?></div>
        <div class="stat-title">Kazanan Bahis</div>
    </div>
    
    <div class="stat-card lost">
        <div class="stat-icon">
            <i class="bi bi-x-circle"></i>
        </div>
        <div class="stat-number"><?php echo number_format($lostBets); ?></div>
        <div class="stat-title">Kaybeden Bahis</div>
    </div>
    
    <div class="stat-card today">
        <div class="stat-icon">
            <i class="bi bi-calendar-day"></i>
        </div>
        <div class="stat-number"><?php echo number_format($todayBets); ?></div>
        <div class="stat-title">Bugünkü Bahis</div>
    </div>
</div>

<div class="bets-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="bets-header">
        <h6><i class="bi bi-list-ul"></i>Bahis Listesi</h6>
        <div class="total-info">
            Toplam: <strong><?php echo number_format($filteredBets); ?></strong> bahis
        </div>
    </div>
    <div class="bets-body">
        <div class="filters-section">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status">
                        <i class="bi bi-funnel me-1"></i>Durum Filtresi:
                    </label>
                    <form action="" method="get" style="display: inline;">
                        <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Tümü</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Bekleyenler</option>
                            <option value="won" <?php echo $status === 'won' ? 'selected' : ''; ?>>Kazananlar</option>
                            <option value="lost" <?php echo $status === 'lost' ? 'selected' : ''; ?>>Kaybedenler</option>
                            <option value="canceled" <?php echo $status === 'canceled' ? 'selected' : ''; ?>>İptal Edilenler</option>
                        </select>
                    </form>
                </div>
                <div class="filter-group">
                    <span class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Sayfa <?php echo $page; ?> / <?php echo $totalPages; ?>
                    </span>
                </div>
            </div>
        </div>
            
            <div class="table-responsive">
                <table id="betTable" class="table" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash me-1"></i>#</th>
                            <th><i class="bi bi-ticket me-1"></i>Kupon No</th>
                            <th><i class="bi bi-person me-1"></i>Kullanıcı</th>
                            <th><i class="bi bi-tag me-1"></i>Bahis Tipi</th>
                            <th><i class="bi bi-info-circle me-1"></i>Bahis</th>
                            <th><i class="bi bi-currency-exchange me-1"></i>Tutar</th>
                            <th><i class="bi bi-graph-up me-1"></i>Oran</th>
                            <th><i class="bi bi-trophy me-1"></i>Pot. Kazanç</th>
                            <th><i class="bi bi-calendar me-1"></i>Tarih</th>
                            <th><i class="bi bi-flag me-1"></i>Durum</th>
                            <th><i class="bi bi-gear me-1"></i>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bets)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                    <p>Gösterilecek bahis bulunamadı.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($bets as $bet): 
                                $betData = json_decode($bet['bet_data'], true);
                                $betType = isset($betData['type']) && $betData['type'] == 1 ? 'Tekli' : 'Kombine';
                                
                                // Bahis detayı özeti
                                $betSummary = "Bilinmiyor";
                                if (!empty($bet['selections_array'])) {
                                    if (count($bet['selections_array']) == 1) {
                                        // Tekli bahis için sadece maç adını göster
                                        $betSummary = substr($bet['selections_array'][0]['match'], 0, 30) . (strlen($bet['selections_array'][0]['match']) > 30 ? '...' : '');
                                    } else {
                                        // Kombine bahis için maç sayısını göster
                                        $betSummary = count($bet['selections_array']) . " Maçlı Kombine";
                                    }
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo $bet['id']; ?></strong></td>
                                <td><code><?php echo htmlspecialchars($bet['coupon_id']); ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person-circle me-2"></i>
                                        <?php echo htmlspecialchars($bet['username']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $betType === 'Tekli' ? 'bg-primary' : 'bg-secondary'; ?>">
                                        <?php echo $betType; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($betSummary); ?>">
                                        <?php echo $betSummary; ?>
                                    </div>
                                </td>
                                <td><strong><?php echo number_format($bet['amount'], 2); ?> ₺</strong></td>
                                <td><code><?php echo number_format($bet['total_odds'], 2); ?></code></td>
                                <td><strong class="text-success"><?php echo number_format($bet['potential_winnings'], 2); ?> ₺</strong></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($bet['created_at'])); ?></td>
                                <td><?php echo $statusLabels[$bet['status']] ?? '<span class="badge badge-secondary">Bilinmiyor</span>'; ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary btn-sm show-details" 
                                                data-id="<?php echo $bet['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($bet['username']); ?>"
                                                data-amount="<?php echo number_format($bet['amount'], 2); ?>"
                                                data-odds="<?php echo number_format($bet['total_odds'], 2); ?>"
                                                data-winnings="<?php echo number_format($bet['potential_winnings'], 2); ?>"
                                                data-date="<?php echo date('d.m.Y H:i:s', strtotime($bet['created_at'])); ?>"
                                                data-status="<?php echo $bet['status']; ?>"
                                                data-selections='<?php echo json_encode($bet['selections_array']); ?>'>
                                            <i class="bi bi-eye me-1"></i>Detay
                                        </button>
                                        
                                        <?php if ($bet['status'] === 'active'): ?>
                                        <button class="btn btn-success btn-sm show-options" data-id="<?php echo $bet['id']; ?>">
                                            <i class="bi bi-check-circle me-1"></i>Güncelle
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="text-center mt-4">
                <nav aria-label="Sayfalama">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&status=<?php echo $status; ?>" title="İlk Sayfa">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status; ?>" title="Önceki Sayfa">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max($page - 2, 1);
                        $endPage = min($startPage + 4, $totalPages);
                        
                        if ($startPage > 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $page) {
                                echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                            } else {
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&status=' . $status . '">' . $i . '</a></li>';
                            }
                        }
                        
                        if ($endPage < $totalPages) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status; ?>" title="Sonraki Sayfa">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $totalPages; ?>&status=<?php echo $status; ?>" title="Son Sayfa">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bahis detaylarını göster
    document.querySelectorAll('.show-details').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');
            var amount = this.getAttribute('data-amount');
            var odds = this.getAttribute('data-odds');
            var winnings = this.getAttribute('data-winnings');
            var date = this.getAttribute('data-date');
            var status = this.getAttribute('data-status');
            var selections = JSON.parse(this.getAttribute('data-selections'));
            
            // Durum etiketi
            var statusBadge = '';
            if (status === 'active') {
                statusBadge = '<span class="badge" style="background-color: #000000; color: #fff; font-weight: bold;">Beklemede</span>';
            } else if (status === 'won') {
                statusBadge = '<span class="badge badge-success" style="background-color: #28a745; color: #fff; font-weight: bold;">Kazandı</span>';
            } else if (status === 'lost') {
                statusBadge = '<span class="badge badge-danger" style="background-color: #dc3545; color: #fff; font-weight: bold;">Kaybetti</span>';
            } else if (status === 'canceled') {
                statusBadge = '<span class="badge badge-secondary" style="background-color: #6c757d; color: #fff; font-weight: bold;">İptal</span>';
            }
            
            // Bahis türünü al
            var betType = '';
            if (selections.length > 0 && selections[0].betType) {
                betType = selections[0].betType;
            }
            
            // Seçimler tablosunu oluştur
            var selectionsHtml = '';
            if (selections.length > 0) {
                selectionsHtml = '<h6 class="font-weight-bold mt-3">Bahis Detayları</h6>' +
                    '<table class="table table-bordered table-striped">' +
                    '<thead><tr><th>Maç</th><th>Tarih</th><th>Seçim</th><th>Oran</th><th>Durum</th></tr></thead>' +
                    '<tbody>';
                    
                selections.forEach(function(selection) {
                    selectionsHtml += '<tr>' +
                        '<td>' + selection.match + '</td>' +
                        '<td>' + selection.date + '</td>' +
                        '<td>' + selection.selection + '</td>' +
                        '<td>' + selection.odds + '</td>' +
                        '<td>' + selection.status + '</td>' +
                        '</tr>';
                });
                
                selectionsHtml += '</tbody></table>';
            } else {
                selectionsHtml = '<div class="alert alert-info mt-3">Bu bahis için detay bilgisi bulunmamaktadır.</div>';
            }
            
            Swal.fire({
                title: 'Bahis Detayı - ID: ' + id,
                html: '<div class="row">' +
                      '<div class="col-md-6">' +
                      '<h6 class="font-weight-bold">Bahis Bilgileri</h6>' +
                      '<table class="table table-sm">' +
                      '<tr><th>Kupon ID:</th><td>' + id + '</td></tr>' +
                      '<tr><th>Kullanıcı:</th><td>' + username + '</td></tr>' +
                      '<tr><th>Bahis Zamanı:</th><td>' + date + '</td></tr>' +
                      '<tr><th>Bahis Türü:</th><td>' + betType + '</td></tr>' +
                      '<tr><th>Durum:</th><td>' + statusBadge + '</td></tr>' +
                      '</table>' +
                      '</div>' +
                      '<div class="col-md-6">' +
                      '<h6 class="font-weight-bold">Finansal Bilgiler</h6>' +
                      '<table class="table table-sm">' +
                      '<tr><th>Bahis Tutarı:</th><td>' + amount + ' TL</td></tr>' +
                      '<tr><th>Toplam Oran:</th><td>' + odds + '</td></tr>' +
                      '<tr><th>Potansiyel Kazanç:</th><td>' + winnings + ' TL</td></tr>' +
                      '</table>' +
                      '</div>' +
                      '</div>' +
                      selectionsHtml,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    container: 'swal-wide',
                    popup: 'swal-wide-popup',
                    content: 'text-left'
                }
            });
        });
    });
    
    // Onay işlemleri menüsünü göster
    document.querySelectorAll('.show-options').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            
            Swal.fire({
                title: 'Bahis Onay İşlemleri',
                html: '<div class="text-center">' +
                      '<button class="btn btn-success mx-2 approve-won" data-id="' + id + '"><i class="fas fa-trophy"></i> Kazandı</button>' +
                      '<button class="btn btn-danger mx-2 approve-lost" data-id="' + id + '"><i class="fas fa-times"></i> Kaybetti</button>' +
                      '<button class="btn btn-warning mx-2 approve-cancel" data-id="' + id + '"><i class="fas fa-ban"></i> İptal Et</button>' +
                      '</div>',
                showCloseButton: true,
                showConfirmButton: false
            });
            
            // Kazandı butonu
            document.querySelector('.approve-won').addEventListener('click', function() {
                var betId = this.getAttribute('data-id');
                
                Swal.fire({
                    title: 'Bahis Kazandı Onayı',
                    html: '<div class="alert alert-warning">' +
                          '<i class="fas fa-exclamation-triangle"></i> ' +
                          '<strong>Dikkat:</strong> Bu bahisi kazandı olarak işaretlediğinizde, kullanıcının bakiyesine kazanç eklenecektir.' +
                          '</div>' +
                          '<div class="form-group">' +
                          '<label for="admin_note">Admin Notu (Opsiyonel):</label>' +
                          '<textarea id="admin_note" class="form-control" rows="3"></textarea>' +
                          '</div>',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Onaylıyorum',
                    cancelButtonText: 'İptal',
                    preConfirm: () => {
                        const adminNote = Swal.getPopup().querySelector('#admin_note').value;
                        return { adminNote: adminNote };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Form gönder
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'bahis_approval.php';
                        
                        var betIdInput = document.createElement('input');
                        betIdInput.type = 'hidden';
                        betIdInput.name = 'bet_id';
                        betIdInput.value = betId;
                        form.appendChild(betIdInput);
                        
                        var actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'approve_won';
                        form.appendChild(actionInput);
                        
                        var noteInput = document.createElement('input');
                        noteInput.type = 'hidden';
                        noteInput.name = 'admin_note';
                        noteInput.value = result.value.adminNote;
                        form.appendChild(noteInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
            
            // Kaybetti butonu
            document.querySelector('.approve-lost').addEventListener('click', function() {
                var betId = this.getAttribute('data-id');
                
                Swal.fire({
                    title: 'Bahis Kaybetti Onayı',
                    html: '<div class="alert alert-warning">' +
                          '<i class="fas fa-exclamation-triangle"></i> ' +
                          '<strong>Dikkat:</strong> Bu bahisi kaybetti olarak işaretliyorsunuz. Kullanıcının bakiyesine herhangi bir ödeme yapılmayacaktır.' +
                          '</div>' +
                          '<div class="form-group">' +
                          '<label for="admin_note">Admin Notu (Opsiyonel):</label>' +
                          '<textarea id="admin_note" class="form-control" rows="3"></textarea>' +
                          '</div>',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Onaylıyorum',
                    cancelButtonText: 'İptal',
                    preConfirm: () => {
                        const adminNote = Swal.getPopup().querySelector('#admin_note').value;
                        return { adminNote: adminNote };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Form gönder
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'bahis_approval.php';
                        
                        var betIdInput = document.createElement('input');
                        betIdInput.type = 'hidden';
                        betIdInput.name = 'bet_id';
                        betIdInput.value = betId;
                        form.appendChild(betIdInput);
                        
                        var actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'approve_lost';
                        form.appendChild(actionInput);
                        
                        var noteInput = document.createElement('input');
                        noteInput.type = 'hidden';
                        noteInput.name = 'admin_note';
                        noteInput.value = result.value.adminNote;
                        form.appendChild(noteInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
            
            // İptal Et butonu
            document.querySelector('.approve-cancel').addEventListener('click', function() {
                var betId = this.getAttribute('data-id');
                
                Swal.fire({
                    title: 'Bahis İptal Onayı',
                    html: '<div class="alert alert-info">' +
                          '<i class="fas fa-info-circle"></i> ' +
                          '<strong>Bilgi:</strong> Bu bahisi iptal ettiğinizde, kullanıcının yatırdığı miktar bakiyesine iade edilecektir.' +
                          '</div>' +
                          '<div class="form-group">' +
                          '<label for="admin_note">İptal Nedeni (Zorunlu):</label>' +
                          '<textarea id="admin_note" class="form-control" rows="3"></textarea>' +
                          '</div>',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Onaylıyorum',
                    cancelButtonText: 'İptal',
                    preConfirm: () => {
                        const adminNote = Swal.getPopup().querySelector('#admin_note').value;
                        if (!adminNote) {
                            Swal.showValidationMessage('İptal nedeni zorunludur');
                        }
                        return { adminNote: adminNote };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Form gönder
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'bahis_approval.php';
                        
                        var betIdInput = document.createElement('input');
                        betIdInput.type = 'hidden';
                        betIdInput.name = 'bet_id';
                        betIdInput.value = betId;
                        form.appendChild(betIdInput);
                        
                        var actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'cancel';
                        form.appendChild(actionInput);
                        
                        var noteInput = document.createElement('input');
                        noteInput.type = 'hidden';
                        noteInput.name = 'admin_note';
                        noteInput.value = result.value.adminNote;
                        form.appendChild(noteInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    });
    
    // Alert mesajlarını 5 saniye sonra otomatik gizle
    setTimeout(function() {
        document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 300);
        });
    }, 5000);
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 
?> 