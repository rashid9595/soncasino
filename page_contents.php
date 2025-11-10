<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Set page title
$pageTitle = "Sayfa İçerikleri";

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

// Get site settings
try {
    $stmt = $db->query("SELECT * FROM site_ayarlar WHERE id = 1");
    $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$siteSettings) {
        // If no settings exist, create default settings
        $stmt = $db->prepare("INSERT INTO site_ayarlar (id, site_ad, site_email) VALUES (1, 'CÜNÜPBET', 'destek@cunupbet.com')");
        $stmt->execute();
        
        $stmt = $db->query("SELECT * FROM site_ayarlar WHERE id = 1");
        $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Site ayarları yüklenirken bir hata oluştu: " . $e->getMessage();
    $siteSettings = [
        'site_ad' => 'CÜNÜPBET',
        'site_email' => 'destek@cunupbet.com'
    ];
}

// Get contact settings
try {
    $stmt = $db->query("SELECT * FROM about_us_contact_settings WHERE id = 1");
    $contactSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contactSettings) {
        // If no contact settings exist, create default settings
        $stmt = $db->prepare("INSERT INTO about_us_contact_settings (id, support_email, finance_email, affiliate_email) VALUES (1, 'destek@ngsbahis.com', 'finans@ngsbahis.com', 'support@ngnaff.com')");
        $stmt->execute();
        
        $stmt = $db->query("SELECT * FROM about_us_contact_settings WHERE id = 1");
        $contactSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "İletişim ayarları yüklenirken bir hata oluştu: " . $e->getMessage();
    $contactSettings = [
        'support_email' => 'destek@ngsbahis.com',
        'finance_email' => 'finans@ngsbahis.com',
        'affiliate_email' => 'support@ngnaff.com'
    ];
}

