<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Riskli Kullanıcılar";

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
        WHERE ap.role_id = ? AND (ap.menu_item = 'risky_users' OR ap.menu_item = 'risky_users_menu') AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Process filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$minWinAmount = isset($_GET['min_win_amount']) ? (float)$_GET['min_win_amount'] : 100000; // Default 100,000 TL
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'total_win';
$orderDir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'DESC';

// Initialize variables
$riskyUsers = [];
$error = '';
$stats = [];

try {
    // First, let's check what columns exist in the transactions table
    $checkColumnsStmt = $db->query("DESCRIBE transactions");
    $columns = $checkColumnsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Determine the correct date column name
    $dateColumn = 'created_at'; // Default
    if (in_array('date', $columns)) {
        $dateColumn = 'date';
    } elseif (in_array('tarih', $columns)) {
        $dateColumn = 'tarih';
    } elseif (in_array('created_at', $columns)) {
        $dateColumn = 'created_at';
    } else {
        // If no date column found, use a simple query without date filtering
        $dateColumn = null;
    }
    
    // Build the query based on available columns
    if ($dateColumn) {
        $query = "
            SELECT 
                k.id AS user_id,
                k.username,
                k.email,
                k.phone,
                k.is_banned,
                k.last_activity,
                COUNT(DISTINCT t.id) AS total_transactions,
                SUM(CASE WHEN t.type = 'bet' THEN t.amount ELSE 0 END) AS total_bet,
                SUM(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) AS total_win,
                (SUM(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) - SUM(CASE WHEN t.type = 'bet' THEN t.amount ELSE 0 END)) AS net_win,
                MAX(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) AS highest_win
            FROM 
                kullanicilar k
            JOIN 
                transactions t ON k.id = t.user_id
            WHERE 
                t.$dateColumn BETWEEN ? AND ?
            GROUP BY 
                k.id, k.username, k.email, k.phone, k.is_banned, k.last_activity
            HAVING 
                SUM(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) >= ?
            ORDER BY 
                " . (in_array($sortBy, ['total_bet', 'total_win', 'net_win', 'highest_win', 'total_transactions']) ? $sortBy : 'total_win') . " " . 
                ($orderDir === 'ASC' ? 'ASC' : 'DESC') . "
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59', $minWinAmount]);
    } else {
        // Fallback query without date filtering
        $query = "
            SELECT 
                k.id AS user_id,
                k.username,
                k.email,
                k.phone,
                k.is_banned,
                k.last_activity,
                COUNT(DISTINCT t.id) AS total_transactions,
                SUM(CASE WHEN t.type = 'bet' THEN t.amount ELSE 0 END) AS total_bet,
                SUM(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) AS total_win,
                (SUM(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) - SUM(CASE WHEN t.type = 'bet' THEN t.amount ELSE 0 END)) AS net_win,
                MAX(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) AS highest_win
            FROM 
                kullanicilar k
            JOIN 
                transactions t ON k.id = t.user_id
            GROUP BY 
                k.id, k.username, k.email, k.phone, k.is_banned, k.last_activity
            HAVING 
                SUM(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) >= ?
            ORDER BY 
                " . (in_array($sortBy, ['total_bet', 'total_win', 'net_win', 'highest_win', 'total_transactions']) ? $sortBy : 'total_win') . " " . 
                ($orderDir === 'ASC' ? 'ASC' : 'DESC') . "
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$minWinAmount]);
    }
    
    $riskyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    if (!empty($riskyUsers)) {
        $stats['total_users'] = count($riskyUsers);
        $stats['total_bet'] = array_sum(array_column($riskyUsers, 'total_bet'));
        $stats['total_win'] = array_sum(array_column($riskyUsers, 'total_win'));
        $stats['total_net_win'] = array_sum(array_column($riskyUsers, 'net_win'));
        $stats['highest_win'] = max(array_column($riskyUsers, 'highest_win'));
        $stats['banned_users'] = count(array_filter($riskyUsers, function($user) { return $user['is_banned']; }));
        $stats['active_users'] = $stats['total_users'] - $stats['banned_users'];
    } else {
        $stats = [
            'total_users' => 0,
            'total_bet' => 0,
            'total_win' => 0,
            'total_net_win' => 0,
            'highest_win' => 0,
            'banned_users' => 0,
            'active_users' => 0
        ];
    }
    
} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $riskyUsers = [];
    $stats = [
        'total_users' => 0,
        'total_bet' => 0,
        'total_win' => 0,
        'total_net_win' => 0,
        'highest_win' => 0,
        'banned_users' => 0,
        'active_users' => 0
    ];
}

