<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Bonus ve Promosyon Yönetimi";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check permissions
$isAdmin = ($_SESSION['role_id'] == 1);

if (!$isAdmin) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_bonus':
            try {
                $stmt = $db->prepare("
                    INSERT INTO bonuslar (
                        bonus_adi, bonus_turu, yuzde, min_miktar, max_miktar,
                        min_kayip_miktar, bonus_kategori, tekrar_alinabilir,
                        resim_url, aktif, min_yatirim_sarti
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['bonus_adi'],
                    $_POST['bonus_turu'],
                    $_POST['yuzde'],
                    $_POST['min_miktar'],
                    $_POST['max_miktar'],
                    $_POST['min_kayip_miktar'],
                    $_POST['bonus_kategori'],
                    $_POST['tekrar_alinabilir'],
                    $_POST['resim_url'],
                    $_POST['aktif'],
                    $_POST['min_yatirim_sarti']
                ]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'add_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Yeni bonus eklendi: {$_POST['bonus_adi']}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus başarıyla eklendi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();

        case 'update_bonus':
            try {
                $stmt = $db->prepare("
                    UPDATE bonuslar SET 
                        bonus_adi = ?,
                        bonus_turu = ?,
                        yuzde = ?,
                        min_miktar = ?,
                        max_miktar = ?,
                        min_kayip_miktar = ?,
                        bonus_kategori = ?,
                        tekrar_alinabilir = ?,
                        resim_url = ?,
                        aktif = ?,
                        min_yatirim_sarti = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['bonus_adi'],
                    $_POST['bonus_turu'],
                    $_POST['yuzde'],
                    $_POST['min_miktar'],
                    $_POST['max_miktar'],
                    $_POST['min_kayip_miktar'],
                    $_POST['bonus_kategori'],
                    $_POST['tekrar_alinabilir'],
                    $_POST['resim_url'],
                    $_POST['aktif'],
                    $_POST['min_yatirim_sarti'],
                    $_POST['id']
                ]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Bonus güncellendi: {$_POST['bonus_adi']}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus başarıyla güncellendi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();

        case 'delete_bonus':
            try {
                $stmt = $db->prepare("DELETE FROM bonuslar WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'delete_bonus', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Bonus silindi: ID {$_POST['id']}"]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus başarıyla silindi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();

        case 'add_promotion':
            try {
                // Format text fields - decode HTML entities and handle line breaks
                $description = str_replace('\n', '<br>', html_entity_decode($_POST['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $rules = str_replace('\n', '<br>', html_entity_decode($_POST['rules'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $terms = str_replace('\n', '<br>', html_entity_decode($_POST['terms_conditions'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                
                $stmt = $db->prepare("
                    INSERT INTO promotions (
                        title, description, image_url, category,
                        rules, terms_conditions, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['title'],
                    $description,
                    $_POST['image_url'],
                    $_POST['category'],
                    $rules,
                    $terms,
                    $_POST['status']
                ]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'add_promotion', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Yeni promosyon eklendi: {$_POST['title']}"]);
                
                echo json_encode(['success' => true, 'message' => 'Promosyon başarıyla eklendi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();

        case 'update_promotion':
            try {
                // Format text fields - decode HTML entities and handle line breaks
                $description = str_replace('\n', '<br>', html_entity_decode($_POST['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $rules = str_replace('\n', '<br>', html_entity_decode($_POST['rules'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $terms = str_replace('\n', '<br>', html_entity_decode($_POST['terms_conditions'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                
                $stmt = $db->prepare("
                    UPDATE promotions SET 
                        title = ?,
                        description = ?,
                        image_url = ?,
                        category = ?,
                        rules = ?,
                        terms_conditions = ?,
                        status = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['title'],
                    $description,
                    $_POST['image_url'],
                    $_POST['category'],
                    $rules,
                    $terms,
                    $_POST['status'],
                    $_POST['id']
                ]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update_promotion', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Promosyon güncellendi: {$_POST['title']}"]);
                
                echo json_encode(['success' => true, 'message' => 'Promosyon başarıyla güncellendi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();

        case 'delete_promotion':
            try {
                $stmt = $db->prepare("DELETE FROM promotions WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'delete_promotion', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Promosyon silindi: ID {$_POST['id']}"]);
                
                echo json_encode(['success' => true, 'message' => 'Promosyon başarıyla silindi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics for dashboard
try {
    // Total bonuses
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bonuslar");
    $stmt->execute();
    $totalBonuses = $stmt->fetch()['total'];
    
    // Active bonuses
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bonuslar WHERE aktif = 1");
    $stmt->execute();
    $activeBonuses = $stmt->fetch()['total'];
    
    // Total promotions
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM promotions");
    $stmt->execute();
    $totalPromotions = $stmt->fetch()['total'];
    
    // Active promotions
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM promotions WHERE status = 1");
    $stmt->execute();
    $activePromotions = $stmt->fetch()['total'];
    
    // Total categories
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM promotion_categories");
    $stmt->execute();
    $totalCategories = $stmt->fetch()['total'];
    
    // Today's bonus usage
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bonus_kullanim WHERE DATE(tarih) = CURDATE()");
    $stmt->execute();
    $todayBonusUsage = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $totalBonuses = $activeBonuses = $totalPromotions = $activePromotions = $totalCategories = $todayBonusUsage = 0;
}

// Get all bonuses
$stmt = $db->query("SELECT * FROM bonuslar ORDER BY id DESC");
$bonuses = $stmt->fetchAll();

// Get all promotion categories
$stmt = $db->query("SELECT * FROM promotion_categories WHERE status = 1");
$categories = $stmt->fetchAll();

// Get all promotions
$stmt = $db->query("SELECT * FROM promotions ORDER BY id DESC");
$promotions = $stmt->fetchAll();

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

    .stat-card.bonuses::after { background: var(--gradient-primary); }
    .stat-card.active-bonuses::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.promotions::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.active-promotions::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.categories::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.usage::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

    .bonus-management-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .bonus-management-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .bonus-management-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .bonus-management-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .bonus-management-body {
        padding: 2rem;
    }

    .nav-tabs {
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 2rem;
    }

    .nav-tabs .nav-link {
        color: var(--light-gray);
        border: none;
        border-bottom: 3px solid transparent;
        padding: 1rem 1.5rem;
        font-weight: 600;
        transition: var(--transition);
    }

    .nav-tabs .nav-link:hover {
        color: var(--primary-blue);
        background: none;
    }

    .nav-tabs .nav-link.active {
        color: var(--primary-blue);
        background: none;
        border-bottom: 3px solid var(--primary-blue);
    }

    .form-section {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        margin-bottom: 2rem;
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

    .btn-danger {
        background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%);
        color: var(--white);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%);
        color: var(--white);
    }

    .btn-info {
        background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
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
        background: #151f31;
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

    .preview-image {
        max-width: 200px;
        max-height: 200px;
        object-fit: contain;
        margin-top: 10px;
        border-radius: 8px;
        box-shadow: var(--shadow-sm);
    }

    .badge {
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
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

        .bonus-management-body {
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
            <i class="bi bi-gift"></i>
            Bonus ve Promosyon Yönetimi
        </div>
        <div class="dashboard-subtitle">
            Bonus ve promosyonları yönetin, istatistikleri takip edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card bonuses">
            <div class="stat-icon">
                <i class="bi bi-gift"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalBonuses); ?></div>
            <div class="stat-label">Toplam Bonus</div>
        </div>
        
        <div class="stat-card active-bonuses">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($activeBonuses); ?></div>
            <div class="stat-label">Aktif Bonus</div>
        </div>
        
        <div class="stat-card promotions">
            <div class="stat-icon">
                <i class="bi bi-megaphone"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalPromotions); ?></div>
            <div class="stat-label">Toplam Promosyon</div>
        </div>
        
        <div class="stat-card active-promotions">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-value"><?php echo number_format($activePromotions); ?></div>
            <div class="stat-label">Aktif Promosyon</div>
        </div>
        
        <div class="stat-card usage">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayBonusUsage); ?></div>
            <div class="stat-label">Bugünkü Kullanım</div>
        </div>
    </div>

    <!-- Bonus Management Card -->
    <div class="bonus-management-card">
        <div class="bonus-management-header">
            <div class="bonus-management-title">
                <i class="bi bi-gear"></i>
                Yönetim Paneli
            </div>
        </div>
        <div class="bonus-management-body">
            <ul class="nav nav-tabs" id="bonusTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="bonuses-tab" data-bs-toggle="tab" href="#bonuses" role="tab">
                        <i class="bi bi-gift me-2"></i>Bonus Yönetimi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="promotions-tab" data-bs-toggle="tab" href="#promotions" role="tab">
                        <i class="bi bi-megaphone me-2"></i>Promosyonlar
                    </a>
                </li>
            </ul>

    <div class="tab-content" id="bonusTabContent">
        <div class="tab-pane fade show active" id="bonuses" role="tabpanel">
            <div class="row">
                <!-- Bonus Ekleme Formu -->
                <div class="col-md-4">
                    <div class="form-section">
                        <h5 class="mb-4">Bonus Ekle/Düzenle</h5>
                        <form id="bonusForm">
                            <input type="hidden" id="bonus_id" name="id">
                            
                            <div class="mb-3">
                                <label class="form-label">Bonus Adı</label>
                                <input type="text" class="form-control" id="bonus_adi" name="bonus_adi" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Bonus Türü</label>
                                <select class="form-control" id="bonus_turu" name="bonus_turu" required>
                                    <option value="">Seçiniz</option>
                                    <option value="deneme">Deneme Bonusu</option>
                                    <option value="kayip">Kayıp Bonusu</option>
                                    <option value="yatirim">Yatırım Bonusu</option>
                                    <option value="casino">Casino Bonusu</option>
                                    <option value="spor">Spor Bonusu</option>
                                    <option value="ilk_yatirim">İlk Yatırım Bonusu</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Yüzde</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="yuzde" name="yuzde" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Min. Miktar</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="min_miktar" name="min_miktar" required>
                                        <span class="input-group-text">₺</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Max. Miktar</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="max_miktar" name="max_miktar" required>
                                        <span class="input-group-text">₺</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Min. Kayıp Miktar</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="min_kayip_miktar" name="min_kayip_miktar">
                                    <span class="input-group-text">₺</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Bonus Kategori</label>
                                <select class="form-control" id="bonus_kategori" name="bonus_kategori" required>
                                    <option value="">Seçiniz</option>
                                    <option value="spor">Spor</option>
                                    <option value="casino">Casino</option>
                                    <option value="genel">Genel</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Min. Yatırım Şartı</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="min_yatirim_sarti" name="min_yatirim_sarti">
                                    <span class="input-group-text">₺</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Resim URL</label>
                                <input type="text" class="form-control" id="resim_url" name="resim_url">
                                <img id="preview" class="preview-image d-none mt-2" alt="Bonus Resmi">
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Tekrar Alınabilir</label>
                                    <select class="form-control" id="tekrar_alinabilir" name="tekrar_alinabilir">
                                        <option value="1">Evet</option>
                                        <option value="0">Hayır</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Durum</label>
                                    <select class="form-control" id="aktif" name="aktif">
                                        <option value="1">Aktif</option>
                                        <option value="0">Pasif</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Kaydet
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i> Formu Temizle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bonus Listesi -->
                <div class="col-md-8">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="bonusTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Bonus Adı</th>
                                    <th>Tür</th>
                                    <th>Yüzde</th>
                                    <th>Min-Max Miktar</th>
                                    <th>Kategori</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bonuses as $bonus): ?>
                                <tr>
                                    <td><?php echo $bonus['id']; ?></td>
                                    <td><?php echo htmlspecialchars($bonus['bonus_adi']); ?></td>
                                    <td><?php echo ucfirst($bonus['bonus_turu']); ?></td>
                                    <td>%<?php echo $bonus['yuzde']; ?></td>
                                    <td><?php echo number_format($bonus['min_miktar'], 2); ?> - <?php echo number_format($bonus['max_miktar'], 2); ?> ₺</td>
                                    <td><?php echo ucfirst($bonus['bonus_kategori']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $bonus['aktif'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $bonus['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editBonus(<?php echo htmlspecialchars(json_encode($bonus)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteBonus(<?php echo $bonus['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tab-pane fade" id="promotions" role="tabpanel">
            <!-- Promosyonlar sekmesi içeriği -->
            <div class="row">
                <div class="col-md-4">
                    <div class="form-section">
                        <h5 class="mb-4">Promosyon Ekle/Düzenle</h5>
                        <form id="promotionForm">
                            <input type="hidden" id="promotion_id" name="id">
                            
                            <div class="mb-3">
                                <label class="form-label">Başlık</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Açıklama</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['name']; ?>"><?php echo $category['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Resim URL</label>
                                <input type="text" class="form-control" id="promotion_image_url" name="image_url">
                                <img id="promotion_preview" class="preview-image d-none mt-2" alt="Promosyon Resmi">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kurallar</label>
                                <textarea class="form-control" id="rules" name="rules" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Şartlar ve Koşullar</label>
                                <textarea class="form-control" id="terms_conditions" name="terms_conditions" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Durum</label>
                                <select class="form-control" id="promotion_status" name="status">
                                    <option value="1">Aktif</option>
                                    <option value="0">Pasif</option>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Kaydet
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetPromotionForm()">
                                    <i class="fas fa-undo me-1"></i> Formu Temizle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="promotionsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Başlık</th>
                                    <th>Kategori</th>
                                    <th>Durum</th>
                                    <th>Oluşturulma Tarihi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotions as $promo): ?>
                                <tr>
                                    <td><?php echo $promo['id']; ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($promo['title'])); ?></td>
                                    <td><?php echo htmlspecialchars($promo['category']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $promo['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $promo['status'] ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($promo['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewPromoDetails(<?php echo htmlspecialchars(json_encode($promo)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="editPromotion(<?php echo htmlspecialchars(json_encode($promo)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deletePromotion(<?php echo $promo['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Promosyon Detay Modal -->
<div class="modal fade" id="promoDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white border-dark">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>
                    <span id="modalPromoTitle"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Görsel Bölümü -->
                <div class="promo-image-container text-center bg-light p-3">
                    <img id="modalPromoImage" src="" alt="Promosyon Resmi" class="img-fluid rounded shadow-sm" style="max-height: 300px; object-fit: contain;">
                </div>
                
                <!-- Detaylar Bölümü -->
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="badge bg-primary px-3 py-2" id="modalPromoCategory"></span>
                        <span class="badge bg-success px-3 py-2" id="modalPromoStatus"></span>
                    </div>

                    <!-- Açıklama -->
                    <div class="promo-section mb-4">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-card-text me-2"></i>Açıklama
                        </h5>
                        <p id="modalPromoDescription" class="text-muted"></p>
                    </div>

                    <!-- Kurallar -->
                    <div class="promo-section mb-4">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-list-check me-2"></i>Kurallar
                        </h5>
                        <div id="modalPromoRules" class="text-muted"></div>
                    </div>

                    <!-- Şartlar ve Koşullar -->
                    <div class="promo-section">
                        <h5 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-shield-check me-2"></i>Şartlar ve Koşullar
                        </h5>
                        <div id="modalPromoTerms" class="text-muted"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Single editMode declaration at the top
let editMode = false;

// Bonus formu işlemleri
function resetForm() {
    editMode = false;
    $('#bonusForm')[0].reset();
    $('#bonus_id').val('');
    $('#preview').addClass('d-none');
}

function editBonus(bonus) {
    editMode = true;
    
    // Form alanlarını doldur
    $('#bonus_id').val(bonus.id);
    $('#bonus_adi').val(bonus.bonus_adi);
    $('#bonus_turu').val(bonus.bonus_turu);
    $('#yuzde').val(bonus.yuzde);
    $('#min_miktar').val(bonus.min_miktar);
    $('#max_miktar').val(bonus.max_miktar);
    $('#min_kayip_miktar').val(bonus.min_kayip_miktar);
    $('#bonus_kategori').val(bonus.bonus_kategori);
    $('#tekrar_alinabilir').val(bonus.tekrar_alinabilir);
    $('#resim_url').val(bonus.resim_url);
    $('#aktif').val(bonus.aktif);
    $('#min_yatirim_sarti').val(bonus.min_yatirim_sarti);
    
    // Resim önizleme
    if (bonus.resim_url) {
        $('#preview').attr('src', bonus.resim_url).removeClass('d-none');
    }
    
    // Forma scroll
    $('html, body').animate({
        scrollTop: $("#bonusForm").offset().top - 100
    }, 500);
}

function deleteBonus(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu bonus silinecek. Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'bonus_management.php',
                type: 'POST',
                data: {
                    action: 'delete_bonus',
                    id: id
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
                }
            });
        }
    });
}

// Bonus form submit
$('#bonusForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', editMode ? 'update_bonus' : 'add_bonus');
    
    Swal.fire({
        title: 'İşleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'bonus_management.php',
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

// Resim önizleme
$('#resim_url, #promotion_image_url').on('change', function() {
    const url = $(this).val();
    const previewId = $(this).attr('id') === 'resim_url' ? 'preview' : 'promotion_preview';
    
    if (url) {
        $(`#${previewId}`).attr('src', url).removeClass('d-none');
    } else {
        $(`#${previewId}`).addClass('d-none');
    }
});

// Promosyon işlemleri
function resetPromotionForm() {
    editMode = false;
    $('#promotionForm')[0].reset();
    $('#promotion_id').val('');
    $('#promotion_preview').addClass('d-none');
}

function editPromotion(promo) {
    editMode = true;
    
    // Form alanlarını doldur
    $('#promotion_id').val(promo.id);
    $('#title').val(promo.title);
    $('#description').val(decodeFormContent(promo.description));
    $('#category').val(promo.category);
    $('#promotion_image_url').val(promo.image_url);
    $('#rules').val(decodeFormContent(promo.rules));
    $('#terms_conditions').val(decodeFormContent(promo.terms_conditions));
    $('#promotion_status').val(promo.status);
    
    // Resim önizleme
    if (promo.image_url) {
        $('#promotion_preview').attr('src', promo.image_url).removeClass('d-none');
    }
    
    // Forma scroll
    $('html, body').animate({
        scrollTop: $("#promotionForm").offset().top - 100
    }, 500);
}

// Form içeriğini decode etme fonksiyonu
function decodeFormContent(content) {
    if (typeof content !== 'string') return '';
    
    // HTML entities'i decode et
    let decoded = content
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'");
    
    // <br> tag'lerini \n'e çevir
    decoded = decoded
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/&lt;br\s*\/?&gt;/gi, '\n');
    
    // Fazla boş satırları temizle
    decoded = decoded
        .split('\n')
        .map(line => line.trim())
        .filter((line, index, arr) => {
            // Eğer bu satır boş ve bir önceki satır da boşsa, bu satırı filtrele
            if (line === '' && index > 0 && arr[index - 1] === '') {
                return false;
            }
            return true;
        })
        .join('\n');
    
    return decoded;
}

// Form gönderme işlemi öncesi içeriği hazırlama
function prepareFormContent(content) {
    if (typeof content !== 'string') return '';
    
    // Satır sonlarını <br> tag'ine çevir
    return content
        .replace(/\r\n|\r|\n/g, '<br>')
        .trim();
}

// Form submit handler
$('#promotionForm').on('submit', function(e) {
    e.preventDefault();
    
    // Form verilerini hazırla
    const formData = {
        action: editMode ? 'update_promotion' : 'add_promotion',
        id: $('#promotion_id').val(),
        title: $('#title').val(),
        description: prepareFormContent($('#description').val()),
        image_url: $('#promotion_image_url').val(),
        category: $('#category').val(),
        rules: prepareFormContent($('#rules').val()),
        terms_conditions: prepareFormContent($('#terms_conditions').val()),
        status: $('#promotion_status').val()
    };
    
    Swal.fire({
        title: 'İşleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'bonus_management.php',
        type: 'POST',
        data: formData,
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
        error: function(xhr, status, error) {
            console.error('AJAX hatası:', error);
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'İşlem sırasında bir hata oluştu: ' + error
            });
        }
    });
});

function deletePromotion(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu promosyon silinecek. Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'bonus_management.php',
                type: 'POST',
                data: {
                    action: 'delete_promotion',
                    id: id
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
                }
            });
        }
    });
}

function viewPromoDetails(promo) {
    // Başlık ve kategori
    $('#modalPromoTitle').text(promo.title);
    $('#modalPromoCategory').text(promo.category);
    
    // Durum badge'i
    $('#modalPromoStatus').text(promo.status ? 'Aktif' : 'Pasif')
        .removeClass('bg-success bg-danger')
        .addClass(promo.status ? 'bg-success' : 'bg-danger');
    
    // İçerik alanları
    $('#modalPromoDescription').html(formatContent(promo.description));
    $('#modalPromoRules').html(formatContent(promo.rules));
    $('#modalPromoTerms').html(formatContent(promo.terms_conditions));
    
    // Resim
    if (promo.image_url) {
        $('#modalPromoImage').attr('src', promo.image_url).show();
    } else {
        $('#modalPromoImage').hide();
    }
    
    $('#promoDetailModal').modal('show');
}

// İçerik formatlama fonksiyonu
function formatContent(content) {
    if (typeof content !== 'string') return '';
    
    // HTML entities'i decode et
    let decodedContent = content
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'");
    
    // Tüm satır sonu karakterlerini normalize et
    decodedContent = decodedContent
        // Önce tüm <br> tag'lerini \n'e çevir
        .replace(/(<br\s*\/?>\s*)+/gi, '\n')
        // Diğer satır sonu karakterlerini normalize et
        .replace(/\r\n|\r|\n/g, '\n')
        // Fazla boş satırları temizle
        .replace(/\n\s*\n/g, '\n')
        // Başındaki ve sonundaki boşlukları temizle
        .trim();
    
    // Satırlara ayır ve işle
    let lines = decodedContent.split('\n');
    let uniqueLines = [];
    let lastLine = '';
    
    // Tekrarlanan satırları temizle ve boş satırları filtrele
    for (let line of lines) {
        line = line.trim();
        if (line && line !== lastLine) {
            uniqueLines.push(line);
            lastLine = line;
        }
    }
    
    // Her satırı formatla
    let formattedContent = '';
    let inList = false;
    
    uniqueLines.forEach((line, index) => {
        // Başlık kontrolü (50 karakterden kısa ve ilk satır veya önceki satır boşsa)
        if (line.length <= 50 && (index === 0 || !uniqueLines[index - 1])) {
            if (inList) {
                formattedContent += '</div>';
                inList = false;
            }
            formattedContent += `<div class="content-title">${line}</div>`;
            return;
        }
        
        // Madde işareti kontrolü
        if (line.match(/^\d+[\-\.\)]\s*/)) {
            // Numaralı madde
            if (!inList || !formattedContent.includes('content-list numbered')) {
                if (inList) formattedContent += '</div>';
                formattedContent += '<div class="content-list numbered">';
                inList = true;
            }
            line = line.replace(/^\d+[\-\.\)]\s*/, '');
            formattedContent += `<div class="list-item">${line}</div>`;
        } else if (line.match(/^[\-\•]\s*/)) {
            // Bullet point
            if (!inList || !formattedContent.includes('content-list bulleted')) {
                if (inList) formattedContent += '</div>';
                formattedContent += '<div class="content-list bulleted">';
                inList = true;
            }
            line = line.replace(/^[\-\•]\s*/, '');
            formattedContent += `<div class="list-item">${line}</div>`;
        } else {
            // Normal metin
            if (inList) {
                formattedContent += '</div>';
                inList = false;
            }
            formattedContent += `<div class="content-paragraph">${line}</div>`;
        }
    });
    
    if (inList) {
        formattedContent += '</div>';
    }
    
    return formattedContent;
}

// Stil güncellemeleri
const modalStyles = `
<style>
/* Genel modal stilleri */
.modal-content {
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.modal-header {
    padding: 1rem 1.5rem;
}

/* İçerik formatlaması için stiller */
.content-title {
    color: #2d3748;
    font-weight: 600;
    font-size: 1.1rem;
    margin: 1.5rem 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e2e8f0;
}

.content-title:first-child {
    margin-top: 0;
}

.content-paragraph {
    margin-bottom: 1rem;
    line-height: 1.6;
    color: #4a5568;
}

.content-list {
    margin: 1rem 0;
    padding-left: 0;
}

.content-list .list-item {
    position: relative;
    padding-left: 1.5rem;
    margin-bottom: 0.75rem;
    line-height: 1.6;
    color: #4a5568;
}

.content-list.numbered {
    counter-reset: item;
}

.content-list.numbered .list-item {
    counter-increment: item;
}

.content-list.numbered .list-item:before {
    content: counter(item) ".";
    position: absolute;
    left: 0;
    color: #4299e1;
    font-weight: 600;
}

.content-list.bulleted .list-item:before {
    content: "•";
    position: absolute;
    left: 0.5rem;
    color: #4299e1;
    font-weight: bold;
}

#modalPromoDescription, #modalPromoRules, #modalPromoTerms {
    font-size: 0.95rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.promo-section {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    margin-bottom: 1.5rem;
}

.promo-section h5 {
    color: #2d3748;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.promo-section h5 i {
    color: #4299e1;
}
</style>
`;

// DataTable initialization
$(document).ready(function() {
    // Stilleri sayfaya ekle
    if (!document.getElementById('modalCustomStyles')) {
        const styleElement = document.createElement('style');
        styleElement.id = 'modalCustomStyles';
        styleElement.textContent = modalStyles;
        document.head.appendChild(styleElement);
    }

    $('#bonusTable, #promotionsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "desc"]],
        "pageLength": 25,
        "responsive": true
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 