<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Kulübü Avantajları";

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
            case 'add':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['add']) || !$userPermissions['vip_settings']['add'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    INSERT INTO pages_vip_advantages (advantage_title, advantage_description, advantage_icon, advantage_order)
                    VALUES (?, ?, ?, ?)
                ");
                
                try {
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['icon'],
                        $_POST['order']
                    ]);
                    echo json_encode(['status' => 'success', 'message' => 'Avantaj başarıyla eklendi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Avantaj eklenirken bir hata oluştu.']);
                }
                break;
                
            case 'edit':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['edit']) || !$userPermissions['vip_settings']['edit'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    UPDATE pages_vip_advantages 
                    SET advantage_title = ?, 
                        advantage_description = ?, 
                        advantage_icon = ?, 
                        advantage_order = ?
                    WHERE id = ?
                ");
                
                try {
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['icon'],
                        $_POST['order'],
                        $_POST['id']
                    ]);
                    echo json_encode(['status' => 'success', 'message' => 'Avantaj başarıyla güncellendi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Avantaj güncellenirken bir hata oluştu.']);
                }
                break;
                
            case 'delete':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['delete']) || !$userPermissions['vip_settings']['delete'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("DELETE FROM pages_vip_advantages WHERE id = ?");
                
                try {
                    $stmt->execute([$_POST['id']]);
                    echo json_encode(['status' => 'success', 'message' => 'Avantaj başarıyla silindi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Avantaj silinirken bir hata oluştu.']);
                }
                break;
                
            case 'get':
                $stmt = $db->prepare("SELECT * FROM pages_vip_advantages WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $advantage = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $advantage]);
                break;
                
            case 'edit_title':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['edit']) || !$userPermissions['vip_settings']['edit'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    UPDATE pages_vip_section_titles 
                    SET advantages_title = ?
                    WHERE id = 1
                ");
                
                try {
                    $stmt->execute([$_POST['advantages_title']]);
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
    // Total advantages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_advantages");
    $stmt->execute();
    $totalAdvantages = $stmt->fetch()['total'];
    
    // Active advantages (with icons)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_advantages WHERE advantage_icon IS NOT NULL AND advantage_icon != ''");
    $stmt->execute();
    $activeAdvantages = $stmt->fetch()['total'];
    
    // Advantages with descriptions
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_advantages WHERE advantage_description IS NOT NULL AND advantage_description != ''");
    $stmt->execute();
    $describedAdvantages = $stmt->fetch()['total'];
    
    // Average order number
    $stmt = $db->prepare("SELECT AVG(advantage_order) as avg_order FROM pages_vip_advantages");
    $stmt->execute();
    $avgOrder = $stmt->fetch()['avg_order'];
    $avgOrder = $avgOrder ? round($avgOrder, 1) : 0;
    
    // Highest order number
    $stmt = $db->prepare("SELECT MAX(advantage_order) as max_order FROM pages_vip_advantages");
    $stmt->execute();
    $maxOrder = $stmt->fetch()['max_order'];
    $maxOrder = $maxOrder ? $maxOrder : 0;
    
    // Recently added advantages (last 30 days)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_advantages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recentAdvantages = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalAdvantages = $activeAdvantages = $describedAdvantages = $avgOrder = $maxOrder = $recentAdvantages = 0;
}

// Get all advantages
try {
    $stmt = $db->prepare("SELECT * FROM pages_vip_advantages ORDER BY advantage_order ASC");
    $stmt->execute();
    $advantages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Avantajlar yüklenirken bir hata oluştu: " . $e->getMessage();
    $advantages = [];
}

// Get section title
try {
    $stmt = $db->prepare("SELECT advantages_title FROM pages_vip_section_titles WHERE id = 1");
    $stmt->execute();
    $sectionTitle = $stmt->fetchColumn();
    if (!$sectionTitle) {
        $sectionTitle = "VIP Kulübü Avantajları";
    }
} catch (PDOException $e) {
    $sectionTitle = "VIP Kulübü Avantajları";
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
    
    .stat-card.active::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.described::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.avg-order::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.max-order::after {
        background: var(--primary-gradient);
    }

    .stat-card.recent::after {
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
    
    .stat-card.active .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.described .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.avg-order .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.max-order .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.recent .stat-icon {
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

    .advantages-table-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .advantages-table-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .advantages-table-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .advantages-table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
    }
    
    .advantages-table-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .advantages-table-body {
        padding: 1.5rem;
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

    .btn-info {
        background: var(--info-blue);
        color: var(--white);
    }

    .btn-info:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .btn-danger {
        background: var(--error-red);
        color: var(--white);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-danger);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    .btn-icon {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        margin: 0 0.25rem;
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

    .advantage-icon {
        width: 50px;
        height: 50px;
        object-fit: contain;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
    }

    .order-column {
        width: 80px;
    }

    .actions-column {
        width: 120px;
    }

    .icon-column {
        width: 100px;
    }

    .modal-lg {
        max-width: 800px;
    }

    .modal .form-group {
        margin-bottom: 1rem;
    }

    .modal .form-label {
        font-weight: 500;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal .form-label small {
        font-weight: 500;
        opacity: 1;
        background: var(--light-blue);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        color: var(--primary-blue-dark);
        border: 1px solid var(--accent-blue);
        font-size: 0.8rem;
        display: inline-block;
        margin-left: 0.5rem;
        transition: all 0.2s ease;
    }

    .modal .form-label small:hover {
        background: var(--accent-blue);
        border-color: var(--primary-blue-light);
    }

    .modal .form-text {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .modal .input-group-text {
        background: var(--bg-secondary);
        border-color: var(--card-border);
        color: var(--text-primary);
    }

    .modal .alert {
        background: var(--light-blue);
        border-color: var(--accent-blue);
    }

    .modal .alert i {
        color: var(--primary-blue-light);
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

    .modal .btn i {
        margin-right: 0.5rem;
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
        
        .advantages-table-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-star-fill me-2"></i>
        <?php echo htmlspecialchars($sectionTitle); ?>
    </h1>
    <p class="dashboard-subtitle">VIP üyelerinizin avantajlarını yönetin ve düzenleyin.</p>
    <?php if ($isAdmin || (isset($userPermissions['vip_settings']['edit']) && $userPermissions['vip_settings']['edit'])): ?>
    <button type="button" class="btn btn-secondary btn-sm" onclick="showEditTitleModal()" style="margin-top: 1rem;">
        <i class="bi bi-pencil me-1"></i>Başlığı Düzenle
    </button>
    <?php endif; ?>
</div>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-star"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalAdvantages); ?></div>
        <div class="stat-title">Toplam Avantaj</div>
    </div>
    
    <div class="stat-card active">
        <div class="stat-icon">
            <i class="bi bi-image-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($activeAdvantages); ?></div>
        <div class="stat-title">Aktif (İkonlu)</div>
    </div>
    
    <div class="stat-card described">
        <div class="stat-icon">
            <i class="bi bi-text-paragraph"></i>
        </div>
        <div class="stat-number"><?php echo number_format($describedAdvantages); ?></div>
        <div class="stat-title">Açıklamalı</div>
    </div>
    
    <div class="stat-card avg-order">
        <div class="stat-icon">
            <i class="bi bi-sort-numeric-down"></i>
        </div>
        <div class="stat-number"><?php echo $avgOrder; ?></div>
        <div class="stat-title">Ortalama Sıra</div>
    </div>
    
    <div class="stat-card max-order">
        <div class="stat-icon">
            <i class="bi bi-arrow-up-circle"></i>
        </div>
        <div class="stat-number"><?php echo $maxOrder; ?></div>
        <div class="stat-title">En Yüksek Sıra</div>
    </div>
</div>

<div class="advantages-table-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="advantages-table-header">
        <h5 class="advantages-table-title">
            <i class="bi bi-table"></i>
            VIP Avantajları Listesi
        </h5>
        <?php if ($isAdmin || (isset($userPermissions['vip_settings']['add']) && $userPermissions['vip_settings']['add'])): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="showAddModal()">
            <i class="bi bi-plus-lg me-1"></i> Yeni Avantaj Ekle
        </button>
        <?php endif; ?>
    </div>
    <div class="advantages-table-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="advantagesTable">
                <thead>
                    <tr>
                        <th class="order-column">Sıra</th>
                        <th class="icon-column">İkon</th>
                        <th>Başlık</th>
                        <th>Açıklama</th>
                        <th class="actions-column">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($advantages)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Henüz VIP avantajı eklenmemiş.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($advantages as $advantage): ?>
                        <tr>
                            <td class="text-center">
                                <span class="badge" style="background: var(--primary-blue-light); color: white; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($advantage['advantage_order']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <img src="<?php echo htmlspecialchars($advantage['advantage_icon']); ?>" alt="<?php echo htmlspecialchars($advantage['advantage_title']); ?>" class="advantage-icon">
                            </td>
                            <td>
                                <span class="fw-semibold" style="color: var(--text-heading);">
                                    <?php echo htmlspecialchars($advantage['advantage_title']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-muted">
                                    <?php echo htmlspecialchars($advantage['advantage_description']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($isAdmin || (isset($userPermissions['vip_settings']['edit']) && $userPermissions['vip_settings']['edit'])): ?>
                                <button type="button" class="btn btn-primary btn-icon" onclick="showEditModal(<?php echo $advantage['id']; ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($isAdmin || (isset($userPermissions['vip_settings']['delete']) && $userPermissions['vip_settings']['delete'])): ?>
                                <button type="button" class="btn btn-danger btn-icon" onclick="deleteAdvantage(<?php echo $advantage['id']; ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="advantageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Avantaj Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="advantageForm">
                    <input type="hidden" id="advantageId" name="id">
                    <input type="hidden" id="action" name="action" value="add">
                    
                    <div class="form-group mb-4">
                        <label for="title" class="form-label">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-type-h1"></i>
                                <span class="ms-2">Başlık</span>
                            </div>
                            <div class="alert alert-info mt-2 mb-2" role="alert">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                VIP avantajının ana başlığı
                            </div>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="title" 
                               name="title" 
                               placeholder="Örn: %10 Kayıp Bonusu"
                               maxlength="100"
                               required>
                        <div class="alert alert-info mt-2" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Kısa ve açıklayıcı bir başlık giriniz.
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="description" class="form-label">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-text-paragraph"></i>
                                <span class="ms-2">Açıklama</span>
                            </div>
                            <div class="alert alert-info mt-2 mb-2" role="alert">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Avantajın detaylı açıklaması
                            </div>
                        </label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="4" 
                                  placeholder="Örn: VIP üyelerimiz her ay düzenli olarak kayıplarının %10'unu geri alabilirler."
                                  maxlength="500"
                                  required></textarea>
                        <div class="alert alert-info mt-2" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Avantajı detaylı bir şekilde açıklayın. Koşulları ve limitleri belirtin.
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group mb-4">
                                <label for="icon" class="form-label">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-image"></i>
                                        <span class="ms-2">İkon URL</span>
                                    </div>
                                    <div class="alert alert-info mt-2 mb-2" role="alert">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        İkon görseli için bağlantı
                                    </div>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                    <input type="url" 
                                           class="form-control" 
                                           id="icon" 
                                           name="icon" 
                                           placeholder="https://example.com/icons/vip-bonus.png"
                                           required>
                                </div>
                                <div class="alert alert-info mt-2" role="alert">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    İkon için geçerli bir resim URL'si girin (PNG, JPG, SVG).
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label for="order" class="form-label">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-sort-numeric-down"></i>
                                        <span class="ms-2">Sıralama</span>
                                    </div>
                                    <div class="alert alert-info mt-2 mb-2" role="alert">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        Görüntüleme sırası
                                    </div>
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="order" 
                                       name="order" 
                                       min="0" 
                                       max="999"
                                       placeholder="0"
                                       required>
                                <div class="alert alert-info mt-2" role="alert">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    Düşük sayı = Üst sırada gösterim
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Önemli:</strong> İkon boyutu tercihen 128x128 piksel olmalıdır. Daha büyük resimler otomatik olarak yeniden boyutlandırılacaktır.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> İptal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveAdvantage()">
                    <i class="bi bi-save"></i> Kaydet
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="titleForm">
                    <input type="hidden" name="action" value="edit_title">
                    
                    <div class="form-group">
                        <label for="advantages_title" class="form-label">
                            <i class="bi bi-type-h1"></i>Sayfa Başlığı
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="advantages_title" 
                               name="advantages_title" 
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
    $('#advantagesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "asc"]],
        "pageLength": 25
    });
});

function showAddModal() {
    $('#modalTitle').text('Avantaj Ekle');
    $('#advantageForm')[0].reset();
    $('#advantageId').val('');
    $('#action').val('add');
    $('#advantageModal').modal('show');
}

function showEditModal(id) {
    $('#modalTitle').text('Avantaj Düzenle');
    $('#advantageId').val(id);
    $('#action').val('edit');
    
    $.post('vip_advantages.php', {
        action: 'get',
        id: id
    }, function(response) {
        if (response.status === 'success') {
            $('#title').val(response.data.advantage_title);
            $('#description').val(response.data.advantage_description);
            $('#icon').val(response.data.advantage_icon);
            $('#order').val(response.data.advantage_order);
            $('#advantageModal').modal('show');
        } else {
            Swal.fire('Hata!', response.message, 'error');
        }
    });
}

function saveAdvantage() {
    const formData = new FormData($('#advantageForm')[0]);
    
    $.ajax({
        url: 'vip_advantages.php',
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

function deleteAdvantage(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu avantajı silmek istediğinizden emin misiniz?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('vip_advantages.php', {
                action: 'delete',
                id: id
            }, function(response) {
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
            });
        }
    });
}

function showEditTitleModal() {
    $('#titleModal').modal('show');
}

function saveTitle() {
    const formData = new FormData($('#titleForm')[0]);
    
    $.ajax({
        url: 'vip_advantages.php',
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