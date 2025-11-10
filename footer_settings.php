<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// İzin kontrolü - Sadece super admin (role_id = 1)
if ($_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

// İstatistikleri hesapla
try {
    $stmt = $db->query("SELECT COUNT(*) as totalSettings FROM footer_settings");
    $totalSettings = $stmt->fetch()['totalSettings'];

    $stmt = $db->query("SELECT COUNT(*) as totalMenus FROM footer_menu_settings");
    $totalMenus = $stmt->fetch()['totalMenus'];

    $stmt = $db->query("SELECT COUNT(*) as totalGames FROM footer_popular_games");
    $totalGames = $stmt->fetch()['totalGames'];

    $stmt = $db->query("SELECT COUNT(*) as todayUpdates FROM footer_settings WHERE DATE(updated_at) = CURDATE()");
    $todayUpdates = $stmt->fetch()['todayUpdates'];

    $stmt = $db->query("SELECT COUNT(*) as todayMenus FROM footer_menu_settings WHERE DATE(created_at) = CURDATE()");
    $todayMenus = $stmt->fetch()['todayMenus'];

    $stmt = $db->query("SELECT COUNT(*) as todayGames FROM footer_popular_games WHERE DATE(created_at) = CURDATE()");
    $todayGames = $stmt->fetch()['todayGames'];

} catch (Exception $e) {
    $totalSettings = 0;
    $totalMenus = 0;
    $totalGames = 0;
    $todayUpdates = 0;
    $todayMenus = 0;
    $todayGames = 0;
}

// Mevcut footer ayarlarını getir
try {
    $stmt = $db->query("SELECT * FROM footer_settings WHERE id = 1");
    $footerSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$footerSettings) {
        // Varsayılan ayarları oluştur
        $stmt = $db->prepare("INSERT INTO footer_settings (id, copyright_text, start_year, company_name, created_at) VALUES (1, 'COPYRIGHT', ?, ?)");
        $stmt->execute([date('Y'), 'Site Adı']);
        
        $stmt = $db->query("SELECT * FROM footer_settings WHERE id = 1");
        $footerSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $footerSettings = [
        'copyright_text' => 'COPYRIGHT',
        'start_year' => date('Y'),
        'company_name' => 'Site Adı'
    ];
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_footer_settings':
                try {
                    $companyName = trim($_POST['company_name']);
                    $copyrightText = trim($_POST['copyright_text']);
                    $startYear = $_POST['start_year'];
                    
                    if (empty($companyName)) {
                        $_SESSION['error'] = "Site adı alanı boş olamaz.";
                    } else {
                        $stmt = $db->prepare("UPDATE footer_settings SET company_name = ?, copyright_text = ?, start_year = ?, updated_at = NOW() WHERE id = 1");
                        $stmt->execute([$companyName, $copyrightText, $startYear]);

                        // Aktivite logu
                        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$_SESSION['admin_id'], 'update_footer_settings', "Footer ayarları güncellendi: $companyName", $_SERVER['REMOTE_ADDR']]);

                        $_SESSION['success'] = "Footer ayarları başarıyla güncellendi.";
                        
                        // Ayarları yenile
                        $stmt = $db->query("SELECT * FROM footer_settings WHERE id = 1");
                        $footerSettings = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Footer ayarları güncellenirken hata oluştu: " . $e->getMessage();
                }
                break;
        }
    }
}

