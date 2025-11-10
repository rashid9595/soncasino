<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Mağaza Bonus Ayarları";

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        // Add new bonus
        if ($_POST['action'] === 'add') {
            $bonusAdi = trim($_POST['bonus_adi']);
            $bonusTutari = floatval($_POST['bonus_tutari']);
            $gerekenPuan = intval($_POST['gereken_puan']);
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            // Validation
            if (empty($bonusAdi) || $bonusTutari <= 0 || $gerekenPuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Lütfen tüm alanları doldurunuz.']);
                exit;
            }
            
            $stmt = $db->prepare("INSERT INTO vip_magaza_bonuslar (bonus_adi, bonus_tutari, gereken_puan, aktif) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$bonusAdi, $bonusTutari, $gerekenPuan, $aktif]);
            
            if ($result) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'add_vip_store_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "VIP mağaza bonusu eklendi: {$bonusAdi}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus başarıyla eklendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bonus eklenirken bir hata oluştu.']);
            }
            exit;
        }
        
        // Update existing bonus
        if ($_POST['action'] === 'update') {
            $id = intval($_POST['id']);
            $bonusAdi = trim($_POST['bonus_adi']);
            $bonusTutari = floatval($_POST['bonus_tutari']);
            $gerekenPuan = intval($_POST['gereken_puan']);
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            // Validation
            if (empty($bonusAdi) || $bonusTutari <= 0 || $gerekenPuan <= 0) {
                echo json_encode(['success' => false, 'message' => 'Lütfen tüm alanları doldurunuz.']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE vip_magaza_bonuslar SET bonus_adi = ?, bonus_tutari = ?, gereken_puan = ?, aktif = ? WHERE id = ?");
            $result = $stmt->execute([$bonusAdi, $bonusTutari, $gerekenPuan, $aktif, $id]);
            
            if ($result) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update_vip_store_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "VIP mağaza bonusu güncellendi: {$bonusAdi}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus başarıyla güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bonus güncellenirken bir hata oluştu.']);
            }
            exit;
        }
        
        // Delete bonus
        if ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            
            // Get bonus name for logging
            $stmt = $db->prepare("SELECT bonus_adi FROM vip_magaza_bonuslar WHERE id = ?");
            $stmt->execute([$id]);
            $bonusName = $stmt->fetchColumn();
            
            $stmt = $db->prepare("DELETE FROM vip_magaza_bonuslar WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'delete_vip_store_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "VIP mağaza bonusu silindi: {$bonusName}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus başarıyla silindi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bonus silinirken bir hata oluştu.']);
            }
            exit;
        }
        
        // Toggle status
        if ($_POST['action'] === 'toggle_status') {
            $id = intval($_POST['id']);
            $newStatus = intval($_POST['status']);
            
            // Get bonus name for logging
            $stmt = $db->prepare("SELECT bonus_adi FROM vip_magaza_bonuslar WHERE id = ?");
            $stmt->execute([$id]);
            $bonusName = $stmt->fetchColumn();
            
            $stmt = $db->prepare("UPDATE vip_magaza_bonuslar SET aktif = ? WHERE id = ?");
            $result = $stmt->execute([$newStatus, $id]);
            
            if ($result) {
                // Log activity
                $statusText = $newStatus == 1 ? 'aktif' : 'pasif';
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'toggle_vip_store_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "VIP mağaza bonusu durumu değiştirildi: {$bonusName} -> {$statusText}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus durumu başarıyla güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bonus durumu güncellenirken bir hata oluştu.']);
            }
            exit;
        }
        
        // If we get here, it's an invalid action
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
        exit;
    }
}

// Get statistics for dashboard
try {
    // Total bonus items
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM vip_magaza_bonuslar");
    $stmt->execute();
    $totalBonuses = $stmt->fetch()['total'];
    
    // Active bonus items
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM vip_magaza_bonuslar WHERE aktif = 1");
    $stmt->execute();
    $activeBonuses = $stmt->fetch()['total'];
    
    // Total bonus value
    $stmt = $db->prepare("SELECT SUM(bonus_tutari) as total FROM vip_magaza_bonuslar WHERE aktif = 1");
    $stmt->execute();
    $totalBonusValue = $stmt->fetch()['total'] ?? 0;
    
    // Average bonus value
    $stmt = $db->prepare("SELECT AVG(bonus_tutari) as avg_value FROM vip_magaza_bonuslar WHERE aktif = 1");
    $stmt->execute();
    $avgBonusValue = $stmt->fetch()['avg_value'] ?? 0;
    
    // Total required points
    $stmt = $db->prepare("SELECT SUM(gereken_puan) as total FROM vip_magaza_bonuslar WHERE aktif = 1");
    $stmt->execute();
    $totalRequiredPoints = $stmt->fetch()['total'] ?? 0;
    
    // Average required points
    $stmt = $db->prepare("SELECT AVG(gereken_puan) as avg_points FROM vip_magaza_bonuslar WHERE aktif = 1");
    $stmt->execute();
    $avgRequiredPoints = $stmt->fetch()['avg_points'] ?? 0;
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalBonuses = $activeBonuses = $totalBonusValue = $avgBonusValue = $totalRequiredPoints = $avgRequiredPoints = 0;
}

