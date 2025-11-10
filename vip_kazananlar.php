<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Kazananlar";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check permissions
$isAdmin = ($_SESSION['role_id'] == 1);
if (!$isAdmin) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

// Process form submissions
$error = "";
$success = "";

// Process game type addition
if (isset($_POST['action']) && $_POST['action'] == 'add_game') {
    $gameName = trim($_POST['game_name']);
    if (!empty($gameName)) {
        // Check if game already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM vip_oyun_tipleri WHERE oyun_adi = ?");
        $stmt->execute([$gameName]);
        if ($stmt->fetchColumn() == 0) {
            // Add new game
            $stmt = $db->prepare("INSERT INTO vip_oyun_tipleri (oyun_adi) VALUES (?)");
            $stmt->execute([$gameName]);
            $success = "Oyun başarıyla eklendi.";
        } else {
            $error = "Bu oyun zaten mevcut.";
        }
    } else {
        $error = "Oyun adı boş olamaz.";
    }
}

// Process game type deletion
if (isset($_GET['delete_game']) && !empty($_GET['delete_game'])) {
    $gameId = (int)$_GET['delete_game'];
    $stmt = $db->prepare("DELETE FROM vip_oyun_tipleri WHERE id = ?");
    $stmt->execute([$gameId]);
    $success = "Oyun başarıyla silindi.";
}

// Process winner addition
if (isset($_POST['action']) && $_POST['action'] == 'add_winner') {
    $username = trim($_POST['username']);
    $amount = floatval($_POST['kazanilan_miktar']);
    $betAmount = floatval($_POST['bahis_tutari']);
    $gameType = trim($_POST['kazanilan_tip']);
    $vipLevel = trim($_POST['vip_seviye']);

    if (!empty($username) && $amount > 0 && $betAmount > 0) {
        $stmt = $db->prepare("INSERT INTO vip_kazananlar (username, kazanilan_miktar, bahis_tutari, kazanilan_tip, vip_seviye) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $amount, $betAmount, $gameType, $vipLevel]);
        $success = "Kazanan başarıyla eklendi.";
    } else {
        $error = "Tüm alanları doldurun.";
    }
}

// Process winner deletion
if (isset($_GET['delete_winner']) && !empty($_GET['delete_winner'])) {
    $winnerId = (int)$_GET['delete_winner'];
    $stmt = $db->prepare("DELETE FROM vip_kazananlar WHERE id = ?");
    $stmt->execute([$winnerId]);
    $success = "Kazanan başarıyla silindi.";
}

// Process automatic winner generation
if (isset($_POST['action']) && $_POST['action'] == 'generate_winners') {
    $count = (int)$_POST['generate_count'];
    $minAmount = floatval($_POST['min_amount']);
    $maxAmount = floatval($_POST['max_amount']);
    $minBet = isset($_POST['min_bet']) ? floatval($_POST['min_bet']) : 100;
    $maxBet = isset($_POST['max_bet']) ? floatval($_POST['max_bet']) : 1000;
    $minMultiplier = isset($_POST['min_multiplier']) ? floatval($_POST['min_multiplier']) : 250.0;
    $maxMultiplier = isset($_POST['max_multiplier']) ? floatval($_POST['max_multiplier']) : 500.0;
    
    // Hardcoded providers
    $providers = ['Pragmatic', 'Evolution', 'Netent', 'Playtech', 'Microgaming', 'Hacksaw', 'Red Tiger', 'PG Soft', 'Wazdan', 'Spinomenal'];
    
    try {
        // Get all game types
        $stmt = $db->prepare("SELECT oyun_adi FROM vip_oyun_tipleri");
        $stmt->execute();
        $gameTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($gameTypes)) {
            // Add default game if no games exist
            $defaultGames = ['Sweet Bonanza', 'Gates of Olympus', 'Fruit Party'];
            $stmt = $db->prepare("INSERT INTO vip_oyun_tipleri (oyun_adi) VALUES (?)");
            foreach ($defaultGames as $game) {
                $stmt->execute([$game]);
            }
            
            // Fetch again
            $stmt = $db->prepare("SELECT oyun_adi FROM vip_oyun_tipleri");
            $stmt->execute();
            $gameTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Get random users or generate fake usernames if none found
        $stmt = $db->prepare("SELECT username FROM kullanicilar ORDER BY RAND() LIMIT 50");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($users)) {
            // Create fake usernames if no users found
            $users = [];
            $prefixes = ['player', 'gamer', 'winner', 'lucky', 'star', 'vip', 'gold', 'silver', 'diamond'];
            for ($i = 0; $i < $count; $i++) {
                $prefix = $prefixes[array_rand($prefixes)];
                $users[] = $prefix . rand(100, 9999);
            }
        }
        
        $insertCount = 0;
        $insertStmt = $db->prepare("INSERT INTO vip_kazananlar (username, kazanilan_miktar, bahis_tutari, kazanilan_tip, vip_seviye) VALUES (?, ?, ?, ?, ?)");
        
        // Take only the number of users requested
        $selectedUsers = array_slice($users, 0, $count);
        
        foreach ($selectedUsers as $user) {
            // Generate a random bet amount
            $betAmount = round(mt_rand($minBet * 100, $maxBet * 100) / 100, 2);
            
            // Generate a random multiplier between min and max
            $multiplier = round(mt_rand($minMultiplier * 100, $maxMultiplier * 100) / 100, 2);
            
            // For smaller bets, force higher multipliers to ensure big wins
            if ($betAmount < 200) {
                $multiplier = max($multiplier, 400); // Ensure at least 400x for small bets
            }
            
            // Calculate win amount as betAmount * multiplier
            $amount = round($betAmount * $multiplier, 2);
            
            // For some entries, just use fixed large amounts like the examples
            if (mt_rand(1, 3) == 1) { // 1/3 chance for a fixed large amount
                $fixedAmounts = [25472.00, 34931.20, 44386.42, 50000.00];
                $amount = $fixedAmounts[array_rand($fixedAmounts)];
            }
            
            // Ensure amount is within specified min and max bounds
            if ($amount < 20000) $amount = 20000 + mt_rand(0, 10000); // Minimum 20,000₺
            if ($amount > $maxAmount) $amount = $maxAmount;
            
            $gameType = $gameTypes[array_rand($gameTypes)];
            $provider = $providers[array_rand($providers)];
            
            $insertStmt->execute([$user, $amount, $betAmount, $gameType, $provider]);
            $insertCount++;
        }
        
        $success = "$insertCount otomatik kazanan başarıyla oluşturuldu.";
    } catch (PDOException $e) {
        $error = "Otomatik kazanan oluşturulurken hata: " . $e->getMessage();
    }
}

