<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Banner Ayarları";

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

// Create table if not exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS pages_vip_settings (
            id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            slider_image varchar(255) DEFAULT NULL,
            mobile_slider_image varchar(255) DEFAULT NULL,
            main_title varchar(255) DEFAULT NULL,
            main_description text DEFAULT NULL,
            created_at timestamp NULL DEFAULT current_timestamp()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Check if settings exist, if not insert default values
    $stmt = $db->query("SELECT COUNT(*) FROM pages_vip_settings");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $db->exec("
            INSERT INTO pages_vip_settings (slider_image, mobile_slider_image, main_title, main_description) VALUES
            ('https://v3.pronetstatic.com/ngsbet/upload_files/slider-vip2024.jpg',
             'https://v3.pronetstatic.com/ngsbet/upload_files/mobilslider-bg2024.jpg',
             'Rakipsiz VIP Hizmeti ve Deneyimi',
             'Özel avantajların kilidini açın ve hiçbir koşula bağlı kalmadan anında çekilebilir bonuslar alın')
        ");
    }
} catch (PDOException $e) {
    error_log('VIP Settings Table Error: ' . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        if (!$isAdmin && (!isset($userPermissions['vip_settings']['edit']) || !$userPermissions['vip_settings']['edit'])) {
            echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            exit();
        }
        
        $stmt = $db->prepare("
            UPDATE pages_vip_settings 
            SET " . $_POST['key'] . " = ?
            WHERE id = 1
        ");
        
        try {
            $stmt->execute([$_POST['value']]);
            echo json_encode(['status' => 'success', 'message' => 'Banner ayarı başarıyla güncellendi.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Banner ayarı güncellenirken bir hata oluştu.']);
        }
        exit();
    }
}

// Get statistics
try {
    // Get settings for statistics
    $stmt = $db->prepare("SELECT * FROM pages_vip_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $hasDesktopBanner = !empty($settings['slider_image']);
    $hasMobileBanner = !empty($settings['mobile_slider_image']);
    $hasTitle = !empty($settings['main_title']);
    $hasDescription = !empty($settings['main_description']);
    $titleLength = $hasTitle ? strlen($settings['main_title']) : 0;
    $descriptionLength = $hasDescription ? strlen($settings['main_description']) : 0;
    $totalSettings = ($hasDesktopBanner ? 1 : 0) + ($hasMobileBanner ? 1 : 0) + ($hasTitle ? 1 : 0) + ($hasDescription ? 1 : 0);
    
    // Check if images are accessible
    $desktopImageAccessible = false;
    $mobileImageAccessible = false;
    
    if ($hasDesktopBanner) {
        $headers = @get_headers($settings['slider_image']);
        $desktopImageAccessible = $headers && strpos($headers[0], '200') !== false;
    }
    
    if ($hasMobileBanner) {
        $headers = @get_headers($settings['mobile_slider_image']);
        $mobileImageAccessible = $headers && strpos($headers[0], '200') !== false;
    }
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $settings = [];
    $hasDesktopBanner = $hasMobileBanner = $hasTitle = $hasDescription = false;
    $titleLength = $descriptionLength = $totalSettings = 0;
    $desktopImageAccessible = $mobileImageAccessible = false;
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
    
    .stat-card.desktop::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.mobile::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.title::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.description::after {
        background: var(--primary-gradient);
    }

    .stat-card.accessible::after {
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
    
    .stat-card.desktop .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.mobile .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.title .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.description .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.accessible .stat-icon {
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

    .settings-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .settings-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .settings-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
    }

    .settings-header h5 {
        margin: 0;
        color: var(--text-heading);
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .settings-header h5 i {
        color: var(--primary-blue-light);
        margin-right: 0.5rem;
    }

    .settings-body {
        padding: 2rem;
    }

    .setting-item {
        margin-bottom: 2.5rem;
        padding: 1.5rem;
        background: var(--bg-secondary);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }

    .setting-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }

    .setting-item:last-child {
        margin-bottom: 0;
    }

    .setting-label {
        font-weight: 600;
        color: var(--text-heading);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.1rem;
    }

    .setting-label i {
        color: var(--primary-blue-light);
        font-size: 1.2rem;
    }

    .setting-value {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .setting-value .form-control {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
        border-radius: var(--border-radius);
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }

    .setting-value textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    .setting-value .form-control:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        outline: none;
    }

    .banner-preview {
        margin-top: 1rem;
        border: 2px solid var(--card-border);
        border-radius: var(--border-radius);
        overflow: hidden;
        max-width: 100%;
        position: relative;
        background: var(--card-bg);
        box-shadow: var(--shadow-sm);
    }

    .banner-preview img {
        width: 100%;
        height: auto;
        display: block;
        transition: all 0.3s ease;
    }

    .banner-preview:hover img {
        transform: scale(1.02);
    }

    .banner-preview .banner-label {
        position: absolute;
        top: 10px;
        left: 10px;
        background: var(--primary-gradient);
        color: white;
        padding: 6px 12px;
        border-radius: var(--border-radius-sm);
        font-size: 0.8rem;
        font-weight: 600;
        box-shadow: var(--shadow-sm);
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

    .alert-info i {
        color: var(--primary-blue-light);
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
        
        .settings-body {
            padding: 1.5rem;
        }
        
        .setting-value {
            flex-direction: column;
            gap: 1rem;
        }
        
        .setting-item {
            padding: 1rem;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-images-fill me-2"></i>
        VIP Banner Ayarları
    </h1>
    <p class="dashboard-subtitle">VIP sayfası banner görsellerini ve metinlerini yönetin ve düzenleyin.</p>
</div>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-gear"></i>
        </div>
        <div class="stat-number"><?php echo $totalSettings; ?>/4</div>
        <div class="stat-title">Aktif Ayar</div>
    </div>
    
    <div class="stat-card desktop">
        <div class="stat-icon">
            <i class="bi bi-display"></i>
        </div>
        <div class="stat-number"><?php echo $hasDesktopBanner ? '✓' : '✗'; ?></div>
        <div class="stat-title">Desktop Banner</div>
    </div>
    
    <div class="stat-card mobile">
        <div class="stat-icon">
            <i class="bi bi-phone"></i>
        </div>
        <div class="stat-number"><?php echo $hasMobileBanner ? '✓' : '✗'; ?></div>
        <div class="stat-title">Mobil Banner</div>
    </div>
    
    <div class="stat-card title">
        <div class="stat-icon">
            <i class="bi bi-type-h1"></i>
        </div>
        <div class="stat-number"><?php echo $titleLength; ?></div>
        <div class="stat-title">Başlık Uzunluğu</div>
    </div>
    
    <div class="stat-card description">
        <div class="stat-icon">
            <i class="bi bi-text-paragraph"></i>
        </div>
        <div class="stat-number"><?php echo $descriptionLength; ?></div>
        <div class="stat-title">Açıklama Uzunluğu</div>
    </div>
</div>

<div class="settings-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="settings-header">
        <h5><i class="bi bi-sliders"></i>VIP Sayfası Banner Ayarları</h5>
    </div>
    <div class="settings-body">
        <div class="setting-item">
            <div class="setting-label">
                <i class="bi bi-display"></i>
                Desktop Banner
            </div>
            <div class="setting-value">
                <input type="text" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($settings['slider_image']); ?>" 
                       id="slider_image"
                       placeholder="https://example.com/desktop-banner.jpg"
                       <?php if (!$isAdmin): ?>readonly<?php endif; ?>>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="updateSetting('slider_image')">
                    <i class="bi bi-save me-1"></i>Kaydet
                </button>
                <?php endif; ?>
            </div>
            <?php if ($settings['slider_image']): ?>
            <div class="banner-preview">
                <div class="banner-label">Desktop Önizleme</div>
                <img src="<?php echo htmlspecialchars($settings['slider_image']); ?>" alt="Desktop Banner" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <div style="display: none; padding: 2rem; text-align: center; color: var(--text-secondary);">
                    <i class="bi bi-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>Görsel yüklenemedi</p>
                </div>
            </div>
            <?php endif; ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                Desktop cihazlarda görüntülenecek banner görseli. Önerilen boyut: 1920x600px
            </div>
        </div>

        <div class="setting-item">
            <div class="setting-label">
                <i class="bi bi-phone"></i>
                Mobil Banner
            </div>
            <div class="setting-value">
                <input type="text" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($settings['mobile_slider_image']); ?>" 
                       id="mobile_slider_image"
                       placeholder="https://example.com/mobile-banner.jpg"
                       <?php if (!$isAdmin): ?>readonly<?php endif; ?>>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="updateSetting('mobile_slider_image')">
                    <i class="bi bi-save me-1"></i>Kaydet
                </button>
                <?php endif; ?>
            </div>
            <?php if ($settings['mobile_slider_image']): ?>
            <div class="banner-preview">
                <div class="banner-label">Mobil Önizleme</div>
                <img src="<?php echo htmlspecialchars($settings['mobile_slider_image']); ?>" alt="Mobile Banner" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <div style="display: none; padding: 2rem; text-align: center; color: var(--text-secondary);">
                    <i class="bi bi-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>Görsel yüklenemedi</p>
                </div>
            </div>
            <?php endif; ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                Mobil cihazlarda görüntülenecek banner görseli. Önerilen boyut: 768x400px
            </div>
        </div>

        <div class="setting-item">
            <div class="setting-label">
                <i class="bi bi-type-h1"></i>
                Ana Başlık
            </div>
            <div class="setting-value">
                <input type="text" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($settings['main_title']); ?>" 
                       id="main_title"
                       placeholder="VIP sayfası ana başlığı"
                       <?php if (!$isAdmin): ?>readonly<?php endif; ?>>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="updateSetting('main_title')">
                    <i class="bi bi-save me-1"></i>Kaydet
                </button>
                <?php endif; ?>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                VIP sayfasında görüntülenecek ana başlık. Maksimum 255 karakter.
            </div>
        </div>

        <div class="setting-item">
            <div class="setting-label">
                <i class="bi bi-text-paragraph"></i>
                Ana Açıklama
            </div>
            <div class="setting-value">
                <textarea 
                       class="form-control" 
                       id="main_description"
                       placeholder="VIP sayfası açıklama metni"
                       <?php if (!$isAdmin): ?>readonly<?php endif; ?>><?php echo htmlspecialchars($settings['main_description']); ?></textarea>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="updateSetting('main_description')">
                    <i class="bi bi-save me-1"></i>Kaydet
                </button>
                <?php endif; ?>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                VIP sayfasında görüntülenecek açıklama metni. HTML etiketleri kullanılabilir.
            </div>
        </div>
    </div>
</div>

<script>
function updateSetting(key) {
    const value = document.getElementById(key).value;
    
    $.ajax({
        url: 'vip_banner_settings.php',
        type: 'POST',
        data: {
            action: 'edit',
            key: key,
            value: value
        },
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    // Sayfa yenilensin ki önizlemeler güncellensin
                    location.reload();
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