// Start output buffering
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
    
    .stat-card.users::after {
        background: var(--primary-gradient);
    }
    
    .stat-card.bets::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.wins::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.net::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.highest::after {
        background: var(--primary-gradient);
    }

    .stat-card.banned::after {
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
    
    .stat-card.users .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.bets .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.wins .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.net .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.highest .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.banned .stat-icon {
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

    .filters-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .filters-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .filters-card-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .filters-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
    }
    
    .filters-card-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .filters-card-body {
        padding: 1.5rem;
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-weight: 500;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .form-control {
        background-color: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
        border-radius: var(--border-radius);
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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

    .btn-secondary {
        background: var(--secondary-gradient);
        color: var(--white);
    }

    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .users-table-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .users-table-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .users-table-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .users-table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
    }
    
    .users-table-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .users-table-body {
        padding: 1.5rem;
    }

    .users-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 1rem;
        font-size: 0.95rem;
    }

    .users-table th {
        padding: 1rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-align: left;
        border-bottom: 2px solid var(--card-border);
        background: var(--bg-secondary);
    }

    .users-table td {
        padding: 1rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--card-border);
    }

    .users-table tbody tr {
        transition: all 0.3s ease;
        cursor: default;
    }

    .users-table tbody tr:hover {
        background-color: var(--ultra-light-blue);
        transform: translateY(-1px);
    }

    .users-table tbody tr:last-child td {
        border-bottom: none;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-weight: 600;
        font-size: 1rem;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-active {
        background: var(--success-green);
        color: var(--white);
    }

    .status-banned {
        background: var(--error-red);
        color: var(--white);
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
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

    .alert {
        border-radius: var(--border-radius);
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
        animation: fadeIn 0.5s ease;
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

    .amount-negative {
        color: var(--error-red);
        font-weight: 600;
    }

    .amount-positive {
        color: var(--success-green);
        font-weight: 600;
    }

    .text-center {
        text-align: center;
    }

    .py-4 {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .text-muted {
        color: var(--text-secondary);
    }

    .animate-fade-in {
        animation: fadeIn 0.8s ease forwards;
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
        
        .filter-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Riskli Kullanıcılar
    </h1>
    <p class="dashboard-subtitle">Yüksek kazanç elde eden kullanıcıları takip edin ve risk yönetimi yapın.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger animate-fade-in">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card users">
        <div class="stat-icon">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
        <div class="stat-title">Riskli Kullanıcı</div>
    </div>
    
    <div class="stat-card bets">
        <div class="stat-icon">
            <i class="bi bi-arrow-down-circle-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($stats['total_bet'], 2); ?> ₺</div>
        <div class="stat-title">Toplam Bahis</div>
    </div>
    
    <div class="stat-card wins">
        <div class="stat-icon">
            <i class="bi bi-arrow-up-circle-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($stats['total_win'], 2); ?> ₺</div>
        <div class="stat-title">Toplam Kazanç</div>
    </div>
    
    <div class="stat-card net">
        <div class="stat-icon">
            <i class="bi bi-graph-up"></i>
        </div>
        <div class="stat-number"><?php echo number_format($stats['total_net_win'], 2); ?> ₺</div>
        <div class="stat-title">Net Kazanç</div>
    </div>
    
    <div class="stat-card highest">
        <div class="stat-icon">
            <i class="bi bi-trophy-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($stats['highest_win'], 2); ?> ₺</div>
        <div class="stat-title">En Yüksek Kazanç</div>
    </div>
</div>

<div class="filters-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="filters-card-header">
        <h5 class="filters-card-title">
            <i class="bi bi-funnel"></i>
            Filtreler
        </h5>
    </div>
    <div class="filters-card-body">
        <form method="GET" action="risky_users.php">
            <div class="filter-row">
                <div class="form-group">
                    <label class="form-label">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Bitiş Tarihi</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Minimum Kazanç (TL)</label>
                    <input type="number" name="min_win_amount" class="form-control" value="<?php echo htmlspecialchars($minWinAmount); ?>" step="1000">
                </div>
                <div class="form-group">
                    <label class="form-label">Sıralama</label>
                    <select name="sort_by" class="form-control">
                        <option value="total_win" <?php echo $sortBy === 'total_win' ? 'selected' : ''; ?>>Toplam Kazanç</option>
                        <option value="total_bet" <?php echo $sortBy === 'total_bet' ? 'selected' : ''; ?>>Toplam Bahis</option>
                        <option value="net_win" <?php echo $sortBy === 'net_win' ? 'selected' : ''; ?>>Net Kazanç</option>
                        <option value="highest_win" <?php echo $sortBy === 'highest_win' ? 'selected' : ''; ?>>En Yüksek Kazanç</option>
                        <option value="total_transactions" <?php echo $sortBy === 'total_transactions' ? 'selected' : ''; ?>>İşlem Sayısı</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sıralama Yönü</label>
                    <select name="order_dir" class="form-control">
                        <option value="DESC" <?php echo $orderDir === 'DESC' ? 'selected' : ''; ?>>Azalan</option>
                        <option value="ASC" <?php echo $orderDir === 'ASC' ? 'selected' : ''; ?>>Artan</option>
                    </select>
                </div>
                <div class="form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                        Filtrele
                    </button>
                    <a href="risky_users.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-arrow-clockwise"></i>
                        Sıfırla
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="users-table-card animate-fade-in" style="animation-delay: 0.3s">
    <div class="users-table-header">
        <h5 class="users-table-title">
            <i class="bi bi-people"></i>
            Riskli Kullanıcı Listesi
        </h5>
        <span class="text-muted">
            Toplam: <strong><?php echo number_format(count($riskyUsers)); ?></strong> kullanıcı
        </span>
    </div>
    <div class="users-table-body">
        <?php if (empty($riskyUsers)): ?>
            <div class="text-center py-4">
                <div class="text-muted">
                    <i class="bi bi-search fs-1 d-block mb-2"></i>
                    Belirtilen kriterlere uygun riskli kullanıcı bulunamadı.
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>İletişim</th>
                            <th>Toplam İşlem</th>
                            <th>Toplam Bahis</th>
                            <th>Toplam Kazanç</th>
                            <th>Net Kazanç</th>
                            <th>En Yüksek Kazanç</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riskyUsers as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(mb_substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <small class="text-muted">ID: <?php echo $user['user_id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="amount-positive"><?php echo number_format($user['total_transactions']); ?></span>
                            </td>
                            <td>
                                <span class="amount-negative"><?php echo number_format($user['total_bet'], 2); ?> ₺</span>
                            </td>
                            <td>
                                <span class="amount-positive"><?php echo number_format($user['total_win'], 2); ?> ₺</span>
                            </td>
                            <td>
                                <span class="<?php echo $user['net_win'] >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo number_format($user['net_win'], 2); ?> ₺
                                </span>
                            </td>
                            <td>
                                <span class="amount-positive"><?php echo number_format($user['highest_win'], 2); ?> ₺</span>
                            </td>
                            <td>
                                <?php if ($user['is_banned']): ?>
                                    <span class="status-badge status-banned">Banlı</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="user_details.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-eye"></i>
                                        Detay
                                    </a>
                                    <a href="transactions_history.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="bi bi-clock-history"></i>
                                        İşlemler
                                    </a>
                                    <?php if (!$user['is_banned']): ?>
                                        <a href="site_users.php?action=ban&id=<?php echo $user['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu kullanıcıyı banlamak istediğinizden emin misiniz?')">
                                            <i class="bi bi-shield-x"></i>
                                            Banla
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 