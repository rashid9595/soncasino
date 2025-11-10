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
    $stmt = $db->query("SELECT COUNT(*) as totalSlots FROM populerslotlaranamenu");
    $totalSlots = $stmt->fetch()['totalSlots'];
    
    $stmt = $db->query("SELECT COUNT(*) as activeSlots FROM populerslotlaranamenu WHERE aktif = 1");
    $activeSlots = $stmt->fetch()['activeSlots'];
    
    $stmt = $db->query("SELECT COUNT(*) as todaySlots FROM populerslotlaranamenu WHERE DATE(created_at) = CURDATE()");
    $todaySlots = $stmt->fetch()['todaySlots'];
    
    $stmt = $db->query("SELECT AVG(sira) as avgOrder FROM populerslotlaranamenu");
    $avgOrder = round($stmt->fetch()['avgOrder'], 1);
    
    $stmt = $db->query("SELECT COUNT(DISTINCT slot_tipi) as uniqueTypes FROM populerslotlaranamenu");
    $uniqueTypes = $stmt->fetch()['uniqueTypes'];
    
    $stmt = $db->query("SELECT COUNT(*) as totalUpdates FROM populerslotlaranamenu WHERE updated_at IS NOT NULL");
    $totalUpdates = $stmt->fetch()['totalUpdates'];
    
} catch (Exception $e) {
    $totalSlots = 0;
    $activeSlots = 0;
    $todaySlots = 0;
    $avgOrder = 0;
    $uniqueTypes = 0;
    $totalUpdates = 0;
}

// Başlık ayarları güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_header'])) {
    try {
        $baslik = $_POST['baslik'];
        $icon_url = $_POST['icon_url'];
        $tumunu_goster_url = $_POST['tumunu_goster_url'];
        $tumunu_goster_icon = $_POST['tumunu_goster_icon'];
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        
        $stmt = $db->prepare("UPDATE populerslotlar_baslik SET baslik = ?, icon_url = ?, tumunu_goster_url = ?, tumunu_goster_icon = ?, aktif = ? WHERE id = 1");
        $stmt->execute([$baslik, $icon_url, $tumunu_goster_url, $tumunu_goster_icon, $aktif]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'update_slots_header', "Popüler slotlar başlık ayarları güncellendi", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Başlık ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Başlık ayarları güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_slotlar.php");
    exit();
}

// Slot ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slot'])) {
    try {
        $slot_adi = $_POST['slot_adi'];
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'] ?? '';
        $slot_tipi = $_POST['slot_tipi'];
        
        // Get the current maximum order
        $stmt = $db->query("SELECT MAX(sira) as max_sira FROM populerslotlaranamenu");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_sira = ($result['max_sira'] ?? 0) + 1;
        
        $stmt = $db->prepare("INSERT INTO populerslotlaranamenu (slot_adi, resim_url, link_url, sira, slot_tipi, aktif, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$slot_adi, $resim_url, $link_url, $new_sira, $slot_tipi]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'add_slot', "Yeni popüler slot eklendi: $slot_adi", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Slot başarıyla eklendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Slot eklenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_slotlar.php");
    exit();
}

// Slot düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_slot'])) {
    try {
        $id = $_POST['id'];
        $slot_adi = $_POST['slot_adi'];
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'] ?? '';
        $slot_tipi = $_POST['slot_tipi'];
        
        $stmt = $db->prepare("UPDATE populerslotlaranamenu SET slot_adi = ?, resim_url = ?, link_url = ?, slot_tipi = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$slot_adi, $resim_url, $link_url, $slot_tipi, $id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'edit_slot', "Popüler slot güncellendi: ID $id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Slot başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Slot güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_slotlar.php");
    exit();
}

// Slot silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_slot'])) {
    try {
        // Check if there are more than 10 slots
        $stmt = $db->query("SELECT COUNT(*) as count FROM populerslotlaranamenu");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] <= 10) {
            $_SESSION['error'] = "En az 10 slot olmalıdır. Silme işlemi yapılamaz.";
        } else {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM populerslotlaranamenu WHERE id = ?");
            $stmt->execute([$id]);
            
            // Reorder remaining slots
            $stmt = $db->query("SELECT id FROM populerslotlaranamenu ORDER BY sira ASC");
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($slots as $index => $slot) {
                $stmt = $db->prepare("UPDATE populerslotlaranamenu SET sira = ? WHERE id = ?");
                $stmt->execute([$index + 1, $slot['id']]);
            }
            
            // Aktivite logu
            $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'delete_slot', "Popüler slot silindi: ID $id", $_SERVER['REMOTE_ADDR']]);
            
            $_SESSION['success'] = "Slot başarıyla silindi ve sıralama güncellendi.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Slot silinirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_slotlar.php");
    exit();
}

