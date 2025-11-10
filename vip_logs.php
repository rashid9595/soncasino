<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Kullanıcı Logları";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'vip_logs' AND ap.can_view = 1
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
    // Store transactions stats
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM kullanici_vip_magaza");
    $stmt->execute();
    $totalStoreTransactions = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT SUM(bonus_tutari) as total FROM kullanici_vip_magaza");
    $stmt->execute();
    $totalStoreAmount = $stmt->fetch()['total'] ?? 0;
    
    // Rakeback stats
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM rakeback_islemler");
    $stmt->execute();
    $totalRakebackTransactions = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT SUM(miktar) as total FROM rakeback_islemler WHERE durum = 'success'");
    $stmt->execute();
    $totalRakebackAmount = $stmt->fetch()['total'] ?? 0;
    
    // Level up bonuses stats
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_levelup_bonuses");
    $stmt->execute();
    $totalLevelUpBonuses = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT SUM(bonus_amount) as total FROM user_levelup_bonuses WHERE status = 'claimed'");
    $stmt->execute();
    $totalLevelUpAmount = $stmt->fetch()['total'] ?? 0;
    
    // VIP winners stats
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM vip_kazananlar");
    $stmt->execute();
    $totalVipWinners = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT SUM(kazanilan_miktar) as total FROM vip_kazananlar");
    $stmt->execute();
    $totalWinnersAmount = $stmt->fetch()['total'] ?? 0;
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalStoreTransactions = $totalStoreAmount = $totalRakebackTransactions = $totalRakebackAmount = 
    $totalLevelUpBonuses = $totalLevelUpAmount = $totalVipWinners = $totalWinnersAmount = 0;
}

