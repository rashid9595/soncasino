<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Seviye Atlama Bonus Ayarları";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'vip_levelup_bonus' AND ap.can_view = 1
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
        // Update bonus
        if ($_POST['action'] === 'update') {
            // Validate parameters
            if (!isset($_POST['id']) || !isset($_POST['bonus_amount'])) {
                echo json_encode(['success' => false, 'message' => 'Eksik parametreler.']);
                exit;
            }
            
            $id = intval($_POST['id']);
            $bonusAmount = floatval($_POST['bonus_amount']);
            
            if ($bonusAmount < 0) {
                echo json_encode(['success' => false, 'message' => 'Bonus tutarı 0\'dan küçük olamaz.']);
                exit;
            }
            
            // Check if bonus exists
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM vip_levelup_bonus_settings WHERE id = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetchColumn() == 0) {
                echo json_encode(['success' => false, 'message' => 'Bonus bulunamadı.']);
                exit;
            }
            
            // Update the bonus
            $stmt = $db->prepare("UPDATE vip_levelup_bonus_settings SET bonus_amount = ? WHERE id = ?");
            $result = $stmt->execute([$bonusAmount, $id]);
            
            if ($result) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update_vip_levelup_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "VIP seviye atlama bonusu güncellendi. ID: {$id}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus başarıyla güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bonus güncellenirken bir hata oluştu.']);
            }
            exit;
        }
        
        // Invalid action
        echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu: ' . $e->getMessage()]);
        exit;
    }
}

// Get statistics for dashboard
try {
    // Total bonus settings
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM vip_levelup_bonus_settings");
    $stmt->execute();
    $totalBonuses = $stmt->fetch()['total'];
    
    // Total bonus value
    $stmt = $db->prepare("SELECT SUM(bonus_amount) as total FROM vip_levelup_bonus_settings");
    $stmt->execute();
    $totalBonusValue = $stmt->fetch()['total'] ?? 0;
    
    // Average bonus value
    $stmt = $db->prepare("SELECT AVG(bonus_amount) as avg_value FROM vip_levelup_bonus_settings");
    $stmt->execute();
    $avgBonusValue = $stmt->fetch()['avg_value'] ?? 0;
    
    // Maximum bonus value
    $stmt = $db->prepare("SELECT MAX(bonus_amount) as max_value FROM vip_levelup_bonus_settings");
    $stmt->execute();
    $maxBonusValue = $stmt->fetch()['max_value'] ?? 0;
    
    // Minimum bonus value
    $stmt = $db->prepare("SELECT MIN(bonus_amount) as min_value FROM vip_levelup_bonus_settings");
    $stmt->execute();
    $minBonusValue = $stmt->fetch()['min_value'] ?? 0;
    
    // Today's updates
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE action = 'update_vip_levelup_bonus' AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayUpdates = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalBonuses = $totalBonusValue = $avgBonusValue = $maxBonusValue = $minBonusValue = $todayUpdates = 0;
}

