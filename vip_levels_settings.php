<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Seviye Ayarları";

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
        WHERE ap.role_id = ? AND ap.menu_item = 'vip_settings' AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['edit']) || !$userPermissions['vip_settings']['edit'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    UPDATE pages_vip_levels 
                    SET level_name = ?, 
                        required_turnover = ?
                    WHERE id = ?
                ");
                
                try {
                    $stmt->execute([
                        $_POST['level_name'],
                        $_POST['required_turnover'],
                        $_POST['id']
                    ]);
                    echo json_encode(['status' => 'success', 'message' => 'VIP seviyesi başarıyla güncellendi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'VIP seviyesi güncellenirken bir hata oluştu.']);
                }
                break;

            case 'edit_feature':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['edit']) || !$userPermissions['vip_settings']['edit'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    UPDATE pages_vip_level_features 
                    SET feature_text = ?
                    WHERE id = ? AND level_id = ?
                ");
                
                try {
                    $stmt->execute([
                        $_POST['feature_text'],
                        $_POST['feature_id'],
                        $_POST['level_id']
                    ]);
                    echo json_encode(['status' => 'success', 'message' => 'Özellik başarıyla güncellendi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Özellik güncellenirken bir hata oluştu.']);
                }
                break;
                
            case 'get':
                $stmt = $db->prepare("
                    SELECT l.*, GROUP_CONCAT(f.feature_text ORDER BY f.feature_order ASC SEPARATOR '||') as features,
                           GROUP_CONCAT(f.id ORDER BY f.feature_order ASC SEPARATOR '||') as feature_ids
                    FROM pages_vip_levels l 
                    LEFT JOIN pages_vip_level_features f ON l.id = f.level_id 
                    WHERE l.id = ?
                    GROUP BY l.id
                ");
                $stmt->execute([$_POST['id']]);
                $level = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($level) {
                    $level['features'] = $level['features'] ? explode('||', $level['features']) : [];
                    $level['feature_ids'] = $level['feature_ids'] ? explode('||', $level['feature_ids']) : [];
                }
                echo json_encode(['status' => 'success', 'data' => $level]);
                break;

            case 'edit_title':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['edit']) || !$userPermissions['vip_settings']['edit'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    UPDATE pages_vip_section_titles 
                    SET levels_title = ?
                    WHERE id = 1
                ");
                
                try {
                    $stmt->execute([$_POST['levels_title']]);
                    echo json_encode(['status' => 'success', 'message' => 'Başlık başarıyla güncellendi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Başlık güncellenirken bir hata oluştu.']);
                }
                break;
        }
        exit();
    }
}

// Get statistics
try {
    // Total VIP levels
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_levels");
    $stmt->execute();
    $totalLevels = $stmt->fetch()['total'];
    
    // Total features across all levels
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_level_features");
    $stmt->execute();
    $totalFeatures = $stmt->fetch()['total'];
    
    // Average features per level
    $stmt = $db->prepare("SELECT AVG(feature_count) as avg_features FROM (
        SELECT COUNT(*) as feature_count 
        FROM pages_vip_level_features 
        GROUP BY level_id
    ) as feature_counts");
    $stmt->execute();
    $avgFeatures = $stmt->fetch()['avg_features'];
    $avgFeatures = $avgFeatures ? round($avgFeatures, 1) : 0;
    
    // Total required turnover across all levels
    $stmt = $db->prepare("SELECT SUM(required_turnover) as total_turnover FROM pages_vip_levels");
    $stmt->execute();
    $totalTurnover = $stmt->fetch()['total_turnover'];
    $totalTurnover = $totalTurnover ? $totalTurnover : 0;
    
    // Average required turnover
    $stmt = $db->prepare("SELECT AVG(required_turnover) as avg_turnover FROM pages_vip_levels");
    $stmt->execute();
    $avgTurnover = $stmt->fetch()['avg_turnover'];
    $avgTurnover = $avgTurnover ? round($avgTurnover, 2) : 0;
    
    // Highest required turnover
    $stmt = $db->prepare("SELECT MAX(required_turnover) as max_turnover FROM pages_vip_levels");
    $stmt->execute();
    $maxTurnover = $stmt->fetch()['max_turnover'];
    $maxTurnover = $maxTurnover ? $maxTurnover : 0;
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalLevels = $totalFeatures = $avgFeatures = $totalTurnover = $avgTurnover = $maxTurnover = 0;
}

// Get section title
try {
    $stmt = $db->prepare("SELECT levels_title FROM pages_vip_section_titles WHERE id = 1");
    $stmt->execute();
    $sectionTitle = $stmt->fetchColumn();
    if (!$sectionTitle) {
        $sectionTitle = "VIP Seviye Ayarları";
    }
} catch (PDOException $e) {
    $sectionTitle = "VIP Seviye Ayarları";
}

// Get all VIP Levels with their features
try {
    $stmt = $db->prepare("
        SELECT 
            l.*,
            GROUP_CONCAT(f.id ORDER BY f.feature_order ASC SEPARATOR '||') as feature_ids,
            GROUP_CONCAT(f.feature_text ORDER BY f.feature_order ASC SEPARATOR '||') as features
        FROM pages_vip_levels l
        LEFT JOIN pages_vip_level_features f ON l.id = f.level_id
        GROUP BY l.id
        ORDER BY l.level_order ASC
    ");
    $stmt->execute();
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process features for each level
    foreach ($levels as &$level) {
        $level['features'] = $level['features'] ? explode('||', $level['features']) : [];
        $level['feature_ids'] = $level['feature_ids'] ? explode('||', $level['feature_ids']) : [];
    }
} catch (PDOException $e) {
    $error = "VIP seviyeleri yüklenirken bir hata oluştu: " . $e->getMessage();
    $levels = [];
}

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
    
    .stat-card.features::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.avg-features::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.total-turnover::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.avg-turnover::after {
        background: var(--primary-gradient);
    }

    .stat-card.max-turnover::after {
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
    
    .stat-card.features .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.avg-features .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.total-turnover .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.avg-turnover .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.max-turnover .stat-icon {
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

    .levels-container {
        margin-bottom: 2rem;
    }

    .level-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 1.5rem;
        position: relative;
    }

    .level-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .level-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .level-info h6 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        margin-bottom: 0.5rem;
    }
    
    .turnover-info {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
    }
    
    .turnover-info i {
        color: var(--primary-blue-light);
        font-size: 1.1rem;
    }
    
    .turnover-label {
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .turnover-value {
        color: var(--text-heading);
        font-weight: 600;
    }

    .level-body {
        padding: 1.5rem;
    }

    .features-section {
        background: var(--bg-secondary);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        overflow: hidden;
    }

    .features-header {
        background: var(--ultra-light-blue);
        padding: 1rem 1.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--card-border);
    }

    .features-header h6 {
        margin: 0;
        color: var(--text-heading);
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .features-header i {
        color: var(--primary-blue-light);
        margin-right: 0.5rem;
    }

    .features-content {
        padding: 1.5rem;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 1rem;
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }

    .feature-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }

    .feature-item:last-child {
        margin-bottom: 0;
    }

    .feature-order {
        min-width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--primary-gradient);
        border-radius: 50%;
        color: white;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .feature-text {
        flex-grow: 1;
        color: var(--text-primary);
        font-weight: 500;
    }

    .feature-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .btn-secondary {
        background: var(--secondary-gradient);
        color: var(--white);
    }

    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        margin: 0 0.25rem;
        transition: all 0.3s ease;
    }

    .btn-icon:hover {
        transform: translateY(-2px);
    }

    .btn-icon i {
        font-size: 1rem;
    }

    .alert {
        border-radius: var(--border-radius);
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
        animation: fadeIn 0.5s ease;
    }

    .alert-success {
        background: #dcfce7;
        border-left: 4px solid var(--success-green);
        color: #166534;
    }

    .alert-danger {
        background: #fee2e2;
        border-left: 4px solid var(--error-red);
        color: #7f1d1d;
    }

    .alert-info {
        background: var(--light-blue);
        border-left: 4px solid var(--info-blue);
        color: var(--primary-blue-dark);
    }

    .modal-content {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
    }

    .modal-header {
        border-bottom: 1px solid var(--card-border);
        background: var(--primary-gradient);
        color: white;
    }

    .modal-footer {
        border-top: 1px solid var(--card-border);
        background: var(--bg-secondary);
    }

    .modal .form-label {
        font-weight: 500;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal .form-label i {
        width: 20px;
        text-align: center;
        color: var(--primary-blue-light);
    }

    .modal .alert-info {
        background: var(--light-blue);
        border: 1px solid var(--accent-blue);
        color: var(--primary-blue-dark);
        font-size: 0.875rem;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        border-radius: 8px;
    }

    .modal .alert-info i {
        color: var(--primary-blue-light);
    }

    .modal .form-control {
        background-color: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }

    .modal .form-control:focus {
        background-color: var(--card-bg);
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        color: var(--text-primary);
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
        
        .level-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .features-header {
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-trophy-fill me-2"></i>
        <?php echo htmlspecialchars($sectionTitle); ?>
    </h1>
    <p class="dashboard-subtitle">VIP seviyelerini ve özelliklerini yönetin ve düzenleyin.</p>
    <?php if ($isAdmin || (isset($userPermissions['vip_settings']['edit']) && $userPermissions['vip_settings']['edit'])): ?>
    <button type="button" class="btn btn-secondary btn-sm" onclick="showEditTitleModal()" style="margin-top: 1rem;">
        <i class="bi bi-pencil me-1"></i>Başlığı Düzenle
    </button>
    <?php endif; ?>
</div>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-trophy"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalLevels); ?></div>
        <div class="stat-title">Toplam Seviye</div>
    </div>
    
    <div class="stat-card features">
        <div class="stat-icon">
            <i class="bi bi-list-check"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalFeatures); ?></div>
        <div class="stat-title">Toplam Özellik</div>
    </div>
    
    <div class="stat-card total-turnover">
        <div class="stat-icon">
            <i class="bi bi-currency-exchange"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalTurnover, 0, ',', '.'); ?></div>
        <div class="stat-title">Toplam Ciro</div>
    </div>
    
    <div class="stat-card avg-turnover">
        <div class="stat-icon">
            <i class="bi bi-calculator"></i>
        </div>
        <div class="stat-number"><?php echo number_format($avgTurnover, 0, ',', '.'); ?></div>
        <div class="stat-title">Ortalama Ciro</div>
    </div>
    
    <div class="stat-card max-turnover">
        <div class="stat-icon">
            <i class="bi bi-arrow-up-circle"></i>
        </div>
        <div class="stat-number"><?php echo number_format($maxTurnover, 0, ',', '.'); ?></div>
        <div class="stat-title">En Yüksek Ciro</div>
    </div>
</div>

<div class="levels-container animate-fade-in" style="animation-delay: 0.2s">
    <?php if (empty($levels)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        Henüz VIP seviyesi eklenmemiş.
    </div>
    <?php else: ?>
        <?php foreach ($levels as $level): ?>
        <div class="level-card">
            <div class="level-header">
                <div class="level-info">
                    <h6><?php echo htmlspecialchars($level['level_name']); ?></h6>
                    <div class="turnover-info">
                        <i class="bi bi-info-circle" data-bs-toggle="tooltip" title="Bu seviyeye ulaşmak için gerekli minimum çevrim miktarı"></i>
                        <span class="turnover-label">Gerekli Çevrim Şartı:</span>
                        <span class="turnover-value"><?php echo number_format($level['required_turnover'], 2, ',', '.') . ' ₺'; ?></span>
                    </div>
                </div>
                <?php if ($isAdmin || (isset($userPermissions['vip_settings']['edit']) && $userPermissions['vip_settings']['edit'])): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="showEditModal(<?php echo $level['id']; ?>)">
                    <i class="bi bi-pencil me-1"></i>Düzenle
                </button>
                <?php endif; ?>
            </div>
            <div class="level-body">
                <div class="features-section">
                    <div class="features-header" data-bs-toggle="collapse" data-bs-target="#features-<?php echo $level['id']; ?>">
                        <h6><i class="bi bi-list-check"></i>Seviye Özellikleri</h6>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="features-content collapse" id="features-<?php echo $level['id']; ?>">
                        <?php if (empty($level['features'])): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Bu seviye için henüz özellik eklenmemiş.
                        </div>
                        <?php else: ?>
                            <?php foreach ($level['features'] as $index => $feature): ?>
                            <div class="feature-item">
                                <div class="feature-order"><?php echo $index + 1; ?></div>
                                <div class="feature-text"><?php echo htmlspecialchars($feature); ?></div>
                                <?php if ($isAdmin || (isset($userPermissions['vip_settings']['edit']) && $userPermissions['vip_settings']['edit'])): ?>
                                <div class="feature-actions">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="editFeature(<?php echo $level['id']; ?>, <?php echo $level['feature_ids'][$index]; ?>, '<?php echo addslashes($feature); ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Edit Level Modal -->
<div class="modal fade" id="levelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Seviye Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="levelForm">
                    <input type="hidden" id="levelId" name="id">
                    <input type="hidden" id="action" name="action" value="edit">
                    
                    <div class="form-group mb-4">
                        <label for="level_name" class="form-label">
                            <i class="bi bi-tag" style="color: var(--primary-blue-light);"></i>Seviye Adı
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="level_name" 
                               name="level_name" 
                               required>
                        <div class="alert alert-info mt-2">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Seviye adını büyük harflerle yazınız.
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="required_turnover" class="form-label">
                            <i class="bi bi-currency-exchange" style="color: var(--primary-blue-light);"></i>Gerekli Ciro
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="required_turnover" 
                               name="required_turnover" 
                               step="0.01"
                               min="0"
                               required>
                        <div class="alert alert-info mt-2">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Ciro miktarını TL cinsinden giriniz.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>İptal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveLevel()">
                    <i class="bi bi-save me-2"></i>Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Feature Modal -->
<div class="modal fade" id="featureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Özellik Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="featureForm">
                    <input type="hidden" id="featureLevelId" name="level_id">
                    <input type="hidden" id="featureId" name="feature_id">
                    <input type="hidden" name="action" value="edit_feature">
                    
                    <div class="form-group">
                        <label for="feature_text" class="form-label">
                            <i class="bi bi-list-check" style="color: var(--primary-blue-light);"></i>Özellik Metni
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="feature_text" 
                               name="feature_text" 
                               required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>İptal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveFeature()">
                    <i class="bi bi-save me-2"></i>Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Title Modal -->
<div class="modal fade" id="titleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Başlığı Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="titleForm">
                    <input type="hidden" name="action" value="edit_title">
                    
                    <div class="form-group">
                        <label for="levels_title" class="form-label">
                            <i class="bi bi-type-h1" style="color: var(--primary-blue-light);"></i>Sayfa Başlığı
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="levels_title" 
                               name="levels_title" 
                               value="<?php echo htmlspecialchars($sectionTitle); ?>"
                               required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>İptal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveTitle()">
                    <i class="bi bi-save me-2"></i>Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#levelsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "asc"]],
        "pageLength": 25,
        "columnDefs": [
            { "orderable": false, "targets": [3, 4] }
        ]
    });
});

