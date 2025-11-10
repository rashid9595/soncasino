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
    $stmt = $db->query("SELECT COUNT(*) as totalGames FROM anamenukazandiranoyun");
    $totalGames = $stmt->fetch()['totalGames'];
    
    $stmt = $db->query("SELECT COUNT(*) as activeGames FROM anamenukazandiranoyun WHERE aktif = 1");
    $activeGames = $stmt->fetch()['activeGames'];
    
    $stmt = $db->query("SELECT COUNT(*) as todayGames FROM anamenukazandiranoyun WHERE DATE(created_at) = CURDATE()");
    $todayGames = $stmt->fetch()['todayGames'];
    
    $stmt = $db->query("SELECT AVG(sira) as avgOrder FROM anamenukazandiranoyun");
    $avgOrder = round($stmt->fetch()['avgOrder'], 1);
    
    $stmt = $db->query("SELECT MAX(sira) as maxOrder FROM anamenukazandiranoyun");
    $maxOrder = $stmt->fetch()['maxOrder'];
    
    $stmt = $db->query("SELECT COUNT(*) as totalUpdates FROM anamenukazandiranoyun WHERE updated_at IS NOT NULL");
    $totalUpdates = $stmt->fetch()['totalUpdates'];
    
} catch (Exception $e) {
    $totalGames = 0;
    $activeGames = 0;
    $todayGames = 0;
    $avgOrder = 0;
    $maxOrder = 0;
    $totalUpdates = 0;
}

// Oyun ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_game'])) {
    try {
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'];
        $sira = $_POST['sira'];

        $stmt = $db->prepare("INSERT INTO anamenukazandiranoyun (resim_url, link_url, sira, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$resim_url, $link_url, $sira]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'add_game', "Yeni kazandıran oyun eklendi: $resim_url", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Oyun başarıyla eklendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Oyun eklenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: kazandiran_oyunlar.php");
    exit();
}

// Oyun düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_game'])) {
    try {
        $game_id = $_POST['game_id'];
        $resim_url = $_POST['resim_url'];
        $link_url = $_POST['link_url'];
        $sira = $_POST['sira'];

        $stmt = $db->prepare("UPDATE anamenukazandiranoyun SET resim_url = ?, link_url = ?, sira = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$resim_url, $link_url, $sira, $game_id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'edit_game', "Kazandıran oyun güncellendi: ID $game_id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Oyun başarıyla güncellendi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Oyun güncellenirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: kazandiran_oyunlar.php");
    exit();
}

// Oyun silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_game'])) {
    try {
        $game_id = $_POST['game_id'];
        
        $stmt = $db->prepare("DELETE FROM anamenukazandiranoyun WHERE id = ?");
        $stmt->execute([$game_id]);
        
        // Aktivite logu
        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'delete_game', "Kazandıran oyun silindi: ID $game_id", $_SERVER['REMOTE_ADDR']]);
        
        $_SESSION['success'] = "Oyun başarıyla silindi.";
    } catch (Exception $e) {
        $_SESSION['error'] = "Oyun silinirken hata oluştu: " . $e->getMessage();
    }
    
    header("Location: kazandiran_oyunlar.php");
    exit();
}

// Tüm oyunları getir
$stmt = $db->query("SELECT * FROM anamenukazandiranoyun ORDER BY sira, id");
$games = $stmt->fetchAll();

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

.games-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.games-card .card-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
    color: white;
    padding: 1.5rem;
    border-bottom: none;
}

.games-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    font-size: 1.25rem;
}

.games-card .card-body {
    padding: 0;
}

