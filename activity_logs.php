<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Aktivite Logları";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Get user info
$stmt = $db->prepare("SELECT * FROM administrators WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();

// Get user permissions
$stmt = $db->prepare("SELECT * FROM admin_permissions WHERE role_id = ?");
$stmt->execute([$_SESSION['role_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userPermissions = [];
foreach ($permissions as $permission) {
    $userPermissions[$permission['menu_item']] = [
        'view' => $permission['can_view'],
        'create' => $permission['can_create'],
        'edit' => $permission['can_edit'],
        'delete' => $permission['can_delete']
    ];
}

$isAdmin = ($_SESSION['role_id'] == 1);
$canViewLogs = $isAdmin || ($userPermissions['activity_logs']['view'] ?? false);
$canDeleteLogs = $isAdmin || ($userPermissions['activity_logs']['delete'] ?? false);

if (!$canViewLogs) {
    header("Location: index.php");
    exit();
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete']) && $canDeleteLogs) {
    $selected_logs = $_POST['selected_logs'] ?? [];
    if (!empty($selected_logs)) {
        try {
            $placeholders = str_repeat('?,', count($selected_logs) - 1) . '?';
            $stmt = $db->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
            $stmt->execute($selected_logs);
            
            // Log the bulk delete action
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'delete', ?, ?)");
            $stmt->execute([$_SESSION['admin_id'], 'Toplu log silme işlemi: ' . count($selected_logs) . ' kayıt silindi', $_SERVER['REMOTE_ADDR']]);
            
            $success_message = count($selected_logs) . " log kaydı başarıyla silindi!";
        } catch (Exception $e) {
            $error_message = "Log kayıtları silinirken hata oluştu: " . $e->getMessage();
        }
    }
}

// Handle single delete
if (isset($_GET['delete']) && $canDeleteLogs) {
    $log_id = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM activity_logs WHERE id = ?");
        $stmt->execute([$log_id]);
        
        // Log the delete action
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'delete', 'Log kaydı silindi (ID: ' . $log_id . ')', ?)");
        $stmt->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
        
        $success_message = "Log kaydı başarıyla silindi!";
    } catch (Exception $e) {
        $error_message = "Log kaydı silinirken hata oluştu: " . $e->getMessage();
    }
}

// Handle clear all logs
if (isset($_GET['clear_all']) && $canDeleteLogs) {
    try {
        $stmt = $db->prepare("DELETE FROM activity_logs");
        $stmt->execute();
        
        // Log the clear all action
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'delete', 'Tüm log kayıtları temizlendi', ?)");
        $stmt->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
        
        $success_message = "Tüm log kayıtları başarıyla temizlendi!";
    } catch (Exception $e) {
        $error_message = "Log kayıtları temizlenirken hata oluştu: " . $e->getMessage();
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$admin_filter = $_GET['admin'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(al.description LIKE ? OR a.username LIKE ? OR al.ip_address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($action_filter)) {
    $where_conditions[] = "al.action = ?";
    $params[] = $action_filter;
}

if (!empty($admin_filter)) {
    $where_conditions[] = "al.admin_id = ?";
    $params[] = $admin_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) FROM activity_logs al LEFT JOIN administrators a ON al.admin_id = a.id $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get logs with pagination
$query = "
    SELECT al.*, a.username, a.email 
    FROM activity_logs al 
    LEFT JOIN administrators a ON al.admin_id = a.id 
    $where_clause 
    ORDER BY al.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$stmt = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get admins for filter
$stmt = $db->query("SELECT id, username FROM administrators ORDER BY username");
$admins = $stmt->fetchAll();

// Get statistics
$stmt = $db->query("SELECT COUNT(*) as total_logs FROM activity_logs");
$total_logs = $stmt->fetch()['total_logs'];

$stmt = $db->query("SELECT COUNT(*) as today_logs FROM activity_logs WHERE DATE(created_at) = CURDATE()");
$today_logs = $stmt->fetch()['today_logs'];

$stmt = $db->query("SELECT COUNT(*) as unique_admins FROM activity_logs WHERE admin_id IS NOT NULL");
$unique_admins = $stmt->fetch()['unique_admins'];

$stmt = $db->query("SELECT action, COUNT(*) as count FROM activity_logs GROUP BY action ORDER BY count DESC LIMIT 5");
$top_actions = $stmt->fetchAll();

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
        --tertiary-gradient: linear-gradient(135deg, #1e3a8a 0%, #93c5fd 100%);
        
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

    .logs-header {
        margin-bottom: 2rem;
        position: relative;
        padding: 2rem;
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-md);
        border-top: 4px solid var(--primary-blue-dark);
    }
    
    .logs-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.8rem;
        color: var(--text-heading);
        position: relative;
        font-family: 'Inter', sans-serif;
        letter-spacing: -0.5px;
    }
    
    .logs-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
        margin-bottom: 0;
        font-weight: 400;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
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
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .stat-card.total::before {
        background: var(--primary-gradient);
    }

    .stat-card.today::before {
        background: var(--secondary-gradient);
    }

    .stat-card.admins::before {
        background: var(--tertiary-gradient);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        color: white;
    }

    .stat-card.total .stat-icon {
        background: var(--primary-gradient);
    }

    .stat-card.today .stat-icon {
        background: var(--secondary-gradient);
    }

    .stat-card.admins .stat-icon {
        background: var(--tertiary-gradient);
    }

    .stat-number {
        font-size: 1.8rem;
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

    .filters-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .filters-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        margin: 0;
    }

    .filters-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .filters-body {
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text-heading);
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: var(--white);
        color: var(--text-primary);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: var(--light-gray);
        color: var(--text-primary);
        border: 1px solid var(--card-border);
    }

    .btn-secondary:hover {
        background: var(--medium-gray);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--error-red) 0%, #ef4444 100%);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .btn-danger:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #f59e0b 100%);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .btn-warning:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    .logs-table {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .table-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        margin: 0;
    }

    .table-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .table-actions {
        display: flex;
        gap: 0.5rem;
    }

    .table-container {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        background: var(--light-gray);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--text-heading);
        border-bottom: 1px solid var(--card-border);
        font-size: 0.9rem;
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid var(--card-border);
        font-size: 0.9rem;
    }

    .table tr:hover {
        background: var(--ultra-light-blue);
    }

    .action-badge {
        padding: 0.25rem 0.75rem;
        border-radius: var(--border-radius-sm);
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .action-create {
        background: rgba(5, 150, 105, 0.1);
        color: var(--success-green);
        border: 1px solid var(--success-green);
    }

    .action-update {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info-blue);
        border: 1px solid var(--info-blue);
    }

    .action-delete {
        background: rgba(220, 38, 38, 0.1);
        color: var(--error-red);
        border: 1px solid var(--error-red);
    }

    .action-login {
        background: rgba(217, 119, 6, 0.1);
        color: var(--warning-orange);
        border: 1px solid var(--warning-orange);
    }

    .action-logout {
        background: rgba(107, 114, 128, 0.1);
        color: var(--dark-gray);
        border: 1px solid var(--dark-gray);
    }

    .admin-info {
        display: flex;
        flex-direction: column;
    }

    .admin-name {
        font-weight: 600;
        color: var(--text-heading);
    }

    .admin-email {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }

    .ip-address {
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .timestamp {
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .page-link {
        padding: 0.5rem 1rem;
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        color: var(--text-primary);
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .page-link:hover {
        background: var(--light-gray);
    }

    .page-link.active {
        background: var(--primary-blue);
        color: white;
        border-color: var(--primary-blue);
    }

    .page-link.disabled {
        color: var(--text-secondary);
        cursor: not-allowed;
        opacity: 0.5;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(5, 150, 105, 0.1);
        border-color: var(--success-green);
        color: var(--success-green);
    }

    .alert-danger {
        background: rgba(220, 38, 38, 0.1);
        border-color: var(--error-red);
        color: var(--error-red);
    }

    .animate-fade-in {
        animation: fadeIn 0.8s ease forwards;
    }

    .checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-check-input {
        width: 1.2rem;
        height: 1.2rem;
        accent-color: var(--primary-blue);
    }

    .bulk-actions {
        background: var(--light-gray);
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    @media (max-width: 768px) {
        .logs-header {
            padding: 1.5rem;
        }
        
        .logs-title {
            font-size: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .table-actions {
            flex-direction: column;
        }
        
        .bulk-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<div class="logs-header animate-fade-in">
    <h1 class="logs-title">
        <i class="bi bi-activity"></i> Aktivite Logları
    </h1>
    <p class="logs-subtitle">Sistem aktivitelerini takip edin ve yönetin.</p>
</div>

<?php if (isset($success_message) && !empty($success_message)): ?>
    <div class="alert alert-success animate-fade-in">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message) && !empty($error_message)): ?>
    <div class="alert alert-danger animate-fade-in">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="stats-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-list-ul"></i>
        </div>
        <div class="stat-number"><?php echo number_format($total_logs); ?></div>
        <div class="stat-title">Toplam Log</div>
    </div>
    
    <div class="stat-card today">
        <div class="stat-icon">
            <i class="bi bi-calendar-day"></i>
        </div>
        <div class="stat-number"><?php echo number_format($today_logs); ?></div>
        <div class="stat-title">Bugünkü Log</div>
    </div>
    
    <div class="stat-card admins">
        <div class="stat-icon">
            <i class="bi bi-people"></i>
        </div>
        <div class="stat-number"><?php echo number_format($unique_admins); ?></div>
        <div class="stat-title">Aktif Admin</div>
    </div>
</div>

<!-- Filters -->
<div class="filters-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="filters-header">
        <h3 class="filters-title">
            <i class="bi bi-funnel"></i> Filtreler
        </h3>
        <a href="activity_logs.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-clockwise"></i> Sıfırla
        </a>
    </div>
    <div class="filters-body">
        <form method="GET" action="">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Arama</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Açıklama, kullanıcı veya IP adresi...">
                </div>
                <div class="form-group">
                    <label class="form-label">İşlem Türü</label>
                    <select class="form-control" name="action">
                        <option value="">Tümü</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($action)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kullanıcı</label>
                    <select class="form-control" name="admin">
                        <option value="">Tümü</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>" <?php echo $admin_filter == $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="form-group" style="display: flex; align-items: end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Filtrele
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="logs-table animate-fade-in" style="animation-delay: 0.3s">
    <div class="table-header">
        <h3 class="table-title">
            <i class="bi bi-table"></i> Log Kayıtları
        </h3>
        <div class="table-actions">
            <?php if ($canDeleteLogs): ?>
                <a href="activity_logs.php?clear_all=1" class="btn btn-danger btn-sm" onclick="return confirm('Tüm log kayıtlarını silmek istediğinizden emin misiniz?')">
                    <i class="bi bi-trash"></i> Tümünü Temizle
                </a>
            <?php endif; ?>
            <a href="activity_logs.php?export=csv" class="btn btn-secondary btn-sm">
                <i class="bi bi-download"></i> CSV İndir
            </a>
        </div>
    </div>
    
    <form method="POST" action="" id="bulk-form">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <?php if ($canDeleteLogs): ?>
                            <th width="50">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                        <?php endif; ?>
                        <th>İşlem</th>
                        <th>Açıklama</th>
                        <th>Kullanıcı</th>
                        <th>IP Adresi</th>
                        <th>Tarih</th>
                        <?php if ($canDeleteLogs): ?>
                            <th width="100">İşlemler</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="<?php echo $canDeleteLogs ? 7 : 6; ?>" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                <i class="bi bi-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                Log kaydı bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <?php if ($canDeleteLogs): ?>
                                    <td>
                                        <input type="checkbox" name="selected_logs[]" value="<?php echo $log['id']; ?>" class="form-check-input log-checkbox">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="action-badge action-<?php echo $log['action']; ?>">
                                        <?php echo ucfirst(htmlspecialchars($log['action'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <div class="admin-info">
                                            <span class="admin-name"><?php echo htmlspecialchars($log['username']); ?></span>
                                            <?php if ($log['email']): ?>
                                                <span class="admin-email"><?php echo htmlspecialchars($log['email']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">Sistem</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ip-address"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="timestamp"><?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?></span>
                                </td>
                                <?php if ($canDeleteLogs): ?>
                                    <td>
                                        <a href="activity_logs.php?delete=<?php echo $log['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu log kaydını silmek istediğinizden emin misiniz?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($canDeleteLogs && !empty($logs)): ?>
            <div class="bulk-actions">
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="select-all-bottom" class="form-check-input">
                    <label for="select-all-bottom">Tümünü Seç</label>
                </div>
                <button type="submit" name="bulk_delete" class="btn btn-danger btn-sm" onclick="return confirm('Seçili log kayıtlarını silmek istediğinizden emin misiniz?')">
                    <i class="bi bi-trash"></i> Seçilenleri Sil
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="pagination animate-fade-in" style="animation-delay: 0.4s">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                <i class="bi bi-chevron-left"></i> Önceki
            </a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                Sonraki <i class="bi bi-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation for elements
    const animateElements = document.querySelectorAll('.animate-fade-in');
    animateElements.forEach((element, index) => {
        const delay = parseFloat(element.style.animationDelay || '0s');
        element.style.opacity = '0';
        setTimeout(() => {
            element.style.opacity = '1';
        }, (delay * 1000) + 100);
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });

    // Select all functionality
    const selectAllCheckboxes = document.querySelectorAll('#select-all, #select-all-bottom');
    const logCheckboxes = document.querySelectorAll('.log-checkbox');

    selectAllCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const isChecked = this.checked;
            logCheckboxes.forEach(logCheckbox => {
                logCheckbox.checked = isChecked;
            });
            selectAllCheckboxes.forEach(otherCheckbox => {
                if (otherCheckbox !== this) {
                    otherCheckbox.checked = isChecked;
                }
            });
        });
    });

    // Update select all when individual checkboxes change
    logCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.log-checkbox:checked').length;
            const totalCount = logCheckboxes.length;
            
            selectAllCheckboxes.forEach(selectAllCheckbox => {
                selectAllCheckbox.checked = checkedCount === totalCount;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            });
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>