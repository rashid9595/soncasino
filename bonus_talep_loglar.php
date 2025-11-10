<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Bonus Talep Logları";

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

// Get statistics for dashboard
try {
    // Total bonus usage
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bonus_kullanim");
    $stmt->execute();
    $totalBonusUsage = $stmt->fetch()['total'];
    
    // Total rejected requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bonus_talep_red");
    $stmt->execute();
    $totalRejectedRequests = $stmt->fetch()['total'];
    
    // Today's bonus usage
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bonus_kullanim WHERE DATE(tarih) = CURDATE()");
    $stmt->execute();
    $todayBonusUsage = $stmt->fetch()['total'];
    
    // Today's rejected requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bonus_talep_red WHERE DATE(tarih) = CURDATE()");
    $stmt->execute();
    $todayRejectedRequests = $stmt->fetch()['total'];
    
    // Total bonus amount used
    $stmt = $db->prepare("SELECT SUM(miktar) as total FROM bonus_kullanim WHERE durum = 1");
    $stmt->execute();
    $totalBonusAmount = $stmt->fetch()['total'] ?? 0;
    
    // Average bonus amount
    $stmt = $db->prepare("SELECT AVG(miktar) as avg_amount FROM bonus_kullanim WHERE durum = 1");
    $stmt->execute();
    $avgBonusAmount = $stmt->fetch()['avg_amount'] ?? 0;
    
} catch (PDOException $e) {
    $totalBonusUsage = $totalRejectedRequests = $todayBonusUsage = $todayRejectedRequests = $totalBonusAmount = $avgBonusAmount = 0;
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'kullanim';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get bonus logs
if ($tab === 'red') {
    // Query for rejected bonus requests
    $countSql = "SELECT COUNT(*) FROM bonus_talep_red";
    $sql = "
        SELECT btr.*, k.username, b.bonus_adi
        FROM bonus_talep_red btr
        LEFT JOIN kullanicilar k ON btr.user_id = k.id
        LEFT JOIN bonuslar b ON btr.bonus_id = b.id
    ";
    
    // Add search condition if search is provided
    if (!empty($search)) {
        $countSql .= " btr JOIN kullanicilar k ON btr.user_id = k.id WHERE k.username LIKE ?";
        $sql .= " WHERE k.username LIKE ?";
        $searchParam = "%$search%";
    }
    
    // Add ordering and limit
    $sql .= " ORDER BY btr.tarih DESC LIMIT $limit OFFSET $offset";
    
    // Prepare and execute count query
    $countStmt = $db->prepare($countSql);
    if (!empty($search)) {
        $countStmt->execute([$searchParam]);
    } else {
        $countStmt->execute();
    }
    $totalRecords = $countStmt->fetchColumn();
    
    // Prepare and execute main query
    $stmt = $db->prepare($sql);
    if (!empty($search)) {
        $stmt->execute([$searchParam]);
    } else {
        $stmt->execute();
    }
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Query for bonus usage logs
    $countSql = "SELECT COUNT(*) FROM bonus_kullanim";
    $sql = "
        SELECT bk.*, k.username, b.bonus_adi
        FROM bonus_kullanim bk
        LEFT JOIN kullanicilar k ON bk.user_id = k.id
        LEFT JOIN bonuslar b ON bk.bonus_id = b.id
    ";
    
    // Add search condition if search is provided
    if (!empty($search)) {
        $countSql .= " bk JOIN kullanicilar k ON bk.user_id = k.id WHERE k.username LIKE ?";
        $sql .= " WHERE k.username LIKE ?";
        $searchParam = "%$search%";
    }
    
    // Add ordering and limit
    $sql .= " ORDER BY bk.tarih DESC LIMIT $limit OFFSET $offset";
    
    // Prepare and execute count query
    $countStmt = $db->prepare($countSql);
    if (!empty($search)) {
        $countStmt->execute([$searchParam]);
    } else {
        $countStmt->execute();
    }
    $totalRecords = $countStmt->fetchColumn();
    
    // Prepare and execute main query
    $stmt = $db->prepare($sql);
    if (!empty($search)) {
        $stmt->execute([$searchParam]);
    } else {
        $stmt->execute();
    }
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate total pages
$totalPages = ceil($totalRecords / $limit);

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

    .stat-card.usage::after { background: var(--gradient-primary); }
    .stat-card.rejected::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.today-usage::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.today-rejected::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.total-amount::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.avg-amount::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .bonus-logs-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .bonus-logs-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .bonus-logs-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .bonus-logs-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .bonus-logs-body {
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

    .log-status {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .log-status.active {
        background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%);
        color: white;
    }

    .log-status.rejected {
        background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%);
        color: white;
    }

    .log-status.pending {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%);
        color: white;
    }

    .description-cell {
        max-width: 250px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: pointer;
    }

    .description-cell:hover {
        color: var(--primary-blue);
    }

    .nav-pills .nav-link {
        border-radius: 0.5rem;
        padding: 0.75rem 1.25rem;
        font-weight: 500;
        color: var(--light-gray);
        border: 1px solid transparent;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-pills .nav-link:hover {
        background-color: rgba(59, 130, 246, 0.1);
        color: var(--primary-blue);
        transform: translateY(-2px);
    }

    .nav-pills .nav-link.active {
        background: var(--gradient-primary);
        color: white;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
    }

    .nav-pills .nav-link .badge {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        transition: var(--transition);
    }

    .nav-pills .nav-link:hover .badge {
        transform: scale(1.1);
    }

    .search-container {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .search-input {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.75rem 1rem 0.75rem 3rem;
        color: var(--dark-gray);
        width: 100%;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        background: var(--white);
    }

    .search-input:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        border-color: var(--primary-blue);
        outline: none;
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--light-gray);
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

    .btn-outline-secondary {
        background: transparent;
        color: var(--light-gray);
        border: 2px solid #e5e7eb;
    }

    .btn-outline-secondary:hover {
        background: var(--light-gray);
        color: var(--white);
    }

    .badge {
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
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

        .bonus-logs-body {
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
            <i class="bi bi-journal-text"></i>
            Bonus Talep Logları
        </div>
        <div class="dashboard-subtitle">
            Bonus kullanım ve reddedilen talep loglarını takip edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card usage">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBonusUsage); ?></div>
            <div class="stat-label">Toplam Kullanım</div>
        </div>
        
        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalRejectedRequests); ?></div>
            <div class="stat-label">Reddedilen Talep</div>
        </div>
        
        <div class="stat-card today-usage">
            <div class="stat-icon">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayBonusUsage); ?></div>
            <div class="stat-label">Bugünkü Kullanım</div>
        </div>
        
        <div class="stat-card today-rejected">
            <div class="stat-icon">
                <i class="bi bi-calendar-x"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayRejectedRequests); ?></div>
            <div class="stat-label">Bugünkü Red</div>
        </div>
        
        <div class="stat-card total-amount">
            <div class="stat-icon">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBonusAmount, 2); ?> ₺</div>
            <div class="stat-label">Toplam Bonus Tutarı</div>
        </div>
    </div>

    <!-- Bonus Logs Management -->
    <div class="bonus-logs-card">
        <div class="bonus-logs-header">
            <div class="bonus-logs-title">
                <i class="bi bi-gear"></i>
                Log Yönetimi
            </div>
        </div>
        <div class="bonus-logs-body">
            <!-- Tab Switching -->
            <ul class="nav nav-pills mb-4">
                <li class="nav-item">
                    <a class="nav-link <?php echo $tab === 'kullanim' ? 'active' : ''; ?>" href="bonus_talep_loglar.php?tab=kullanim<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="bi bi-check-circle me-1"></i> Bonus Kullanım
                        <span class="badge bg-light text-dark ms-1"><?php echo $totalBonusUsage; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $tab === 'red' ? 'active' : ''; ?>" href="bonus_talep_loglar.php?tab=red<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="bi bi-x-circle me-1"></i> Reddedilen Talepler
                        <span class="badge bg-light text-dark ms-1"><?php echo $totalRejectedRequests; ?></span>
                    </a>
                </li>
            </ul>

            <!-- Search Form -->
            <div class="search-container">
                <form id="filterForm" method="get" action="bonus_talep_loglar.php" class="row g-3">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                    
                    <div class="col-md-8">
                        <div class="search-container">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" class="search-input" id="searchInput" name="search" placeholder="Kullanıcı adı ara..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Ara
                            </button>
                            
                            <?php if (!empty($search)): ?>
                            <a href="bonus_talep_loglar.php?tab=<?php echo htmlspecialchars($tab); ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Temizle
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (!empty($search)): ?>
            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div>
                        <strong>"<?php echo htmlspecialchars($search); ?>"</strong> için arama sonuçları gösteriliyor.
                        <a href="bonus_talep_loglar.php?tab=<?php echo htmlspecialchars($tab); ?>" class="alert-link ms-2">
                            <i class="bi bi-x-circle me-1"></i>Aramayı Temizle
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <?php if ($tab === 'red'): ?>
                <!-- Rejected Bonus Requests Table -->
                <table class="table log-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Bonus</th>
                            <th>Tarih</th>
                            <th>IP Adresi</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td>
                                    <a href="site_users.php?action=view&id=<?php echo $log['user_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($log['username'] ?? 'Silinmiş Kullanıcı'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($log['bonus_adi'] ?? 'Bilinmeyen Bonus'); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($log['tarih'])); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_adresi']); ?></td>
                                <td class="description-cell" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($log['aciklama']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($log['aciklama'], 0, 30) . (mb_strlen($log['aciklama']) > 30 ? '...' : '')); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Kayıt bulunamadı.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <!-- Bonus Usage Table -->
                <table class="table log-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Bonus</th>
                            <th>Miktar</th>
                            <th>Yatırım</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                            <th>Detay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td>
                                    <a href="site_users.php?action=view&id=<?php echo $log['user_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($log['username'] ?? 'Silinmiş Kullanıcı'); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($log['bonus_adi'] ?? 'Bilinmeyen Bonus'); ?></td>
                                <td><?php echo number_format($log['miktar'], 2) . ' TL'; ?></td>
                                <td><?php echo number_format($log['yatirim_miktari'], 2) . ' TL'; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($log['tarih'])); ?></td>
                                <td>
                                    <?php if ($log['durum'] == 1): ?>
                                        <span class="log-status active">Aktif</span>
                                    <?php else: ?>
                                        <span class="log-status rejected">İptal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="description-cell" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($log['aciklama']); ?>">
                                    <?php echo htmlspecialchars(mb_substr($log['aciklama'], 0, 30) . (mb_strlen($log['aciklama']) > 30 ? '...' : '')); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Kayıt bulunamadı.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Sayfalama">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="bonus_talep_loglar.php?tab=<?php echo $tab; ?>&page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="bonus_talep_loglar.php?tab=<?php echo $tab; ?>&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="bonus_talep_loglar.php?tab=<?php echo $tab; ?>&page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Make description cells clickable to show full text
    document.querySelectorAll('.description-cell').forEach(function(cell) {
        cell.addEventListener('click', function() {
            Swal.fire({
                title: 'Açıklama',
                text: this.getAttribute('data-bs-toggle') === 'tooltip' ? this.getAttribute('title') : this.textContent,
                icon: 'info',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'Kapat'
            });
        });
    });
    
    // Add animation to table rows
    const logRows = document.querySelectorAll('.log-table tbody tr');
    if (logRows.length > 0) {
        logRows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(10px)';
            row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            
            setTimeout(() => {
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, 30 * index);
        });
    }
    
    // Auto-submit search after a delay
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('filterForm').submit();
            } else if (this.value.length >= 3) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 1000);
            }
        });
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 