// Slot durumu değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    try {
        $id = $_POST['id'];
        $stmt = $db->prepare("UPDATE populerslotlaranamenu SET aktif = NOT aktif, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'toggle_slot_status', "Slot durumu değiştirildi: ID $id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Slot durumu güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Slot durumu güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_slotlar.php");
    exit();
}

// Get header settings
$stmt = $db->query("SELECT * FROM populerslotlar_baslik WHERE id = 1");
$header_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// If header settings don't exist, create default
if (!$header_settings) {
    $stmt = $db->prepare("INSERT INTO populerslotlar_baslik (id, baslik, icon_url, tumunu_goster_url, tumunu_goster_icon, aktif) 
                          VALUES (1, 'POPÜLER SLOTLAR', 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv3_sp_icon.png', 
                          '/tr/games/casino', 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv3_allview.png', 1)");
    $stmt->execute();
    
    $stmt = $db->query("SELECT * FROM populerslotlar_baslik WHERE id = 1");
    $header_settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all slots
$stmt = $db->query("SELECT * FROM populerslotlaranamenu ORDER BY sira ASC");
$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    color: var(--text-color);
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
    color: var(--text-color);
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

.slots-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.slots-card .card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
    color: white;
    padding: 1.5rem;
    border-bottom: none;
}

.slots-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
}

.slots-card .card-body {
    padding: 0;
}

.table {
    margin: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid var(--border-color);
    color: #ffffff;
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

.slot-image {
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
    color: var(--text-color);
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
    color: var(--text-color);
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
    color: #000000;
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
    color: var(--text-color);
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
                <h1><i class="bi bi-slot-machine me-3"></i>Popüler Slotlar</h1>
                <p>Popüler slotları yönetin ve başlık ayarlarını düzenleyin</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addSlotModal">
                    <i class="bi bi-plus-circle me-2"></i>Yeni Slot Ekle
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
    <div class="slots-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-gear-fill me-2"></i>Popüler Slotlar Başlık Ayarları</h5>
            <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#headerSettingsCollapse" aria-expanded="false" aria-controls="headerSettingsCollapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse show" id="headerSettingsCollapse">
            <div class="card-body">
                <form method="POST" id="headerSettingsForm">
                    <input type="hidden" name="update_header" value="1">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="baslik" class="form-label">
                                    <i class="bi bi-type me-2"></i>Başlık
                                </label>
                                <input type="text" class="form-control" id="baslik" name="baslik" value="<?php echo htmlspecialchars($header_settings['baslik']); ?>" required>
                                <div class="form-text">Popüler slotlar bölümünün başlığı</div>
                            </div>
                            <div class="mb-3">
                                <label for="icon_url" class="form-label">
                                    <i class="bi bi-image me-2"></i>İkon URL
                                </label>
                                <input type="url" class="form-control" id="icon_url" name="icon_url" value="<?php echo htmlspecialchars($header_settings['icon_url']); ?>" required>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($header_settings['icon_url']); ?>" alt="İkon" id="icon_preview" class="img-thumbnail" style="max-height: 50px;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tumunu_goster_url" class="form-label">
                                    <i class="bi bi-link-45deg me-2"></i>Tümünü Göster URL
                                </label>
                                <input type="url" class="form-control" id="tumunu_goster_url" name="tumunu_goster_url" value="<?php echo htmlspecialchars($header_settings['tumunu_goster_url']); ?>" required>
                                <div class="form-text">Tüm slotları göster sayfasının linki</div>
                            </div>
                            <div class="mb-3">
                                <label for="tumunu_goster_icon" class="form-label">
                                    <i class="bi bi-image me-2"></i>Tümünü Göster İkon URL
                                </label>
                                <input type="url" class="form-control" id="tumunu_goster_icon" name="tumunu_goster_icon" value="<?php echo htmlspecialchars($header_settings['tumunu_goster_icon']); ?>" required>
                                <div class="mt-2">
                                    <img src="<?php echo htmlspecialchars($header_settings['tumunu_goster_icon']); ?>" alt="Tümünü Göster İkon" id="tumunu_goster_preview" class="img-thumbnail" style="max-height: 50px;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Aktif değeri için gizli input - daima aktif olarak gönderilir -->
                        <input type="hidden" name="aktif" value="1">
                    </div>
                    
                    <!-- Önizleme Bölümü -->
                    <div class="mt-4 mb-4">
                        <h6 class="text-primary mb-3"><i class="bi bi-eye me-2"></i>Başlık Önizleme</h6>
                        <div class="p-3 border rounded bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($header_settings['icon_url']); ?>" alt="İkon" id="preview_icon" class="me-2" style="height: 30px;">
                                    <span class="h5 mb-0" id="preview_title"><?php echo htmlspecialchars($header_settings['baslik']); ?></span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="small me-2 text-muted">Tümünü Göster</span>
                                    <img src="<?php echo htmlspecialchars($header_settings['tumunu_goster_icon']); ?>" alt="Tümünü Göster" id="preview_all_icon" style="height: 20px;">
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Not: Bu bölüm sitenizde görünecek olan popüler slotlar başlığının önizlemesidir.</div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Başlık Ayarlarını Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="slots-card">
        <div class="card-header">
            <h5><i class="bi bi-list-ul me-2"></i>Popüler Slotlar Listesi</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="slotsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Slot Adı</th>
                            <th>Resim</th>
                            <th>Link URL</th>
                            <th>Sıra</th>
                            <th>Slot Tipi</th>
                            <th>Durum</th>
                            <th>Oluşturulma</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($slots)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="bi bi-slot-machine"></i>
                                        <h5>Henüz slot eklenmemiş</h5>
                                        <p>İlk popüler slotu eklemek için yukarıdaki butonu kullanın.</p>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSlotModal">
                                            <i class="bi bi-plus-circle me-2"></i>İlk Slotu Ekle
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $slot['id']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($slot['slot_adi']); ?></strong>
                                </td>
                                <td>
                                    <img src="<?php echo htmlspecialchars($slot['resim_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($slot['slot_adi']); ?>" 
                                         class="slot-image"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA4MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg2MFY0MEgyMFYyMFoiIGZpbGw9IiNEN0Q3RDciLz4KPHN2ZyB4PSIyOCIgeT0iMjgiIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMTkgM0g1QzMuOSAzIDMgMy45IDMgNVYxOUMzIDIwLjEgMy45IDIxIDUgMjFIMTlDMjAuMSAyMSAyMSAyMC4xIDIxIDE5VjVDMjEgMy45IDIwLjEgMyAxOSAzWk0xOSAxOUg1VjVIMTlWMTlaIiBmaWxsPSIjOTk5OTk5Ii8+Cjwvc3ZnPgo8L3N2Zz4K'">
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($slot['link_url']); ?>">
                                        <?php echo htmlspecialchars($slot['link_url']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $slot['sira']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($slot['slot_tipi']); ?></span>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <input type="hidden" name="id" value="<?php echo $slot['id']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $slot['aktif'] ? 'btn-success' : 'btn-danger'; ?>">
                                            <i class="bi <?php echo $slot['aktif'] ? 'bi-check-circle' : 'bi-x-circle'; ?> me-1"></i>
                                            <?php echo $slot['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (isset($slot['created_at'])): ?>
                                        <small class="text-muted">
                                            <?php echo date('d.m.Y H:i', strtotime($slot['created_at'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm edit-slot" 
                                                data-id="<?php echo $slot['id']; ?>"
                                                data-slot-adi="<?php echo htmlspecialchars($slot['slot_adi']); ?>"
                                                data-resim-url="<?php echo htmlspecialchars($slot['resim_url']); ?>"
                                                data-link-url="<?php echo htmlspecialchars($slot['link_url']); ?>"
                                                data-slot-tipi="<?php echo htmlspecialchars($slot['slot_tipi']); ?>">
                                            <i class="bi bi-pencil me-1"></i>Düzenle
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm delete-slot" 
                                                data-id="<?php echo $slot['id']; ?>"
                                                data-slot-adi="<?php echo htmlspecialchars($slot['slot_adi']); ?>">
                                            <i class="bi bi-trash me-1"></i>Sil
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

<!-- Add Slot Modal -->
<div class="modal fade" id="addSlotModal" tabindex="-1" aria-labelledby="addSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSlotModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Yeni Slot Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="addSlotForm">
                <div class="modal-body">
                    <input type="hidden" name="add_slot" value="1">
                    <div class="mb-3">
                        <label for="slot_adi" class="form-label">
                            <i class="bi bi-tag me-2"></i>Slot Adı
                        </label>
                        <input type="text" class="form-control" id="slot_adi" name="slot_adi" placeholder="Slot ismi" required>
                        <div class="form-text">Slotun görünen ismini girin</div>
                    </div>
                    <div class="mb-3">
                        <label for="resim_url" class="form-label">
                            <i class="bi bi-image me-2"></i>Resim URL
                        </label>
                        <input type="url" class="form-control" id="resim_url" name="resim_url" placeholder="https://example.com/slot-image.jpg" required>
                        <div class="form-text">Slot resminin URL adresini girin</div>
                    </div>
                    <div class="mb-3">
                        <label for="link_url" class="form-label">
                            <i class="bi bi-link-45deg me-2"></i>Link URL
                        </label>
                        <input type="url" class="form-control" id="link_url" name="link_url" placeholder="https://example.com/slot-game">
                        <div class="form-text">İsteğe bağlı alan. Boş bırakılabilir.</div>
                    </div>
                    <div class="mb-3">
                        <label for="slot_tipi" class="form-label">
                            <i class="bi bi-collection me-2"></i>Slot Tipi
                        </label>
                        <select class="form-select" id="slot_tipi" name="slot_tipi" required>
                            <option value="">Slot tipi seçin</option>
                            <option value="NGS">NGS</option>
                            <option value="PRAGMATIC">PRAGMATIC</option>
                            <option value="EVOPLAY">EVOPLAY</option>
                            <option value="SPINOMENAL">SPINOMENAL</option>
                        </select>
                        <div class="form-text">Slotun sağlayıcı tipini belirtin</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>İptal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Slot Modal -->
<div class="modal fade" id="editSlotModal" tabindex="-1" aria-labelledby="editSlotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="editSlotModalLabel">
                    <i class="bi bi-pencil-square"></i> Slot Düzenle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editSlotForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_slot_adi" class="form-label">Slot Adı</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_slot_adi" name="slot_adi" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_resim_url" class="form-label">Resim URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_resim_url" name="resim_url" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_link_url" class="form-label">Link URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_link_url" name="link_url">
                        <div class="form-text text-muted">İsteğe bağlı alan. Boş bırakılabilir.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_slot_tipi" class="form-label">Slot Tipi</label>
                        <select class="form-select bg-dark text-light border-secondary" id="edit_slot_tipi" name="slot_tipi" required>
                            <option value="NGS">NGS</option>
                            <option value="PRAGMATIC">PRAGMATIC</option>
                            <option value="EVOPLAY">EVOPLAY</option>
                            <option value="SPINOMENAL">SPINOMENAL</option>
                        </select>
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
    color: #0f172a;
    margin-bottom: 10px;
}

.dataTables_wrapper .dataTables_length select {
    color: #e3e3e3;
    background-color: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    padding: 5px 10px;
    border-radius: 5px;
}

.dataTables_wrapper .dataTables_filter input {
    color: #0f172a;
    background-color: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    padding: 5px 10px;
    border-radius: 5px;
    margin-left: 5px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: #0f172a;
    background: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    border-radius: 5px;
    margin: 0 2px;
    padding: 5px 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--accent-color) !important;
    color: #0f172a;
    border: 1px solid var(--accent-color) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--dark-accent) !important;
    color: #0f172a;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#slotsTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        // DataTable görünüm ayarları
        dom: '<"top"lf>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]]
    });
    
    // Düzenleme butonlarını daha belirgin yapma
    const editButtons = document.querySelectorAll('.edit-slot');
    editButtons.forEach(button => {
        button.classList.add('btn-primary');
        button.style.fontWeight = 'bold';
    });
    
    // Düzenleme butonu işlemleri - Tek ve basit yaklaşım
    const editModal = new bootstrap.Modal(document.getElementById('editSlotModal'));
    
    // Düzenleme butonlarına tıklama olayı ekle
    document.querySelectorAll('.edit-slot').forEach(button => {
        button.addEventListener('click', function() {
            console.log('Düzenle butonuna tıklandı!');
            
            // Data attributelerini al
            const id = this.getAttribute('data-id');
            const slotAdi = this.getAttribute('data-slot-adi');
            const resimUrl = this.getAttribute('data-resim-url');
            const linkUrl = this.getAttribute('data-link-url');
            const slotTipi = this.getAttribute('data-slot-tipi');
            
            console.log('Slot bilgileri:', { id, slotAdi, resimUrl, linkUrl, slotTipi });
            
            // Form alanlarını doldur
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_slot_adi').value = slotAdi;
            document.getElementById('edit_resim_url').value = resimUrl;
            document.getElementById('edit_link_url').value = linkUrl;
            document.getElementById('edit_slot_tipi').value = slotTipi;
            
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
                text: 'Popüler slotlar başlık ayarları güncellenecek',
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
            
            // Slot adını bul (aynı satırdaki ikinci hücreden)
            const row = this.closest('tr');
            const slotName = row ? row.querySelector('td:nth-child(2)').textContent.trim() : 'bu slotu';
            
            // SweetAlert ile onay iste
            Swal.fire({
                title: 'Emin misiniz?',
                text: `"${slotName}" adlı slot silinecek. Bu işlem geri alınamaz!`,
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
                        text: 'Slot silme işlemi gerçekleştiriliyor.',
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
    document.getElementById('editSlotForm').addEventListener('submit', function(e) {
        console.log('Form gönderiliyor...');
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 