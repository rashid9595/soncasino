<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Sıkça Sorulan Sorular";

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
                    INSERT INTO pages_vip_faqs (question, answer, faq_order)
                    VALUES (?, ?, ?)
                ");
                
                try {
                    $stmt->execute([
                        $_POST['question'],
                        $_POST['answer'],
                        $_POST['order']
                    ]);
                    echo json_encode(['status' => 'success', 'message' => 'Soru başarıyla eklendi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Soru eklenirken bir hata oluştu.']);
                }
                break;
                
            case 'edit':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['edit']) || !$userPermissions['vip_settings']['edit'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("
                    UPDATE pages_vip_faqs 
                    SET question = ?, 
                        answer = ?, 
                        faq_order = ?
                    WHERE id = ?
                ");
                
                try {
                    $stmt->execute([
                        $_POST['question'],
                        $_POST['answer'],
                        $_POST['order'],
                        $_POST['id']
                    ]);
                    echo json_encode(['status' => 'success', 'message' => 'Soru başarıyla güncellendi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Soru güncellenirken bir hata oluştu.']);
                }
                break;
                
            case 'delete':
                if (!$isAdmin && (!isset($userPermissions['vip_settings']['delete']) || !$userPermissions['vip_settings']['delete'])) {
                    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
                    exit();
                }
                
                $stmt = $db->prepare("DELETE FROM pages_vip_faqs WHERE id = ?");
                
                try {
                    $stmt->execute([$_POST['id']]);
                    echo json_encode(['status' => 'success', 'message' => 'Soru başarıyla silindi.']);
                } catch (PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Soru silinirken bir hata oluştu.']);
                }
                break;
                
            case 'get':
                $stmt = $db->prepare("SELECT * FROM pages_vip_faqs WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $faq = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $faq]);
                break;
        }
        exit();
    }
}

// Get statistics
try {
    // Total FAQs
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_faqs");
    $stmt->execute();
    $totalFaqs = $stmt->fetch()['total'];
    
    // FAQs with HTML content
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_faqs WHERE answer LIKE '%<%'");
    $stmt->execute();
    $htmlFaqs = $stmt->fetch()['total'];
    
    // Average answer length
    $stmt = $db->prepare("SELECT AVG(LENGTH(answer)) as avg_length FROM pages_vip_faqs");
    $stmt->execute();
    $avgLength = $stmt->fetch()['avg_length'];
    $avgLength = $avgLength ? round($avgLength) : 0;
    
    // Average order number
    $stmt = $db->prepare("SELECT AVG(faq_order) as avg_order FROM pages_vip_faqs");
    $stmt->execute();
    $avgOrder = $stmt->fetch()['avg_order'];
    $avgOrder = $avgOrder ? round($avgOrder, 1) : 0;
    
    // Highest order number
    $stmt = $db->prepare("SELECT MAX(faq_order) as max_order FROM pages_vip_faqs");
    $stmt->execute();
    $maxOrder = $stmt->fetch()['max_order'];
    $maxOrder = $maxOrder ? $maxOrder : 0;
    
    // Recently added FAQs (last 30 days)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pages_vip_faqs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recentFaqs = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalFaqs = $htmlFaqs = $avgLength = $avgOrder = $maxOrder = $recentFaqs = 0;
}

