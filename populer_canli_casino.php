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
    $stmt = $db->query("SELECT COUNT(*) as totalGames FROM populercanlicasinoanasayfa");
    $totalGames = $stmt->fetch()['totalGames'];
    
    $stmt = $db->query("SELECT COUNT(*) as activeGames FROM populercanlicasinoanasayfa WHERE aktif = 1");
    $activeGames = $stmt->fetch()['activeGames'];
    
    $stmt = $db->query("SELECT COUNT(*) as todayGames FROM populercanlicasinoanasayfa WHERE DATE(created_at) = CURDATE()");
    $todayGames = $stmt->fetch()['todayGames'];
    
    $stmt = $db->query("SELECT AVG(sira) as avgOrder FROM populercanlicasinoanasayfa");
    $avgOrder = round($stmt->fetch()['avgOrder'], 1);
    
    $stmt = $db->query("SELECT COUNT(*) as totalUpdates FROM populercanlicasinoanasayfa WHERE updated_at IS NOT NULL");
    $totalUpdates = $stmt->fetch()['totalUpdates'];
    
    $stmt = $db->query("SELECT COUNT(*) as headerConfigured FROM populercanlicasinoanasayfa_baslik WHERE id = 1");
    $headerConfigured = $stmt->fetch()['headerConfigured'];
    
} catch (Exception $e) {
    $totalGames = 0;
    $activeGames = 0;
    $todayGames = 0;
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
        
        $stmt = $db->prepare("UPDATE populercanlicasinoanasayfa_baslik SET baslik = ?, icon_url = ?, tumunu_goster_url = ?, tumunu_goster_icon = ?, aktif = ? WHERE id = 1");
        $stmt->execute([$baslik, $icon_url, $tumunu_goster_url, $tumunu_goster_icon, $aktif]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'update_live_casino_header', "Popüler canlı casino başlık ayarları güncellendi", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Başlık ayarları başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Başlık ayarları güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_canli_casino.php");
    exit();
}

// Oyun ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_game'])) {
    try {
        $oyun_adi = $_POST['oyun_adi'];
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'] ?? '';
        
        // Get the current maximum order
        $stmt = $db->query("SELECT MAX(sira) as max_sira FROM populercanlicasinoanasayfa");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_sira = ($result['max_sira'] ?? 0) + 1;
        
        $stmt = $db->prepare("INSERT INTO populercanlicasinoanasayfa (oyun_adi, resim_url, link_url, sira, aktif, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$oyun_adi, $resim_url, $link_url, $new_sira]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'add_live_casino_game', "Yeni popüler canlı casino oyunu eklendi: $oyun_adi", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Oyun başarıyla eklendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Oyun eklenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_canli_casino.php");
    exit();
}

// Oyun düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_game'])) {
    try {
        $id = $_POST['id'];
        $oyun_adi = $_POST['oyun_adi'];
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'] ?? '';
        
        $stmt = $db->prepare("UPDATE populercanlicasinoanasayfa SET oyun_adi = ?, resim_url = ?, link_url = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$oyun_adi, $resim_url, $link_url, $id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'edit_live_casino_game', "Popüler canlı casino oyunu güncellendi: ID $id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Oyun başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Oyun güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_canli_casino.php");
    exit();
}

// Oyun silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_game'])) {
    try {
        // Check if there are more than 10 games
        $stmt = $db->query("SELECT COUNT(*) as count FROM populercanlicasinoanasayfa");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] <= 10) {
            $_SESSION['error'] = "En az 10 oyun olmalıdır. Silme işlemi yapılamaz.";
        } else {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM populercanlicasinoanasayfa WHERE id = ?");
            $stmt->execute([$id]);
            
            // Reorder remaining games
            $stmt = $db->query("SELECT id FROM populercanlicasinoanasayfa ORDER BY sira ASC");
            $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($games as $index => $game) {
                $stmt = $db->prepare("UPDATE populercanlicasinoanasayfa SET sira = ? WHERE id = ?");
                $stmt->execute([$index + 1, $game['id']]);
            }
            
            // Aktivite logu
            $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'delete_live_casino_game', "Popüler canlı casino oyunu silindi: ID $id", $_SERVER['REMOTE_ADDR']]);
            
            $_SESSION['success'] = "Oyun başarıyla silindi ve sıralama güncellendi.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Oyun silinirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_canli_casino.php");
    exit();
}

// Oyun durumu değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    try {
        $id = $_POST['id'];
        $stmt = $db->prepare("UPDATE populercanlicasinoanasayfa SET aktif = NOT aktif, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'toggle_live_casino_status', "Canlı casino oyunu durumu değiştirildi: ID $id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Oyun durumu güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Oyun durumu güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: populer_canli_casino.php");
    exit();
}