// Get VIP store transactions
$stmt = $db->prepare("
    SELECT * FROM kullanici_vip_magaza 
    ORDER BY islem_tarihi DESC 
    LIMIT 1000
");
$stmt->execute();
$storeTransactions = $stmt->fetchAll();

// Get rakeback history
$stmt = $db->prepare("
    SELECT * FROM rakeback_islemler 
    ORDER BY islem_tarihi DESC 
    LIMIT 1000
");
$stmt->execute();
$rakebackHistory = $stmt->fetchAll();

// Get level up bonus logs
$stmt = $db->prepare("
    SELECT * FROM user_levelup_bonuses 
    ORDER BY claimed_at DESC 
    LIMIT 1000
");
$stmt->execute();
$levelUpBonuses = $stmt->fetchAll();

// Get VIP winners
$stmt = $db->prepare("
    SELECT * FROM vip_kazananlar 
    ORDER BY tarih DESC 
    LIMIT 1000
");
$stmt->execute();
$vipWinners = $stmt->fetchAll();

// Get VIP cash bonus usage
$stmt = $db->prepare("
    SELECT * FROM vip_nakit_bonus 
    ORDER BY last_used DESC 
    LIMIT 1000
");
$stmt->execute();
$cashBonusUsage = $stmt->fetchAll();

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

    .stat-card.store::after { background: var(--gradient-primary); }
    .stat-card.rakeback::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.levelup::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.winners::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }

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

    .vip-logs-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .vip-logs-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .vip-logs-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .vip-logs-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .vip-logs-body {
        padding: 2rem;
    }

    .nav-tabs {
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 2rem;
    }

    .nav-tabs .nav-link {
        color: var(--light-gray);
        border: none;
        border-bottom: 3px solid transparent;
        padding: 1rem 1.5rem;
        font-weight: 600;
        transition: var(--transition);
        border-radius: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-tabs .nav-link:hover {
        color: var(--primary-blue);
        background: rgba(59, 130, 246, 0.05);
        border-bottom-color: var(--primary-blue-light);
    }

    .nav-tabs .nav-link.active {
        color: var(--primary-blue);
        background: rgba(59, 130, 246, 0.1);
        border-bottom-color: var(--primary-blue);
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

    .status-badge.success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
    }

    .status-badge.pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-orange);
    }

    .status-badge.failed {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-red);
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

        .vip-logs-body {
            padding: 1rem;
        }

        .nav-tabs .nav-link {
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
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
            <i class="bi bi-journal-text"></i>
            VIP Kullanıcı Logları
        </div>
        <div class="dashboard-subtitle">
            VIP kullanıcı aktivitelerini ve işlemlerini detaylı olarak görüntüleyin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card store">
            <div class="stat-icon">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalStoreAmount, 2); ?> ₺</div>
            <div class="stat-label">Mağaza Tutarı</div>
        </div>
        
        <div class="stat-card rakeback">
            <div class="stat-icon">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalRakebackTransactions); ?></div>
            <div class="stat-label">Rakeback İşlemi</div>
        </div>
        
        <div class="stat-card rakeback">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalRakebackAmount, 2); ?> ₺</div>
            <div class="stat-label">Rakeback Tutarı</div>
        </div>
        
        <div class="stat-card levelup">
            <div class="stat-icon">
                <i class="bi bi-trophy"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalLevelUpBonuses); ?></div>
            <div class="stat-label">Seviye Bonusu</div>
        </div>
        
        <div class="stat-card winners">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalVipWinners); ?></div>
            <div class="stat-label">VIP Kazanan</div>
        </div>
    </div>

    <!-- VIP Logs Card -->
    <div class="vip-logs-card">
        <div class="vip-logs-header">
            <div class="vip-logs-title">
                <i class="bi bi-table"></i>
                VIP Kullanıcı Logları
            </div>
        </div>
        <div class="vip-logs-body">
            <ul class="nav nav-tabs" id="vipLogTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="store-tab" data-bs-toggle="tab" href="#store" role="tab">
                        <i class="bi bi-shop"></i>
                        Mağaza İşlemleri
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="rakeback-tab" data-bs-toggle="tab" href="#rakeback" role="tab">
                        <i class="bi bi-cash-coin"></i>
                        Rakeback Geçmişi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="levelup-tab" data-bs-toggle="tab" href="#levelup" role="tab">
                        <i class="bi bi-trophy"></i>
                        Seviye Bonusları
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="winners-tab" data-bs-toggle="tab" href="#winners" role="tab">
                        <i class="bi bi-star"></i>
                        VIP Kazananlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="cashbonus-tab" data-bs-toggle="tab" href="#cashbonus" role="tab">
                        <i class="bi bi-credit-card"></i>
                        Nakit Bonus Kullanımı
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="vipLogTabContent">
                <!-- Store Transactions -->
                <div class="tab-pane fade show active" id="store" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="storeTable">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash"></i> ID</th>
                                    <th><i class="bi bi-person"></i> Kullanıcı</th>
                                    <th><i class="bi bi-tag"></i> Bonus ID</th>
                                    <th><i class="bi bi-currency-exchange"></i> Bonus Tutarı</th>
                                    <th><i class="bi bi-coin"></i> Harcanan Puan</th>
                                    <th><i class="bi bi-calendar"></i> İşlem Tarihi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($storeTransactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted"></i>
                                        <span class="text-muted">Mağaza işlem geçmişi bulunamadı.</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($storeTransactions as $transaction): ?>
                                    <tr>
                                        <td><code><?php echo $transaction['id']; ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($transaction['username']); ?></strong></td>
                                        <td><?php echo $transaction['bonus_id']; ?></td>
                                        <td><span class="fw-bold text-success"><?php echo number_format($transaction['bonus_tutari'], 2); ?> ₺</span></td>
                                        <td><span class="fw-bold text-danger"><?php echo number_format($transaction['harcanan_puan'], 2); ?></span></td>
                                        <td><small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($transaction['islem_tarihi'])); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Rakeback History -->
                <div class="tab-pane fade" id="rakeback" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="rakebackTable">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash"></i> ID</th>
                                    <th><i class="bi bi-person"></i> Kullanıcı</th>
                                    <th><i class="bi bi-currency-exchange"></i> Miktar</th>
                                    <th><i class="bi bi-calendar"></i> İşlem Tarihi</th>
                                    <th><i class="bi bi-info-circle"></i> Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rakebackHistory)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted"></i>
                                        <span class="text-muted">Rakeback geçmişi bulunamadı.</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($rakebackHistory as $rakeback): ?>
                                    <tr>
                                        <td><code><?php echo $rakeback['id']; ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($rakeback['username']); ?></strong></td>
                                        <td><span class="fw-bold text-success"><?php echo number_format($rakeback['miktar'], 2); ?> ₺</span></td>
                                        <td><small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($rakeback['islem_tarihi'])); ?></small></td>
                                        <td>
                                            <span class="status-badge <?php echo $rakeback['durum']; ?>">
                                                <?php echo $rakeback['durum'] === 'success' ? 'BAŞARILI' : 'BAŞARISIZ'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Level Up Bonuses -->
                <div class="tab-pane fade" id="levelup" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="levelupTable">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash"></i> ID</th>
                                    <th><i class="bi bi-person"></i> Kullanıcı</th>
                                    <th><i class="bi bi-arrow-down"></i> Önceki Seviye</th>
                                    <th><i class="bi bi-arrow-up"></i> Yeni Seviye</th>
                                    <th><i class="bi bi-currency-exchange"></i> Bonus Tutarı</th>
                                    <th><i class="bi bi-calendar"></i> Talep Tarihi</th>
                                    <th><i class="bi bi-info-circle"></i> Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($levelUpBonuses)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted"></i>
                                        <span class="text-muted">Seviye bonus geçmişi bulunamadı.</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($levelUpBonuses as $bonus): ?>
                                    <tr>
                                        <td><code><?php echo $bonus['id']; ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($bonus['username']); ?></strong></td>
                                        <td><span class="text-muted"><?php echo htmlspecialchars($bonus['from_level']); ?></span></td>
                                        <td><span class="fw-bold text-success"><?php echo htmlspecialchars($bonus['to_level']); ?></span></td>
                                        <td><span class="fw-bold text-success"><?php echo number_format($bonus['bonus_amount'], 2); ?> ₺</span></td>
                                        <td><small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($bonus['claimed_at'])); ?></small></td>
                                        <td>
                                            <span class="status-badge <?php echo $bonus['status'] === 'claimed' ? 'success' : 'pending'; ?>">
                                                <?php echo $bonus['status'] === 'claimed' ? 'ALINDI' : 'BEKLEMEDE'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- VIP Winners -->
                <div class="tab-pane fade" id="winners" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="winnersTable">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash"></i> ID</th>
                                    <th><i class="bi bi-person"></i> Kullanıcı</th>
                                    <th><i class="bi bi-currency-exchange"></i> Kazanılan Miktar</th>
                                    <th><i class="bi bi-tag"></i> Kazanılan Tip</th>
                                    <th><i class="bi bi-crown"></i> VIP Seviye</th>
                                    <th><i class="bi bi-calendar"></i> Tarih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($vipWinners)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted"></i>
                                        <span class="text-muted">VIP kazanan geçmişi bulunamadı.</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($vipWinners as $winner): ?>
                                    <tr>
                                        <td><code><?php echo $winner['id']; ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($winner['username']); ?></strong></td>
                                        <td><span class="fw-bold text-success"><?php echo number_format($winner['kazanilan_miktar'], 2); ?> ₺</span></td>
                                        <td><?php echo htmlspecialchars($winner['kazanilan_tip']); ?></td>
                                        <td><span class="text-info"><?php echo htmlspecialchars($winner['vip_seviye']); ?></span></td>
                                        <td><small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($winner['tarih'])); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cash Bonus Usage -->
                <div class="tab-pane fade" id="cashbonus" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="cashbonusTable">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash"></i> ID</th>
                                    <th><i class="bi bi-person"></i> Kullanıcı</th>
                                    <th><i class="bi bi-crown"></i> VIP Seviye</th>
                                    <th><i class="bi bi-currency-exchange"></i> Bonus Tutarı</th>
                                    <th><i class="bi bi-repeat"></i> Kullanım Sayısı</th>
                                    <th><i class="bi bi-calendar"></i> Son Kullanım</th>
                                    <th><i class="bi bi-calendar-week"></i> Hafta Başlangıcı</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cashBonusUsage)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted"></i>
                                        <span class="text-muted">Nakit bonus kullanım geçmişi bulunamadı.</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($cashBonusUsage as $bonus): ?>
                                    <tr>
                                        <td><code><?php echo $bonus['id']; ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($bonus['username']); ?></strong></td>
                                        <td><span class="text-info"><?php echo htmlspecialchars($bonus['vip_level']); ?></span></td>
                                        <td><span class="fw-bold text-success"><?php echo number_format($bonus['bonus_amount'], 2); ?> ₺</span></td>
                                        <td><span class="fw-bold"><?php echo $bonus['use_count']; ?></span></td>
                                        <td><small class="text-muted"><?php echo $bonus['last_used'] ? date('d.m.Y H:i', strtotime($bonus['last_used'])) : '-'; ?></small></td>
                                        <td><small class="text-muted"><?php echo $bonus['week_start'] ? date('d.m.Y H:i', strtotime($bonus['week_start'])) : '-'; ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize all DataTables
    const tables = {
        'storeTable': [5, "desc"],      // Sort by transaction date
        'rakebackTable': [3, "desc"],   // Sort by transaction date
        'levelupTable': [5, "desc"],    // Sort by claimed_at
        'winnersTable': [5, "desc"],    // Sort by date
        'cashbonusTable': [5, "desc"]   // Sort by last_used
    };

    Object.entries(tables).forEach(([tableId, defaultSort]) => {
        $(`#${tableId}`).DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
            },
            "order": [defaultSort],
            "pageLength": 25,
            "responsive": true,
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                   '<"row"<"col-sm-12"tr>>' +
                   '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]]
        });
    });

    // Handle tab changes
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        // Adjust DataTables columns when showing a tab
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
