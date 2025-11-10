<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Seviye Atlama Bonus Logları";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'levelup_history' AND ap.can_view = 1
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
    // Total level up bonuses
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_levelup_bonuses");
    $stmt->execute();
    $totalBonuses = $stmt->fetch()['total'];
    
    // Total bonus amount
    $stmt = $db->prepare("SELECT SUM(bonus_amount) as total FROM user_levelup_bonuses");
    $stmt->execute();
    $totalAmount = $stmt->fetch()['total'] ?? 0;
    
    // Claimed bonuses
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_levelup_bonuses WHERE status = 'claimed'");
    $stmt->execute();
    $claimedBonuses = $stmt->fetch()['total'];
    
    // Pending bonuses
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_levelup_bonuses WHERE status = 'pending'");
    $stmt->execute();
    $pendingBonuses = $stmt->fetch()['total'];
    
    // Today's bonuses
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM user_levelup_bonuses WHERE DATE(claimed_at) = CURDATE()");
    $stmt->execute();
    $todayBonuses = $stmt->fetch()['total'];
    
    // Average bonus amount
    $avgBonusAmount = $totalBonuses > 0 ? $totalAmount / $totalBonuses : 0;
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalBonuses = $totalAmount = $claimedBonuses = $pendingBonuses = $todayBonuses = $avgBonusAmount = 0;
}

// Get all level up bonuses with user VIP level
$stmt = $db->prepare("
    SELECT ulb.*, kv.vip_level 
    FROM user_levelup_bonuses ulb
    LEFT JOIN kullanici_vip kv ON ulb.username = kv.username
    ORDER BY ulb.claimed_at DESC
");
$stmt->execute();
$bonuses = $stmt->fetchAll();

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
    .stat-card.amount::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.claimed::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.pending::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.today::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.average::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .levelup-history-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .levelup-history-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .levelup-history-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .levelup-history-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .levelup-history-body {
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

    .vip-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .vip-badge.standart {
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-blue-light);
    }

    .vip-badge.bronz {
        background: rgba(205, 127, 50, 0.1);
        color: #cd7f32;
    }

    .vip-badge.gumus {
        background: rgba(192, 192, 192, 0.1);
        color: #c0c0c0;
    }

    .vip-badge.altin {
        background: rgba(255, 215, 0, 0.1);
        color: #ffd700;
    }

    .vip-badge.platin {
        background: rgba(229, 228, 226, 0.1);
        color: #e5e4e2;
    }

    .vip-badge.elmas {
        background: rgba(185, 242, 255, 0.1);
        color: #b9f2ff;
    }

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.claimed {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
    }

    .status-badge.pending {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-orange);
    }

    .level-change {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .level-arrow {
        color: var(--primary-blue-light);
        font-size: 1.2rem;
        margin: 0 0.5rem;
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

        .levelup-history-body {
            padding: 1rem;
        }

        .table-responsive {
            font-size: 0.85rem;
        }

        .table th, .table td {
            padding: 0.75rem 0.5rem;
        }

        .level-change {
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
        }

        .level-arrow {
            transform: rotate(90deg);
        }
    }
</style>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="greeting">
            <i class="bi bi-trophy"></i>
            Seviye Atlama Bonus Logları
        </div>
        <div class="dashboard-subtitle">
            VIP seviye atlama bonuslarını görüntüleyin ve takip edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="bi bi-list-ul"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBonuses); ?></div>
            <div class="stat-label">Toplam Bonus</div>
        </div>
        
        <div class="stat-card amount">
            <div class="stat-icon">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalAmount, 2); ?> ₺</div>
            <div class="stat-label">Toplam Tutar</div>
        </div>
        
        <div class="stat-card claimed">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($claimedBonuses); ?></div>
            <div class="stat-label">Alınan Bonus</div>
        </div>
        
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-value"><?php echo number_format($pendingBonuses); ?></div>
            <div class="stat-label">Bekleyen Bonus</div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayBonuses); ?></div>
            <div class="stat-label">Bugünkü Bonus</div>
        </div>
    </div>

    <!-- Level Up History Table -->
    <div class="levelup-history-card">
        <div class="levelup-history-header">
            <div class="levelup-history-title">
                <i class="bi bi-table"></i>
                Seviye Atlama Bonus Geçmişi
            </div>
        </div>
        <div class="levelup-history-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="levelupHistoryTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-person"></i> Kullanıcı</th>
                            <th><i class="bi bi-arrow-right"></i> Seviye Değişimi</th>
                            <th><i class="bi bi-currency-exchange"></i> Bonus Tutarı</th>
                            <th><i class="bi bi-calendar"></i> Talep Tarihi</th>
                            <th><i class="bi bi-info-circle"></i> Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bonuses)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">Seviye atlama bonus geçmişi bulunamadı.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($bonuses as $bonus): ?>
                            <tr>
                                <td>
                                    <code><?php echo $bonus['id']; ?></code>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($bonus['username']); ?></strong>
                                </td>
                                <td>
                                    <div class="level-change">
                                        <span class="vip-badge <?php echo strtolower($bonus['from_level']); ?>">
                                            <?php echo htmlspecialchars($bonus['from_level']); ?>
                                        </span>
                                        <i class="bi bi-arrow-right level-arrow"></i>
                                        <span class="vip-badge <?php echo strtolower($bonus['to_level']); ?>">
                                            <?php echo htmlspecialchars($bonus['to_level']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">
                                        <?php echo number_format($bonus['bonus_amount'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($bonus['claimed_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $bonus['status']; ?>">
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
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable initialization
    $('#levelupHistoryTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[4, "desc"]], // Sort by claimed_at date descending
        "pageLength": 25,
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]]
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
