<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Kullanıcı Yönetimi";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'vip_users' AND ap.can_view = 1
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
    // Total VIP users count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM kullanici_vip");
    $stmt->execute();
    $totalVipUsers = $stmt->fetch()['total'];
    
    // Total turnover
    $stmt = $db->prepare("SELECT SUM(total_turnover) as total FROM kullanici_vip");
    $stmt->execute();
    $totalTurnover = $stmt->fetch()['total'] ?? 0;
    
    // Total points
    $stmt = $db->prepare("SELECT SUM(toplam_puan) as total FROM kullanici_vip_puan");
    $stmt->execute();
    $totalPoints = $stmt->fetch()['total'] ?? 0;
    
    // Total used points
    $stmt = $db->prepare("SELECT SUM(kullanilan_puan) as total FROM kullanici_vip_puan");
    $stmt->execute();
    $totalUsedPoints = $stmt->fetch()['total'] ?? 0;
    
    // Total rakeback balance
    $stmt = $db->prepare("SELECT SUM(rakeback_balance) as total FROM kullanicilar_rakeback");
    $stmt->execute();
    $totalRakebackBalance = $stmt->fetch()['total'] ?? 0;
    
    // Average VIP level
    $stmt = $db->prepare("SELECT AVG(CASE 
        WHEN vip_level = 'STANDART' THEN 1
        WHEN vip_level = 'BRONZ' THEN 2
        WHEN vip_level = 'GUMUS' THEN 3
        WHEN vip_level = 'ALTIN' THEN 4
        WHEN vip_level = 'PLATIN' THEN 5
        WHEN vip_level = 'ELMAS' THEN 6
        ELSE 1 END) as avg_level FROM kullanici_vip");
    $stmt->execute();
    $avgVipLevel = $stmt->fetch()['avg_level'] ?? 1;
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalVipUsers = $totalTurnover = $totalPoints = $totalUsedPoints = $totalRakebackBalance = $avgVipLevel = 0;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_user_details':
            $username = trim($_POST['username']);
            
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı adı']);
                exit();
            }
            
            try {
                // Get user's VIP details
                $stmt = $db->prepare("
                    SELECT 
                        kv.*,
                        kvp.toplam_puan,
                        kvp.kullanilan_puan,
                        kr.rakeback_balance,
                        kr.total_rakeback_earned,
                        kr.used_transactions_amount
                    FROM kullanici_vip kv
                    LEFT JOIN kullanici_vip_puan kvp ON kv.username = kvp.username
                    LEFT JOIN kullanicilar_rakeback kr ON kv.username = kr.username
                    WHERE kv.username = ?
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    echo json_encode(['success' => true, 'data' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
            exit();
    }
}

// Get all VIP users with their details
$stmt = $db->prepare("
    SELECT 
        kv.*,
        kvp.toplam_puan,
        kvp.kullanilan_puan,
        kr.rakeback_balance,
        kr.total_rakeback_earned,
        kr.used_transactions_amount
    FROM kullanici_vip kv
    LEFT JOIN kullanici_vip_puan kvp ON kv.username = kvp.username
    LEFT JOIN kullanicilar_rakeback kr ON kv.username = kr.username
    ORDER BY kv.total_turnover DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

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
    .stat-card.turnover::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.points::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.used::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.rakeback::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.level::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .vip-users-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .vip-users-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .vip-users-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .vip-users-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .vip-users-body {
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

    .btn {
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 600;
        transition: var(--transition);
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }

    .btn-primary {
        background: var(--gradient-primary);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
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

    /* Modal Styles */
    .modal-content {
        background: var(--white);
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        background: var(--gradient-primary);
        color: var(--white);
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        border-bottom: none;
    }

    .modal-title {
        color: var(--white);
        font-weight: 600;
    }

    .btn-close {
        filter: brightness(0) invert(1);
    }

    .modal-body {
        padding: 2rem;
    }

    .modal-footer {
        border-top: 1px solid #e5e7eb;
        padding: 1rem 2rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark-gray);
        margin-bottom: 0.5rem;
    }

    .form-control {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0.75rem;
        font-size: 0.9rem;
        transition: var(--transition);
        background: var(--white);
    }

    .form-control:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .form-control[readonly] {
        background: #f8fafc;
        color: var(--dark-gray);
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

        .vip-users-body {
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
            <i class="bi bi-crown"></i>
            VIP Kullanıcı Yönetimi
        </div>
        <div class="dashboard-subtitle">
            VIP kullanıcıları yönetin ve detaylarını görüntüleyin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalVipUsers); ?></div>
            <div class="stat-label">Toplam VIP Kullanıcı</div>
        </div>
        
        <div class="stat-card turnover">
            <div class="stat-icon">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalTurnover, 2); ?> ₺</div>
            <div class="stat-label">Toplam Turnover</div>
        </div>
        
        <div class="stat-card points">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalPoints, 2); ?></div>
            <div class="stat-label">Toplam Puan</div>
        </div>
        
        <div class="stat-card rakeback">
            <div class="stat-icon">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalRakebackBalance, 2); ?> ₺</div>
            <div class="stat-label">Toplam Rakeback</div>
        </div>
        
        <div class="stat-card level">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($avgVipLevel, 1); ?></div>
            <div class="stat-label">Ortalama VIP Seviyesi</div>
        </div>
    </div>

    <!-- VIP Users Table -->
    <div class="vip-users-card">
        <div class="vip-users-header">
            <div class="vip-users-title">
                <i class="bi bi-table"></i>
                VIP Kullanıcı Listesi
            </div>
            <div class="text-muted">
                Toplam: <?php echo number_format(count($users)); ?> kullanıcı
            </div>
        </div>
        <div class="vip-users-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="vipUsersTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-person"></i> Kullanıcı Adı</th>
                            <th><i class="bi bi-crown"></i> VIP Seviyesi</th>
                            <th><i class="bi bi-currency-exchange"></i> Toplam Turnover</th>
                            <th><i class="bi bi-star"></i> Toplam Puan</th>
                            <th><i class="bi bi-star-fill"></i> Kullanılan Puan</th>
                            <th><i class="bi bi-cash-stack"></i> Rakeback Bakiyesi</th>
                            <th><i class="bi bi-calendar"></i> Son Güncelleme</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">VIP kullanıcı bulunamadı.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </td>
                                <td>
                                    <span class="vip-badge <?php echo strtolower($user['vip_level']); ?>">
                                        <?php echo htmlspecialchars($user['vip_level']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">
                                        <?php echo number_format($user['total_turnover'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-warning">
                                        <?php echo number_format($user['toplam_puan'] ?? 0, 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger">
                                        <?php echo number_format($user['kullanilan_puan'] ?? 0, 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-info">
                                        <?php echo number_format($user['rakeback_balance'] ?? 0, 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($user['last_update'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showUserDetails('<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="bi bi-eye"></i>
                                        Detaylar
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

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userDetailsModalLabel">
                    <i class="bi bi-person-circle"></i>
                    Kullanıcı Detayları
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-person" style="color: var(--primary-blue-light);"></i>
                                Kullanıcı Adı
                            </label>
                            <input type="text" class="form-control" id="detail_username" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-crown" style="color: var(--primary-blue-light);"></i>
                                VIP Seviyesi
                            </label>
                            <input type="text" class="form-control" id="detail_vip_level" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-currency-exchange" style="color: var(--primary-blue-light);"></i>
                                Toplam Turnover
                            </label>
                            <input type="text" class="form-control" id="detail_turnover" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-star" style="color: var(--primary-blue-light);"></i>
                                Toplam Puan
                            </label>
                            <input type="text" class="form-control" id="detail_total_points" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-star-fill" style="color: var(--primary-blue-light);"></i>
                                Kullanılan Puan
                            </label>
                            <input type="text" class="form-control" id="detail_used_points" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-cash-stack" style="color: var(--primary-blue-light);"></i>
                                Rakeback Bakiyesi
                            </label>
                            <input type="text" class="form-control" id="detail_rakeback_balance" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-graph-up" style="color: var(--primary-blue-light);"></i>
                                Toplam Kazanılan Rakeback
                            </label>
                            <input type="text" class="form-control" id="detail_total_rakeback" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-credit-card" style="color: var(--primary-blue-light);"></i>
                                Kullanılan İşlem Miktarı
                            </label>
                            <input type="text" class="form-control" id="detail_used_transactions" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i>
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable initialization
    $('#vipUsersTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[2, "desc"]], // Sort by total turnover column descending
        "pageLength": 25, // Show 25 entries per page
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "columnDefs": [
            {
                "targets": -1,
                "orderable": false,
                "searchable": false
            }
        ]
    });
});

function showUserDetails(username) {
    // Show loading state
    Swal.fire({
        title: 'Yükleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        customClass: {
            popup: 'swal2-popup-custom'
        }
    });

    $.ajax({
        url: 'vip_users.php',
        type: 'POST',
        data: {
            action: 'get_user_details',
            username: username
        },
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const user = response.data;
                
                $('#detail_username').val(user.username);
                $('#detail_vip_level').val(user.vip_level);
                $('#detail_turnover').val(numberFormat(user.total_turnover) + ' ₺');
                $('#detail_total_points').val(numberFormat(user.toplam_puan || 0));
                $('#detail_used_points').val(numberFormat(user.kullanilan_puan || 0));
                $('#detail_rakeback_balance').val(numberFormat(user.rakeback_balance || 0) + ' ₺');
                $('#detail_total_rakeback').val(numberFormat(user.total_rakeback_earned || 0) + ' ₺');
                $('#detail_used_transactions').val(numberFormat(user.used_transactions_amount || 0) + ' ₺');
                
                // Show modal using Bootstrap 5 syntax
                var detailsModal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
                detailsModal.show();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: response.message,
                    customClass: {
                        popup: 'swal2-popup-custom'
                    }
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Sunucu ile iletişim kurulurken bir hata oluştu',
                customClass: {
                    popup: 'swal2-popup-custom'
                }
            });
        }
    });
}

function numberFormat(number) {
    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number || 0);
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
