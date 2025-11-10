<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Set page title
$pageTitle = "Banner Yönetimi";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Simplified permission check for super admin
if ($_SESSION['role_id'] != 1) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'desktop';

// Get all promotions for dropdown
try {
    $promoStmt = $db->query("SELECT * FROM promotions ORDER BY id DESC");
    $promotions = $promoStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Promosyonlar yüklenirken bir hata oluştu: " . $e->getMessage();
    $promotions = [];
}

// Handle banner add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $imageUrl = trim($_POST['image_url']);
    $altText = isset($_POST['alt_text']) ? trim($_POST['alt_text']) : 'Banner Image';
    $promoId = isset($_POST['promo_id']) && !empty($_POST['promo_id']) ? (int)$_POST['promo_id'] : null;
    $bannerType = $_POST['banner_type'];
    
    if (empty($imageUrl)) {
        $error = "Banner URL boş olamaz.";
    } else {
        try {
            // Check if the URL is valid and accessible
            $headers = @get_headers($imageUrl);
            if (!$headers || strpos($headers[0], '200') === false) {
                $error = "Geçersiz URL veya erişilemeyen resim.";
            } else {
                $tableName = ($bannerType === 'mobile') ? 'bannersmobil' : 'banners';
                $stmt = $db->prepare("INSERT INTO $tableName (image_url, alt_text, promo_id) VALUES (?, ?, ?)");
                $stmt->execute([$imageUrl, $altText, $promoId]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'create', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Yeni banner eklendi: $altText ($bannerType)"]);
                
                $message = "Banner başarıyla eklendi.";
                $activeTab = $bannerType;
            }
        } catch (PDOException $e) {
            $error = "Banner eklenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Handle banner delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $bannerId = (int)$_GET['delete'];
    $bannerType = isset($_GET['type']) ? $_GET['type'] : 'desktop';
    $tableName = ($bannerType === 'mobile') ? 'bannersmobil' : 'banners';
    
    try {
        // Get banner info for logging
        $stmt = $db->prepare("SELECT alt_text FROM $tableName WHERE id = ?");
        $stmt->execute([$bannerId]);
        $bannerInfo = $stmt->fetch();
        
        $stmt = $db->prepare("DELETE FROM $tableName WHERE id = ?");
        $stmt->execute([$bannerId]);
        
        if ($stmt->rowCount() > 0) {
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'delete', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Banner silindi: " . ($bannerInfo['alt_text'] ?? 'Unknown') . " ($bannerType)"]);
            
            $message = "Banner başarıyla silindi.";
        } else {
            $error = "Banner bulunamadı.";
        }
        $activeTab = $bannerType;
    } catch (PDOException $e) {
        $error = "Banner silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Get all desktop banners
try {
    $stmt = $db->query("SELECT b.*, p.title as promo_title FROM banners b 
                        LEFT JOIN promotions p ON b.promo_id = p.id 
                        ORDER BY b.id DESC");
    $desktopBanners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Masaüstü bannerlar yüklenirken bir hata oluştu: " . $e->getMessage();
    $desktopBanners = [];
}

// Get all mobile banners
try {
    $stmt = $db->query("SELECT b.*, p.title as promo_title FROM bannersmobil b 
                        LEFT JOIN promotions p ON b.promo_id = p.id 
                        ORDER BY b.id DESC");
    $mobileBanners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Mobil bannerlar yüklenirken bir hata oluştu: " . $e->getMessage();
    $mobileBanners = [];
}

// Calculate statistics
try {
    // Total desktop banners
    $stmt = $db->prepare("SELECT COUNT(*) FROM banners");
    $stmt->execute();
    $totalDesktopBanners = $stmt->fetchColumn();
    
    // Total mobile banners
    $stmt = $db->prepare("SELECT COUNT(*) FROM bannersmobil");
    $stmt->execute();
    $totalMobileBanners = $stmt->fetchColumn();
    
    // Total banners
    $totalBanners = $totalDesktopBanners + $totalMobileBanners;
    
    // Today's banners
    $stmt = $db->prepare("SELECT COUNT(*) FROM banners WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayDesktopBanners = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM bannersmobil WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayMobileBanners = $stmt->fetchColumn();
    
    $todayBanners = $todayDesktopBanners + $todayMobileBanners;
    
    // Banners with promotions
    $stmt = $db->prepare("SELECT COUNT(*) FROM banners WHERE promo_id IS NOT NULL");
    $stmt->execute();
    $desktopWithPromo = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM bannersmobil WHERE promo_id IS NOT NULL");
    $stmt->execute();
    $mobileWithPromo = $stmt->fetchColumn();
    
    $totalWithPromo = $desktopWithPromo + $mobileWithPromo;
    
} catch (Exception $e) {
    $totalBanners = $totalDesktopBanners = $totalMobileBanners = $todayBanners = $totalWithPromo = 0;
}

// Start output buffering
ob_start();
?>

<style>
:root {
    --primary-color: #0ea5e9;
    --primary-dark: #0284c7;
    --secondary-color: #64748b;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #06b6d4;
    --light-bg: #f8fafc;
    --dark-bg: #0f172a;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --border-radius: 0.5rem;
    --card-radius: 0.75rem;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.dashboard-header {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.8));
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: var(--shadow-lg);
}

.greeting {
    color: #ffffff;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.dashboard-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
    font-weight: 400;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--card-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--info-color));
}

.stat-card:nth-child(1)::before { background: linear-gradient(90deg, #3b82f6, #1d4ed8); }
.stat-card:nth-child(2)::before { background: linear-gradient(90deg, #10b981, #059669); }
.stat-card:nth-child(3)::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
.stat-card:nth-child(4)::before { background: linear-gradient(90deg, #ef4444, #dc2626); }
.stat-card:nth-child(5)::before { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
.stat-card:nth-child(6)::before { background: linear-gradient(90deg, #06b6d4, #0891b2); }

.stat-card h3 {
    color: #ffffff;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-card p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    font-weight: 500;
    margin: 0;
}

.stat-card i {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 2rem;
    opacity: 0.3;
    color: #ffffff;
}

.banners-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.banners-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.banners-title {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.banners-body {
    padding: 1.5rem;
}

.nav-tabs {
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 1.5rem;
}

.nav-tabs .nav-link {
    border: none;
    color: var(--text-secondary);
    font-weight: 500;
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem 0.375rem 0 0;
    transition: all 0.2s ease;
}

.nav-tabs .nav-link:hover {
    color: var(--primary-color);
    background-color: rgba(14, 165, 233, 0.05);
}

.nav-tabs .nav-link.active {
    color: var(--primary-color);
    background-color: #ffffff;
    border-bottom: 2px solid var(--primary-color);
}

.form-section {
    background: #ffffff;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.form-section-title {
    color: var(--text-primary);
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: block;
}

.form-control, .form-select {
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    padding: 0.75rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    outline: none;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 2px solid var(--border-color);
    color: var(--text-primary);
    font-weight: 600;
    padding: 1rem 0.75rem;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(6, 182, 212, 0.05));
}

.banner-img {
    max-height: 100px;
    max-width: 100%;
    object-fit: contain;
    border-radius: 0.25rem;
    box-shadow: var(--shadow-sm);
}

.banner-preview {
    width: 150px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.badge {
    padding: 0.5em 0.75em;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.375rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge.bg-info {
    background: linear-gradient(135deg, var(--info-color), #0891b2) !important;
}

.badge.bg-secondary {
    background: linear-gradient(135deg, var(--secondary-color), #475569) !important;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: #ffffff;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
    color: #ffffff;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.alert {
    border-radius: var(--border-radius);
    border: none;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
    color: #dc2626;
    border-left: 4px solid var(--danger-color);
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
    color: #059669;
    border-left: 4px solid var(--success-color);
}

@media (max-width: 768px) {
    .stat-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card h3 {
        font-size: 1.5rem;
    }
    
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .greeting {
        font-size: 1.5rem;
    }
    
    .banners-body {
        padding: 1rem;
    }
    
    .form-section {
        padding: 1rem;
    }
}
</style>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="greeting">Banner Yönetimi</h1>
            <p class="dashboard-subtitle">Banner slider'larını yönetin ve düzenleyin</p>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <i class="fas fa-images"></i>
        <h3><?php echo number_format($totalBanners); ?></h3>
        <p>Toplam Banner</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-desktop"></i>
        <h3><?php echo number_format($totalDesktopBanners); ?></h3>
        <p>Masaüstü Banner</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-mobile-alt"></i>
        <h3><?php echo number_format($totalMobileBanners); ?></h3>
        <p>Mobil Banner</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-link"></i>
        <h3><?php echo number_format($totalWithPromo); ?></h3>
        <p>Promosyon Bağlantılı</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-percentage"></i>
        <h3><?php echo $totalBanners > 0 ? round(($totalWithPromo / $totalBanners) * 100, 1) : 0; ?>%</h3>
        <p>Bağlantı Oranı</p>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Banners Card -->
<div class="banners-card">
    <div class="banners-header">
        <h2 class="banners-title">
            <i class="fas fa-sliders-h"></i>
            Banner Slider Yönetimi
        </h2>
    </div>
    <div class="banners-body">
        <!-- Add Banner Form -->
        <div class="form-section">
            <h3 class="form-section-title">
                <i class="fas fa-plus-circle"></i>
                Yeni Banner Ekle
            </h3>
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="image_url" class="form-label">Banner Resim URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="image_url" name="image_url" required 
                                   placeholder="https://example.com/image.jpg">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="alt_text" class="form-label">Alt Metin</label>
                            <input type="text" class="form-control" id="alt_text" name="alt_text" 
                                   placeholder="Banner için bir alt metin girin">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="promo_id" class="form-label">Promosyon Bağlantısı</label>
                            <select class="form-select" id="promo_id" name="promo_id">
                                <option value="">Promosyon Seçin (İsteğe Bağlı)</option>
                                <?php foreach ($promotions as $promo): ?>
                                <option value="<?php echo $promo['id']; ?>"><?php echo htmlspecialchars($promo['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="mb-3">
                            <label for="banner_type" class="form-label">Banner Türü <span class="text-danger">*</span></label>
                            <select class="form-select" id="banner_type" name="banner_type" required>
                                <option value="desktop" <?php echo $activeTab === 'desktop' ? 'selected' : ''; ?>>PC</option>
                                <option value="mobile" <?php echo $activeTab === 'mobile' ? 'selected' : ''; ?>>Mobil</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <button type="submit" name="add_banner" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Banner Ekle
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Banner Tabs -->
        <ul class="nav nav-tabs" id="bannerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $activeTab === 'desktop' ? 'active' : ''; ?>" 
                       id="desktop-tab" data-bs-toggle="tab" data-bs-target="#desktop" 
                       type="button" role="tab" aria-controls="desktop" 
                       aria-selected="<?php echo $activeTab === 'desktop' ? 'true' : 'false'; ?>">
                    <i class="fas fa-desktop me-2"></i>PC Bannerlar                 </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $activeTab === 'mobile' ? 'active' : ''; ?>" 
                       id="mobile-tab" data-bs-toggle="tab" data-bs-target="#mobile" 
                       type="button" role="tab" aria-controls="mobile" 
                       aria-selected="<?php echo $activeTab === 'mobile' ? 'true' : 'false'; ?>">
                    <i class="fas fa-mobile-alt me-2"></i>Mobil Bannerlar                 </button>
            </li>
        </ul>
        
        <div class="tab-content" id="bannerTabsContent">
            <!-- Desktop Banners Tab -->
            <div class="tab-pane fade <?php echo $activeTab === 'desktop' ? 'show active' : ''; ?>" 
                 id="desktop" role="tabpanel" aria-labelledby="desktop-tab">
                <div class="table-responsive">
                    <table class="table table-hover" id="desktopBannersTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                <th><i class="fas fa-image me-2"></i>Önizleme</th>
                                <th><i class="fas fa-link me-2"></i>URL</th>
                                <th><i class="fas fa-tag me-2"></i>Alt Metin</th>
                                <th><i class="fas fa-gift me-2"></i>Promosyon</th>
                                <th class="text-center"><i class="fas fa-cogs me-2"></i>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($desktopBanners)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-images fa-3x mb-3"></i>
                                    <p>Henüz masaüstü banner eklenmemiş.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($desktopBanners as $banner): ?>
                                <tr>
                                    <td><strong><?php echo $banner['id']; ?></strong></td>
                                    <td>
                                        <div class="banner-preview">
                                            <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($banner['alt_text']); ?>" class="banner-img"
                                                 onerror="this.src='assets/img/image-error.png'">
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($banner['image_url']); ?>" 
                                           target="_blank" class="text-truncate d-inline-block" 
                                           style="max-width: 200px;" title="<?php echo htmlspecialchars($banner['image_url']); ?>">
                                            <?php echo htmlspecialchars($banner['image_url']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($banner['alt_text']); ?></td>
                                    <td>
                                        <?php if (!empty($banner['promo_title'])): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($banner['promo_title']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Bağlantı Yok</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-danger delete-banner"
                                                    data-id="<?php echo $banner['id']; ?>"
                                                    data-type="desktop"
                                                    title="Banner Sil">
                                                <i class="fas fa-trash"></i>
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
            
            <!-- Mobile Banners Tab -->
            <div class="tab-pane fade <?php echo $activeTab === 'mobile' ? 'show active' : ''; ?>" 
                 id="mobile" role="tabpanel" aria-labelledby="mobile-tab">
                <div class="table-responsive">
                    <table class="table table-hover" id="mobileBannersTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                <th><i class="fas fa-image me-2"></i>Önizleme</th>
                                <th><i class="fas fa-link me-2"></i>URL</th>
                                <th><i class="fas fa-tag me-2"></i>Alt Metin</th>
                                <th><i class="fas fa-gift me-2"></i>Promosyon</th>
                                <th class="text-center"><i class="fas fa-cogs me-2"></i>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mobileBanners)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fas fa-mobile-alt fa-3x mb-3"></i>
                                    <p>Henüz mobil banner eklenmemiş.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($mobileBanners as $banner): ?>
                                <tr>
                                    <td><strong><?php echo $banner['id']; ?></strong></td>
                                    <td>
                                        <div class="banner-preview">
                                            <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($banner['alt_text']); ?>" class="banner-img"
                                                 onerror="this.src='assets/img/image-error.png'">
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($banner['image_url']); ?>" 
                                           target="_blank" class="text-truncate d-inline-block" 
                                           style="max-width: 200px;" title="<?php echo htmlspecialchars($banner['image_url']); ?>">
                                            <?php echo htmlspecialchars($banner['image_url']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($banner['alt_text']); ?></td>
                                    <td>
                                        <?php if (!empty($banner['promo_title'])): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($banner['promo_title']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Bağlantı Yok</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-danger delete-banner"
                                                    data-id="<?php echo $banner['id']; ?>"
                                                    data-type="mobile"
                                                    title="Banner Sil">
                                                <i class="fas fa-trash"></i>
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
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#desktopBannersTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columnDefs: [
            {
                targets: -1,
                orderable: false,
                searchable: false
            }
        ]
    });
    
    $('#mobileBannersTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columnDefs: [
            {
                targets: -1,
                orderable: false,
                searchable: false
            }
        ]
    });
    
    // Initialize the tab that should be active
    const activeTab = '<?php echo $activeTab; ?>';
    const triggerEl = document.querySelector(`#${activeTab}-tab`);
    if (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        tabTrigger.show();
    }
    
    // Preview image when URL is entered
    document.getElementById('image_url').addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            const img = new Image();
            img.onload = function() {
                console.log('Image loaded successfully');
            };
            img.onerror = function() {
                console.log('Invalid image URL');
            };
            img.src = url;
        }
    });
    
    // Sweet Alert for delete confirmation
    document.querySelectorAll('.delete-banner').forEach(button => {
        button.addEventListener('click', function() {
            const bannerId = this.getAttribute('data-id');
            const bannerType = this.getAttribute('data-type');
            
            Swal.fire({
                title: 'Banner Sil',
                text: "Bu banner kalıcı olarak silinecektir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?delete=${bannerId}&type=${bannerType}&tab=${bannerType}`;
                }
            });
        });
    });
    
    // Auto-select banner type based on active tab
    document.querySelectorAll('#bannerTabs button').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            const activeTab = event.target.getAttribute('aria-controls');
            document.getElementById('banner_type').value = activeTab === 'mobile' ? 'mobile' : 'desktop';
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 