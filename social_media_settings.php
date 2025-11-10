<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Set page title
$pageTitle = "Sosyal Medya Ayarları";

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

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_social_links'])) {
    try {
        $db->beginTransaction();
        
        // Prepare statement for update
        $stmt = $db->prepare("UPDATE social_links SET url = ? WHERE id = ?");
        
        // Count successful updates
        $updateCount = 0;
        
        // Process each social media URL update
        foreach ($_POST['social_url'] as $id => $url) {
            $url = trim($url);
            
            // Validate URL
            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $error = "Geçersiz URL formatı: " . htmlspecialchars($url);
                $db->rollBack();
                break;
            }
            
            // Execute update
            $stmt->execute([$url, $id]);
            $updateCount++;
        }
        
        if (empty($error)) {
            $db->commit();
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Sosyal medya bağlantıları güncellendi: $updateCount bağlantı"]);
            
            $message = "$updateCount sosyal medya bağlantısı başarıyla güncellendi.";
        }
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Sosyal medya bağlantıları güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// Get all social links
try {
    $stmt = $db->query("SELECT * FROM social_links ORDER BY id");
    $socialLinks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Sosyal medya bağlantıları yüklenirken bir hata oluştu: " . $e->getMessage();
    $socialLinks = [];
}

// Platform display names map
$platformNames = [
    'facebook-square' => 'Facebook',
    'twitter-square' => 'Twitter',
    'youtube' => 'YouTube',
    'instagram' => 'Instagram',
    'telegram' => 'Telegram',
    'linkedin' => 'LinkedIn',
    'pinterest' => 'Pinterest',
    'snapchat-square' => 'Snapchat',
    'tiktok' => 'TikTok',
    'whatsapp' => 'WhatsApp',
    'viber' => 'Viber',
    'discord' => 'Discord'
];