// Get all bonus items
$stmt = $db->prepare("SELECT * FROM vip_magaza_bonuslar ORDER BY gereken_puan ASC");
$stmt->execute();
$bonusItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    .stat-card.active::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.value::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.avg-value::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.points::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.avg-points::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.active {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
    }

    .status-badge.inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-red);
    }

    .points-badge {
        background: var(--primary-blue-light);
        color: var(--white);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-block;
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

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%);
        color: var(--white);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%);
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
            VIP Mağaza Bonus Ayarları
        </div>
        <div class="dashboard-subtitle">
            VIP üyelere özel mağaza bonuslarını yönetin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="bi bi-gift"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBonuses); ?></div>
            <div class="stat-label">Toplam Bonus</div>
        </div>
        
        <div class="stat-card active">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($activeBonuses); ?></div>
            <div class="stat-label">Aktif Bonus</div>
        </div>
        
        <div class="stat-card value">
            <div class="stat-icon">
                <i class="bi bi-currency-exchange"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBonusValue, 2); ?> ₺</div>
            <div class="stat-label">Toplam Bonus Değeri</div>
        </div>
        
        <div class="stat-card avg-value">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($avgBonusValue, 2); ?> ₺</div>
            <div class="stat-label">Ortalama Bonus Değeri</div>
        </div>
        
        <div class="stat-card points">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalRequiredPoints); ?></div>
            <div class="stat-label">Toplam Gereken Puan</div>
        </div>
        
    </div>

    <!-- VIP Store Settings Table -->
    <div class="vip-store-card">
        <div class="vip-store-header">
            <div class="vip-store-title">
                <i class="bi bi-table"></i>
                VIP Mağaza Bonusları
            </div>
            <button type="button" class="btn btn-success add-bonus-btn">
                <i class="bi bi-plus-circle"></i>
                Yeni Bonus Ekle
            </button>
        </div>
        <div class="vip-store-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="bonusTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-gift"></i> Bonus Adı</th>
                            <th><i class="bi bi-currency-exchange"></i> Bonus Tutarı</th>
                            <th><i class="bi bi-star"></i> Gereken Puan</th>
                            <th><i class="bi bi-toggle-on"></i> Durum</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bonusItems)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">VIP mağaza bonusu bulunamadı.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($bonusItems as $item): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo $item['id']; ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold">
                                        <?php echo htmlspecialchars($item['bonus_adi']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">
                                        <?php echo number_format($item['bonus_tutari'], 2); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <span class="points-badge">
                                        <i class="bi bi-star me-1"></i>
                                        <?php echo number_format($item['gereken_puan']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo ($item['aktif'] == 1) ? 'active' : 'inactive'; ?>">
                                        <i class="bi bi-<?php echo ($item['aktif'] == 1) ? 'check-circle' : 'x-circle'; ?> me-1"></i>
                                        <?php echo ($item['aktif'] == 1) ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-primary edit-bonus-btn" 
                                                data-id="<?php echo $item['id']; ?>"
                                                data-bonus-adi="<?php echo htmlspecialchars($item['bonus_adi']); ?>"
                                                data-bonus-tutari="<?php echo $item['bonus_tutari']; ?>"
                                                data-gereken-puan="<?php echo $item['gereken_puan']; ?>"
                                                data-aktif="<?php echo $item['aktif']; ?>">
                                            <i class="bi bi-pencil"></i>
                                            Düzenle
                                        </button>
                                        <button type="button" class="btn btn-sm btn-<?php echo ($item['aktif'] == 1) ? 'warning' : 'success'; ?> toggle-status-btn" 
                                                data-id="<?php echo $item['id']; ?>" 
                                                data-status="<?php echo ($item['aktif'] == 1) ? '0' : '1'; ?>">
                                            <i class="bi bi-<?php echo ($item['aktif'] == 1) ? 'pause' : 'play'; ?>"></i>
                                            <?php echo ($item['aktif'] == 1) ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-bonus-btn" 
                                                data-id="<?php echo $item['id']; ?>"
                                                data-bonus-adi="<?php echo htmlspecialchars($item['bonus_adi']); ?>">
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

<!-- Add/Edit Bonus Modal -->
<div class="modal fade" id="bonusModal" tabindex="-1" aria-labelledby="bonusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bonusModalLabel">
                    <i class="bi bi-plus-circle"></i>
                    Yeni Bonus Ekle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bonusForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="bonusId">
                    
                    <div class="mb-3">
                        <label for="bonus_adi" class="form-label">Bonus Adı</label>
                        <input type="text" class="form-control" id="bonus_adi" name="bonus_adi" required>
                        <div class="form-text">Bonus için açıklayıcı bir isim girin</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bonus_tutari" class="form-label">Bonus Tutarı (₺)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="bonus_tutari" name="bonus_tutari" required>
                            <span class="input-group-text">₺</span>
                        </div>
                        <div class="form-text">Kullanıcılara verilecek bonus miktarını belirleyin</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gereken_puan" class="form-label">Gereken Puan</label>
                        <div class="input-group">
                            <input type="number" min="1" class="form-control" id="gereken_puan" name="gereken_puan" required>
                            <span class="input-group-text">puan</span>
                        </div>
                        <div class="form-text">Bu bonusu almak için gereken VIP puan miktarını belirleyin</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="aktif" name="aktif" checked>
                            <label class="form-check-label" for="aktif">
                                Bonus Aktif
                            </label>
                        </div>
                        <div class="form-text">Bu bonusu kullanıcıların görebilmesi için aktif yapın</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i>
                    İptal
                </button>
                <button type="button" class="btn btn-primary" id="saveBonusBtn">
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
    $('#bonusTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[3, "asc"]],
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

    // Add new bonus button
    $('.add-bonus-btn').on('click', function() {
        $('#bonusModalLabel').html('<i class="bi bi-plus-circle"></i> Yeni Bonus Ekle');
        $('#formAction').val('add');
        $('#bonusForm')[0].reset();
        $('#bonusId').val('');
        $('#bonusModal').modal('show');
    });

    // Edit bonus button
    $(document).on('click', '.edit-bonus-btn', function() {
        const id = $(this).data('id');
        const bonusAdi = $(this).data('bonus-adi');
        const bonusTutari = $(this).data('bonus-tutari');
        const gerekenPuan = $(this).data('gereken-puan');
        const aktif = $(this).data('aktif');

        $('#bonusModalLabel').html('<i class="bi bi-pencil-square"></i> Bonus Düzenle');
        $('#formAction').val('update');
        $('#bonusId').val(id);
        $('#bonus_adi').val(bonusAdi);
        $('#bonus_tutari').val(bonusTutari);
        $('#gereken_puan').val(gerekenPuan);
        $('#aktif').prop('checked', aktif == 1);
        $('#bonusModal').modal('show');
    });

    // Save bonus button
    $('#saveBonusBtn').on('click', function() {
        const formData = new FormData($('#bonusForm')[0]);
        formData.append('aktif', $('#aktif').is(':checked') ? '1' : '0');

        $.ajax({
            url: 'vip_store_settings.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#bonusModal').modal('hide');
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
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: 'Sunucu hatası! Lütfen daha sonra tekrar deneyin.'
                });
            }
        });
    });

    // Toggle status button
    $(document).on('click', '.toggle-status-btn', function() {
        const id = $(this).data('id');
        const status = $(this).data('status');
        const statusText = status == 1 ? 'aktif' : 'pasif';

        Swal.fire({
            title: 'Emin misiniz?',
            text: `Bu bonusu ${statusText} durumuna geçirmek istediğinize emin misiniz?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, Değiştir',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'vip_store_settings.php',
                    type: 'POST',
                    data: {
                        action: 'toggle_status',
                        id: id,
                        status: status
                    },
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
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: 'Sunucu hatası! Lütfen daha sonra tekrar deneyin.'
                        });
                    }
                });
            }
        });
    });

    // Delete bonus button
    $(document).on('click', '.delete-bonus-btn', function() {
        const id = $(this).data('id');
        const bonusAdi = $(this).data('bonus-adi');

        Swal.fire({
            title: 'Emin misiniz?',
            text: `"${bonusAdi}" bonusunu silmek istediğinize emin misiniz? Bu işlem geri alınamaz!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#ef4444'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'vip_store_settings.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi',
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
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: 'Sunucu hatası! Lütfen daha sonra tekrar deneyin.'
                        });
                    }
                });
            }
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 