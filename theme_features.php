<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Tema Ayarları";
$currentPage = "theme_features";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Get user info
$stmt = $db->prepare("SELECT * FROM administrators WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();

// Handle theme save
if ($_POST && isset($_POST['save_theme'])) {
    $selectedTheme = $_POST['theme'] ?? 'default';
    
    // Save to database
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('active_theme', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$selectedTheme, $selectedTheme]);
    
    // Set session
    $_SESSION['active_theme'] = $selectedTheme;
    
    $successMessage = "Tema başarıyla güncellendi!";
}

// Get current theme
$currentTheme = $_SESSION['active_theme'] ?? 'default';

ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-palette"></i> Tema Ayarları</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> <?php echo $successMessage; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="themeForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="theme-preview-section">
                                    <h6 class="mb-3">Tema Önizleme</h6>
                                    <div class="theme-preview-container" id="themePreview">
                                        <!-- Theme preview will be updated here -->
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="theme-selection-section">
                                    <h6 class="mb-3">Tema Seçimi</h6>
                                    <div class="theme-options-grid">
                                        <div class="theme-option-card" data-theme="default">
                                            <div class="theme-preview-box default-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Varsayılan</h6>
                                                <p>Beyaz ve Mavi</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="blue">
                                            <div class="theme-preview-box blue-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Mavi</h6>
                                                <p>Modern Mavi</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="green">
                                            <div class="theme-preview-box green-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Yeşil</h6>
                                                <p>Doğal Yeşil</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="purple">
                                            <div class="theme-preview-box purple-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Mor</h6>
                                                <p>Elegant Mor</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="orange">
                                            <div class="theme-preview-box orange-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Turuncu</h6>
                                                <p>Enerjik Turuncu</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="red">
                                            <div class="theme-preview-box red-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Kırmızı</h6>
                                                <p>Dinamik Kırmızı</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="pink">
                                            <div class="theme-preview-box pink-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Pembe</h6>
                                                <p>Şık Pembe</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="teal">
                                            <div class="theme-preview-box teal-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Teal</h6>
                                                <p>Modern Teal</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="indigo">
                                            <div class="theme-preview-box indigo-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Indigo</h6>
                                                <p>Profesyonel Indigo</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="amber">
                                            <div class="theme-preview-box amber-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Amber</h6>
                                                <p>Sıcak Amber</p>
                                            </div>
                                        </div>
                                        
                                        <div class="theme-option-card" data-theme="cyan">
                                            <div class="theme-preview-box cyan-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-sidebar"></div>
                                                <div class="theme-content"></div>
                                            </div>
                                            <div class="theme-info">
                                                <h6>Cyan</h6>
                                                <p>Ferah Cyan</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="theme" id="selectedTheme" value="<?php echo $currentTheme; ?>">
                                    
                                    <div class="mt-4">
                                        <button type="submit" name="save_theme" class="btn btn-primary btn-lg w-100">
                                            <i class="bi bi-check-circle"></i> Temayı Uygula
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.theme-preview-section {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 2rem;
    border: 1px solid var(--border-color);
}

.theme-preview-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: var(--shadow);
    min-height: 300px;
}

.theme-selection-section {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
}

.theme-options-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
}

.theme-option-card {
    background: white;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.theme-option-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.theme-option-card.active {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 2px var(--accent-color), var(--shadow-lg);
}

.theme-preview-box {
    width: 100%;
    height: 80px;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.theme-header {
    height: 20%;
    background: var(--accent-color);
    border-radius: 8px 8px 0 0;
}

.theme-sidebar {
    position: absolute;
    left: 0;
    top: 20%;
    width: 30%;
    height: 80%;
    background: var(--bg-secondary);
    border-right: 1px solid var(--border-color);
}

.theme-content {
    position: absolute;
    right: 0;
    top: 20%;
    width: 70%;
    height: 80%;
    background: var(--bg-primary);
    border-radius: 0 0 8px 0;
}

.theme-info h6 {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

.theme-info p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

/* Theme Preview Styles */
.default-theme .theme-header { background: #2563eb; }
.default-theme .theme-sidebar { background: #f8f9fa; }
.default-theme .theme-content { background: #ffffff; }

.blue-theme .theme-header { background: #3b82f6; }
.blue-theme .theme-sidebar { background: #eff6ff; }
.blue-theme .theme-content { background: #ffffff; }

.green-theme .theme-header { background: #22c55e; }
.green-theme .theme-sidebar { background: #f0fdf4; }
.green-theme .theme-content { background: #ffffff; }

.purple-theme .theme-header { background: #a855f7; }
.purple-theme .theme-sidebar { background: #faf5ff; }
.purple-theme .theme-content { background: #ffffff; }

.orange-theme .theme-header { background: #f97316; }
.orange-theme .theme-sidebar { background: #fff7ed; }
.orange-theme .theme-content { background: #ffffff; }

.red-theme .theme-header { background: #ef4444; }
.red-theme .theme-sidebar { background: #fef2f2; }
.red-theme .theme-content { background: #ffffff; }

.pink-theme .theme-header { background: #ec4899; }
.pink-theme .theme-sidebar { background: #fdf2f8; }
.pink-theme .theme-content { background: #ffffff; }

.teal-theme .theme-header { background: #14b8a6; }
.teal-theme .theme-sidebar { background: #f0fdfa; }
.teal-theme .theme-content { background: #ffffff; }

.indigo-theme .theme-header { background: #6366f1; }
.indigo-theme .theme-sidebar { background: #eef2ff; }
.indigo-theme .theme-content { background: #ffffff; }

.amber-theme .theme-header { background: #f59e0b; }
.amber-theme .theme-sidebar { background: #fffbeb; }
.amber-theme .theme-content { background: #ffffff; }

.cyan-theme .theme-header { background: #06b6d4; }
.cyan-theme .theme-sidebar { background: #ecfeff; }
.cyan-theme .theme-content { background: #ffffff; }

@media (max-width: 768px) {
    .theme-options-grid {
        grid-template-columns: 1fr;
    }
    
    .theme-preview-section {
        margin-bottom: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const themeCards = document.querySelectorAll('.theme-option-card');
    const selectedThemeInput = document.getElementById('selectedTheme');
    const themePreview = document.getElementById('themePreview');
    
    // Set initial active theme
    const currentTheme = '<?php echo $currentTheme; ?>';
    const activeCard = document.querySelector(`[data-theme="${currentTheme}"]`);
    if (activeCard) {
        activeCard.classList.add('active');
    }
    
    // Theme selection
    themeCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove active class from all cards
            themeCards.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked card
            this.classList.add('active');
            
            // Update hidden input
            const theme = this.dataset.theme;
            selectedThemeInput.value = theme;
            
            // Update theme preview
            updateThemePreview(theme);
            
            // Apply theme to page for preview
            document.documentElement.setAttribute('data-theme', theme);
        });
    });
    
    function updateThemePreview(theme) {
        // Update preview content based on theme
        themePreview.innerHTML = `
            <div class="text-center">
                <h5>${theme.charAt(0).toUpperCase() + theme.slice(1)} Tema Önizlemesi</h5>
                <p class="text-muted">Bu tema tüm sayfalarda uygulanacaktır.</p>
            </div>
        `;
    }
    
    // Initialize preview
    updateThemePreview(currentTheme);
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <?php echo $pageContent; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