// Get all FAQs
try {
    $stmt = $db->prepare("SELECT * FROM pages_vip_faqs ORDER BY faq_order ASC");
    $stmt->execute();
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "SSS'ler yüklenirken bir hata oluştu: " . $e->getMessage();
    $faqs = [];
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
    
    .stat-card.html::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.avg-length::after {
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
    
    .stat-card.html .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.avg-length .stat-icon {
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

    .faqs-table-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .faqs-table-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .faqs-table-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .faqs-table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
    }
    
    .faqs-table-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .faqs-table-body {
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

    .table {
        background: var(--card-bg) !important;
        border-radius: var(--border-radius) !important;
        overflow: hidden !important;
        margin-bottom: 0;
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
        vertical-align: middle;
    }

    .table tbody tr {
        transition: all 0.3s ease !important;
    }

    .table tbody tr:hover {
        background-color: var(--ultra-light-blue) !important;
        transform: translateY(-1px) !important;
    }

    .answer-preview {
        max-height: 100px;
        overflow: hidden;
        position: relative;
    }

    .answer-preview::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 40px;
        background: linear-gradient(transparent, var(--card-bg));
    }

    .order-column {
        width: 80px;
    }

    .actions-column {
        width: 120px;
    }

    .question-column {
        width: 25%;
    }

    .answer-column {
        width: 45%;
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

    .modal .btn {
        padding: 0.5rem 1.25rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .modal .btn i {
        font-size: 1.1rem;
    }

    .modal .btn:hover {
        transform: translateY(-2px);
    }

    .html-tools .btn-outline-primary {
        color: var(--primary-blue-light);
        border-color: var(--primary-blue-light);
        background: transparent;
        font-size: 0.875rem;
        padding: 0.25rem 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .html-tools .btn-outline-primary:hover {
        background: var(--primary-blue-light);
        color: white;
        transform: translateY(-2px);
    }

    .html-tools .btn-outline-primary i {
        font-size: 1rem;
    }

    .html-tools .btn-outline-primary:focus {
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
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
        
        .faqs-table-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-question-circle-fill me-2"></i>
        VIP Sıkça Sorulan Sorular
    </h1>
    <p class="dashboard-subtitle">VIP üyelerinizin sıkça sorulan sorularını yönetin ve düzenleyin.</p>
</div>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-question-circle"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalFaqs); ?></div>
        <div class="stat-title">Toplam Soru</div>
    </div>
    
    <div class="stat-card html">
        <div class="stat-icon">
            <i class="bi bi-code-slash"></i>
        </div>
        <div class="stat-number"><?php echo number_format($htmlFaqs); ?></div>
        <div class="stat-title">HTML İçerikli</div>
    </div>
    
    <div class="stat-card avg-length">
        <div class="stat-icon">
            <i class="bi bi-text-paragraph"></i>
        </div>
        <div class="stat-number"><?php echo $avgLength; ?></div>
        <div class="stat-title">Ortalama Uzunluk</div>
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

<div class="faqs-table-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="faqs-table-header">
        <h5 class="faqs-table-title">
            <i class="bi bi-table"></i>
            SSS Listesi
        </h5>
        <?php if ($isAdmin || (isset($userPermissions['vip_settings']['add']) && $userPermissions['vip_settings']['add'])): ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="showAddModal()">
            <i class="bi bi-plus-lg me-1"></i> Yeni Soru Ekle
        </button>
        <?php endif; ?>
    </div>
    <div class="faqs-table-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="faqsTable">
                <thead>
                    <tr>
                        <th class="order-column">Sıra</th>
                        <th class="question-column">Soru</th>
                        <th class="answer-column">Cevap</th>
                        <th>Oluşturulma Tarihi</th>
                        <th class="actions-column">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faqs)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Henüz SSS eklenmemiş.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($faqs as $faq): ?>
                        <tr>
                            <td class="text-center">
                                <span class="badge" style="background: var(--primary-blue-light); color: white; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($faq['faq_order']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold" style="color: var(--text-heading);">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="answer-preview">
                                    <?php echo $faq['answer']; ?>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted">
                                    <?php echo date('d.m.Y H:i', strtotime($faq['created_at'])); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($isAdmin || (isset($userPermissions['vip_settings']['edit']) && $userPermissions['vip_settings']['edit'])): ?>
                                <button type="button" class="btn btn-primary btn-icon" onclick="showEditModal(<?php echo $faq['id']; ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($isAdmin || (isset($userPermissions['vip_settings']['delete']) && $userPermissions['vip_settings']['delete'])): ?>
                                <button type="button" class="btn btn-danger btn-icon" onclick="deleteFaq(<?php echo $faq['id']; ?>)">
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
<div class="modal fade" id="faqModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Soru Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="faqForm">
                    <input type="hidden" id="faqId" name="id">
                    <input type="hidden" id="action" name="action" value="add">
                    
                    <div class="form-group mb-4">
                        <label for="question" class="form-label">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-question-circle" style="color: var(--primary-blue-light);"></i>
                                <span class="ms-2">Soru</span>
                            </div>
                            <div class="alert alert-info mt-2 mb-2" role="alert">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                VIP sistemi ile ilgili soru
                            </div>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="question" 
                               name="question" 
                               placeholder="Örn: VIP sistemi nedir?"
                               required>
                        <div class="alert alert-info mt-2" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Kısa ve net bir soru giriniz.
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="answer" class="form-label">
                            <i class="bi bi-chat-dots me-2" style="color: var(--primary-blue-light);"></i>Cevap
                        </label>
                        
                        <div class="html-tools mb-2 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertTag('p')">
                                <i class="bi bi-paragraph"></i> Paragraf
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertTag('ul')">
                                <i class="bi bi-list-ul"></i> Liste Başlat
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertTag('li')">
                                <i class="bi bi-dash"></i> Liste Öğesi
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertTag('br')">
                                <i class="bi bi-arrow-return-right"></i> Satır Sonu
                            </button>
                        </div>
                        
                        <textarea class="form-control" 
                                  id="answer" 
                                  name="answer" 
                                  rows="5" 
                                  required></textarea>

                        <div class="alert alert-info mt-2" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Yukarıdaki butonları kullanarak hızlıca HTML etiketleri ekleyebilirsiniz.
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label for="order" class="form-label">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-sort-numeric-down" style="color: var(--primary-blue-light);"></i>
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-2"></i>İptal
                </button>
                <button type="button" class="btn btn-primary" onclick="saveFaq()">
                    <i class="bi bi-save me-2"></i>Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#faqsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "asc"]],
        "pageLength": 25
    });
});

function showAddModal() {
    $('#modalTitle').text('Soru Ekle');
    $('#faqForm')[0].reset();
    $('#faqId').val('');
    $('#action').val('add');
    $('#faqModal').modal('show');
}

function showEditModal(id) {
    $('#modalTitle').text('Soru Düzenle');
    $('#faqId').val(id);
    $('#action').val('edit');
    
    $.post('vip_faqs.php', {
        action: 'get',
        id: id
    }, function(response) {
        if (response.status === 'success') {
            $('#question').val(response.data.question);
            $('#answer').val(response.data.answer);
            $('#order').val(response.data.faq_order);
            $('#faqModal').modal('show');
        } else {
            Swal.fire('Hata!', response.message, 'error');
        }
    });
}

function saveFaq() {
    const formData = new FormData($('#faqForm')[0]);
    
    $.ajax({
        url: 'vip_faqs.php',
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

function deleteFaq(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu soruyu silmek istediğinizden emin misiniz?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('vip_faqs.php', {
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

function insertTag(tag) {
    const textarea = document.getElementById('answer');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    let selectedText = text.substring(start, end);
    let insertion = '';
    
    switch(tag) {
        case 'p':
            insertion = `<p>${selectedText || 'Paragraf metni'}</p>`;
            break;
        case 'ul':
            insertion = `<ul>\n    <li>Liste öğesi</li>\n</ul>`;
            break;
        case 'li':
            insertion = `<li>${selectedText || 'Liste öğesi'}</li>`;
            break;
        case 'br':
            insertion = '<br>';
            break;
    }
    
    textarea.value = text.substring(0, start) + insertion + text.substring(end);
    
    // Yeni pozisyonu hesapla
    const newPosition = start + insertion.length;
    
    // İmleci yeni pozisyona taşı
    textarea.focus();
    textarea.setSelectionRange(newPosition, newPosition);
    
    // Değişikliği göster
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Textarea otomatik yükseklik ayarı
document.getElementById('answer').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 