function showEditModal(id) {
    $('#modalTitle').text('Seviye Düzenle');
    $('#levelId').val(id);
    $('#action').val('edit');
    
    $.post('vip_levels_settings.php', {
        action: 'get',
        id: id
    }, function(response) {
        if (response.status === 'success') {
            $('#level_name').val(response.data.level_name);
            $('#required_turnover').val(response.data.required_turnover);
            
            // Display features
            const featuresList = $('#featuresList');
            featuresList.empty();
            
            if (response.data.features && response.data.features.length > 0) {
                response.data.features.forEach(function(feature) {
                    featuresList.append(`<li>${feature}</li>`);
                });
            }
            
            $('#levelModal').modal('show');
        } else {
            Swal.fire('Hata!', response.message, 'error');
        }
    });
}

function saveLevel() {
    const formData = new FormData($('#levelForm')[0]);
    
    $.ajax({
        url: 'vip_levels_settings.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Hata!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Hata!', 'Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
        }
    });
}

function editFeature(levelId, featureId, featureText) {
    $('#featureLevelId').val(levelId);
    $('#featureId').val(featureId);
    $('#feature_text').val(featureText);
    $('#featureModal').modal('show');
}

function saveFeature() {
    const formData = new FormData($('#featureForm')[0]);
    
    $.ajax({
        url: 'vip_levels_settings.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Hata!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Hata!', 'Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
        }
    });
}

// Initialize collapse functionality
document.addEventListener('DOMContentLoaded', function() {
    const featureHeaders = document.querySelectorAll('.features-header');
    featureHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const icon = this.querySelector('.bi-chevron-down');
            icon.style.transform = this.getAttribute('aria-expanded') === 'true' ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    });
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function showEditTitleModal() {
    $('#titleModal').modal('show');
}

function saveTitle() {
    const formData = new FormData($('#titleForm')[0]);
    
    $.ajax({
        url: 'vip_levels_settings.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Hata!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Hata!', 'Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 