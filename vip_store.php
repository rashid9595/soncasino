<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Mağaza Yönetimi";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'vip_store' AND ap.can_view = 1
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
    // Total transactions
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM kullanici_vip_magaza");
    $stmt->execute();
    $totalTransactions = $stmt->fetch()['total'];
    
    // Total bonus amount
    $stmt = $db->prepare("SELECT SUM(bonus_tutari) as total FROM kullanici_vip_magaza");
    $stmt->execute();
    $totalBonusAmount = $stmt->fetch()['total'] ?? 0;
    
    // Total points spent
    $stmt = $db->prepare("SELECT SUM(harcanan_puan) as total FROM kullanici_vip_magaza");
    $stmt->execute();
    $totalPointsSpent = $stmt->fetch()['total'] ?? 0;
    
    // Today's transactions
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM kullanici_vip_magaza WHERE DATE(islem_tarihi) = CURDATE()");
    $stmt->execute();
    $todayTransactions = $stmt->fetch()['total'];
    
    // Average bonus per transaction
    $avgBonusPerTransaction = $totalTransactions > 0 ? $totalBonusAmount / $totalTransactions : 0;
    
    // Unique users
    $stmt = $db->prepare("SELECT COUNT(DISTINCT username) as total FROM kullanici_vip_magaza");
    $stmt->execute();
    $uniqueUsers = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalTransactions = $totalBonusAmount = $totalPointsSpent = $todayTransactions = $avgBonusPerTransaction = $uniqueUsers = 0;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_transaction':
            $username = trim($_POST['username']);
            $bonus_id = intval($_POST['bonus_id']);
            $bonus_tutari = floatval($_POST['bonus_tutari']);
            $harcanan_puan = floatval($_POST['harcanan_puan']);
            
            if (empty($username) || $bonus_tutari < 0 || $harcanan_puan < 0) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
                exit();
            }
            
            try {
                $db->beginTransaction();
                
                // Add transaction
                $stmt = $db->prepare("
                    INSERT INTO kullanici_vip_magaza 
                    (username, bonus_id, bonus_tutari, harcanan_puan, islem_tarihi) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$username, $bonus_id, $bonus_tutari, $harcanan_puan]);
                
                // Update user's VIP points
                $stmt = $db->prepare("
                    UPDATE kullanici_vip_puan 
                    SET kullanilan_puan = kullanilan_puan + ? 
                    WHERE username = ?
                ");
                $stmt->execute([$harcanan_puan, $username]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'create', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "VIP mağaza işlemi eklendi: $username"]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'İşlem başarıyla eklendi']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
            exit();
            
        case 'get_user_points':
            $username = trim($_POST['username']);
            
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı adı']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT vp.*, kv.vip_level 
                FROM kullanici_vip_puan vp
                LEFT JOIN kullanici_vip kv ON vp.username = kv.username
                WHERE vp.username = ?
            ");
            $stmt->execute([$username]);
            $points = $stmt->fetch();
            
            if ($points) {
                echo json_encode(['success' => true, 'data' => $points]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
            }
            exit();
    }
}

