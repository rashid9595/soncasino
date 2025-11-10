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
    $stmt = $db->query("SELECT COUNT(*) as totalProviders FROM populersaglayicilar");
    $totalProviders = $stmt->fetch()['totalProviders'];
    
    $stmt = $db->query("SELECT COUNT(*) as activeProviders FROM populersaglayicilar WHERE aktif = 1");
    $activeProviders = $stmt->fetch()['activeProviders'];
    
    $stmt = $db->query("SELECT COUNT(*) as todayProviders FROM populersaglayicilar WHERE DATE(created_at) = CURDATE()");
    $todayProviders = $stmt->fetch()['todayProviders'];
    
    $stmt = $db->query("SELECT AVG(sira) as avgOrder FROM populersaglayicilar");
    $avgOrder = round($stmt->fetch()['avgOrder'], 1);
    
    $stmt = $db->query("SELECT COUNT(*) as totalUpdates FROM populersaglayicilar WHERE updated_at IS NOT NULL");
    $totalUpdates = $stmt->fetch()['totalUpdates'];
    
    $stmt = $db->query("SELECT COUNT(*) as headerConfigured FROM populersaglayicilar_baslik WHERE id = 1");
    $headerConfigured = $stmt->fetch()['headerConfigured'];
    
} catch (Exception $e) {
    $totalProviders = 0;
    $activeProviders = 0;
    $todayProviders = 0;
    $avgOrder = 0;
    $totalUpdates = 0;
    $headerConfigured = 0;
}

// Başlık ayarları güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_header'])) {
    try {
        $baslik = $_POST['baslik'];
        $icon_url = $_POST['icon_url'];
        $tumunu_goster_url = $_POST['tumunu_goster_url'];
        $tumunu_goster_icon = $_POST['tumunu_goster_icon'];
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        $stmt = $db->prepare("UPDATE populersaglayicilar_baslik SET baslik = ?, icon_url = ?, tumunu_goster_url = ?, tumunu_goster_icon = ?, aktif = ? WHERE id = 1");
        $stmt->execute([$baslik, $icon_url, $tumunu_goster_url, $tumunu_goster_icon, $aktif]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'update_providers_header', "Popüler sağlayıcılar başlık ayarları güncellendi", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Başlık ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Başlık ayarları güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_saglayicilar.php");
    exit();
}

// Sağlayıcı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_provider'])) {
    try {
        $saglayici_adi = $_POST['saglayici_adi'];
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'];
        
        // Get the current maximum order
        $stmt = $db->query("SELECT MAX(sira) as max_sira FROM populersaglayicilar");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_sira = ($result['max_sira'] ?? 0) + 1;
        
        $stmt = $db->prepare("INSERT INTO populersaglayicilar (saglayici_adi, resim_url, link_url, sira, aktif, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$saglayici_adi, $resim_url, $link_url, $new_sira]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'add_provider', "Yeni popüler sağlayıcı eklendi: $saglayici_adi", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Sağlayıcı başarıyla eklendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Sağlayıcı eklenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_saglayicilar.php");
    exit();
}

// Sağlayıcı düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_provider'])) {
    try {
        $id = $_POST['id'];
        $saglayici_adi = $_POST['saglayici_adi'];
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'];
        
        $stmt = $db->prepare("UPDATE populersaglayicilar SET saglayici_adi = ?, resim_url = ?, link_url = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$saglayici_adi, $resim_url, $link_url, $id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'edit_provider', "Popüler sağlayıcı güncellendi: ID $id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Sağlayıcı başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Sağlayıcı güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_saglayicilar.php");
    exit();
}

// Sağlayıcı silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_provider'])) {
    try {
        // Check if there are more than 10 providers
        $stmt = $db->query("SELECT COUNT(*) as count FROM populersaglayicilar");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] <= 10) {
            $_SESSION['error'] = "En az 10 sağlayıcı olmalıdır. Silme işlemi yapılamaz.";
        } else {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM populersaglayicilar WHERE id = ?");
            $stmt->execute([$id]);
            
            // Reorder remaining providers
            $stmt = $db->query("SELECT id FROM populersaglayicilar ORDER BY sira ASC");
            $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($providers as $index => $provider) {
                $stmt = $db->prepare("UPDATE populersaglayicilar SET sira = ? WHERE id = ?");
                $stmt->execute([$index + 1, $provider['id']]);
            }
            
            // Aktivite logu
            $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'delete_provider', "Popüler sağlayıcı silindi: ID $id", $_SERVER['REMOTE_ADDR']]);
            
            $_SESSION['success'] = "Sağlayıcı başarıyla silindi ve sıralama güncellendi.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Sağlayıcı silinirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_saglayicilar.php");
    exit();
}

