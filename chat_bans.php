<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Chat Ban Yönetimi";

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

// Process ban creation
if (isset($_POST['action']) && $_POST['action'] == 'create_ban') {
    $userId = (int)$_POST['user_id'];
    $reason = trim($_POST['reason']);
    $duration = (int)$_POST['duration'];
    
    try {
        $currentTime = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO chat_bans (user_id, banned_by, reason, ban_time, ban_duration) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $_SESSION['admin_id'], $reason, $currentTime, $duration]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            'create',
            $_SERVER['REMOTE_ADDR'],
            "Kullanıcı chat'ten banlandı (ID: $userId)"
        ]);
        
        $success = "Kullanıcı başarıyla banlandı.";
    } catch (PDOException $e) {
        $error = "Kullanıcı banlanırken bir hata oluştu: " . $e->getMessage();
    }
}

// Process ban update
if (isset($_POST['action']) && $_POST['action'] == 'update_ban') {
    $banId = (int)$_POST['ban_id'];
    $reason = trim($_POST['reason']);
    $duration = (int)$_POST['duration'];
    
    try {
        $stmt = $db->prepare("UPDATE chat_bans SET reason = ?, ban_duration = ? WHERE id = ?");
        $stmt->execute([$reason, $duration, $banId]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            'update',
            $_SERVER['REMOTE_ADDR'],
            "Chat ban kaydı güncellendi (ID: $banId)"
        ]);
        
        $success = "Ban kaydı başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error = "Ban kaydı güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// Process ban deletion
if (isset($_POST['action']) && $_POST['action'] == 'delete_ban') {
    $banId = (int)$_POST['ban_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM chat_bans WHERE id = ?");
        $stmt->execute([$banId]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            'delete',
            $_SERVER['REMOTE_ADDR'],
            "Chat ban kaydı silindi (ID: $banId)"
        ]);
        
        $success = "Ban kaydı başarıyla silindi.";
    } catch (PDOException $e) {
        $error = "Ban kaydı silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Get statistics for dashboard
try {
    // Total bans
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_bans");
    $stmt->execute();
    $totalBans = $stmt->fetch()['total'];
    
    // Active bans
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_bans WHERE ban_time + INTERVAL ban_duration DAY > NOW()");
    $stmt->execute();
    $activeBans = $stmt->fetch()['total'];
    
    // Today's bans
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_bans WHERE DATE(ban_time) = CURDATE()");
    $stmt->execute();
    $todayBans = $stmt->fetch()['total'];
    
    // Expired bans
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_bans WHERE ban_time + INTERVAL ban_duration DAY <= NOW()");
    $stmt->execute();
    $expiredBans = $stmt->fetch()['total'];
    
    // Average ban duration
    $stmt = $db->prepare("SELECT AVG(ban_duration) as avg_duration FROM chat_bans WHERE ban_duration > 0");
    $stmt->execute();
    $avgBanDuration = $stmt->fetch()['avg_duration'] ?? 0;
    
    // Most banned users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM (SELECT user_id, COUNT(*) as ban_count FROM chat_bans GROUP BY user_id HAVING ban_count > 1) as repeat_bans");
    $stmt->execute();
    $repeatBannedUsers = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalBans = $activeBans = $todayBans = $expiredBans = $avgBanDuration = $repeatBannedUsers = 0;
}

// Initialize filter variables
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query based on filters
$query = "
    SELECT 
        cb.*,
        cu.username as banned_username,
        a.username as banned_by_user
    FROM 
        chat_bans cb
    LEFT JOIN 
        chat_users cu ON cb.user_id = cu.id
    LEFT JOIN 
        administrators a ON cb.banned_by = a.id
    WHERE 1=1
";

$queryParams = [];

if ($userId) {
    $query .= " AND cb.user_id = ?";
    $queryParams[] = $userId;
}

if ($dateFrom) {
    $query .= " AND DATE(cb.ban_time) >= ?";
    $queryParams[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(cb.ban_time) <= ?";
    $queryParams[] = $dateTo;
}

$query .= " ORDER BY cb.ban_time DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($queryParams);
$bans = $stmt->fetchAll();

// Get all users for dropdown
$stmt = $db->prepare("
    SELECT cu.id, cu.username 
    FROM chat_users cu 
    INNER JOIN chat_bans cb ON cu.id = cb.user_id 
    GROUP BY cu.id, cu.username 
    ORDER BY cu.username
");
$stmt->execute();
$chatUsers = $stmt->fetchAll();

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
    .stat-card.active::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.today::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.expired::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.avg::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.repeat::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .chat-bans-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .chat-bans-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .chat-bans-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chat-bans-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .chat-bans-body {
        padding: 2rem;
    }

    .filter-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        margin-bottom: 2rem;
    }

    .filter-header {
        background: var(--gradient-primary);
        color: var(--white);
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-body {
        padding: 1.5rem;
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

    .user-color-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }

    .reason-cell {
        max-width: 300px;
        overflow: hidden;
    }

    .reason-content {
        max-height: 60px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .ban-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
    }

    .ban-active {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-red);
    }

    .ban-expired {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
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

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%);
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

        .chat-bans-body {
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
            <i class="bi bi-shield-exclamation"></i>
            Chat Ban Yönetimi
        </div>
        <div class="dashboard-subtitle">
            Chat banlarını yönetin ve kullanıcı erişimlerini kontrol edin
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
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBans); ?></div>
            <div class="stat-label">Toplam Ban</div>
        </div>
        
        <div class="stat-card active">
            <div class="stat-icon">
                <i class="bi bi-slash-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($activeBans); ?></div>
            <div class="stat-label">Aktif Ban</div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayBans); ?></div>
            <div class="stat-label">Bugünkü Ban</div>
        </div>
        
        <div class="stat-card expired">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($expiredBans); ?></div>
            <div class="stat-label">Süresi Dolmuş</div>
        </div>
        
        <div class="stat-card avg">
            <div class="stat-icon">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-value"><?php echo number_format($avgBanDuration, 0); ?> dk</div>
            <div class="stat-label">Ortalama Süre</div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-header">
            <i class="bi bi-funnel"></i>
            Ban Filtreleri
        </div>
        <div class="filter-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="user_id" class="form-label">Kullanıcı</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Tüm Kullanıcılar</option>
                        <?php foreach ($chatUsers as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?> (ID: <?php echo $user['id']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                        Filtrele
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Chat Bans Management -->
    <div class="chat-bans-card">
        <div class="chat-bans-header">
            <div class="chat-bans-title">
                <i class="bi bi-table"></i>
                Chat Ban Yönetimi
            </div>
        </div>
        <div class="chat-bans-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="bansTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-person"></i> Kullanıcı</th>
                            <th><i class="bi bi-chat-text"></i> Sebep</th>
                            <th><i class="bi bi-calendar"></i> Ban Tarihi</th>
                            <th><i class="bi bi-clock"></i> Süre (dk)</th>
                            <th><i class="bi bi-person-badge"></i> Banlayan Admin</th>
                            <th><i class="bi bi-circle-fill"></i> Durum</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bans)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">Henüz ban kaydı yok.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($bans as $ban): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo $ban['id']; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-color-dot" style="background-color: #777;"></div>
                                        <div class="ms-2">
                                            <div class="fw-bold">ID: <?php echo $ban['user_id']; ?></div>
                                            <div class="small text-muted">
                                                <?php echo $ban['banned_username'] ? htmlspecialchars($ban['banned_username']) : 'Kullanıcı bulunamadı'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="reason-cell">
                                    <div class="reason-content">
                                        <?php echo nl2br(htmlspecialchars($ban['reason'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($ban['ban_time'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo number_format($ban['ban_duration']); ?> dk</span>
                                </td>
                                <td>
                                    <span class="ban-badge">
                                        <?php echo htmlspecialchars($ban['banned_by_user']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $banTime = new DateTime($ban['ban_time']);
                                    $now = new DateTime();
                                    
                                    if ($ban['ban_duration'] <= 0) {
                                        $isActive = true;
                                        $remainingText = 'Süresiz';
                                    } else {
                                        $banEndTime = clone $banTime;
                                        $banEndTime->add(new DateInterval('PT' . $ban['ban_duration'] . 'M'));
                                        $isActive = $now < $banEndTime;
                                        
                                        if ($isActive) {
                                            $interval = $now->diff($banEndTime);
                                            $remainingMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                                            
                                            if ($remainingMinutes > 1440) {
                                                $days = floor($remainingMinutes / 1440);
                                                $hours = floor(($remainingMinutes % 1440) / 60);
                                                $remainingText = $days . " gün " . $hours . " saat";
                                            } elseif ($remainingMinutes > 60) {
                                                $hours = floor($remainingMinutes / 60);
                                                $mins = $remainingMinutes % 60;
                                                $remainingText = $hours . " saat " . $mins . " dk";
                                            } else {
                                                $remainingText = $remainingMinutes . " dk";
                                            }
                                        } else {
                                            $remainingText = "0 dk";
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($isActive): ?>
                                        <span class="ban-badge ban-active">
                                            <i class="bi bi-slash-circle me-1"></i>
                                            Aktif (<?php echo $remainingText; ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="ban-badge ban-expired">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Sona Erdi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if ($isActive): ?>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editBan(<?php echo $ban['id']; ?>, '<?php echo htmlspecialchars($ban['banned_username'] ? $ban['banned_username'] : 'Kullanıcı ID: ' . $ban['user_id']); ?>', '<?php echo htmlspecialchars($ban['reason']); ?>', <?php echo $ban['ban_duration']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                            Düzenle
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteBan(<?php echo $ban['id']; ?>, '<?php echo htmlspecialchars($ban['banned_username'] ? $ban['banned_username'] : 'Kullanıcı ID: ' . $ban['user_id']); ?>', '<?php echo htmlspecialchars($ban['reason']); ?>')">
                                            <i class="bi bi-trash"></i>
                                            Sil
                                        </button>
                                    </div>
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
    $('#bansTable').DataTable({
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

function editBan(banId, username, reason, duration) {
    Swal.fire({
        title: 'Ban Düzenle',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Kullanıcı</label>
                    <input type="text" class="form-control" value="${username}" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ban Sebebi</label>
                    <textarea class="form-control" id="edit-reason" rows="3" required>${reason}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ban Süresi (dk)</label>
                    <input type="number" class="form-control" id="edit-duration" min="1" max="43200" value="${duration}" required>
                    <small class="text-muted">Maksimum 30 gün (43200 dakika)</small>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const newReason = document.getElementById('edit-reason').value;
            const newDuration = document.getElementById('edit-duration').value;
            
            if (!newReason || !newDuration) {
                Swal.showValidationMessage('Lütfen tüm alanları doldurun');
                return false;
            }
            
            return { reason: newReason, duration: newDuration };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { reason: newReason, duration: newDuration } = result.value;
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_ban">
                <input type="hidden" name="ban_id" value="${banId}">
                <input type="hidden" name="reason" value="${newReason}">
                <input type="hidden" name="duration" value="${newDuration}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteBan(banId, username, reason) {
    Swal.fire({
        title: 'Ban Kaydını Sil',
        html: `
            <div class="text-start">
                <p><strong>${username}</strong> kullanıcısının ban kaydını silmek istediğinize emin misiniz?</p>
                <div class="alert alert-warning">
                    <small><strong>Ban Sebebi:</strong><br>${reason}</small>
                </div>
                <p class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Bu işlem geri alınamaz!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_ban">
                <input type="hidden" name="ban_id" value="${banId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 