// Get all level-up bonus settings
$stmt = $db->prepare("
    SELECT * FROM vip_levelup_bonus_settings
    ORDER BY 
        CASE 
            WHEN from_level = 'Standart' THEN 1
            WHEN from_level = 'Bronz' THEN 2
            WHEN from_level = 'Gümüş' THEN 3
            WHEN from_level = 'Altın' THEN 4
            WHEN from_level = 'Platin' THEN 5
            WHEN from_level = 'Elmas' THEN 6
            ELSE 7
        END,
        CASE 
            WHEN to_level = 'Standart' THEN 1
            WHEN to_level = 'Bronz' THEN 2
            WHEN to_level = 'Gümüş' THEN 3
            WHEN to_level = 'Altın' THEN 4
            WHEN to_level = 'Platin' THEN 5
            WHEN to_level = 'Elmas' THEN 6
            ELSE 7
        END
");
$stmt->execute();
$bonusSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    .stat-card.value::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.avg-value::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.max-value::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.min-value::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.updates::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .vip-levelup-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .vip-levelup-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .vip-levelup-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .vip-levelup-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .vip-levelup-body {
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

    .level-indicator {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
        min-width: 90px;
    }

    .level-standart {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }

    .level-bronz {
        background: rgba(205, 127, 50, 0.1);
        color: #cd7f32;
    }

    .level-gümüş {
        background: rgba(170, 169, 173, 0.1);
        color: #aaa9ad;
    }

    .level-altın {
        background: rgba(255, 215, 0, 0.1);
        color: #ffd700;
    }

    .level-platin {
        background: rgba(229, 228, 226, 0.1);
        color: #e5e4e2;
    }

    .level-elmas {
        background: rgba(185, 242, 255, 0.1);
        color: #b9f2ff;
    }

    .bonus-amount {
        font-weight: 700;
        color: var(--success-green);
        font-size: 1.1rem;
    }

    .arrow-right {
        color: var(--primary-blue-light);
        font-size: 1.2rem;
        margin: 0 0.5rem;
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

        .vip-levelup-body {
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
            <i class="bi bi-arrow-up-circle"></i>
            VIP Seviye Atlama Bonus Ayarları
        </div>
        <div class="dashboard-subtitle">
            VIP seviye atlama bonuslarını yönetin
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
            <div class="stat-label">Ortalama Bonus</div>
        </div>
        
        <div class="stat-card max-value">
            <div class="stat-icon">
                <i class="bi bi-arrow-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($maxBonusValue, 2); ?> ₺</div>
            <div class="stat-label">En Yüksek Bonus</div>
        </div>
        
        <div class="stat-card min-value">
            <div class="stat-icon">
                <i class="bi bi-arrow-down"></i>
            </div>
            <div class="stat-value"><?php echo number_format($minBonusValue, 2); ?> ₺</div>
            <div class="stat-label">En Düşük Bonus</div>
        </div>
    </div>

    <!-- VIP Levelup Settings Table -->
    <div class="vip-levelup-card">
        <div class="vip-levelup-header">
            <div class="vip-levelup-title">
                <i class="bi bi-table"></i>
                VIP Seviye Atlama Bonusları
            </div>
        </div>
        <div class="vip-levelup-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="bonusTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-arrow-right"></i> Başlangıç Seviyesi</th>
                            <th><i class="bi bi-arrow-right"></i> Hedef Seviye</th>
                            <th><i class="bi bi-currency-exchange"></i> Bonus Tutarı</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bonusSettings)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">VIP seviye atlama bonusu bulunamadı.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($bonusSettings as $bonus): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo $bonus['id']; ?></span>
                                </td>
                                <td>
                                    <span class="level-indicator level-<?php echo strtolower($bonus['from_level']); ?>">
                                        <i class="bi bi-circle me-1"></i>
                                        <?php echo htmlspecialchars($bonus['from_level']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="arrow-right">
                                            <i class="bi bi-arrow-right"></i>
                                        </span>
                                        <span class="level-indicator level-<?php echo strtolower($bonus['to_level']); ?>">
                                            <i class="bi bi-circle me-1"></i>
                                            <?php echo htmlspecialchars($bonus['to_level']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="bonus-amount">
                                        <?php echo number_format($bonus['bonus_amount'], 2, ',', '.'); ?> ₺
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary edit-bonus-btn" 
                                            onclick="editBonus(<?php echo $bonus['id']; ?>, '<?php echo htmlspecialchars($bonus['from_level']); ?>', '<?php echo htmlspecialchars($bonus['to_level']); ?>', <?php echo $bonus['bonus_amount']; ?>)"
                                            data-id="<?php echo $bonus['id']; ?>"
                                            data-from-level="<?php echo htmlspecialchars($bonus['from_level']); ?>"
                                            data-to-level="<?php echo htmlspecialchars($bonus['to_level']); ?>"
                                            data-bonus-amount="<?php echo $bonus['bonus_amount']; ?>">
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

<!-- Edit Bonus Modal -->
<div class="modal fade" id="editBonusModal" tabindex="-1" aria-labelledby="editBonusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBonusModalLabel">
                    <i class="bi bi-pencil-square"></i>
                    Bonus Düzenle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <span class="level-indicator" id="modalFromLevel"></span>
                        <span class="arrow-right">
                            <i class="bi bi-arrow-right"></i>
                        </span>
                        <span class="level-indicator" id="modalToLevel"></span>
                    </div>
                </div>
                <form id="editBonusForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="bonusId">
                    
                    <div class="mb-3">
                        <label for="bonus_amount" class="form-label">Bonus Tutarı (₺)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="bonus_amount" name="bonus_amount" required>
                            <span class="input-group-text">₺</span>
                        </div>
                        <div class="form-text">Bu seviye atlaması için verilecek bonus miktarını belirleyin</div>
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
// Global function for editing bonus
function editBonus(id, fromLevel, toLevel, bonusAmount) {
    console.log('editBonus function called:', { id, fromLevel, toLevel, bonusAmount });
    
    // Set modal content
    document.getElementById('bonusId').value = id;
    document.getElementById('bonus_amount').value = bonusAmount;
    
    // Update level indicators
    const modalFromLevel = document.getElementById('modalFromLevel');
    const modalToLevel = document.getElementById('modalToLevel');
    
    modalFromLevel.className = 'level-indicator level-' + fromLevel.toLowerCase();
    modalFromLevel.innerHTML = '<i class="bi bi-circle me-1"></i>' + fromLevel;
    
    modalToLevel.className = 'level-indicator level-' + toLevel.toLowerCase();
    modalToLevel.innerHTML = '<i class="bi bi-circle me-1"></i>' + toLevel;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editBonusModal'));
    modal.show();
}

$(document).ready(function() {
    // DataTable initialization - Delay to ensure buttons are ready
    setTimeout(function() {
        $('#bonusTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
            },
            "order": [[0, "asc"]],
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
    }, 100);

    // Edit bonus button - Multiple event handlers for reliability
    $(document).on('click', '.edit-bonus-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Edit button clicked via jQuery');
        
        const id = $(this).data('id');
        const fromLevel = $(this).data('from-level');
        const toLevel = $(this).data('to-level');
        const bonusAmount = $(this).data('bonus-amount');
        
        console.log('Data:', { id, fromLevel, toLevel, bonusAmount });

        // Set modal content
        $('#bonusId').val(id);
        $('#bonus_amount').val(bonusAmount);
        
        // Update level indicators
        $('#modalFromLevel').removeClass().addClass('level-indicator level-' + fromLevel.toLowerCase()).html(
            '<i class="bi bi-circle me-1"></i>' + fromLevel
        );
        $('#modalToLevel').removeClass().addClass('level-indicator level-' + toLevel.toLowerCase()).html(
            '<i class="bi bi-circle me-1"></i>' + toLevel
        );

        // Show modal using Bootstrap 5 API
        const modal = new bootstrap.Modal(document.getElementById('editBonusModal'));
        modal.show();
    });
    
    // Alternative click handler for testing
    $(document).on('click', 'button[data-id]', function() {
        console.log('Alternative click handler triggered');
        if ($(this).hasClass('edit-bonus-btn')) {
            console.log('This is an edit button');
        }
    });
    
    // Debug: Check if buttons exist
    console.log('Edit buttons found:', $('.edit-bonus-btn').length);
    $('.edit-bonus-btn').each(function(index) {
        console.log('Button', index, ':', $(this).data());
    });

    // Save bonus button
    $('#saveBonusBtn').on('click', function() {
        const formData = new FormData($('#editBonusForm')[0]);

        $.ajax({
            url: 'vip_levelup_bonus_settings.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#editBonusModal').modal('hide');
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
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 