<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Oyun İşlem Geçmişi";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check if user has permission to view this page
if (!isset($_SESSION['role_id'])) {
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

// If user is admin (role_id = 1), grant full access
$isAdmin = ($_SESSION['role_id'] == 1);

if (!$isAdmin) {
    $stmt = $db->prepare("
        SELECT ap.* 
        FROM admin_permissions ap 
        WHERE ap.role_id = ? AND ap.menu_item = 'transactions_history' AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Get statistics for dashboard
try {
    // Total transactions count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM transactions");
    $stmt->execute();
    $totalTransactions = $stmt->fetch()['total'];
    
    // Today's transactions count
    $stmt = $db->prepare("SELECT COUNT(*) as today FROM transactions WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayTransactions = $stmt->fetch()['today'];
    
    // Total bet amount
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM transactions WHERE type = 'bet'");
    $stmt->execute();
    $totalBetAmount = $stmt->fetch()['total'] ?? 0;
    
    // Total win amount
    $stmt = $db->prepare("SELECT SUM(type_money) as total FROM transactions WHERE type = 'win'");
    $stmt->execute();
    $totalWinAmount = $stmt->fetch()['total'] ?? 0;
    
    // Total profit/loss
    $totalProfitLoss = $totalWinAmount - $totalBetAmount;
    
    // Unique users count
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as unique_users FROM transactions");
    $stmt->execute();
    $uniqueUsers = $stmt->fetch()['unique_users'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalTransactions = $todayTransactions = $totalBetAmount = $totalWinAmount = $totalProfitLoss = $uniqueUsers = 0;
}

// Process filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$transactionType = isset($_GET['transaction_type']) ? $_GET['transaction_type'] : '';
$gameId = isset($_GET['game_id']) ? $_GET['game_id'] : '';
$provider = isset($_GET['provider']) ? $_GET['provider'] : '';

// Create WHERE clause for filtering
$whereConditions = [];
$params = [];

// Add date range condition
$whereConditions[] = "t.created_at BETWEEN ? AND ?";
$params[] = $startDate . ' 00:00:00';
$params[] = $endDate . ' 23:59:59';

// Add user ID condition if specified
if ($userId > 0) {
    $whereConditions[] = "t.user_id = ?";
    $params[] = $userId;
}

// Add transaction type condition if specified
if (!empty($transactionType)) {
    $whereConditions[] = "t.type = ?";
    $params[] = $transactionType;
}

// Add game ID condition if specified
if (!empty($gameId)) {
    $whereConditions[] = "t.game = ?";
    $params[] = $gameId;
}

// Add provider condition if specified
if (!empty($provider)) {
    $whereConditions[] = "t.providers = ?";
    $params[] = $provider;
}

// Build the WHERE clause
$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get unique users for dropdown
$stmt = $db->prepare("
    SELECT DISTINCT k.id, k.username
    FROM kullanicilar k
    JOIN transactions t ON k.id = t.user_id
    ORDER BY k.username ASC
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get unique transaction types for dropdown
$stmt = $db->prepare("SELECT DISTINCT type FROM transactions ORDER BY type ASC");
$stmt->execute();
$transactionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique providers for dropdown
$stmt = $db->prepare("SELECT DISTINCT providers FROM transactions WHERE providers IS NOT NULL ORDER BY providers ASC");
$stmt->execute();
$providers = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique games for dropdown
$stmt = $db->prepare("SELECT DISTINCT game FROM transactions WHERE game IS NOT NULL ORDER BY game ASC");
$stmt->execute();
$games = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get transactions with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Count total records for pagination
$countQuery = "SELECT COUNT(*) FROM transactions t $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Query transactions with join to get username
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
$transactions = $stmt->fetchAll();

// Start output buffering
ob_start();
?>

<style>
    :root {
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --secondary-blue: #60a5fa;
        --accent-blue: #dbeafe;
        --success-green: #10b981;
        --warning-orange: #f59e0b;
        --danger-red: #ef4444;
        --info-cyan: #06b6d4;
        --dark-gray: #1f2937;
        --medium-gray: #374151;
        --light-gray: #6b7280;
        --white: #ffffff;
        --gradient-primary: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
        --gradient-secondary: linear-gradient(135deg, var(--secondary-blue) 0%, var(--accent-blue) 100%);
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --border-radius: 12px;
        --transition: all 0.3s ease;
    }

    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--dark-gray);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }

    .dashboard-header {
        background: var(--gradient-primary);
        color: var(--white);
        padding: 2rem 0;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
    }

    .greeting {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .dashboard-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 0;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .stat-card.total::after { background: var(--gradient-primary); }
    .stat-card.today::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.bet::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.win::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.profit::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.users::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        background: var(--gradient-secondary);
        color: var(--primary-blue);
    }

    .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin-bottom: 0.5rem;
    }

    .stat-card .stat-label {
        color: var(--light-gray);
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .transactions-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .transactions-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .transactions-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .transactions-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .transactions-body {
        padding: 2rem;
    }

    .filters-section {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        border: 1px solid #e2e8f0;
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .filter-group label {
        font-weight: 600;
        color: var(--dark-gray);
        font-size: 0.9rem;
    }

    .form-control, .form-select {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0.75rem;
        font-size: 0.9rem;
        transition: var(--transition);
        background: #0f172a;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .btn {
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: var(--transition);
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: var(--gradient-primary);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: var(--medium-gray);
        color: var(--white);
    }

    .btn-secondary:hover {
        background: var(--dark-gray);
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    .table th {
        background: #f8fafc;
        color: var(--dark-gray);
        font-weight: 600;
        padding: 1rem;
        border-bottom: 2px solid #e5e7eb;
        text-align: left;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: #f8fafc;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.bet {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-orange);
    }

    .status-badge.win {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
    }

    .status-badge.loss {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-red);
    }

    .alert {
        border-radius: var(--border-radius);
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        border: none;
        border-left: 4px solid;
    }

    .alert-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
        border-left-color: var(--success-green);
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-red);
        border-left-color: var(--danger-red);
    }

    .alert-info {
        background: rgba(6, 182, 212, 0.1);
        color: var(--info-cyan);
        border-left-color: var(--info-cyan);
    }

    .text-muted {
        color: var(--light-gray) !important;
    }

    .text-success {
        color: var(--success-green) !important;
    }

    .text-danger {
        color: var(--danger-red) !important;
    }

    .text-warning {
        color: var(--warning-orange) !important;
    }

    .text-info {
        color: var(--info-cyan) !important;
    }

    .fw-bold {
        font-weight: 700;
    }

    .small {
        font-size: 0.875rem;
    }

    .animate-fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--white);
        color: var(--dark-gray);
        text-decoration: none;
        transition: var(--transition);
        border: 1px solid #e5e7eb;
    }

    .pagination a:hover,
    .pagination a.active {
        background: var(--primary-blue-light);
        color: var(--white);
        border-color: var(--primary-blue-light);
    }

    .pagination a.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .round-id {
        font-family: monospace;
        background: rgba(59, 130, 246, 0.1);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
        color: var(--primary-blue-light);
    }

    .provider-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-blue-light);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem 0;
        }

        .greeting {
            font-size: 1.5rem;
        }

        .stat-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .transactions-body {
            padding: 1rem;
        }

        .filter-row {
            grid-template-columns: 1fr;
        }

        .table-responsive {
            font-size: 0.85rem;
        }

        .table th, .table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="greeting">
            <i class="bi bi-clock-history"></i>
            Oyun İşlem Geçmişi
        </div>
        <div class="dashboard-subtitle">
            Tüm oyun işlemlerini görüntüleyin ve analiz edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="bi bi-arrow-repeat"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalTransactions); ?></div>
            <div class="stat-label">Toplam İşlem</div>
        </div>
        
        <div class="stat-card bet">
            <div class="stat-icon">
                <i class="bi bi-arrow-down-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBetAmount, 2); ?> ₺</div>
            <div class="stat-label">Toplam Bahis</div>
        </div>
        
        <div class="stat-card win">
            <div class="stat-icon">
                <i class="bi bi-arrow-up-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalWinAmount, 2); ?> ₺</div>
            <div class="stat-label">Toplam Kazanç</div>
        </div>
        
        <div class="stat-card profit">
            <div class="stat-icon">
                <i class="bi bi-<?php echo $totalProfitLoss >= 0 ? 'plus' : 'minus'; ?>-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format(abs($totalProfitLoss), 2); ?> ₺</div>
            <div class="stat-label"><?php echo $totalProfitLoss >= 0 ? 'Toplam Kâr' : 'Toplam Zarar'; ?></div>
        </div>
        
        <div class="stat-card users">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?php echo number_format($uniqueUsers); ?></div>
            <div class="stat-label">Benzersiz Kullanıcı</div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="transactions-card">
        <div class="transactions-header">
            <div class="transactions-title">
                <i class="bi bi-funnel"></i>
                Filtreleme Seçenekleri
            </div>
        </div>
        <div class="transactions-body">
            <form method="get" action="" id="filterForm">
                <div class="filters-section">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="start_date">
                                <i class="bi bi-calendar" style="color: var(--primary-blue-light);"></i>
                                Başlangıç Tarihi
                            </label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="end_date">
                                <i class="bi bi-calendar" style="color: var(--primary-blue-light);"></i>
                                Bitiş Tarihi
                            </label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="user_id">
                                <i class="bi bi-person" style="color: var(--primary-blue-light);"></i>
                                Kullanıcı
                            </label>
                            <select id="user_id" name="user_id" class="form-select">
                                <option value="">Tüm Kullanıcılar</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="transaction_type">
                                <i class="bi bi-arrow-left-right" style="color: var(--primary-blue-light);"></i>
                                İşlem Tipi
                            </label>
                            <select id="transaction_type" name="transaction_type" class="form-select">
                                <option value="">Tüm İşlemler</option>
                                <?php foreach ($transactionTypes as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $transactionType == $type ? 'selected' : ''; ?>>
                                    <?php echo $type === 'bet' ? 'Bahis' : ($type === 'win' ? 'Kazanç' : htmlspecialchars($type)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="provider">
                                <i class="bi bi-server" style="color: var(--primary-blue-light);"></i>
                                Sağlayıcı
                            </label>
                            <select id="provider" name="provider" class="form-select">
                                <option value="">Tüm Sağlayıcılar</option>
                                <?php foreach ($providers as $providerName): ?>
                                <option value="<?php echo $providerName; ?>" <?php echo $provider == $providerName ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($providerName)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="game_id">
                                <i class="bi bi-controller" style="color: var(--primary-blue-light);"></i>
                                Oyun ID
                            </label>
                            <select id="game_id" name="game_id" class="form-select">
                                <option value="">Tüm Oyunlar</option>
                                <?php foreach ($games as $gameCode): ?>
                                <option value="<?php echo $gameCode; ?>" <?php echo $gameId == $gameCode ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($gameCode); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i>
                                    Filtrele
                                </button>
                                <a href="transactions_history.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    Sıfırla
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="transactions-card">
        <div class="transactions-header">
            <div class="transactions-title">
                <i class="bi bi-table"></i>
                İşlem Listesi
            </div>
            <div class="text-muted">
                Toplam: <?php echo number_format($totalRecords); ?> işlem
            </div>
        </div>
        <div class="transactions-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-person"></i> Kullanıcı</th>
                            <th><i class="bi bi-arrow-left-right"></i> İşlem Tipi</th>
                            <th><i class="bi bi-receipt"></i> İşlem No</th>
                            <th><i class="bi bi-tag"></i> Bahis No</th>
                            <th><i class="bi bi-currency-exchange"></i> Tutar</th>
                            <th><i class="bi bi-controller"></i> Oyun</th>
                            <th><i class="bi bi-server"></i> Sağlayıcı</th>
                            <th><i class="bi bi-collection"></i> Kategori</th>
                            <th><i class="bi bi-calendar"></i> Tarih</th>
                            <th><i class="bi bi-check-circle"></i> Statü</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">İşlem bulunamadı.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><strong><?php echo $transaction['id']; ?></strong></td>
                                <td>
                                    <a href="site_users.php?search=<?php echo urlencode($transaction['username']); ?>" class="text-primary fw-bold">
                                        <?php echo htmlspecialchars($transaction['username']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $transaction['type']; ?>">
                                        <?php 
                                        echo $transaction['type'] === 'bet' ? 'Bahis' : 
                                            ($transaction['type'] === 'win' ? 'Kazanç' : 
                                            htmlspecialchars($transaction['type'])); 
                                        ?>
                                    </span>
                                </td>
                                <td><code><?php echo htmlspecialchars($transaction['transaction_id'] ?? '-'); ?></code></td>
                                <td>
                                    <span class="round-id">
                                        <?php echo htmlspecialchars($transaction['round_id'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($transaction['type'] === 'bet'): ?>
                                        <span class="text-danger fw-bold">
                                            -<?php echo number_format($transaction['amount'], 2); ?> ₺
                                        </span>
                                    <?php elseif ($transaction['type'] === 'win'): ?>
                                        <span class="text-success fw-bold">
                                            +<?php echo number_format($transaction['type_money'], 2); ?> ₺
                                        </span>
                                    <?php else: ?>
                                        <span class="fw-bold">
                                            <?php echo number_format($transaction['amount'] ?? $transaction['type_money'] ?? 0, 2); ?> ₺
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['game_name'] ?? $transaction['game'] ?? '-'); ?></td>
                                <td>
                                    <?php if (!empty($transaction['provider_game'])): ?>
                                        <span class="provider-badge">
                                            <?php echo htmlspecialchars(ucfirst($transaction['provider_game'])); ?>
                                        </span>
                                    <?php elseif (!empty($transaction['providers'])): ?>
                                        <span class="provider-badge">
                                            <?php echo htmlspecialchars(ucfirst($transaction['providers'])); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($transaction['game_type'] ?? '-'); ?></td>
                                <td><?php echo date('d.m.Y H:i:s', strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <?php if ($transaction['type'] === 'win'): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>" class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
                
                <?php
                $maxPages = 7;
                $startPage = max(1, min($page - floor($maxPages / 2), $totalPages - $maxPages + 1));
                $endPage = min($startPage + $maxPages - 1, $totalPages);
                
                if ($startPage > 1) {
                    echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'">1</a>';
                    if ($startPage > 2) {
                        echo '<a class="disabled">...</a>';
                    }
                }
                
                for ($i = $startPage; $i <= $endPage; $i++) {
                    $activeClass = $i == $page ? 'active' : '';
                    echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => $i])).'" class="'.$activeClass.'">'.$i.'</a>';
                }
                
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<a class="disabled">...</a>';
                    }
                    echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => $totalPages])).'">'.$totalPages.'</a>';
                }
                ?>
                
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>" class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize filter form validation
    const filterForm = document.getElementById('filterForm');
    
    filterForm.addEventListener('submit', function(e) {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Başlangıç tarihi, bitiş tarihinden sonra olamaz!',
                customClass: {
                    popup: 'swal2-popup-custom'
                }
            });
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