// Handle site settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_site_settings'])) {
    $siteName = trim($_POST['site_ad']);
    $siteEmail = trim($_POST['site_email']);
    
    if (empty($siteName) || empty($siteEmail)) {
        $error = "Site adı ve e-posta alanları boş olamaz.";
    } elseif (!filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçersiz e-posta formatı.";
    } else {
        try {
            // Update settings
            $stmt = $db->prepare("UPDATE site_ayarlar SET site_ad = ?, site_email = ? WHERE id = 1");
            $stmt->execute([$siteName, $siteEmail]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Site ayarları güncellendi: $siteName"]);
            
            $message = "Site ayarları başarıyla güncellendi.";
            
            // Refresh settings after update
            $stmt = $db->query("SELECT * FROM site_ayarlar WHERE id = 1");
            $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Site ayarları güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Handle contact settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact_settings'])) {
    $supportEmail = trim($_POST['support_email']);
    $financeEmail = trim($_POST['finance_email']);
    $affiliateEmail = trim($_POST['affiliate_email']);
    
    $invalidEmails = [];
    
    if (empty($supportEmail)) {
        $invalidEmails[] = "Müşteri Destek E-posta";
    } elseif (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        $invalidEmails[] = "Müşteri Destek E-posta (geçersiz format)";
    }
    
    if (empty($financeEmail)) {
        $invalidEmails[] = "Finansal İşlemler E-posta";
    } elseif (!filter_var($financeEmail, FILTER_VALIDATE_EMAIL)) {
        $invalidEmails[] = "Finansal İşlemler E-posta (geçersiz format)";
    }
    
    if (empty($affiliateEmail)) {
        $invalidEmails[] = "Affiliate ve Reklam E-posta";
    } elseif (!filter_var($affiliateEmail, FILTER_VALIDATE_EMAIL)) {
        $invalidEmails[] = "Affiliate ve Reklam E-posta (geçersiz format)";
    }
    
    if (!empty($invalidEmails)) {
        $error = "Aşağıdaki alanlar hatalı veya boş: " . implode(", ", $invalidEmails);
    } else {
        try {
            // Update contact settings
            $stmt = $db->prepare("UPDATE about_us_contact_settings SET support_email = ?, finance_email = ?, affiliate_email = ? WHERE id = 1");
            $stmt->execute([$supportEmail, $financeEmail, $affiliateEmail]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "İletişim ayarları güncellendi"]);
            
            $message = "İletişim ayarları başarıyla güncellendi.";
            
            // Refresh contact settings after update
            $stmt = $db->query("SELECT * FROM about_us_contact_settings WHERE id = 1");
            $contactSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "İletişim ayarları güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Define content pages with their URLs
$contentPages = [
    [
        'id' => 'about_us',
        'title' => 'Hakkımızda',
        'url' => 'https://trendxgaming.com/contents/about-us'
    ],
    [
        'id' => 'terms_cond',
        'title' => 'Genel Kurallar ve Şartlar',
        'url' => 'https://trendxgaming.com/contents/about-us-gen-terms-cond'
    ],
    [
        'id' => 'responsible_gaming',
        'title' => 'Sorumlu Bahis',
        'url' => 'https://trendxgaming.com/contents/about-us-responsible-gaming'
    ],
    [
        'id' => 'privacy_policy',
        'title' => 'Gizlilik Şartları',
        'url' => 'https://trendxgaming.com/contents/about-us-privacy-policy'
    ],
    [
        'id' => 'terms_of_use',
        'title' => 'Kullanım Şartları',
        'url' => 'https://trendxgaming.com/contents/about-us-terms-of-use'
    ],
    [
        'id' => 'support_rules',
        'title' => 'Genel Bahis Kuralları',
        'url' => 'https://trendxgaming.com/contents/support-rules'
    ],
    [
        'id' => 'promotions_rules',
        'title' => 'Genel Bonus Kuralları',
        'url' => 'https://trendxgaming.com/contents/promotions-general-rules'
    ],
    [
        'id' => 'contact',
        'title' => 'Bize Ulaşın',
        'url' => 'https://trendxgaming.com/contents/about-us-contact'
    ],
    [
        'id' => 'withdrawal',
        'title' => 'Para Çekme',
        'url' => 'https://trendxgaming.com/contents/support-withdrawal'
    ],
    [
        'id' => 'deposit',
        'title' => 'Para Yatırma',
        'url' => 'https://trendxgaming.com/contents/support-deposit'
    ],
    [
        'id' => 'payments',
        'title' => 'Ödemeler',
        'url' => 'https://trendxgaming.com/contents/support-payments'
    ]
];

// Calculate statistics
try {
    // Total content pages
    $totalContentPages = count($contentPages);
    
    // Today's updates
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'update' AND (description LIKE '%Site ayarları%' OR description LIKE '%İletişim ayarları%') AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayUpdates = $stmt->fetchColumn();
    
    // Last update
    $stmt = $db->prepare("SELECT created_at FROM activity_logs WHERE action = 'update' AND (description LIKE '%Site ayarları%' OR description LIKE '%İletişim ayarları%') ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $lastUpdate = $stmt->fetchColumn();
    
    // Total updates
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'update' AND (description LIKE '%Site ayarları%' OR description LIKE '%İletişim ayarları%')");
    $stmt->execute();
    $totalUpdates = $stmt->fetchColumn();
    
    // Site settings status
    $siteSettingsConfigured = !empty($siteSettings['site_ad']) && !empty($siteSettings['site_email']);
    
    // Contact settings status
    $contactSettingsConfigured = !empty($contactSettings['support_email']) && !empty($contactSettings['finance_email']) && !empty($contactSettings['affiliate_email']);
    
} catch (Exception $e) {
    $totalContentPages = count($contentPages);
    $todayUpdates = $totalUpdates = 0;
    $lastUpdate = null;
    $siteSettingsConfigured = false;
    $contactSettingsConfigured = false;
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

.content-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.content-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.content-title {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.content-body {
    padding: 1.5rem;
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

.form-control {
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    padding: 0.75rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    outline: none;
}

.form-text {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.input-group-text {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: #ffffff;
    border: 1px solid var(--primary-color);
    font-weight: 500;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-size: 0.875rem;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: #ffffff;
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

.alert-info {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(8, 145, 178, 0.1));
    color: #0891b2;
    border-left: 4px solid var(--info-color);
}

.alert-link {
    color: var(--info-color);
    text-decoration: none;
    font-weight: 500;
}

.alert-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
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
    
    .content-body {
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
            <h1 class="greeting">Sayfa İçerikleri</h1>
            <p class="dashboard-subtitle">Site ayarları ve içerik sayfalarını yönetin</p>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <i class="fas fa-file-alt"></i>
        <h3><?php echo number_format($totalContentPages); ?></h3>
        <p>Toplam İçerik Sayfası</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-calendar-day"></i>
        <h3><?php echo number_format($todayUpdates); ?></h3>
        <p>Bugünkü Güncelleme</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-history"></i>
        <h3><?php echo number_format($totalUpdates); ?></h3>
        <p>Toplam Güncelleme</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-check-circle"></i>
        <h3><?php echo $siteSettingsConfigured ? 'Aktif' : 'Pasif'; ?></h3>
        <p>Site Ayarları</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-envelope"></i>
        <h3><?php echo $contactSettingsConfigured ? 'Aktif' : 'Pasif'; ?></h3>
        <p>İletişim Ayarları</p>
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

<!-- Content Card -->
<div class="content-card">
    <div class="content-header">
        <h2 class="content-title">
            <i class="fas fa-cogs"></i>
            Site ve İletişim Ayarları
        </h2>
    </div>
    <div class="content-body">
        <div class="row">
            <div class="col-md-6">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-gear"></i>
                        Site Temel Ayarları
                    </h3>
                    
                    <form method="post" action="">
                        <div class="mb-4">
                            <label for="site_ad" class="form-label">Site Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="site_ad" name="site_ad" 
                                   value="<?php echo htmlspecialchars($siteSettings['site_ad']); ?>" required
                                   placeholder="Site adını girin">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i> Bu isim web sitesinde ve içerik sayfalarında görüntülenecektir.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="site_email" class="form-label">Site E-posta Adresi <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="site_email" name="site_email" 
                                   value="<?php echo htmlspecialchars($siteSettings['site_email']); ?>" required
                                   placeholder="ornek@site.com">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i> Bu e-posta adresi iletişim formları ve bildirimler için kullanılacaktır.
                            </div>
                        </div>
                        
                        <button type="submit" name="update_site_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Ayarları Kaydet
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-envelope"></i>
                        İletişim Sayfası E-posta Ayarları
                    </h3>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Bu ayarlar "Bize Ulaşın" sayfasında gösterilen e-posta adreslerini belirler.
                        <a href="https://trendxgaming.com/contents/about-us-contact" target="_blank" class="alert-link">
                            <i class="fas fa-external-link-alt ms-1"></i>
                        </a>
                    </div>
                    
                    <form method="post" action="">
                        <div class="mb-4">
                            <label for="support_email" class="form-label">Müşteri Destek E-posta <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-headset"></i>
                                </span>
                                <input type="email" class="form-control" id="support_email" name="support_email" 
                                       value="<?php echo htmlspecialchars($contactSettings['support_email']); ?>" required
                                       placeholder="destek@site.com">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="finance_email" class="form-label">Finansal İşlemler E-posta <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-coins"></i>
                                </span>
                                <input type="email" class="form-control" id="finance_email" name="finance_email" 
                                       value="<?php echo htmlspecialchars($contactSettings['finance_email']); ?>" required
                                       placeholder="finans@site.com">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="affiliate_email" class="form-label">Affiliate ve Reklam E-posta <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-chart-line"></i>
                                </span>
                                <input type="email" class="form-control" id="affiliate_email" name="affiliate_email" 
                                       value="<?php echo htmlspecialchars($contactSettings['affiliate_email']); ?>" required
                                       placeholder="affiliate@site.com">
                            </div>
                        </div>
                        
                        <button type="submit" name="update_contact_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> İletişim Ayarlarını Kaydet
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let hasEmptyFields = false;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    hasEmptyFields = true;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Email validation
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value.trim() && !isValidEmail(field.value.trim())) {
                    hasEmptyFields = true;
                    field.classList.add('is-invalid');
                }
            });
            
            if (hasEmptyFields) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: 'Lütfen tüm gerekli alanları doğru şekilde doldurun.',
                    confirmButtonColor: '#ef4444'
                });
                return false;
            }
        });
    });
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 