// Get all store transactions
$stmt = $db->prepare("
    SELECT vm.*, kv.vip_level 
    FROM kullanici_vip_magaza vm
    LEFT JOIN kullanici_vip kv ON vm.username = kv.username
    ORDER BY vm.islem_tarihi DESC
");
$stmt->execute();
$transactions = $stmt->fetchAll();

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
    .stat-card.bonus::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.points::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.today::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.average::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }
    .stat-card.users::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }

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

    .vip-store-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .vip-store-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .vip-store-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .vip-store-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .vip-store-body {
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

    .user-info {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
        margin-top: 0.5rem;
    }

    .user-info p {
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .user-info p:last-child {
        margin-bottom: 0;
    }

    .user-info strong {
        color: var(--primary-blue-light);
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

        .vip-store-body {
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
            <i class="bi bi-shop"></i>
            VIP Mağaza Yönetimi
        </div>
        <div class="dashboard-subtitle">
            VIP mağaza işlemlerini yönetin ve bonus takibi yapın
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="bi bi-cart"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalTransactions); ?></div>
            <div class="stat-label">Toplam İşlem</div>
        </div>
        
        <div class="stat-card bonus">
            <div class="stat-icon">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBonusAmount, 2); ?> ₺</div>
            <div class="stat-label">Toplam Bonus</div>
        </div>
        
        <div class="stat-card points">
            <div class="stat-icon">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalPointsSpent, 2); ?></div>
            <div class="stat-label">Harcanan Puan</div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayTransactions); ?></div>
            <div class="stat-label">Bugünkü İşlem</div>
        </div>
        
        <div class="stat-card average">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($avgBonusPerTransaction, 2); ?> ₺</div>
            <div class="stat-label">Ortalama Bonus</div>
        </div>
    </div>

    <!-- VIP Store Table -->
    <div class="vip-store-card">
        <div class="vip-store-header">
            <div class="vip-store-title">
                <i class="bi bi-table"></i>
                VIP Mağaza İşlemleri
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" onclick="showAddTransactionModal()">
                    <i class="bi bi-plus-circle"></i>
                    Yeni İşlem
                </button>
            </div>
        </div>
        <div class="vip-store-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="storeTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-person"></i> Kullanıcı</th>
                            <th><i class="bi bi-crown"></i> VIP Seviye</th>
                            <th><i class="bi bi-tag"></i> Bonus ID</th>
                            <th><i class="bi bi-currency-exchange"></i> Bonus Tutarı</th>
                            <th><i class="bi bi-star-fill"></i> Harcanan Puan</th>
                            <th><i class="bi bi-calendar"></i> İşlem Tarihi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">VIP mağaza işlemi bulunamadı.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <code><?php echo $transaction['id']; ?></code>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($transaction['username']); ?></strong>
                                </td>
                                <td>
                                    <span class="vip-badge <?php echo strtolower($transaction['vip_level'] ?? 'standart'); ?>">
                                        <?php echo htmlspecialchars($transaction['vip_level'] ?? 'STANDART'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-info">
                                        <?php echo $transaction['bonus_id']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">
                                        <?php echo number_format($transaction['bonus_tutari'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-warning">
                                        <?php echo number_format($transaction['harcanan_puan'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($transaction['islem_tarihi'])); ?>
                                    </small>
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

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTransactionModalLabel">
                    <i class="bi bi-plus-circle"></i>
                    Yeni VIP Mağaza İşlemi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTransactionForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="bi bi-person" style="color: var(--primary-blue-light);"></i>
                            Kullanıcı Adı
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="bonus_id" class="form-label">
                            <i class="bi bi-tag" style="color: var(--primary-blue-light);"></i>
                            Bonus ID
                        </label>
                        <input type="number" class="form-control" id="bonus_id" name="bonus_id" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="bonus_tutari" class="form-label">
                            <i class="bi bi-currency-exchange" style="color: var(--primary-blue-light);"></i>
                            Bonus Tutarı
                        </label>
                        <input type="number" class="form-control" id="bonus_tutari" name="bonus_tutari" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="harcanan_puan" class="form-label">
                            <i class="bi bi-star-fill" style="color: var(--primary-blue-light);"></i>
                            Harcanan Puan
                        </label>
                        <input type="number" class="form-control" id="harcanan_puan" name="harcanan_puan" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-info-circle" style="color: var(--primary-blue-light);"></i>
                            Kullanıcı Bilgileri
                        </label>
                        <div id="userInfo" class="user-info">
                            <p>VIP Seviye: <strong id="userVipLevel">-</strong></p>
                            <p>Toplam Puan: <strong id="userTotalPoints">-</strong></p>
                            <p>Kullanılan Puan: <strong id="userUsedPoints">-</strong></p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i>
                    İptal
                </button>
                <button type="button" class="btn btn-primary" onclick="addTransaction()">
                    <i class="bi bi-check-circle"></i>
                    Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable initialization
    $('#storeTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "desc"]], // Sort by ID descending
        "pageLength": 25,
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]]
    });

    // Username input event handler
    $('#username').on('input', function() {
        const username = $(this).val().trim();
        if (username.length > 2) {
            getUserPoints(username);
        } else {
            resetUserInfo();
        }
    });
});

function showAddTransactionModal() {
    // Reset form and user info
    $('#addTransactionForm')[0].reset();
    resetUserInfo();
    
    // Show modal using Bootstrap 5 syntax
    var modal = new bootstrap.Modal(document.getElementById('addTransactionModal'));
    modal.show();
}

function resetUserInfo() {
    $('#userVipLevel').text('-');
    $('#userTotalPoints').text('-');
    $('#userUsedPoints').text('-');
}

function getUserPoints(username) {
    $.ajax({
        url: 'vip_store.php',
        type: 'POST',
        data: {
            action: 'get_user_points',
            username: username
        },
        success: function(response) {
            if (response.success) {
                $('#userVipLevel').text(response.data.vip_level || 'Standart');
                $('#userTotalPoints').text(response.data.toplam_puan + ' Puan');
                $('#userUsedPoints').text(response.data.kullanilan_puan + ' Puan');
            } else {
                resetUserInfo();
            }
        },
        error: function() {
            resetUserInfo();
        }
    });
}

function addTransaction() {
    const formData = {
        action: 'add_transaction',
        username: $('#username').val().trim(),
        bonus_id: $('#bonus_id').val(),
        bonus_tutari: $('#bonus_tutari').val(),
        harcanan_puan: $('#harcanan_puan').val()
    };

    if (!formData.username || !formData.bonus_id || !formData.bonus_tutari || !formData.harcanan_puan) {
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
        url: 'vip_store.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                // Hide modal
                bootstrap.Modal.getInstance(document.getElementById('addTransactionModal')).hide();
                
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
    $('#addTransactionForm').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            addTransaction();
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