// Sağlayıcı durumu değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    try {
        $id = $_POST['id'];
        $stmt = $db->prepare("UPDATE populersaglayicilar SET aktif = NOT aktif, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'toggle_provider_status', "Sağlayıcı durumu değiştirildi: ID $id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Sağlayıcı durumu güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Sağlayıcı durumu güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_saglayicilar.php");
    exit();
}

// Get header settings
$stmt = $db->query("SELECT * FROM populersaglayicilar_baslik WHERE id = 1");
$header_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// If header settings don't exist, create default
if (!$header_settings) {
    $stmt = $db->prepare("INSERT INTO populersaglayicilar_baslik (id, baslik, icon_url, tumunu_goster_url, tumunu_goster_icon, aktif) 
                          VALUES (1, 'SAĞLAYICILAR', 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv4-providers-icon.png', 
                          '/tr/games/casino/category/0', 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv3_allview.png', 1)");
    $stmt->execute();
    
    $stmt = $db->query("SELECT * FROM populersaglayicilar_baslik WHERE id = 1");
    $header_settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all providers
$stmt = $db->query("SELECT * FROM populersaglayicilar ORDER BY sira ASC");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

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
    --body-bg: #f5f5f5;
    --card-bg: #ffffff;
    --border-color: #dee2e6;
    --text-color: #212529;
    --text-muted: #6c757d;
}

body {
    background-color: var(--body-bg);
    color: #b3b3b3;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.dashboard-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dashboard-header h1 {
    margin: 0;
    font-weight: 600;
    font-size: 2.5rem;
}

.dashboard-header p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border-color);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.stat-card:nth-child(1) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stat-card:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.stat-card:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.stat-card:nth-child(4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    color: white;
}

.stat-card:nth-child(5) {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    color: white;
}

.stat-card:nth-child(6) {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #b3b3b3;
}

.stat-card .stat-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.8;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-card .stat-label {
    font-size: 0.9rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.providers-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.providers-card .card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
    color: white;
    padding: 1.5rem;
    border-bottom: none;
}

.providers-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
}

.providers-card .card-body {
    padding: 0;
}

.table {
    margin: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid var(--border-color);
    color: #b3b3b3;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
    border: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-color) 0%, #c82333 100%);
}

.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-color) 0%, #146c43 100%);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning-color) 0%, #e0a800 100%);
    color: var(--dark-color);
}

.badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    font-weight: 500;
}

.provider-image {
    max-width: 80px;
    max-height: 60px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
    color: white;
    border-bottom: none;
    border-radius: 12px 12px 0 0;
}

.modal-title {
    font-weight: 600;
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
}

.form-label {
    font-weight: 600;
    color: #b3b3b3;
    margin-bottom: 0.5rem;
}