// Calculate statistics
try {
    // Total social links
    $stmt = $db->prepare("SELECT COUNT(*) FROM social_links");
    $stmt->execute();
    $totalSocialLinks = $stmt->fetchColumn();
    
    // Active social links (with URL)
    $stmt = $db->prepare("SELECT COUNT(*) FROM social_links WHERE url IS NOT NULL AND url != ''");
    $stmt->execute();
    $activeSocialLinks = $stmt->fetchColumn();
    
    // Today's updates
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'update' AND description LIKE '%Sosyal medya%' AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayUpdates = $stmt->fetchColumn();
    
    // Last update
    $stmt = $db->prepare("SELECT created_at FROM activity_logs WHERE action = 'update' AND description LIKE '%Sosyal medya%' ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $lastUpdate = $stmt->fetchColumn();
    
    // Total updates
    $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'update' AND description LIKE '%Sosyal medya%'");
    $stmt->execute();
    $totalUpdates = $stmt->fetchColumn();
    
    // Most popular platforms
    $stmt = $db->prepare("SELECT platform, COUNT(*) as count FROM social_links WHERE url IS NOT NULL AND url != '' GROUP BY platform ORDER BY count DESC LIMIT 1");
    $stmt->execute();
    $mostPopularPlatform = $stmt->fetch();
    
} catch (Exception $e) {
    $totalSocialLinks = $activeSocialLinks = $todayUpdates = $totalUpdates = 0;
    $lastUpdate = null;
    $mostPopularPlatform = null;
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

.social-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.social-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.social-title {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.social-body {
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

.input-group {
    margin-bottom: 1rem;
}

.input-group-text {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: #ffffff;
    border: 1px solid var(--primary-color);
    font-weight: 500;
    min-width: 120px;
    justify-content: center;
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

.btn-outline-secondary {
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    background: transparent;
}

.btn-outline-secondary:hover {
    background: var(--light-bg);
    color: var(--text-primary);
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

.info-section {
    background: #f8fafc;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    height: 100%;
}

.preview-container {
    background: #ffffff;
    border-radius: var(--border-radius);
    padding: 1rem;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.preview-frame {
    background: #f8fafc;
    border-radius: 0.375rem;
    padding: 1rem;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-color);
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
    
    .social-body {
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
            <h1 class="greeting">Sosyal Medya Ayarları</h1>
            <p class="dashboard-subtitle">Sosyal medya bağlantılarını yönetin ve düzenleyin</p>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <i class="fas fa-share-alt"></i>
        <h3><?php echo number_format($totalSocialLinks); ?></h3>
        <p>Toplam Platform</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-link"></i>
        <h3><?php echo number_format($activeSocialLinks); ?></h3>
        <p>Aktif Bağlantı</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-history"></i>
        <h3><?php echo number_format($totalUpdates); ?></h3>
        <p>Toplam Güncelleme</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-clock"></i>
        <h3><?php echo $lastUpdate ? date('d.m', strtotime($lastUpdate)) : 'N/A'; ?></h3>
        <p>Son Güncelleme</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-star"></i>
        <h3><?php echo $mostPopularPlatform ? $platformNames[$mostPopularPlatform['platform']] ?? $mostPopularPlatform['platform'] : 'N/A'; ?></h3>
        <p>En Popüler Platform</p>
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

<!-- Social Media Card -->
<div class="social-card">
    <div class="social-header">
        <h2 class="social-title">
            <i class="fas fa-share-alt"></i>
            Sosyal Medya Bağlantıları
        </h2>
    </div>
    <div class="social-body">
        <form method="post" action="">
            <div class="row">
                <div class="col-md-7">
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-link"></i>
                            Platform Bağlantıları
                        </h3>
                        
                        <?php if (empty($socialLinks)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Henüz sosyal medya bağlantısı eklenmemiş.
                            </div>
                        <?php else: ?>
                            <?php foreach ($socialLinks as $link): ?>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fab fa-<?php echo htmlspecialchars($link['platform']); ?> me-2"></i>
                                        <?php echo htmlspecialchars($platformNames[$link['platform']] ?? $link['platform']); ?>
                                    </span>
                                    <input type="url" class="form-control" 
                                           name="social_url[<?php echo $link['id']; ?>]" 
                                           value="<?php echo htmlspecialchars($link['url']); ?>"
                                           placeholder="https://example.com">
                                    <button class="btn btn-outline-secondary preview-btn" type="button" 
                                            data-url="<?php echo htmlspecialchars($link['url']); ?>"
                                            title="Önizle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                            
                            <button type="submit" name="update_social_links" class="btn btn-primary mt-3">
                                <i class="fas fa-save"></i> Değişiklikleri Kaydet
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <div class="info-section">
                        <h3 class="form-section-title">
                            <i class="fas fa-info-circle"></i>
                            Bilgi ve İpuçları
                        </h3>
                        
                        <div class="alert alert-info">
                            <h5 class="alert-heading">
                                <i class="fas fa-lightbulb me-2"></i> İpuçları
                            </h5>
                            <p>Sosyal medya bağlantılarınızı güncellemek için aşağıdaki adımları izleyin:</p>
                            <ol>
                                <li>Her platformun tam URL'sini girin (https:// dahil)</li>
                                <li>Boş bırakmak istediğiniz platformlar için alanı temizleyin</li>
                                <li>URL'leri test etmek için göz simgesine tıklayın</li>
                                <li>Değişiklikleri kaydetmek için formu gönderin</li>
                            </ol>
                            <hr>
                            <p class="mb-0">Sosyal medya bağlantıları, sitenizin ziyaretçilerinin sosyal medya hesaplarınıza kolayca ulaşmasını sağlar.</p>
                        </div>
                        
                        <div class="preview-container mt-4 d-none">
                            <h6 class="mb-3">
                                <i class="fas fa-eye me-2"></i> Önizleme
                            </h6>
                            <div class="preview-frame">
                                <a href="#" target="_blank" id="previewLink" class="btn btn-primary">
                                    <span id="previewText"></span>
                                </a>
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
    // Preview button functionality
    document.querySelectorAll('.preview-btn').forEach(button => {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            if (url) {
                const container = document.querySelector('.preview-container');
                const linkElement = document.getElementById('previewLink');
                const textElement = document.getElementById('previewText');
                
                container.classList.remove('d-none');
                linkElement.setAttribute('href', url);
                
                // Display domain name from URL
                let domain = url;
                try {
                    domain = new URL(url).hostname;
                } catch (e) {
                    console.error('Invalid URL:', e);
                }
                
                textElement.textContent = domain;
            }
        });
    });
    
    // Live update of preview link and data-url attribute when input changes
    document.querySelectorAll('.input-group input[type="url"]').forEach(input => {
        input.addEventListener('input', function() {
            const url = this.value.trim();
            const previewBtn = this.parentElement.querySelector('.preview-btn');
            previewBtn.setAttribute('data-url', url);
        });
    });
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const urlInputs = document.querySelectorAll('input[type="url"]');
        let hasInvalidUrl = false;
        
        urlInputs.forEach(input => {
            const url = input.value.trim();
            if (url && !isValidUrl(url)) {
                hasInvalidUrl = true;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (hasInvalidUrl) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Lütfen geçerli URL formatları girin.',
                confirmButtonColor: '#ef4444'
            });
            return false;
        }
    });
    
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 