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
    $stmt = $db->query("SELECT COUNT(*) as totalItems FROM financial_menu");
    $totalItems = $stmt->fetch()['totalItems'];

    $stmt = $db->query("SELECT COUNT(*) as activeItems FROM financial_menu WHERE active = 1");
    $activeItems = $stmt->fetch()['activeItems'];

    $stmt = $db->query("SELECT COUNT(*) as inactiveItems FROM financial_menu WHERE active = 0");
    $inactiveItems = $stmt->fetch()['inactiveItems'];

    $stmt = $db->query("SELECT COUNT(*) as todayUpdates FROM financial_menu WHERE DATE(updated_at) = CURDATE()");
    $todayUpdates = $stmt->fetch()['todayUpdates'];

    $stmt = $db->query("SELECT COUNT(*) as withColor FROM financial_menu WHERE text_color IS NOT NULL AND text_color != ''");
    $withColor = $stmt->fetch()['withColor'];

    $stmt = $db->query("SELECT AVG(sira) as avgOrder FROM financial_menu");
    $avgOrder = round($stmt->fetch()['avgOrder'] ?? 0, 1);

} catch (Exception $e) {
    $totalItems = 0;
    $activeItems = 0;
    $inactiveItems = 0;
    $todayUpdates = 0;
    $withColor = 0;
    $avgOrder = 0;
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit':
                try {
                    $id = $_POST['id'];
                    $title = $_POST['title'];
                    $icon_url = $_POST['icon_url'];
                    $link = $_POST['link'];
                    $alt_text = $_POST['alt_text'];
                    $text_color = $_POST['text_color'];
                    $sira = $_POST['sira'];
                    $active = isset($_POST['active']) ? 1 : 0;
                    
                    $stmt = $db->prepare("UPDATE financial_menu SET 
                        title = ?, 
                        icon_url = ?, 
                        link = ?, 
                        alt_text = ?, 
                        text_color = ?, 
                        sira = ?, 
                        active = ?,
                        updated_at = NOW()
                    WHERE id = ?");
                    $stmt->execute([$title, $icon_url, $link, $alt_text, $text_color, $sira, $active, $id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'edit_financial_menu', "Finansal menü öğesi güncellendi: $title", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Menü öğesi başarıyla güncellendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Güncelleme sırasında hata oluştu: " . $e->getMessage();
                }
                break;

            case 'add':
                try {
                    $title = $_POST['title'];
                    $icon_url = $_POST['icon_url'];
                    $link = $_POST['link'];
                    $alt_text = $_POST['alt_text'];
                    $text_color = $_POST['text_color'];
                    $sira = $_POST['sira'];
                    $active = isset($_POST['active']) ? 1 : 0;
                    
                    $stmt = $db->prepare("INSERT INTO financial_menu (title, icon_url, link, alt_text, text_color, sira, active, created_at, updated_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmt->execute([$title, $icon_url, $link, $alt_text, $text_color, $sira, $active]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'add_financial_menu', "Yeni finansal menü öğesi eklendi: $title", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Yeni menü öğesi başarıyla eklendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Ekleme sırasında hata oluştu: " . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $db->prepare("DELETE FROM financial_menu WHERE id = ?");
                    $stmt->execute([$id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'delete_financial_menu', "Finansal menü öğesi silindi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Menü öğesi başarıyla silindi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Silme sırasında hata oluştu: " . $e->getMessage();
                }
                break;
        }
    }
}

// Menü öğelerini getir
try {
    $stmt = $db->query("SELECT * FROM financial_menu ORDER BY sira ASC");
    $menuItems = $stmt->fetchAll();
} catch (Exception $e) {
    $menuItems = [];
}

ob_start();
?>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-0">
                    <i class="bi bi-currency-exchange me-2"></i>
                    Finansal İşlemler Menü Ayarları
                </h1>
                <p class="mb-0 mt-2 opacity-75">Finansal işlemler menü öğelerini yönetin</p>
            </div>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addMenuItemModal">
                <i class="bi bi-plus-circle me-2"></i>
                Yeni Menü Öğesi Ekle
            </button>
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

    <!-- Menü Öğeleri Tablosu -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-list-ul me-2"></i> Menü Öğeleri
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="menuItemsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>İkon</th>
                            <th>Link</th>
                            <th>Alt Metin</th>
                            <th>Metin Rengi</th>
                            <th>Sıra</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menuItems as $item): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($item['icon_url']); ?>" alt="İkon" style="max-width: 40px; height: auto;">
                            </td>
                            <td>
                                <?php if (!empty($item['link'])): ?>
                                    <span class="badge bg-success">Linkli</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Linksiz</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['alt_text']); ?></td>
                            <td>
                                <?php if (!empty($item['text_color'])): ?>
                                    <span style="color: <?php echo htmlspecialchars($item['text_color']); ?>;">
                                        <?php echo htmlspecialchars($item['text_color']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Varsayılan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $item['sira']; ?></span>
                            </td>
                            <td>
                                <?php if ($item['active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary edit-menu-item" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($item['title']); ?>"
                                        data-icon-url="<?php echo htmlspecialchars($item['icon_url']); ?>"
                                        data-link="<?php echo htmlspecialchars($item['link']); ?>"
                                        data-alt-text="<?php echo htmlspecialchars($item['alt_text']); ?>"
                                        data-text-color="<?php echo htmlspecialchars($item['text_color']); ?>"
                                        data-sira="<?php echo $item['sira']; ?>"
                                        data-active="<?php echo $item['active']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-menu-item" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($item['title']); ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Menu Item Modal -->
<div class="modal fade" id="addMenuItemModal" tabindex="-1" aria-labelledby="addMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMenuItemModalLabel">
                    <i class="bi bi-plus-circle me-2"></i> Yeni Menü Öğesi Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label">
                                <i class="bi bi-tag me-1"></i> Menü Başlığı
                            </label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="icon_url" class="form-label">
                                <i class="bi bi-image me-1"></i> İkon URL
                            </label>
                            <input type="text" class="form-control" id="icon_url" name="icon_url" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="link" class="form-label">
                                <i class="bi bi-link-45deg me-1"></i> Link
                            </label>
                            <input type="text" class="form-control" id="link" name="link" required>
                        </div>
                        <div class="col-md-6">
                            <label for="alt_text" class="form-label">
                                <i class="bi bi-text-paragraph me-1"></i> Alt Metin
                            </label>
                            <input type="text" class="form-control" id="alt_text" name="alt_text" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="text_color" class="form-label">
                                <i class="bi bi-palette me-1"></i> Metin Rengi (Opsiyonel)
                            </label>
                            <input type="text" class="form-control" id="text_color" name="text_color" placeholder="#rrggbb">
                        </div>
                        <div class="col-md-4">
                            <label for="sira" class="form-label">
                                <i class="bi bi-sort-numeric-up me-1"></i> Sıra
                            </label>
                            <input type="number" class="form-control" id="sira" name="sira" min="1" value="<?php echo count($menuItems) + 1; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="active" name="active" checked>
                                <label class="form-check-label" for="active">
                                    <i class="bi bi-check-circle me-1"></i> Aktif
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Renk Önizleme</label>
                            <div class="p-2 bg-dark rounded text-center border">
                                <span id="color_preview" class="text-white">Varsayılan Renk</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">İkon Önizleme</label>
                            <div class="p-2 bg-light rounded text-center">
                                <img id="image_preview" src="" alt="İkon Önizleme" class="img-thumbnail d-none" style="max-height: 60px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-2"></i> Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Menu Item Modal -->
<div class="modal fade" id="editMenuItemModal" tabindex="-1" aria-labelledby="editMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMenuItemModalLabel">
                    <i class="bi bi-pencil-square me-2"></i> Menü Öğesi Düzenle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editMenuItemForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_menu_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_title" class="form-label">
                                <i class="bi bi-tag me-1"></i> Menü Başlığı
                            </label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_icon_url" class="form-label">
                                <i class="bi bi-image me-1"></i> İkon URL
                            </label>
                            <input type="text" class="form-control" id="edit_icon_url" name="icon_url" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_link" class="form-label">
                                <i class="bi bi-link-45deg me-1"></i> Link
                            </label>
                            <input type="text" class="form-control" id="edit_link" name="link" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_alt_text" class="form-label">
                                <i class="bi bi-text-paragraph me-1"></i> Alt Metin
                            </label>
                            <input type="text" class="form-control" id="edit_alt_text" name="alt_text" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_text_color" class="form-label">
                                <i class="bi bi-palette me-1"></i> Metin Rengi (Opsiyonel)
                            </label>
                            <input type="text" class="form-control" id="edit_text_color" name="text_color" placeholder="#rrggbb">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_sira" class="form-label">
                                <i class="bi bi-sort-numeric-up me-1"></i> Sıra
                            </label>
                            <input type="number" class="form-control" id="edit_sira" name="sira" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="edit_active" name="active">
                                <label class="form-check-label" for="edit_active">
                                    <i class="bi bi-check-circle me-1"></i> Aktif
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Renk Önizleme</label>
                            <div class="p-2 bg-dark rounded text-center border">
                                <span id="edit_color_preview" class="text-white">Varsayılan Renk</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">İkon Önizleme</label>
                            <div class="p-2 bg-light rounded text-center">
                                <img id="edit_image_preview" src="" alt="İkon Önizleme" class="img-thumbnail" style="max-height: 60px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i> Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMenuItemModal" tabindex="-1" aria-labelledby="deleteMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMenuItemModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i> Silme Onayı
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bu menü öğesini silmek istediğinizden emin misiniz?</p>
                <p class="text-muted" id="delete_item_title"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <form method="POST" class="d-inline" id="deleteMenuItemForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_menu_id">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i> Sil
                    </button>
                </form>
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

/* Tables */
.table {
    color: var(--text-light);
    margin-bottom: 0;
}

.table thead th {
    background-color: var(--dark-accent);
    border-color: var(--border-color);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table tbody td {
    border-color: var(--border-color);
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: var(--dark-accent);
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

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #146c43;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #b02a37;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-secondary:hover {
    background-color: #5c636a;
    transform: translateY(-1px);
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
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

/* Modals */
.modal-content {
    background-color: var(--dark-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
}

.modal-header {
    border-bottom: 1px solid var(--border-color);
    background-color: var(--dark-accent);
    border-radius: 12px 12px 0 0;
}

.modal-title {
    color: white;
    font-weight: 600;
}

.modal-body {
    color: var(--text-light);
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    background-color: var(--dark-accent);
    border-radius: 0 0 12px 12px;
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

.form-check-label {
    color: var(--text-light);
}

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

/* Badges */
.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
}

.bg-primary {
    background-color: var(--primary-color) !important;
}

.bg-success {
    background-color: var(--success-color) !important;
}

.bg-danger {
    background-color: var(--danger-color) !important;
}

.bg-secondary {
    background-color: var(--secondary-color) !important;
}

.bg-info {
    background-color: var(--info-color) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables with enhanced configuration
    const dataTableConfig = {
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        dom: '<"top"lf>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]],
        responsive: true,
        pageLength: 25,
        order: [[6, 'asc']], // Sort by sira column
        columnDefs: [
            {
                targets: -1, // Last column (actions)
                orderable: false,
                searchable: false
            }
        ]
    };

    // Initialize DataTables
    $('#menuItemsTable').DataTable(dataTableConfig);

    // Handle edit menu item button clicks
    document.querySelectorAll('.edit-menu-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const iconUrl = this.getAttribute('data-icon-url');
            const link = this.getAttribute('data-link');
            const altText = this.getAttribute('data-alt-text');
            const textColor = this.getAttribute('data-text-color');
            const sira = this.getAttribute('data-sira');
            const active = this.getAttribute('data-active');

            // Populate edit modal
            document.getElementById('edit_menu_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_icon_url').value = iconUrl;
            document.getElementById('edit_link').value = link;
            document.getElementById('edit_alt_text').value = altText;
            document.getElementById('edit_text_color').value = textColor;
            document.getElementById('edit_sira').value = sira;
            document.getElementById('edit_active').checked = active === '1';

            // Update image preview
            const preview = document.getElementById('edit_image_preview');
            if (iconUrl) {
                preview.src = iconUrl;
                preview.classList.remove('d-none');
            } else {
                preview.classList.add('d-none');
            }

            // Update color preview
            const colorPreview = document.getElementById('edit_color_preview');
            if (textColor) {
                colorPreview.style.color = textColor;
                colorPreview.textContent = 'Renkli Metin Örneği';
            } else {
                colorPreview.style.color = 'white';
                colorPreview.textContent = 'Varsayılan Renk';
            }

            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('editMenuItemModal'));
            editModal.show();
        });
    });

    // Handle delete menu item button clicks
    document.querySelectorAll('.delete-menu-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');

            // Populate delete modal
            document.getElementById('delete_menu_id').value = id;
            document.getElementById('delete_item_title').textContent = title;

            // Show modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteMenuItemModal'));
            deleteModal.show();
        });
    });

    // Handle image URL changes for preview
    document.getElementById('icon_url').addEventListener('input', function() {
        const preview = document.getElementById('image_preview');
        if (this.value) {
            preview.src = this.value;
            preview.classList.remove('d-none');
        } else {
            preview.classList.add('d-none');
        }
    });

    document.getElementById('edit_icon_url').addEventListener('input', function() {
        const preview = document.getElementById('edit_image_preview');
        if (this.value) {
            preview.src = this.value;
            preview.classList.remove('d-none');
        } else {
            preview.classList.add('d-none');
        }
    });

    // Handle color changes for preview
    document.getElementById('text_color').addEventListener('input', function() {
        const colorPreview = document.getElementById('color_preview');
        if (this.value) {
            colorPreview.style.color = this.value;
            colorPreview.textContent = 'Renkli Metin Örneği';
        } else {
            colorPreview.style.color = 'white';
            colorPreview.textContent = 'Varsayılan Renk';
        }
    });

    document.getElementById('edit_text_color').addEventListener('input', function() {
        const colorPreview = document.getElementById('edit_color_preview');
        if (this.value) {
            colorPreview.style.color = this.value;
            colorPreview.textContent = 'Renkli Metin Örneği';
        } else {
            colorPreview.style.color = 'white';
            colorPreview.textContent = 'Varsayılan Renk';
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 