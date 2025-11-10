<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Şifre Değiştirme Logları";

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
        WHERE ap.role_id = ? AND (ap.menu_item = 'password_reset_logs' OR ap.menu_item = 'logs') AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Get statistics
try {
    // Total password reset requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM password_resets");
    $stmt->execute();
    $totalRequests = $stmt->fetch()['total'];
    
    // Used password reset requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM password_resets WHERE used = 1");
    $stmt->execute();
    $usedRequests = $stmt->fetch()['total'];
    
    // Expired password reset requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM password_resets WHERE expires_at < NOW() AND used = 0");
    $stmt->execute();
    $expiredRequests = $stmt->fetch()['total'];
    
    // Active password reset requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM password_resets WHERE expires_at > NOW() AND used = 0");
    $stmt->execute();
    $activeRequests = $stmt->fetch()['total'];
    
    // Recent password reset requests (last 7 days)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM password_resets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recentRequests = $stmt->fetch()['total'];
    
    // Today's password reset requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM password_resets WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayRequests = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $totalRequests = $usedRequests = $expiredRequests = $activeRequests = $recentRequests = $todayRequests = 0;
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
    
    .stat-card.total::after {
        background: var(--primary-gradient);
    }
    
    .stat-card.used::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.expired::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.active::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.recent::after {
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
    
    .stat-card.used .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.expired .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.active .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.recent .stat-icon {
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

    .logs-table-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .logs-table-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .logs-table-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .logs-table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
    }
    
    .logs-table-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .logs-table-body {
        padding: 1.5rem;
    }

    .dataTables_wrapper {
        background: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 1rem;
    }

    .dataTables_filter input {
        background-color: var(--card-bg) !important;
        border: 1px solid var(--card-border) !important;
        color: var(--text-primary) !important;
        border-radius: var(--border-radius) !important;
        padding: 0.5rem 1rem !important;
        font-size: 0.9rem !important;
    }

    .dataTables_filter input:focus {
        outline: none !important;
        border-color: var(--primary-blue-light) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    }

    .dataTables_length select {
        background-color: var(--card-bg) !important;
        border: 1px solid var(--card-border) !important;
        color: var(--text-primary) !important;
        border-radius: var(--border-radius) !important;
        padding: 0.5rem !important;
        font-size: 0.9rem !important;
    }

    .dataTables_info {
        color: var(--text-secondary) !important;
        font-size: 0.9rem !important;
    }

    .dataTables_paginate .paginate_button {
        background: var(--card-bg) !important;
        border: 1px solid var(--card-border) !important;
        color: var(--text-primary) !important;
        border-radius: var(--border-radius) !important;
        padding: 0.5rem 1rem !important;
        margin: 0 0.25rem !important;
        text-decoration: none !important;
        transition: all 0.3s ease !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background: var(--primary-blue-light) !important;
        color: var(--white) !important;
        border-color: var(--primary-blue-light) !important;
        transform: translateY(-1px) !important;
    }

    .dataTables_paginate .paginate_button.current {
        background: var(--primary-gradient) !important;
        color: var(--white) !important;
        border-color: var(--primary-blue-light) !important;
    }

    .dataTables_paginate .paginate_button.disabled {
        background: var(--light-gray) !important;
        color: var(--text-secondary) !important;
        border-color: var(--card-border) !important;
        cursor: not-allowed !important;
    }

    .table {
        background: var(--card-bg) !important;
        border-radius: var(--border-radius) !important;
        overflow: hidden !important;
    }

    .table thead th {
        background: var(--bg-secondary) !important;
        color: var(--text-secondary) !important;
        font-weight: 600 !important;
        border-bottom: 2px solid var(--card-border) !important;
        padding: 1rem !important;
        font-size: 0.9rem !important;
    }

    .table tbody td {
        color: var(--text-primary) !important;
        border-bottom: 1px solid var(--card-border) !important;
        padding: 1rem !important;
        font-size: 0.9rem !important;
    }

    .table tbody tr {
        transition: all 0.3s ease !important;
    }

    .table tbody tr:hover {
        background-color: var(--ultra-light-blue) !important;
        transform: translateY(-1px) !important;
    }

    .badge {
        padding: 6px 12px !important;
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
    }

    .badge.bg-success {
        background: var(--success-green) !important;
        color: var(--white) !important;
    }

    .badge.bg-danger {
        background: var(--error-red) !important;
        color: var(--white) !important;
    }

    .btn {
        padding: 0.5rem 1rem !important;
        border-radius: var(--border-radius) !important;
        font-weight: 600 !important;
        font-size: 0.8rem !important;
        border: none !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        text-decoration: none !important;
    }

    .btn-info {
        background: var(--info-blue) !important;
        color: var(--white) !important;
    }

    .btn-info:hover {
        transform: translateY(-2px) !important;
        box-shadow: var(--shadow-blue) !important;
        color: var(--white) !important;
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

    .text-muted {
        color: var(--text-secondary) !important;
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
        
        .logs-table-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-shield-lock-fill me-2"></i>
        Şifre Değiştirme Logları
    </h1>
    <p class="dashboard-subtitle">Kullanıcıların şifre sıfırlama isteklerini takip edin ve güvenlik durumunu izleyin.</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger animate-fade-in">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-shield-lock"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalRequests); ?></div>
        <div class="stat-title">Toplam İstek</div>
    </div>
    
    <div class="stat-card used">
        <div class="stat-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($usedRequests); ?></div>
        <div class="stat-title">Kullanılan</div>
    </div>
    
    <div class="stat-card expired">
        <div class="stat-icon">
            <i class="bi bi-clock-history"></i>
        </div>
        <div class="stat-number"><?php echo number_format($expiredRequests); ?></div>
        <div class="stat-title">Süresi Dolmuş</div>
    </div>
    
    <div class="stat-card recent">
        <div class="stat-icon">
            <i class="bi bi-calendar-week"></i>
        </div>
        <div class="stat-number"><?php echo number_format($recentRequests); ?></div>
        <div class="stat-title">Son 7 Gün</div>
    </div>
    
    <div class="stat-card today">
        <div class="stat-icon">
            <i class="bi bi-calendar-day"></i>
        </div>
        <div class="stat-number"><?php echo number_format($todayRequests); ?></div>
        <div class="stat-title">Bugün</div>
    </div>
</div>

<div class="logs-table-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="logs-table-header">
        <h5 class="logs-table-title">
            <i class="bi bi-table"></i>
            Şifre Sıfırlama İstekleri
        </h5>
        <span class="text-muted">
            Toplam: <strong><?php echo number_format($totalRequests); ?></strong> istek
        </span>
    </div>
    <div class="logs-table-body">
        <div class="table-responsive">
            <table id="password-reset-logs-table" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı</th>
                        <th>Token</th>
                        <th>Son Geçerlilik Tarihi</th>
                        <th>Kullanıldı</th>
                        <th>Oluşturulma Tarihi</th>
                        <th>Detay</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DataTables & jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#password-reset-logs-table').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "ajax/password_reset_logs_ajax.php",
        "columns": [
            { "data": "id" },
            { "data": "username" },
            { "data": "token" },
            { "data": "expires_at" },
            { "data": "used" },
            { "data": "created_at" },
            { "data": null, "defaultContent": "", "orderable": false }
        ],
        "columnDefs": [
            {
                "targets": 1,
                "render": function(data, type, row) {
                    return '<a href="site_users.php?action=view&id=' + row.user_id + '" style="color: var(--primary-blue-light); text-decoration: none; font-weight: 600;">' + data + '</a>';
                }
            },
            {
                "targets": 2,
                "render": function(data, type, row) {
                    return '<span style="font-family: monospace; background: var(--light-gray); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem;">' + data.substring(0, 15) + '...</span>';
                }
            },
            {
                "targets": 3,
                "render": function(data, type, row) {
                    return '<span style="color: var(--text-secondary);">' + data + '</span>';
                }
            },
            {
                "targets": 4,
                "render": function(data, type, row) {
                    return data == 1 ? '<span class="badge bg-success">Evet</span>' : '<span class="badge bg-danger">Hayır</span>';
                }
            },
            {
                "targets": 5,
                "render": function(data, type, row) {
                    return '<span style="color: var(--text-secondary);">' + data + '</span>';
                }
            },
            {
                "targets": 6,
                "render": function(data, type, row) {
                    return '<a href="password_reset_details.php?id=' + row.id + '" class="btn btn-info"><i class="bi bi-eye"></i> Detay</a>';
                }
            }
        ],
        "order": [[0, "desc"]],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json"
        },
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "initComplete": function() {
            // Add custom styling after DataTable initialization
            $('.dataTables_wrapper').addClass('animate-fade-in');
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 