// Başlık ayarlarını getir
try {
    $stmt = $db->query("SELECT * FROM populercanlicasinoanasayfa_baslik WHERE id = 1");
    $header_settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // If header settings don't exist, create default
    if (!$header_settings) {
        $stmt = $db->prepare("INSERT INTO populercanlicasinoanasayfa_baslik (id, baslik, icon_url, tumunu_goster_url, tumunu_goster_icon, aktif) 
                              VALUES (1, 'POPÜLER CANLI CASINO', 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv3_slc_icon.png', 
                              '/tr/games/livecasino', 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv3_allview.png', 1)");
        $stmt->execute();
        
        $stmt = $db->query("SELECT * FROM populercanlicasinoanasayfa_baslik WHERE id = 1");
        $header_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $header_settings = [
        'baslik' => 'POPÜLER CANLI CASINO',
        'icon_url' => 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv3_slc_icon.png',
        'tumunu_goster_url' => '/tr/games/livecasino',
        'tumunu_goster_icon' => 'https://v3.pronetstatic.com/ngsbet/upload_files/ngsv3_allview.png',
        'aktif' => 1
    ];
}

// Oyunları getir
try {
    $stmt = $db->query("SELECT * FROM populercanlicasinoanasayfa ORDER BY sira ASC");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $games = [];
}

ob_start();
?>

<style>
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --body-bg: #1a1a1a;
    --card-bg: #2d2d2d;
    --border-color: #404040;
    --text-color: #ffffff;
    --text-muted: #b0b0b0;
}

body {
    background-color: var(--body-bg);
    color: var(--text-color);
}

.dashboard-header {
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 15px 15px;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--card-bg), #3a3a3a);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.stat-card .stat-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-card .stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.stat-card .stat-label {
    color: var(--text-muted);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.live-casino-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    margin-bottom: 2rem;
}

.live-casino-card .card-header {
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    color: white;
    border-bottom: 1px solid var(--border-color);
    border-radius: 15px 15px 0 0;
    padding: 1.5rem;
}

.live-casino-card .card-body {
    padding: 2rem;
}

.form-control, .form-select {
    background-color: var(--dark-color);
    border: 1px solid var(--border-color);
    color: var(--text-color);
}

.form-control:focus, .form-select:focus {
    background-color: var(--dark-color);
    border-color: var(--primary-color);
    color: var(--text-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-label {
    color: var(--text-color);
    font-weight: 500;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success-color), #1e7e34);
    border: none;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-color), #c82333);
    border: none;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning-color), #e0a800);
    border: none;
    color: var(--dark-color);
}

.table {
    background-color: var(--card-bg);
    color: var(--text-color);
}

.table th {
    background-color: var(--dark-color);
    border-color: var(--border-color);
    color: var(--text-color);
    font-weight: 600;
}

.table td {
    border-color: var(--border-color);
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.1);
}

.modal-content {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    color: white;
    border-bottom: 1px solid var(--border-color);
    border-radius: 15px 15px 0 0;
}

.modal-footer {
    border-top: 1px solid var(--border-color);
}

.alert {
    border-radius: 10px;
    border: none;
}