.form-control {
    border-radius: 8px;
    border: 2px solid var(--border-color);
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.alert {
    border-radius: 8px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_processing,
.dataTables_wrapper .dataTables_paginate {
    padding: 1rem 1.5rem;
    color: #b3b3b3;
}

.dataTables_wrapper .dataTables_filter input {
    border-radius: 6px;
    border: 2px solid var(--border-color);
    padding: 0.5rem 0.75rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 6px;
    border: 1px solid var(--border-color);
    padding: 0.5rem 0.75rem;
    margin: 0 0.25rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white !important;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h5 {
    margin-bottom: 0.5rem;
    color: #b3b3b3;
}

.empty-state p {
    margin-bottom: 1.5rem;
}

@media (max-width: 768px) {
    .stat-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card .stat-value {
        font-size: 1.5rem;
    }
    
    .dashboard-header h1 {
        font-size: 2rem;
    }
}
</style>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col">
                <h1><i class="bi bi-building me-3"></i>Popüler Sağlayıcılar</h1>
                <p>Popüler sağlayıcıları yönetin ve başlık ayarlarını düzenleyin</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addProviderModal">
                    <i class="bi bi-plus-circle me-2"></i>Yeni Sağlayıcı Ekle
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Başlık Ayarları Kartı -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-gear-fill me-1"></i> Popüler Sağlayıcılar Başlık Ayarları
            </h6>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#headerSettingsCollapse" aria-expanded="false" aria-controls="headerSettingsCollapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse show" id="headerSettingsCollapse">
            <div class="card-body">
                <form method="POST" id="headerSettingsForm">
                    <input type="hidden" name="action" value="update_header">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="baslik" class="form-label">Başlık</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="baslik" name="baslik" value="<?php echo htmlspecialchars($header_settings['baslik']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="icon_url" class="form-label">İkon URL</label>
                                <input type="url" class="form-control bg-dark text-light border-secondary" id="icon_url" name="icon_url" value="<?php echo htmlspecialchars($header_settings['icon_url']); ?>" required>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($header_settings['icon_url']); ?>" alt="İkon" id="icon_preview" class="img-thumbnail bg-dark" style="max-height: 50px;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tumunu_goster_url" class="form-label">Tümünü Göster URL</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="tumunu_goster_url" name="tumunu_goster_url" value="<?php echo htmlspecialchars($header_settings['tumunu_goster_url']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="tumunu_goster_icon" class="form-label">Tümünü Göster İkon URL</label>
                                <input type="url" class="form-control bg-dark text-light border-secondary" id="tumunu_goster_icon" name="tumunu_goster_icon" value="<?php echo htmlspecialchars($header_settings['tumunu_goster_icon']); ?>" required>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($header_settings['tumunu_goster_icon']); ?>" alt="Tümünü Göster İkon" id="tumunu_goster_preview" class="img-thumbnail bg-dark" style="max-height: 50px;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Aktif değeri için gizli input - daima aktif olarak gönderilir -->
                        <input type="hidden" name="aktif" value="1">
                    </div>
                    
                    <!-- Önizleme Bölümü -->
                    <div class="mt-4 mb-4">
                        <h6 class="text-primary mb-3"><i class="bi bi-eye"></i> Başlık Önizleme</h6>
                        <div class="p-3 border rounded bg-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($header_settings['icon_url']); ?>" alt="İkon" id="preview_icon" class="me-2" style="height: 30px;">
                                    <span class="h5 mb-0 text-white" id="preview_title"><?php echo htmlspecialchars($header_settings['baslik']); ?></span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="small me-2 text-white">Tümünü Göster</span>
                                    <img src="<?php echo htmlspecialchars($header_settings['tumunu_goster_icon']); ?>" alt="Tümünü Göster" id="preview_all_icon" style="height: 20px;">
                                </div>
                            </div>
                        </div>
                        <div class="small text-white mt-2"><i class="bi bi-info-circle"></i> Not: Bu bölüm sitenizde görünecek olan popüler sağlayıcılar başlığının önizlemesidir.</div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Başlık Ayarlarını Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-list me-1"></i> Popüler Sağlayıcılar Listesi
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="providersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sağlayıcı Adı</th>
                            <th>Resim</th>
                            <th>Link URL</th>
                            <th>Sıra</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $provider): ?>
                        <tr>
                            <td><?php echo $provider['id']; ?></td>
                            <td><?php echo htmlspecialchars($provider['saglayici_adi']); ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($provider['resim_url']); ?>" alt="<?php echo htmlspecialchars($provider['saglayici_adi']); ?>" style="max-width: 100px;">
                            </td>
                            <td><?php echo htmlspecialchars($provider['link_url']); ?></td>
                            <td><?php echo $provider['sira']; ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $provider['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $provider['aktif'] ? 'btn-success' : 'btn-danger'; ?>">
                                        <?php echo $provider['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary edit-provider" 
                                        data-id="<?php echo $provider['id']; ?>"
                                        data-saglayici-adi="<?php echo htmlspecialchars($provider['saglayici_adi']); ?>"
                                        data-resim-url="<?php echo htmlspecialchars($provider['resim_url']); ?>"
                                        data-link-url="<?php echo htmlspecialchars($provider['link_url']); ?>">
                                    <i class="bi bi-pencil"></i> Düzenle
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Bu sağlayıcıyı silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $provider['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Provider Modal -->
<div class="modal fade" id="addProviderModal" tabindex="-1" aria-labelledby="addProviderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addProviderModalLabel"><i class="bi bi-plus-circle"></i> Yeni Sağlayıcı Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="saglayici_adi" class="form-label">Sağlayıcı Adı</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="saglayici_adi" name="saglayici_adi" required>
                    </div>
                    <div class="mb-3">
                        <label for="resim_url" class="form-label">Resim URL</label>
                        <input type="url" class="form-control bg-dark text-light border-secondary" id="resim_url" name="resim_url" required>
                    </div>
                    <div class="mb-3">
                        <label for="link_url" class="form-label">Link URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="link_url" name="link_url" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg"></i> Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Provider Modal -->
<div class="modal fade" id="editProviderModal" tabindex="-1" aria-labelledby="editProviderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="editProviderModalLabel">
                    <i class="bi bi-pencil-square"></i> Sağlayıcı Düzenle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editProviderForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_saglayici_adi" class="form-label">Sağlayıcı Adı</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_saglayici_adi" name="saglayici_adi" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_resim_url" class="form-label">Resim URL</label>
                        <input type="url" class="form-control bg-dark text-light border-secondary" id="edit_resim_url" name="resim_url" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_link_url" class="form-label">Link URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_link_url" name="link_url" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* DataTable özel stilleri */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    color: white !important;
    margin-bottom: 10px;
}

.dataTables_wrapper .dataTables_length select {
    color: white !important;
    background-color: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    padding: 5px 10px;
    border-radius: 5px;
}

.dataTables_wrapper .dataTables_filter input {
    color: white !important;
    background-color: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    padding: 5px 10px;
    border-radius: 5px;
    margin-left: 5px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: white !important;
    background: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    border-radius: 5px;
    margin: 0 2px;
    padding: 5px 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--accent-color) !important;
    color: white !important;
    border: 1px solid var(--accent-color) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--dark-accent) !important;
    color: white !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#providersTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        // DataTable görünüm ayarları
        dom: '<"top"lf>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]]
    });
    
    // Düzenleme butonlarını daha belirgin yapma
    const editButtons = document.querySelectorAll('.edit-provider');
    editButtons.forEach(button => {
        button.classList.add('btn-primary');
        button.style.fontWeight = 'bold';
    });
    
    // Düzenleme butonu işlemleri - Tek ve basit yaklaşım
    const editModal = new bootstrap.Modal(document.getElementById('editProviderModal'));
    
    // Düzenleme butonlarına tıklama olayı ekle
    document.querySelectorAll('.edit-provider').forEach(button => {
        button.addEventListener('click', function() {
            console.log('Düzenle butonuna tıklandı!');
            
            // Data attributelerini al
            const id = this.getAttribute('data-id');
            const saglayiciAdi = this.getAttribute('data-saglayici-adi');
            const resimUrl = this.getAttribute('data-resim-url');
            const linkUrl = this.getAttribute('data-link-url');
            
            console.log('Sağlayıcı bilgileri:', { id, saglayiciAdi, resimUrl, linkUrl });
            
            // Form alanlarını doldur
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_saglayici_adi').value = saglayiciAdi;
            document.getElementById('edit_resim_url').value = resimUrl;
            document.getElementById('edit_link_url').value = linkUrl;
            
            // Modalı göster
            editModal.show();
        });
    });
    
    // Başlık ayarları formu gönderimi SweetAlert ile
    const headerSettingsForm = document.getElementById('headerSettingsForm');
    if (headerSettingsForm) {
        headerSettingsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Form verilerini al
            const formData = new FormData(this);
            
            // SweetAlert ile onaylama
            Swal.fire({
                title: 'Başlık ayarlarını güncellemek istiyor musunuz?',
                text: 'Popüler sağlayıcılar başlık ayarları güncellenecek',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Güncelle',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Yükleniyor durumu göster
                    Swal.fire({
                        title: 'Güncelleniyor...',
                        text: 'Başlık ayarları güncelleniyor',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Form gönderimi
                    this.submit();
                }
            });
        });
    }
    
    // Başlık ayarları önizleme güncelleme fonksiyonları
    function updateHeaderPreview() {
        // Değerleri al
        const baslik = document.getElementById('baslik').value;
        const iconUrl = document.getElementById('icon_url').value;
        const tumunuGosterIcon = document.getElementById('tumunu_goster_icon').value;
        
        // Önizleme elemanlarını güncelle
        document.getElementById('preview_title').textContent = baslik;
        document.getElementById('preview_icon').src = iconUrl;
        document.getElementById('preview_all_icon').src = tumunuGosterIcon;
    }
    
    // Input değişikliklerinde önizlemeyi güncelle
    document.getElementById('baslik').addEventListener('input', updateHeaderPreview);
    
    // URL değişikliğinde önizleme resimlerini güncelle
    document.getElementById('icon_url').addEventListener('input', function() {
        document.getElementById('icon_preview').src = this.value;
        updateHeaderPreview();
    });
    
    document.getElementById('tumunu_goster_icon').addEventListener('input', function() {
        document.getElementById('tumunu_goster_preview').src = this.value;
        updateHeaderPreview();
    });
    
    // SweetAlert ile silme işlemi
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        // Form üzerindeki onsubmit attribute'ünü kaldır
        form.removeAttribute('onsubmit');
        
        // Form submit olayını dinle
        form.addEventListener('submit', function(e) {
            // Varsayılan form gönderimini engelle
            e.preventDefault();
            
            // Sağlayıcı adını bul (aynı satırdaki ikinci hücreden)
            const row = this.closest('tr');
            const providerName = row ? row.querySelector('td:nth-child(2)').textContent.trim() : 'bu sağlayıcıyı';
            
            // SweetAlert ile onay iste
            Swal.fire({
                title: 'Emin misiniz?',
                text: `"${providerName}" adlı sağlayıcı silinecek. Bu işlem geri alınamaz!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'İptal',
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kullanıcı onayladıysa formu gönder
                    this.submit();
                    
                    // Silme işlemi başladı bilgisi göster
                    Swal.fire({
                        title: 'Siliniyor...',
                        text: 'Sağlayıcı silme işlemi gerçekleştiriliyor.',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }
            });
        });
    });
    
    // Düzenleme formu gönderim işlemi
    document.getElementById('editProviderForm').addEventListener('submit', function(e) {
        console.log('Form gönderiliyor...');
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 