.table {
    margin: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid var(--border-color);
    color: var(--text-color);
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

.game-image {
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
                <h1><i class="bi bi-trophy-fill me-3"></i>Kazandıran Oyunlar</h1>
                <p>Ana menüde gösterilecek kazandıran oyunları yönetin</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addGameModal">
                    <i class="bi bi-plus-circle me-2"></i>Yeni Oyun Ekle
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="games-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-list-ul me-2"></i>Oyun Listesi</h5>
        </div>
        <div class="card-body">
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

            <div class="table-responsive">
                <table class="table table-hover" id="gamesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Sıra</th>
                            <th>Resim</th>
                            <th>Link URL</th>
                            <th>Durum</th>
                            <th>Oluşturulma</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($games)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="bi bi-trophy"></i>
                                        <h5>Henüz oyun eklenmemiş</h5>
                                        <p>İlk kazandıran oyunu eklemek için yukarıdaki butonu kullanın.</p>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                                            <i class="bi bi-plus-circle me-2"></i>İlk Oyunu Ekle
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($games as $game): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $game['sira']; ?></span>
                                    </td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($game['resim_url']); ?>" 
                                             alt="Oyun Resmi" 
                                             class="game-image"
                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA4MCA2MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjYwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik0yMCAyMEg2MFY0MEgyMFYyMFoiIGZpbGw9IiNEN0Q3RDciLz4KPHN2ZyB4PSIyOCIgeT0iMjgiIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMTkgM0g1QzMuOSAzIDMgMy45IDMgNVYxOUMzIDIwLjEgMy45IDIxIDUgMjFIMTlDMjAuMSAyMSAyMSAyMC4xIDIxIDE5VjVDMjEgMy45IDIwLjEgMyAxOSAzWk0xOSAxOUg1VjVIMTlWMTlaIiBmaWxsPSIjOTk5OTk5Ii8+Cjwvc3ZnPgo8L3N2Zz4K'">
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($game['link_url']); ?>">
                                            <?php echo htmlspecialchars($game['link_url']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (isset($game['aktif']) && $game['aktif']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle me-1"></i>Pasif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($game['created_at'])): ?>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y H:i', strtotime($game['created_at'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-primary btn-sm edit-game-btn" 
                                                    data-game-id="<?php echo $game['id']; ?>"
                                                    data-resim-url="<?php echo htmlspecialchars($game['resim_url']); ?>"
                                                    data-link-url="<?php echo htmlspecialchars($game['link_url']); ?>"
                                                    data-sira="<?php echo $game['sira']; ?>">
                                                <i class="bi bi-pencil me-1"></i>Düzenle
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm delete-game-btn" 
                                                    data-game-id="<?php echo $game['id']; ?>"
                                                    data-game-name="<?php echo htmlspecialchars($game['resim_url']); ?>">
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

<!-- Yeni Oyun Ekleme Modal -->
<div class="modal fade" id="addGameModal" tabindex="-1" aria-labelledby="addGameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGameModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Yeni Oyun Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="addGameForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_resim_url" class="form-label">
                            <i class="bi bi-image me-2"></i>Resim URL
                        </label>
                        <input type="url" class="form-control" id="new_resim_url" name="resim_url" 
                               placeholder="https://example.com/image.jpg" required>
                        <div class="form-text">Oyun resminin URL adresini girin</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_link_url" class="form-label">
                            <i class="bi bi-link-45deg me-2"></i>Link URL
                        </label>
                        <input type="url" class="form-control" id="new_link_url" name="link_url" 
                               placeholder="https://example.com/game" required>
                        <div class="form-text">Oyun linkinin URL adresini girin</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_sira" class="form-label">
                            <i class="bi bi-sort-numeric-up me-2"></i>Sıra
                        </label>
                        <input type="number" class="form-control" id="new_sira" name="sira" 
                               placeholder="1" min="1" required>
                        <div class="form-text">Oyunun görüntülenme sırasını belirleyin</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>İptal
                    </button>
                    <button type="submit" name="add_game" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Oyun Düzenleme Modal -->
<div class="modal fade" id="editGameModal" tabindex="-1" aria-labelledby="editGameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGameModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Oyun Düzenle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editGameForm">
                <div class="modal-body">
                    <input type="hidden" name="game_id" id="edit_game_id">
                    
                    <div class="mb-3">
                        <label for="edit_resim_url" class="form-label">
                            <i class="bi bi-image me-2"></i>Resim URL
                        </label>
                        <input type="url" class="form-control" id="edit_resim_url" name="resim_url" required>
                        <div class="form-text">Oyun resminin URL adresini girin</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_link_url" class="form-label">
                            <i class="bi bi-link-45deg me-2"></i>Link URL
                        </label>
                        <input type="url" class="form-control" id="edit_link_url" name="link_url" required>
                        <div class="form-text">Oyun linkinin URL adresini girin</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_sira" class="form-label">
                            <i class="bi bi-sort-numeric-up me-2"></i>Sıra
                        </label>
                        <input type="number" class="form-control" id="edit_sira" name="sira" min="1" required>
                        <div class="form-text">Oyunun görüntülenme sırasını belirleyin</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>İptal
                    </button>
                    <button type="submit" name="edit_game" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Oyun Silme Modal -->
<div class="modal fade" id="deleteGameModal" tabindex="-1" aria-labelledby="deleteGameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGameModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Oyunu Sil
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="deleteGameForm">
                <div class="modal-body">
                    <input type="hidden" name="game_id" id="delete_game_id">
                    <div class="text-center">
                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Bu oyunu silmek istediğinizden emin misiniz?</h5>
                        <p class="text-muted" id="delete_game_name"></p>
                        <p class="text-danger"><small>Bu işlem geri alınamaz!</small></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>İptal
                    </button>
                    <button type="submit" name="delete_game" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Sil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTables initialization
    $('#gamesTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        order: [[0, 'asc']],
        pageLength: 25,
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        columnDefs: [
            { orderable: false, targets: [1, 5] }
        ]
    });

    // Edit game button click
    $('.edit-game-btn').on('click', function() {
        const gameId = $(this).data('game-id');
        const resimUrl = $(this).data('resim-url');
        const linkUrl = $(this).data('link-url');
        const sira = $(this).data('sira');

        $('#edit_game_id').val(gameId);
        $('#edit_resim_url').val(resimUrl);
        $('#edit_link_url').val(linkUrl);
        $('#edit_sira').val(sira);

        $('#editGameModal').modal('show');
    });

    // Delete game button click
    $('.delete-game-btn').on('click', function() {
        const gameId = $(this).data('game-id');
        const gameName = $(this).data('game-name');

        $('#delete_game_id').val(gameId);
        $('#delete_game_name').text(gameName);

        $('#deleteGameModal').modal('show');
    });

    // Form validation
    $('#addGameForm, #editGameForm').on('submit', function(e) {
        const resimUrl = $(this).find('input[name="resim_url"]').val();
        const linkUrl = $(this).find('input[name="link_url"]').val();
        const sira = $(this).find('input[name="sira"]').val();

        if (!resimUrl || !linkUrl || !sira) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Lütfen tüm alanları doldurun.',
                confirmButtonText: 'Tamam'
            });
            return false;
        }

        if (!isValidUrl(resimUrl) || !isValidUrl(linkUrl)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Lütfen geçerli URL adresleri girin.',
                confirmButtonText: 'Tamam'
            });
            return false;
        }

        if (sira < 1) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata!',
                text: 'Sıra numarası 1\'den küçük olamaz.',
                confirmButtonText: 'Tamam'
            });
            return false;
        }
    });

    // URL validation function
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    // Delete confirmation with SweetAlert2
    $('#deleteGameForm').on('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu oyun kalıcı olarak silinecek!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Evet, sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();
$pageTitle = "Kazandıran Oyunlar";
require_once 'includes/layout.php';
?> 