.alert-success {
    background: linear-gradient(135deg, var(--success-color), #1e7e34);
    color: white;
}

.alert-danger {
    background: linear-gradient(135deg, var(--danger-color), #c82333);
    color: white;
}

.badge {
    border-radius: 6px;
    font-weight: 500;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
}

.status-active {
    background: linear-gradient(135deg, var(--success-color), #1e7e34);
    color: white;
}

.status-inactive {
    background: linear-gradient(135deg, var(--secondary-color), #545b62);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.preview-section {
    background: var(--dark-color);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.preview-section h6 {
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.preview-content {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .stat-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card .stat-icon {
        font-size: 2rem;
    }
    
    .stat-card .stat-number {
        font-size: 1.5rem;
    }
}
</style>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-0">
                    <i class="bi bi-controller me-2"></i>
                    Popüler Canlı Casino Yönetimi
                </h1>
                <p class="mb-0 mt-2 opacity-75">Canlı casino oyunlarını yönetin ve düzenleyin</p>
            </div>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addGameModal">
                <i class="bi bi-plus-circle me-2"></i>
                Yeni Oyun Ekle
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">

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
    
    <!-- Başlık Ayarları Kartı -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-gear-fill me-1"></i> Popüler Canlı Casino Başlık Ayarları
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
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="icon_url" name="icon_url" value="<?php echo htmlspecialchars($header_settings['icon_url']); ?>" required>
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
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="tumunu_goster_icon" name="tumunu_goster_icon" value="<?php echo htmlspecialchars($header_settings['tumunu_goster_icon']); ?>" required>
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
                        <div class="small text-white mt-2"><i class="bi bi-info-circle"></i> Not: Bu bölüm sitenizde görünecek olan popüler canlı casino başlığının önizlemesidir.</div>
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
                <i class="bi bi-list me-1"></i> Popüler Canlı Casino Oyunları Listesi
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="gamesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Oyun Adı</th>
                            <th>Resim</th>
                            <th>Link URL</th>
                            <th>Sıra</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $game): ?>
                        <tr>
                            <td><?php echo $game['id']; ?></td>
                            <td><?php echo htmlspecialchars($game['oyun_adi']); ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($game['resim_url']); ?>" alt="<?php echo htmlspecialchars($game['oyun_adi']); ?>" style="max-width: 100px;">
                            </td>
                            <td><?php echo htmlspecialchars($game['link_url']); ?></td>
                            <td><?php echo $game['sira']; ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $game['aktif'] ? 'btn-success' : 'btn-danger'; ?>">
                                        <?php echo $game['aktif'] ? 'Aktif' : 'Pasif'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary edit-game" 
                                        data-id="<?php echo $game['id']; ?>"
                                        data-oyun-adi="<?php echo htmlspecialchars($game['oyun_adi']); ?>"
                                        data-resim-url="<?php echo htmlspecialchars($game['resim_url']); ?>"
                                        data-link-url="<?php echo htmlspecialchars($game['link_url']); ?>">
                                    <i class="bi bi-pencil"></i> Düzenle
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Bu oyunu silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
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

<!-- Add Game Modal -->
<div class="modal fade" id="addGameModal" tabindex="-1" aria-labelledby="addGameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addGameModalLabel"><i class="bi bi-plus-circle"></i> Yeni Canlı Casino Oyunu Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="oyun_adi" class="form-label">Oyun Adı</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="oyun_adi" name="oyun_adi" required>
                    </div>
                    <div class="mb-3">
                        <label for="resim_url" class="form-label">Resim URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="resim_url" name="resim_url" required>
                    </div>
                    <div class="mb-3">
                        <label for="link_url" class="form-label">Link URL</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="link_url" name="link_url">
                        <div class="form-text text-muted">İsteğe bağlı alan. Boş bırakılabilir.</div>
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

<!-- Edit Game Modal -->
<div class="modal fade" id="editGameModal" tabindex="-1" aria-labelledby="editGameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="editGameModalLabel">
                    <i class="bi bi-pencil-square"></i> Canlı Casino Oyunu Düzenle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editGameForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_oyun_adi" class="form-label">Oyun Adı</label>
                        <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_oyun_adi" name="oyun_adi" required>
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
    $('#gamesTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        // DataTable görünüm ayarları
        dom: '<"top"lf>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]]
    });
    
    // Düzenleme butonlarını daha belirgin yapma
    const editButtons = document.querySelectorAll('.edit-game');
    editButtons.forEach(button => {
        button.classList.add('btn-primary');
        button.style.fontWeight = 'bold';
    });
    
    // Düzenleme butonu işlemleri - Tek ve basit yaklaşım
    const editModal = new bootstrap.Modal(document.getElementById('editGameModal'));
    
    // Düzenleme butonlarına tıklama olayı ekle
    document.querySelectorAll('.edit-game').forEach(button => {
        button.addEventListener('click', function() {
            console.log('Düzenle butonuna tıklandı!');
            
            // Data attributelerini al
            const id = this.getAttribute('data-id');
            const oyunAdi = this.getAttribute('data-oyun-adi');
            const resimUrl = this.getAttribute('data-resim-url');
            const linkUrl = this.getAttribute('data-link-url');
            
            console.log('Oyun bilgileri:', { id, oyunAdi, resimUrl, linkUrl });
            
            // Form alanlarını doldur
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_oyun_adi').value = oyunAdi;
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
                text: 'Popüler canlı casino başlık ayarları güncellenecek',
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
            
            // Oyun adını bul (aynı satırdaki ikinci hücreden)
            const row = this.closest('tr');
            const gameName = row ? row.querySelector('td:nth-child(2)').textContent.trim() : 'bu oyunu';
            
            // SweetAlert ile onay iste
            Swal.fire({
                title: 'Emin misiniz?',
                text: `"${gameName}" adlı oyun silinecek. Bu işlem geri alınamaz!`,
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
                        text: 'Oyun silme işlemi gerçekleştiriliyor.',
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
    document.getElementById('editGameForm').addEventListener('submit', function(e) {
        console.log('Form gönderiliyor...');
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 