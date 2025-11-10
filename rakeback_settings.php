<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Rakeback Bonus Ayarları";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'rakeback_settings' AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_rakeback':
            try {
                $stmt = $db->prepare("
                    UPDATE rakeback_ayarlar SET 
                        min_tutar = ?,
                        oran = ?,
                        min_cekim = ?,
                        last_update = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['min_tutar'],
                    $_POST['oran'],
                    $_POST['min_cekim'],
                    $_POST['id']
                ]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update_rakeback', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Rakeback ayarları güncellendi: {$_POST['vip_level']}"]);
                
                echo json_encode(['success' => true, 'message' => 'Rakeback ayarları başarıyla güncellendi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics for dashboard
try {
    // Total rakeback settings
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM rakeback_ayarlar");
    $stmt->execute();
    $totalSettings = $stmt->fetch()['total'];
    
    // Average rakeback rate
    $stmt = $db->prepare("SELECT AVG(oran) as avg_rate FROM rakeback_ayarlar");
    $stmt->execute();
    $avgRate = $stmt->fetch()['avg_rate'] ?? 0;
    
    // Total minimum amount
    $stmt = $db->prepare("SELECT SUM(min_tutar) as total_min FROM rakeback_ayarlar");
    $stmt->execute();
    $totalMinAmount = $stmt->fetch()['total_min'] ?? 0;
    
    // Total minimum withdrawal
    $stmt = $db->prepare("SELECT SUM(min_cekim) as total_withdrawal FROM rakeback_ayarlar");
    $stmt->execute();
    $totalWithdrawal = $stmt->fetch()['total_withdrawal'] ?? 0;
    
    // Last updated setting
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM rakeback_ayarlar WHERE DATE(last_update) = CURDATE()");
    $stmt->execute();
    $todayUpdates = $stmt->fetch()['total'];
    
    // Most used rate
    $stmt = $db->prepare("SELECT oran, COUNT(*) as count FROM rakeback_ayarlar GROUP BY oran ORDER BY count DESC LIMIT 1");
    $stmt->execute();
    $mostUsedRate = $stmt->fetch();
    $popularRate = $mostUsedRate ? $mostUsedRate['oran'] : 0;
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalSettings = $avgRate = $totalMinAmount = $totalWithdrawal = $todayUpdates = $popularRate = 0;
}

// Get all rakeback settings
$stmt = $db->query("SELECT * FROM rakeback_ayarlar ORDER BY id ASC");
$rakeback_settings = $stmt->fetchAll();

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
    .stat-card.rate::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.amount::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.withdrawal::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.updates::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.popular::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .rakeback-settings-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .rakeback-settings-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .rakeback-settings-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .rakeback-settings-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .rakeback-settings-body {
        padding: 2rem;
    }

    .form-section {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        height: fit-content;
    }

    .form-section h5 {
        color: var(--dark-gray);
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
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

    .btn-secondary {
        background: var(--light-gray);
        color: var(--white);
    }

    .btn-secondary:hover {
        background: var(--medium-gray);
        transform: translateY(-1px);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
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

    .vip-badge.bronz {
        background: rgba(205, 127, 50, 0.1);
        color: #cd7f32;
    }

    .vip-badge.gümüş {
        background: rgba(192, 192, 192, 0.1);
        color: #c0c0c0;
    }

    .vip-badge.altın {
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

        .rakeback-settings-body {
            padding: 1rem;
        }

        .form-section {
            margin-bottom: 1.5rem;
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
            <i class="bi bi-gear"></i>
            Rakeback Bonus Ayarları
        </div>
        <div class="dashboard-subtitle">
            VIP seviyelerine göre rakeback bonus ayarlarını yönetin
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
            <div class="stat-value"><?php echo number_format($totalSettings); ?></div>
            <div class="stat-label">Toplam Ayar</div>
        </div>
        
        <div class="stat-card rate">
            <div class="stat-icon">
                <i class="bi bi-percent"></i>
            </div>
            <div class="stat-value">%<?php echo number_format($avgRate, 2); ?></div>
            <div class="stat-label">Ortalama Oran</div>
        </div>
        
        <div class="stat-card amount">
            <div class="stat-icon">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalMinAmount, 2); ?> ₺</div>
            <div class="stat-label">Toplam Min. Tutar</div>
        </div>
        
        <div class="stat-card withdrawal">
            <div class="stat-icon">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalWithdrawal, 2); ?> ₺</div>
            <div class="stat-label">Toplam Min. Çekim</div>
        </div>
        
        <div class="stat-card popular">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-value">%<?php echo number_format($popularRate, 2); ?></div>
            <div class="stat-label">En Popüler Oran</div>
        </div>
    </div>

    <!-- Rakeback Settings Content -->
    <div class="row">
        <div class="col-lg-4">
            <div class="form-section">
                <h5>
                    <i class="bi bi-pencil-square"></i>
                    Rakeback Ayarları Düzenle
                </h5>
                <form id="rakebackForm">
                    <input type="hidden" id="rakeback_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">VIP Seviyesi</label>
                        <input type="text" class="form-control" id="vip_level" name="vip_level" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Minimum Tutar</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="min_tutar" name="min_tutar" required>
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rakeback Oranı</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="oran" name="oran" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Minimum Çekim</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" id="min_cekim" name="min_cekim" required>
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i>
                            Kaydet
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="bi bi-arrow-clockwise"></i>
                            Formu Temizle
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="rakeback-settings-card">
                <div class="rakeback-settings-header">
                    <div class="rakeback-settings-title">
                        <i class="bi bi-table"></i>
                        Rakeback Ayarları Listesi
                    </div>
                </div>
                <div class="rakeback-settings-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="rakebackTable">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash"></i> ID</th>
                                    <th><i class="bi bi-crown"></i> VIP Seviye</th>
                                    <th><i class="bi bi-currency-exchange"></i> Min. Tutar</th>
                                    <th><i class="bi bi-percent"></i> Oran</th>
                                    <th><i class="bi bi-cash-coin"></i> Min. Çekim</th>
                                    <th><i class="bi bi-calendar"></i> Son Güncelleme</th>
                                    <th><i class="bi bi-gear"></i> İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rakeback_settings)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted"></i>
                                        <span class="text-muted">Rakeback ayarı bulunamadı.</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($rakeback_settings as $setting): ?>
                                    <?php $vipLevel = strtolower($setting['vip_level']); ?>
                                    <tr>
                                        <td>
                                            <code><?php echo $setting['id']; ?></code>
                                        </td>
                                        <td>
                                            <span class="vip-badge <?php echo $vipLevel; ?>">
                                                <i class="bi bi-crown me-1"></i>
                                                <?php echo htmlspecialchars($setting['vip_level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">
                                                <?php echo number_format($setting['min_tutar'], 2); ?> ₺
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-info">
                                                %<?php echo number_format($setting['oran'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-warning">
                                                <?php echo number_format($setting['min_cekim'], 2); ?> ₺
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y H:i', strtotime($setting['last_update'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editRakeback(<?php echo htmlspecialchars(json_encode($setting)); ?>)">
                                                <i class="bi bi-pencil"></i>
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
    </div>
</div>

<script>
let editMode = false;

function resetForm() {
    editMode = false;
    $('#rakebackForm')[0].reset();
    $('#rakeback_id').val('');
}

function editRakeback(setting) {
    editMode = true;
    
    // Form alanlarını doldur
    $('#rakeback_id').val(setting.id);
    $('#vip_level').val(setting.vip_level);
    $('#min_tutar').val(setting.min_tutar);
    $('#oran').val(setting.oran);
    $('#min_cekim').val(setting.min_cekim);
    
    // Forma scroll
    $('html, body').animate({
        scrollTop: $("#rakebackForm").offset().top - 100
    }, 500);
}

// Form submit handler
$('#rakebackForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_rakeback');
    
    Swal.fire({
        title: 'İşleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'rakeback_settings.php',
        type: 'POST',
        data: Object.fromEntries(formData),
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: response.message
                });
            }
        }
    });
});

// DataTable initialization
$(document).ready(function() {
    $('#rakebackTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "asc"]],
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