// Get statistics for dashboard
try {
    // Total winners
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM vip_kazananlar");
    $stmt->execute();
    $totalWinners = $stmt->fetch()['total'];
    
    // Total winnings
    $stmt = $db->prepare("SELECT SUM(kazanilan_miktar) as total FROM vip_kazananlar");
    $stmt->execute();
    $totalWinnings = $stmt->fetch()['total'] ?? 0;
    
    // Total bets
    $stmt = $db->prepare("SELECT SUM(bahis_tutari) as total FROM vip_kazananlar");
    $stmt->execute();
    $totalBets = $stmt->fetch()['total'] ?? 0;
    
    // Average win amount
    $stmt = $db->prepare("SELECT AVG(kazanilan_miktar) as avg_amount FROM vip_kazananlar");
    $stmt->execute();
    $avgWinAmount = $stmt->fetch()['avg_amount'] ?? 0;
    
    // Maximum win amount
    $stmt = $db->prepare("SELECT MAX(kazanilan_miktar) as max_amount FROM vip_kazananlar");
    $stmt->execute();
    $maxWinAmount = $stmt->fetch()['max_amount'] ?? 0;
    
    // Today's winners
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM vip_kazananlar WHERE DATE(tarih) = CURDATE()");
    $stmt->execute();
    $todayWinners = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalWinners = $totalWinnings = $totalBets = $avgWinAmount = $maxWinAmount = $todayWinners = 0;
}

