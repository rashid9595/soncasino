<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Rakeback Yönetimi";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'rakeback' AND ap.can_view = 1
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
    // Total users with rakeback
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM kullanicilar_rakeback");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['total'];
    
    // Total rakeback balance
    $stmt = $db->prepare("SELECT SUM(rakeback_balance) as total FROM kullanicilar_rakeback");
    $stmt->execute();
    $totalBalance = $stmt->fetch()['total'] ?? 0;
    
    // Total rakeback earned
    $stmt = $db->prepare("SELECT SUM(total_rakeback_earned) as total FROM kullanicilar_rakeback");
    $stmt->execute();
    $totalEarned = $stmt->fetch()['total'] ?? 0;
    
    // Total used transactions
    $stmt = $db->prepare("SELECT SUM(used_transactions_amount) as total FROM kullanicilar_rakeback");
    $stmt->execute();
    $totalUsed = $stmt->fetch()['total'] ?? 0;
    
    // Average rakeback per user
    $avgRakebackPerUser = $totalUsers > 0 ? $totalBalance / $totalUsers : 0;
    
    // Users with high rakeback (>1000)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM kullanicilar_rakeback WHERE rakeback_balance > 1000");
    $stmt->execute();
    $highRakebackUsers = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalUsers = $totalBalance = $totalEarned = $totalUsed = $avgRakebackPerUser = $highRakebackUsers = 0;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_rakeback':
            $username = trim($_POST['username']);
            $balance = floatval($_POST['balance']);
            
            if (empty($username) || $balance < 0) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
                exit();
            }
            
            try {
                $db->beginTransaction();
                
                // Update rakeback balance
                $stmt = $db->prepare("
                    UPDATE kullanicilar_rakeback 
                    SET rakeback_balance = ? 
                    WHERE username = ?
                ");
                $stmt->execute([$balance, $username]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Rakeback bakiyesi güncellendi: $username"]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Rakeback bakiyesi başarıyla güncellendi']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
            exit();
            
        case 'get_user_rakeback':
            $username = trim($_POST['username']);
            
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı adı']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT * FROM kullanicilar_rakeback WHERE username = ?
            ");
            $stmt->execute([$username]);
            $rakeback = $stmt->fetch();
            
            if ($rakeback) {
                echo json_encode(['success' => true, 'data' => $rakeback]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
            }
            exit();
    }
}

// Get all users with rakeback data
$stmt = $db->prepare("
    SELECT 
        kr.*,
        kv.vip_level,
        kv.total_turnover
    FROM kullanicilar_rakeback kr
    LEFT JOIN kullanici_vip kv ON kr.username = kv.username
    ORDER BY kr.rakeback_balance DESC
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
    .stat-card.balance::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.earned::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.used::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.average::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }
    .stat-card.high::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }

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

    .rakeback-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .rakeback-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .rakeback-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .rakeback-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .rakeback-body {
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

    .btn-secondary {
        background: var(--medium-gray);
        color: var(--white);
    }

    .btn-secondary:hover {
        background: var(--dark-gray);
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

        .rakeback-body {
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
            <i class="bi bi-cash-coin"></i>
            Rakeback Yönetimi
        </div>
        <div class="dashboard-subtitle">
            Rakeback bakiyelerini yönetin ve kullanıcı rakeback bilgilerini takip edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        
        <div class="stat-card balance">
            <div class="stat-icon">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBalance, 2); ?> ₺</div>
            <div class="stat-label">Toplam Bakiye</div>
        </div>
        
        <div class="stat-card earned">
            <div class="stat-icon">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalEarned, 2); ?> ₺</div>
            <div class="stat-label">Toplam Kazanılan</div>
        </div>
        
        <div class="stat-card used">
            <div class="stat-icon">
                <i class="bi bi-cart-check"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalUsed, 2); ?> ₺</div>
            <div class="stat-label">Kullanılan Miktar</div>
        </div>
        
        <div class="stat-card average">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($avgRakebackPerUser, 2); ?> ₺</div>
            <div class="stat-label">Ortalama Rakeback</div>
        </div>
        
        <div class="stat-card high">
            <div class="stat-icon">
                <i class="bi bi-trophy"></i>
            </div>
            <div class="stat-value"><?php echo number_format($highRakebackUsers); ?></div>
            <div class="stat-label">Yüksek Rakeback</div>
        </div>
    </div>

    <!-- Rakeback Table -->
    <div class="rakeback-card">
        <div class="rakeback-header">
            <div class="rakeback-title">
                <i class="bi bi-table"></i>
                Rakeback Kullanıcı Listesi
            </div>
        </div>
        <div class="rakeback-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="rakebackTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-person"></i> Kullanıcı Adı</th>
                            <th><i class="bi bi-crown"></i> VIP Seviyesi</th>
                            <th><i class="bi bi-wallet2"></i> Rakeback Bakiyesi</th>
                            <th><i class="bi bi-cash-stack"></i> Toplam Kazanılan</th>
                            <th><i class="bi bi-cart-check"></i> Kullanılan Miktar</th>
                            <th><i class="bi bi-graph-up"></i> Toplam Turnover</th>
                            <th><i class="bi bi-calendar"></i> Son Güncelleme</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">Rakeback kaydı bulunamadı.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </td>
                                <td>
                                    <span class="vip-badge <?php echo strtolower($user['vip_level'] ?? 'standart'); ?>">
                                        <?php echo htmlspecialchars($user['vip_level'] ?? 'STANDART'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">
                                        <?php echo number_format($user['rakeback_balance'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-info">
                                        <?php echo number_format($user['total_rakeback_earned'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-warning">
                                        <?php echo number_format($user['used_transactions_amount'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary">
                                        <?php echo number_format($user['total_turnover'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($user['last_update'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showEditRakebackModal('<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="bi bi-pencil"></i>
                                        Düzenle
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

<!-- Edit Rakeback Modal -->
<div class="modal fade" id="editRakebackModal" tabindex="-1" aria-labelledby="editRakebackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRakebackModalLabel">
                    <i class="bi bi-pencil"></i>
                    Rakeback Bakiyesini Düzenle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editRakebackForm">
                    <input type="hidden" id="edit_username" name="username">
                    <div class="mb-3">
                        <label for="edit_rakeback_balance" class="form-label">
                            <i class="bi bi-wallet2" style="color: var(--primary-blue-light);"></i>
                            Rakeback Bakiyesi
                        </label>
                        <input type="number" class="form-control" id="edit_rakeback_balance" name="balance" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_total_rakeback" class="form-label">
                            <i class="bi bi-cash-stack" style="color: var(--primary-blue-light);"></i>
                            Toplam Kazanılan Rakeback
                        </label>
                        <input type="number" class="form-control" id="edit_total_rakeback" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_used_transactions" class="form-label">
                            <i class="bi bi-cart-check" style="color: var(--primary-blue-light);"></i>
                            Kullanılan İşlem Miktarı
                        </label>
                        <input type="number" class="form-control" id="edit_used_transactions" readonly>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i>
                    İptal
                </button>
                <button type="button" class="btn btn-primary" onclick="updateRakeback()">
                    <i class="bi bi-check-circle"></i>
                    Güncelle
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable initialization
    $('#rakebackTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[2, "desc"]], // Sort by rakeback balance column descending
        "pageLength": 25,
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

function showEditRakebackModal(username) {
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
        url: 'rakeback.php',
        type: 'POST',
        data: {
            action: 'get_user_rakeback',
            username: username
        },
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                $('#edit_username').val(username);
                $('#edit_rakeback_balance').val(response.data.rakeback_balance);
                $('#edit_total_rakeback').val(response.data.total_rakeback_earned);
                $('#edit_used_transactions').val(response.data.used_transactions_amount);
                
                // Show modal using Bootstrap 5 syntax
                var editModal = new bootstrap.Modal(document.getElementById('editRakebackModal'));
                editModal.show();
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

function updateRakeback() {
    const username = $('#edit_username').val();
    const balance = $('#edit_rakeback_balance').val();

    if (!username || !balance) {
        Swal.fire({
            icon: 'error',
            title: 'Hata',
            text: 'Lütfen tüm alanları doldurun',
            customClass: {
                popup: 'swal2-popup-custom'
            }
        });
        return;
    }

    // Show loading state
    Swal.fire({
        title: 'İşleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        customClass: {
            popup: 'swal2-popup-custom'
        }
    });

    $.ajax({
        url: 'rakeback.php',
        type: 'POST',
        data: {
            action: 'update_rakeback',
            username: username,
            balance: balance
        },
        success: function(response) {
            if (response.success) {
                // Hide modal
                bootstrap.Modal.getInstance(document.getElementById('editRakebackModal')).hide();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500,
                    customClass: {
                        popup: 'swal2-popup-custom'
                    }
                }).then(() => {
                    location.reload();
                });
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

// Enter key handler for form
$(document).ready(function() {
    $('#editRakebackForm').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            updateRakeback();
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
