<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Site Kullanıcıları";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$country_filter = isset($_GET['country']) ? $_GET['country'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'createdAt';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "is_banned = 0";
    } elseif ($status_filter === 'banned') {
        $where_conditions[] = "is_banned = 1";
    } elseif ($status_filter === 'verified') {
        $where_conditions[] = "email_verification = 'evet'";
    } elseif ($status_filter === 'unverified') {
        $where_conditions[] = "email_verification = 'hayir'";
    }
}

if (!empty($country_filter)) {
    $where_conditions[] = "countryId = ?";
    $params[] = $country_filter;
}

if (!empty($gender_filter)) {
    $where_conditions[] = "gender = ?";
    $params[] = $gender_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM kullanicilar $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Get users with pagination
$sql = "SELECT * FROM kullanicilar $where_clause ORDER BY $sort_by $sort_order LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
function getUserStats($db) {
    $stats = [];
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar");
    $stats['total'] = $stmt->fetchColumn();
    
    // Active users (not banned)
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE is_banned = 0");
    $stats['active'] = $stmt->fetchColumn();
    
    // Banned users
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE is_banned = 1");
    $stats['banned'] = $stmt->fetchColumn();
    
    // Verified users
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE email_verification = 'evet'");
    $stats['verified'] = $stmt->fetchColumn();
    
    // Users with 2FA
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE twofactor = 'aktif'");
    $stats['twofactor'] = $stmt->fetchColumn();
    
    // Today's registrations
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE DATE(createdAt) = CURDATE()");
    $stats['today'] = $stmt->fetchColumn();
    
    // This week's registrations
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE YEARWEEK(createdAt) = YEARWEEK(NOW())");
    $stats['week'] = $stmt->fetchColumn();
    
    // This month's registrations
    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE MONTH(createdAt) = MONTH(NOW()) AND YEAR(createdAt) = YEAR(NOW())");
    $stats['month'] = $stmt->fetchColumn();
    
    return $stats;
}

// Get country options
function getCountries() {
    return [
        1 => 'Türkiye',
        2 => 'Almanya',
        3 => 'Fransa',
        4 => 'İtalya',
        5 => 'İspanya',
        6 => 'Hollanda',
        7 => 'Belçika',
        8 => 'Avusturya',
        9 => 'İsviçre',
        10 => 'Polonya'
    ];
}

$user_stats = getUserStats($db);
$countries = getCountries();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ban_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $db->prepare("UPDATE kullanicilar SET is_banned = 1 WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = "Kullanıcı başarıyla yasaklandı";
        } else {
            $error = "Kullanıcı yasaklanırken hata oluştu";
        }
    } elseif (isset($_POST['unban_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $db->prepare("UPDATE kullanicilar SET is_banned = 0 WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = "Kullanıcının yasağı kaldırıldı";
        } else {
            $error = "Kullanıcı yasağı kaldırılırken hata oluştu";
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        $stmt = $db->prepare("DELETE FROM kullanicilar WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = "Kullanıcı başarıyla silindi";
        } else {
            $error = "Kullanıcı silinirken hata oluştu";
        }
    } elseif (isset($_POST['export_users'])) {
        // Export users to CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=kullanicilar_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        // CSV headers
        fputcsv($output, ['ID', 'Kullanıcı Adı', 'E-posta', 'Telefon', 'Ülke', 'Cinsiyet', 'Doğum Yılı', 'Durum', 'E-posta Doğrulama', '2FA', 'Kayıt Tarihi', 'Son Aktivite']);
        
        // Get all users for export
        $stmt = $db->query("SELECT * FROM kullanicilar ORDER BY createdAt DESC");
        while ($user = $stmt->fetch()) {
            $status = $user['is_banned'] ? 'Yasaklı' : 'Aktif';
            $country_name = isset($countries[$user['countryId']]) ? $countries[$user['countryId']] : 'Bilinmiyor';
            $gender_name = $user['gender'] == 1 ? 'Erkek' : ($user['gender'] == 2 ? 'Kadın' : 'Bilinmiyor');
            
            fputcsv($output, [
                $user['id'],
                $user['username'],
                $user['email'],
                $user['phone'],
                $country_name,
                $gender_name,
                $user['birthYear'],
                $status,
                $user['email_verification'],
                $user['twofactor'],
                $user['createdAt'],
                $user['last_activity']
            ]);
        }
        
        fclose($output);
        exit();
    }
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
        
        /* Corporate Gradients */
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        --secondary-gradient: linear-gradient(135deg, #1e40af 0%, #60a5fa 100%);
        --success-gradient: linear-gradient(135deg, #059669 0%, #10b981 100%);
        --warning-gradient: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        --danger-gradient: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        
        /* Layout */
        --border-radius: 8px;
        --border-radius-lg: 12px;
        --border-radius-sm: 6px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    }

    body {
        background: var(--light-gray);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: var(--text-gray);
        line-height: 1.6;
    }

    .page-header {
        background: var(--white);
        padding: 2rem;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        margin-bottom: 2rem;
        border: 1px solid var(--medium-gray);
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
        margin: 0 0 1rem 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .page-title i {
        color: var(--primary-blue-light);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        padding: 1.5rem;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--medium-gray);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--primary-gradient);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-blue-dark);
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--text-gray);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .stat-change {
        font-size: 0.8rem;
        margin-top: 0.5rem;
    }

    .stat-change.positive {
        color: var(--success-green);
    }

    .stat-change.negative {
        color: var(--error-red);
    }

    .filters-section {
        background: var(--white);
        padding: 1.5rem;
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-sm);
        margin-bottom: 2rem;
        border: 1px solid var(--medium-gray);
    }

    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .filters-title {
        font-weight: 600;
        color: var(--primary-blue-dark);
        margin: 0;
    }

    .filters-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        margin: 0;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-gray);
        font-size: 0.9rem;
    }

    .form-control, .form-select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--medium-gray);
        border-radius: var(--border-radius);
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: white;
    }

    .btn-success {
        background: var(--success-gradient);
        color: white;
    }

    .btn-warning {
        background: var(--warning-gradient);
        color: white;
    }

    .btn-danger {
        background: var(--danger-gradient);
        color: white;
    }

    .btn-outline {
        background: transparent;
        border: 1px solid var(--medium-gray);
        color: var(--text-gray);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    .users-table-container {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        overflow: hidden;
        border: 1px solid var(--medium-gray);
    }

    .table-header {
        background: var(--ultra-light-blue);
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--medium-gray);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .table-title {
        font-weight: 600;
        color: var(--primary-blue-dark);
        margin: 0;
    }

    .table-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
    }

    .users-table th {
        background: var(--light-gray);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text-gray);
        border-bottom: 1px solid var(--medium-gray);
        font-size: 0.9rem;
    }

    .users-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--medium-gray);
        vertical-align: middle;
    }

    .users-table tr:hover {
        background: var(--ultra-light-blue);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-details h5 {
        margin: 0 0 0.25rem 0;
        font-weight: 600;
        color: var(--primary-blue-dark);
    }

    .user-email {
        margin: 0;
        font-size: 0.85rem;
        color: var(--text-gray);
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        text-align: center;
    }

    .status-active {
        background: var(--light-blue);
        color: var(--success-green);
    }

    .status-banned {
        background: #fee2e2;
        color: var(--error-red);
    }

    .status-verified {
        background: #dcfce7;
        color: var(--success-green);
    }

    .status-unverified {
        background: #fef3c7;
        color: var(--warning-orange);
    }

    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        padding: 1.5rem;
        background: var(--light-gray);
        border-top: 1px solid var(--medium-gray);
    }

    .page-link {
        padding: 0.5rem 1rem;
        border: 1px solid var(--medium-gray);
        border-radius: var(--border-radius);
        color: var(--text-gray);
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .page-link:hover {
        background: var(--primary-blue-light);
        color: white;
        border-color: var(--primary-blue-light);
    }

    .page-link.active {
        background: var(--primary-blue-light);
        color: white;
        border-color: var(--primary-blue-light);
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }

    .alert-success {
        background: var(--light-blue);
        border-left-color: var(--success-green);
        color: var(--primary-blue-dark);
    }

    .alert-danger {
        background: #fee2e2;
        border-left-color: var(--error-red);
        color: #7f1d1d;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--text-gray);
    }

    .empty-state i {
        font-size: 3rem;
        color: var(--medium-gray);
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .filters-form {
            grid-template-columns: 1fr;
        }
        
        .table-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .users-table {
            font-size: 0.8rem;
        }
        
        .users-table th,
        .users-table td {
            padding: 0.75rem 0.5rem;
        }
        
        .action-buttons {
            flex-direction: column;
        }
    }
</style>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-people-fill"></i>
        Site Kullanıcıları
    </h1>
    <p class="text-muted mb-0">Sistemdeki tüm kullanıcıları görüntüleyin, yönetin ve analiz edin</p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($user_stats['total']); ?></div>
        <div class="stat-label">Toplam Kullanıcı</div>
        <div class="stat-change positive">+<?php echo $user_stats['today']; ?> bugün</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($user_stats['active']); ?></div>
        <div class="stat-label">Aktif Kullanıcı</div>
        <div class="stat-change positive">%<?php echo round(($user_stats['active'] / $user_stats['total']) * 100, 1); ?> oran</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($user_stats['verified']); ?></div>
        <div class="stat-label">E-posta Doğrulanmış</div>
        <div class="stat-change positive">%<?php echo round(($user_stats['verified'] / $user_stats['total']) * 100, 1); ?> oran</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($user_stats['twofactor']); ?></div>
        <div class="stat-label">2FA Aktif</div>
        <div class="stat-change positive">%<?php echo round(($user_stats['twofactor'] / $user_stats['total']) * 100, 1); ?> oran</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($user_stats['banned']); ?></div>
        <div class="stat-label">Yasaklı Kullanıcı</div>
        <div class="stat-change negative">%<?php echo round(($user_stats['banned'] / $user_stats['total']) * 100, 1); ?> oran</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-number"><?php echo number_format($user_stats['month']); ?></div>
        <div class="stat-label">Bu Ay Kayıt</div>
        <div class="stat-change positive">+<?php echo $user_stats['week']; ?> bu hafta</div>
    </div>
</div>

<!-- Filters Section -->
<div class="filters-section">
    <div class="filters-header">
        <h3 class="filters-title">
            <i class="bi bi-funnel"></i>
            Filtreler ve Arama
        </h3>
        <div class="table-actions">
            <form method="POST" style="display: inline;">
                <button type="submit" name="export_users" class="btn btn-outline btn-sm">
                    <i class="bi bi-download"></i>
                    CSV İndir
                </button>
            </form>
        </div>
    </div>
    
    <form method="GET" class="filters-form">
        <div class="form-group">
            <label class="form-label">Arama</label>
            <input type="text" name="search" class="form-control" placeholder="Kullanıcı adı, e-posta veya telefon..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">Durum</label>
            <select name="status" class="form-select">
                <option value="">Tümü</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Yasaklı</option>
                <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>E-posta Doğrulanmış</option>
                <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>E-posta Doğrulanmamış</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Ülke</label>
            <select name="country" class="form-select">
                <option value="">Tümü</option>
                <?php foreach ($countries as $id => $name): ?>
                    <option value="<?php echo $id; ?>" <?php echo $country_filter == $id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Cinsiyet</label>
            <select name="gender" class="form-select">
                <option value="">Tümü</option>
                <option value="1" <?php echo $gender_filter == '1' ? 'selected' : ''; ?>>Erkek</option>
                <option value="2" <?php echo $gender_filter == '2' ? 'selected' : ''; ?>>Kadın</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Sıralama</label>
            <select name="sort" class="form-select">
                <option value="createdAt" <?php echo $sort_by === 'createdAt' ? 'selected' : ''; ?>>Kayıt Tarihi</option>
                <option value="username" <?php echo $sort_by === 'username' ? 'selected' : ''; ?>>Kullanıcı Adı</option>
                <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>E-posta</option>
                <option value="last_activity" <?php echo $sort_by === 'last_activity' ? 'selected' : ''; ?>>Son Aktivite</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Sıra</label>
            <select name="order" class="form-select">
                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Azalan</option>
                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Artan</option>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i>
                Filtrele
            </button>
            <a href="users.php" class="btn btn-outline">
                <i class="bi bi-arrow-clockwise"></i>
                Sıfırla
            </a>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="users-table-container">
    <div class="table-header">
        <h3 class="table-title">
            <i class="bi bi-table"></i>
            Kullanıcı Listesi (<?php echo number_format($total_users); ?> kullanıcı)
        </h3>
        <div class="table-actions">
            <span class="text-muted">
                Sayfa <?php echo $page; ?> / <?php echo $total_pages; ?>
            </span>
        </div>
    </div>
    
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            <h4>Kullanıcı Bulunamadı</h4>
            <p>Arama kriterlerinize uygun kullanıcı bulunamadı.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>İletişim</th>
                        <th>Ülke</th>
                        <th>Cinsiyet</th>
                        <th>Durum</th>
                        <th>Kayıt Tarihi</th>
                        <th>Son Aktivite</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <h5><?php echo htmlspecialchars($user['username']); ?></h5>
                                        <p class="user-email">ID: <?php echo $user['id']; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['phone'] ?: 'Telefon yok'); ?></small>
                                </div>
                            </td>
                            <td>
                                <?php echo isset($countries[$user['countryId']]) ? htmlspecialchars($countries[$user['countryId']]) : 'Bilinmiyor'; ?>
                            </td>
                            <td>
                                <?php 
                                if ($user['gender'] == 1) echo 'Erkek';
                                elseif ($user['gender'] == 2) echo 'Kadın';
                                else echo 'Bilinmiyor';
                                ?>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                    <span class="status-badge <?php echo $user['is_banned'] ? 'status-banned' : 'status-active'; ?>">
                                        <?php echo $user['is_banned'] ? 'Yasaklı' : 'Aktif'; ?>
                                    </span>
                                    <span class="status-badge <?php echo $user['email_verification'] === 'evet' ? 'status-verified' : 'status-unverified'; ?>">
                                        <?php echo $user['email_verification'] === 'evet' ? 'Doğrulanmış' : 'Doğrulanmamış'; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div><?php echo date('d.m.Y', strtotime($user['createdAt'])); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($user['createdAt'])); ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($user['last_activity']): ?>
                                    <div>
                                        <div><?php echo date('d.m.Y', strtotime($user['last_activity'])); ?></div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($user['last_activity'])); ?></small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Hiç giriş yapmamış</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="bi bi-eye"></i>
                                        Detay
                                    </a>
                                    
                                    <?php if ($user['is_banned']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="unban_user" class="btn btn-success btn-sm" onclick="return confirm('Bu kullanıcının yasağını kaldırmak istediğinizden emin misiniz?')">
                                                <i class="bi bi-unlock"></i>
                                                Yasak Kaldır
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="ban_user" class="btn btn-warning btn-sm" onclick="return confirm('Bu kullanıcıyı yasaklamak istediğinizden emin misiniz?')">
                                                <i class="bi bi-lock"></i>
                                                Yasakla
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm" onclick="return confirm('Bu kullanıcıyı kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')">
                                            <i class="bi bi-trash"></i>
                                            Sil
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                        <i class="bi bi-chevron-left"></i>
                        Önceki
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                        Sonraki
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Auto-refresh statistics every 30 seconds
setInterval(function() {
    // You can add AJAX call here to refresh stats
    console.log('Refreshing statistics...');
}, 30000);

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.querySelector('input[name="search"]').focus();
    }
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        document.querySelector('button[name="export_users"]').click();
    }
});

// Add smooth scrolling for pagination
document.querySelectorAll('.page-link').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!this.classList.contains('active')) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>