// Fetch game types
$stmt = $db->prepare("
    SELECT * FROM vip_oyun_tipleri 
    ORDER BY oyun_adi ASC
");
$stmt->execute();
$gameTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Use hardcoded VIP levels
$providers = ['Pragmatic', 'Evolution', 'Netent', 'Playtech', 'Microgaming', 'Hacksaw', 'Red Tiger', 'PG Soft', 'Wazdan', 'Spinomenal'];

// Fetch all winners with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT COUNT(*) FROM vip_kazananlar
");
$stmt->execute();
$totalWinnersCount = $stmt->fetchColumn();
$totalPages = ceil($totalWinnersCount / $perPage);

$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'tarih';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort field to prevent SQL injection
$allowedSortFields = ['id', 'username', 'kazanilan_miktar', 'bahis_tutari', 'kazanilan_tip', 'vip_seviye', 'tarih'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'tarih';
}

// Validate sort order
if ($sortOrder != 'ASC' && $sortOrder != 'DESC') {
    $sortOrder = 'DESC';
}

$stmt = $db->prepare("
    SELECT * FROM vip_kazananlar 
    ORDER BY $sortField $sortOrder
    LIMIT :offset, :perPage
");
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    .stat-card.winnings::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.bets::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.avg-win::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.max-win::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.today::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .vip-winners-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .vip-winners-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .vip-winners-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .vip-winners-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .vip-winners-body {
        padding: 2rem;
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

    .amount {
        font-weight: 700;
        color: var(--success-green);
    }

    .amount-negative {
        font-weight: 700;
        color: var(--danger-red);
    }

    .vip-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-blue);
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: var(--transition);
        border: none;
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

    .btn-success {
        background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%);
        color: var(--white);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
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

    /* Modal styles */
    .modal-content {
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        background: var(--gradient-primary);
        color: var(--white);
        border-bottom: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .form-label {
        color: var(--dark-gray);
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-control {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: var(--transition);
        background: var(--white);
    }

    .form-control:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .input-group-text {
        background: #f8fafc;
        border: 2px solid #e5e7eb;
        border-left: none;
        color: var(--light-gray);
        font-weight: 600;
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

        .vip-winners-body {
            padding: 1rem;
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
            <i class="bi bi-trophy"></i>
            VIP Kazananlar
        </div>
        <div class="dashboard-subtitle">
            VIP kazananları yönetin ve takip edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="bi bi-trophy"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalWinners); ?></div>
            <div class="stat-label">Toplam Kazanan</div>
        </div>
        
        <div class="stat-card winnings">
            <div class="stat-icon">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalWinnings, 2); ?> ₺</div>
            <div class="stat-label">Toplam Kazanç</div>
        </div>
        
        <div class="stat-card bets">
            <div class="stat-icon">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBets, 2); ?> ₺</div>
            <div class="stat-label">Toplam Bahis</div>
        </div>
        
        <div class="stat-card avg-win">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($avgWinAmount, 2); ?> ₺</div>
            <div class="stat-label">Ortalama Kazanç</div>
        </div>
        
        <div class="stat-card max-win">
            <div class="stat-icon">
                <i class="bi bi-arrow-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($maxWinAmount, 2); ?> ₺</div>
            <div class="stat-label">En Yüksek Kazanç</div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayWinners); ?></div>
            <div class="stat-label">Bugünkü Kazanan</div>
        </div>
    </div>

    <!-- Game Types Management -->
    <div class="vip-winners-card">
        <div class="vip-winners-header">
            <div class="vip-winners-title">
                <i class="bi bi-controller"></i>
                Oyun Tipleri Yönetimi
            </div>
        </div>
        <div class="vip-winners-body">
            <form method="post" action="" class="mb-4">
                <input type="hidden" name="action" value="add_game">
                <div class="row">
                    <div class="col-md-8">
                        <label for="game_name" class="form-label">Yeni Oyun Adı</label>
                        <input type="text" class="form-control" id="game_name" name="game_name" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i>
                            Oyun Ekle
                        </button>
                    </div>
                </div>
            </form>

            <h6 class="mb-3">Mevcut Oyun Tipleri</h6>
            <div class="row">
                <?php foreach ($gameTypes as $game): ?>
                <div class="col-md-3 mb-2">
                    <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                        <span class="fw-bold"><?php echo htmlspecialchars($game['oyun_adi']); ?></span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteGame(<?php echo $game['id']; ?>)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add New Winner -->
    <div class="vip-winners-card">
        <div class="vip-winners-header">
            <div class="vip-winners-title">
                <i class="bi bi-plus-circle"></i>
                Yeni Kazanan Ekle
            </div>
        </div>
        <div class="vip-winners-body">
            <form method="post" action="">
                <input type="hidden" name="action" value="add_winner">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="bahis_tutari" class="form-label">Bahis Tutarı (₺)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="bahis_tutari" name="bahis_tutari" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="kazanilan_miktar" class="form-label">Kazanılan Miktar (₺)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="kazanilan_miktar" name="kazanilan_miktar" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="kazanilan_tip" class="form-label">Oyun Tipi</label>
                        <select class="form-select" id="kazanilan_tip" name="kazanilan_tip" required>
                            <?php foreach ($gameTypes as $game): ?>
                            <option value="<?php echo htmlspecialchars($game['oyun_adi']); ?>"><?php echo htmlspecialchars($game['oyun_adi']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="vip_seviye" class="form-label">Sağlayıcı</label>
                        <select class="form-select" id="vip_seviye" name="vip_seviye" required>
                            <?php foreach ($providers as $provider): ?>
                            <option value="<?php echo htmlspecialchars($provider); ?>"><?php echo htmlspecialchars($provider); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i>
                            Kazanan Ekle
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Auto Generate Winners -->
    <div class="vip-winners-card">
        <div class="vip-winners-header">
            <div class="vip-winners-title">
                <i class="bi bi-magic"></i>
                Otomatik Kazanan Oluştur
            </div>
        </div>
        <div class="vip-winners-body">
            <form method="post" action="">
                <input type="hidden" name="action" value="generate_winners">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="generate_count" class="form-label">Oluşturulacak Sayı</label>
                        <input type="number" min="1" max="50" class="form-control" id="generate_count" name="generate_count" value="10" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="min_bet" class="form-label">Minimum Bahis (₺)</label>
                        <input type="number" step="0.01" min="10" class="form-control" id="min_bet" name="min_bet" value="100" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="max_bet" class="form-label">Maksimum Bahis (₺)</label>
                        <input type="number" step="0.01" min="10" class="form-control" id="max_bet" name="max_bet" value="1000" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="min_multiplier" class="form-label">Minimum Çarpan</label>
                        <input type="number" step="0.1" min="1.1" class="form-control" id="min_multiplier" name="min_multiplier" value="250.0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="max_multiplier" class="form-label">Maksimum Çarpan</label>
                        <input type="number" step="0.1" min="1.5" class="form-control" id="max_multiplier" name="max_multiplier" value="500.0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="min_amount" class="form-label">Minimum Kazanç (₺)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="min_amount" name="min_amount" value="20000" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="max_amount" class="form-label">Maksimum Kazanç (₺)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="max_amount" name="max_amount" value="50000" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-magic"></i>
                            Otomatik Oluştur
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- VIP Winners List -->
    <div class="vip-winners-card">
        <div class="vip-winners-header">
            <div class="vip-winners-title">
                <i class="bi bi-table"></i>
                VIP Kazananlar Listesi
            </div>
        </div>
        <div class="vip-winners-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="winnersTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-person"></i> Kullanıcı Adı</th>
                            <th><i class="bi bi-cash-stack"></i> Bahis Tutarı</th>
                            <th><i class="bi bi-currency-exchange"></i> Kazanılan Miktar</th>
                            <th><i class="bi bi-controller"></i> Oyun Tipi</th>
                            <th><i class="bi bi-building"></i> Sağlayıcı</th>
                            <th><i class="bi bi-calendar"></i> Tarih</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($winners)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">Henüz kazanan yok.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($winners as $winner): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo $winner['id']; ?></span>
                                </td>
                                <td>
                                    <a href="site_users.php?action=view&search=<?php echo urlencode($winner['username']); ?>" class="text-primary">
                                        <?php echo htmlspecialchars($winner['username']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="amount-negative">
                                        <?php echo number_format($winner['bahis_tutari'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <span class="amount">
                                        <?php echo number_format($winner['kazanilan_miktar'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($winner['kazanilan_tip']); ?>
                                </td>
                                <td>
                                    <span class="vip-badge">
                                        <?php echo htmlspecialchars($winner['vip_seviye']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($winner['tarih'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteWinner(<?php echo $winner['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                        Sil
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable initialization
    $('#winnersTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "desc"]],
        "pageLength": 25,
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "columnDefs": [
            { "targets": -1, "orderable": false }
        ]
    });
});

function confirmDeleteGame(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu oyun silinecek! Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?delete_game=${id}`;
        }
    });
}

function confirmDeleteWinner(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu kazanan silinecek! Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?delete_winner=${id}`;
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 