ob_start();
?>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-0">
                    <i class="bi bi-gear me-2"></i>
                    Footer Ayarları
                </h1>
                <p class="mb-0 mt-2 opacity-75">Site footer ayarlarını ve görünümünü yönetin</p>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- İstatistikler -->

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo $_SESSION['success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo $_SESSION['error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Footer Settings Card -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-gear me-2"></i> Footer Ayarları
            </h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_footer_settings">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-label">
                            <i class="bi bi-building me-1"></i> Site Adı
                        </label>
                        <input type="text" class="form-control" id="company_name" name="company_name" 
                               value="<?php echo htmlspecialchars($footerSettings['company_name']); ?>" required>
                        <div class="form-text">Footer'da görünecek site adı</div>
                    </div>
                    <div class="col-md-6">
                        <label for="copyright_text" class="form-label">
                            <i class="bi bi-c-circle me-1"></i> Copyright Metni
                        </label>
                        <input type="text" class="form-control" id="copyright_text" name="copyright_text" 
                               value="<?php echo htmlspecialchars($footerSettings['copyright_text']); ?>" required>
                        <div class="form-text">Copyright sembolü ve metni</div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_year" class="form-label">
                            <i class="bi bi-calendar me-1"></i> Başlangıç Yılı
                        </label>
                        <input type="number" class="form-control" id="start_year" name="start_year" 
                               value="<?php echo $footerSettings['start_year']; ?>" min="2000" max="<?php echo date('Y'); ?>" required>
                        <div class="form-text">Site kuruluş yılı</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="bi bi-eye me-1"></i> Önizleme
                        </label>
                        <div class="form-control bg-light text-dark" id="copyright_preview" style="min-height: 38px; display: flex; align-items: center;">
                            <?php 
                            $currentYear = date('Y');
                            $startYear = $footerSettings['start_year'];
                            $yearText = $startYear;
                            if ($startYear < $currentYear) {
                                $yearText = $startYear . " - " . $currentYear;
                            }
                            echo htmlspecialchars($footerSettings['copyright_text']) . " © " . $yearText . " " . htmlspecialchars($footerSettings['company_name']) . ". Tüm hakları saklıdır.";
                            ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-lightning me-2"></i> Hızlı İşlemler
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="d-grid">
                        <a href="footer_menu_settings.php" class="btn btn-outline-primary">
                            <i class="bi bi-list me-2"></i> Footer Menü Ayarları
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid">
                        <a href="footer_popular_games.php" class="btn btn-outline-success">
                            <i class="bi bi-controller me-2"></i> Popüler Oyunlar
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i> Ana Sayfa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #0d6efd;
    --secondary-color: #6c757d;
    --success-color: #198754;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #0dcaf0;
    --light-color: #f8f9fa;
    --dark-color: #212529;
    --accent-color: #0d6efd;
    --dark-bg: #1a1a1a;
    --dark-secondary: #2d2d2d;
    --dark-accent: #404040;
    --text-light: #ffffff;
    --text-muted: #6c757d;
    --border-color: #404040;
}

/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dashboard-header h1 {
    color: white;
    font-weight: 600;
}

.dashboard-header p {
    color: rgba(255, 255, 255, 0.8);
}

/* Stat Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--dark-secondary), var(--dark-accent));
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    color: white;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid var(--border-color);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--accent-color);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Cards */
.card {
    background-color: var(--dark-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: var(--dark-accent);
    border-bottom: 1px solid var(--border-color);
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.5rem;
}

.card-header h5 {
    color: white;
    margin: 0;
    font-weight: 600;
}

.card-body {
    padding: 1.5rem;
}

/* Buttons */
.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
    border: none;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
    transform: translateY(-1px);
}

.btn-outline-primary {
    color: var(--primary-color);
    border-color: var(--primary-color);
    background-color: transparent;
}

.btn-outline-primary:hover {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
    transform: translateY(-1px);
}

.btn-outline-success {
    color: var(--success-color);
    border-color: var(--success-color);
    background-color: transparent;
}

.btn-outline-success:hover {
    background-color: var(--success-color);
    border-color: var(--success-color);
    color: white;
    transform: translateY(-1px);
}

.btn-outline-secondary {
    color: var(--secondary-color);
    border-color: var(--secondary-color);
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background-color: var(--secondary-color);
    border-color: var(--secondary-color);
    color: white;
    transform: translateY(-1px);
}

/* Alerts */
.alert {
    border-radius: 8px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.alert-success {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
    border-left: 4px solid #198754;
}

.alert-danger {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border-left: 4px solid #dc3545;
}

/* Form Controls */
.form-control, .form-select {
    background-color: var(--dark-bg);
    border: 1px solid var(--border-color);
    color: var(--text-light);
    border-radius: 8px;
    padding: 0.75rem 1rem;
}

.form-control:focus, .form-select:focus {
    background-color: var(--dark-bg);
    border-color: var(--accent-color);
    color: var(--text-light);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.form-label {
    color: var(--text-light);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-text {
    color: var(--text-muted);
    font-size: 0.875rem;
}

.bg-light {
    background-color: var(--dark-accent) !important;
    color: var(--text-light) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copyright preview güncelleme
    function updateCopyrightPreview() {
        const companyName = document.getElementById('company_name').value.trim();
        const copyrightText = document.getElementById('copyright_text').value.trim();
        const startYear = document.getElementById('start_year').value;
        const currentYear = new Date().getFullYear();
        
        let yearText = startYear;
        if (startYear < currentYear) {
            yearText = startYear + " - " + currentYear;
        }
        
        const preview = document.getElementById('copyright_preview');
        preview.textContent = copyrightText + " © " + yearText + " " + companyName + ". Tüm hakları saklıdır.";
    }
    
    // Input değişikliklerini dinle
    document.getElementById('company_name').addEventListener('input', updateCopyrightPreview);
    document.getElementById('copyright_text').addEventListener('input', updateCopyrightPreview);
    document.getElementById('start_year').addEventListener('input', updateCopyrightPreview);
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 