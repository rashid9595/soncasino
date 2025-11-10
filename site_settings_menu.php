<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Set page title
$pageTitle = "Site Adı ve Logo Ayarları";

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

// Get current site settings
try {
    $stmt = $db->query("SELECT * FROM site_settings WHERE id = 1");
    $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$siteSettings) {
        // If no settings exist, create default settings
        $stmt = $db->prepare("INSERT INTO site_settings (id, logo_path, site_title) VALUES (1, '/views/trader/ngsbet/assets/images/logo.png', 'NGSBahis - Türkiye''nin En İyi Spor Bahis ve Casino Sitesi')");
        $stmt->execute();
        
        $stmt = $db->query("SELECT * FROM site_settings WHERE id = 1");
        $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Site ayarları yüklenirken bir hata oluştu: " . $e->getMessage();
    $siteSettings = [
        'logo_path' => '/views/trader/ngsbet/assets/images/logo.png',
        'site_title' => 'NGSBahis - Türkiye\'nin En İyi Spor Bahis ve Casino Sitesi'
    ];
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $logoPath = trim($_POST['logo_path']);
    $siteTitle = trim($_POST['site_title']);
    
    if (empty($logoPath) || empty($siteTitle)) {
        $error = "Logo yolu ve site başlığı alanları boş olamaz.";
    } else {
        try {
            // Update settings
            $stmt = $db->prepare("UPDATE site_settings SET logo_path = ?, site_title = ? WHERE id = 1");
            $stmt->execute([$logoPath, $siteTitle]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Site ayarları güncellendi: $siteTitle"]);
            
            $message = "Site ayarları başarıyla güncellendi.";
            
            // Refresh settings after update
            $stmt = $db->query("SELECT * FROM site_settings WHERE id = 1");
            $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Site ayarları güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Calculate statistics
try {
    // Total settings records
    $stmt = $db->prepare("SELECT COUNT(*) FROM site_settings");
    $stmt->execute();
    $totalSettings = $stmt->fetchColumn();
    
    // Settings with logo
    $stmt = $db->prepare("SELECT COUNT(*) FROM site_settings WHERE logo_path IS NOT NULL AND logo_path != ''");
    $stmt->execute();
    $settingsWithLogo = $stmt->fetchColumn();
    
    // Settings with title
    $stmt = $db->prepare("SELECT COUNT(*) FROM site_settings WHERE site_title IS NOT NULL AND site_title != ''");
    $stmt->execute();
    $settingsWithTitle = $stmt->fetchColumn();
    
    // Today's updates
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'update' AND description LIKE '%Site ayarları%' AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayUpdates = $stmt->fetchColumn();
    
    // Last update
    $stmt = $db->prepare("SELECT created_at FROM activity_logs WHERE action = 'update' AND description LIKE '%Site ayarları%' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $lastUpdate = $stmt->fetchColumn();
    
    // Total updates
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'update' AND description LIKE '%Site ayarları%'");
    $stmt->execute();
    $totalUpdates = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $totalSettings = $settingsWithLogo = $settingsWithTitle = $todayUpdates = $totalUpdates = 0;
    $lastUpdate = null;
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

.settings-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.settings-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.settings-title {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.settings-body {
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

.preview-section {
    background: #f8fafc;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    border: 1px solid var(--border-color);
}

.logo-preview {
    background: #ffffff;
    border-radius: 0.375rem;
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 120px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.browser-tab {
    background: #ffffff;
    border-radius: 0.375rem 0.375rem 0 0;
    padding: 0.75rem;
    display: flex;
    align-items: center;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
    width: 250px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.browser-tab img {
    width: 16px;
    height: 16px;
    margin-right: 0.5rem;
    border-radius: 0.125rem;
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
    
    .settings-body {
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
            <h1 class="greeting">Site Adı ve Logo Ayarları</h1>
            <p class="dashboard-subtitle">Site başlığı ve logo ayarlarını yönetin</p>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <i class="fas fa-cogs"></i>
        <h3><?php echo number_format($totalSettings); ?></h3>
        <p>Toplam Ayar</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-image"></i>
        <h3><?php echo number_format($settingsWithLogo); ?></h3>
        <p>Logo Ayarı</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-heading"></i>
        <h3><?php echo number_format($settingsWithTitle); ?></h3>
        <p>Başlık Ayarı</p>
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

<!-- Settings Card -->
<div class="settings-card">
    <div class="settings-header">
        <h2 class="settings-title">
            <i class="fas fa-gear"></i>
            Site Ayarları
        </h2>
    </div>
    <div class="settings-body">
        <form method="post" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-sliders-h"></i>
                            Temel Ayarlar
                        </h3>
                        
                        <div class="mb-4">
                            <label for="site_title" class="form-label">Site Başlığı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" 
                                   id="site_title" name="site_title" required 
                                   value="<?php echo htmlspecialchars($siteSettings['site_title']); ?>"
                                   placeholder="Site başlığını girin">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i> Site başlığı, tarayıcı sekmesinde ve SEO için kullanılır.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="logo_path" class="form-label">Logo URL Yolu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" 
                                   id="logo_path" name="logo_path" required 
                                   value="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>"
                                   placeholder="/views/trader/ngsbet/assets/images/logo.png">
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i> Logo yolunu /views/ ile başlayan şekilde girin.
                            </div>
                        </div>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Ayarları Kaydet
                        </button>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-eye"></i>
                            Önizleme
                        </h3>
                        
                        <div class="preview-section">
                            <div class="mb-4">
                                <label class="form-label">Mevcut Logo</label>
                                <div class="logo-preview">
                                    <img id="logoPreview" src="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>" 
                                         alt="Site Logo" class="img-fluid" style="max-height: 80px;"
                                         onerror="this.src='assets/img/image-error.png'; this.classList.add('img-thumbnail');">
                                </div>
                                <div class="form-text text-center">
                                    Logo önizlemesi
                                </div>
                            </div>
                            
                            <div class="browser-preview">
                                <label class="form-label">Tarayıcı Sekmesi Önizlemesi</label>
                                <div class="browser-tab">
                                    <img src="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>" 
                                         alt="Favicon" 
                                         onerror="this.src='assets/img/image-error.png';">
                                    <span id="titlePreview"><?php echo htmlspecialchars($siteSettings['site_title']); ?></span>
                                </div>
                                <div class="form-text text-center mt-2">
                                    Site başlığı ve favicon önizlemesi
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Update logo preview when logo path changes
    document.getElementById('logo_path').addEventListener('input', function() {
        const logoPath = this.value.trim();
        if (logoPath) {
            document.getElementById('logoPreview').src = logoPath;
            const faviconPreview = document.querySelector('.browser-tab img');
            faviconPreview.src = logoPath;
        }
    });
    
    // Update title preview when site title changes
    document.getElementById('site_title').addEventListener('input', function() {
        const siteTitle = this.value.trim();
        if (siteTitle) {
            document.getElementById('titlePreview').textContent = siteTitle;
        }
    });
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const logoPath = document.getElementById('logo_path').value.trim();
        const siteTitle = document.getElementById('site_title').value.trim();
        
        if (!logoPath || !siteTitle) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Lütfen tüm gerekli alanları doldurun.',
                confirmButtonColor: '#ef4444'
            });
            return false;
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 