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
    $stmt = $db->query("SELECT COUNT(*) as totalMenus FROM footer_menu");
    $totalMenus = $stmt->fetch()['totalMenus'];

    $stmt = $db->query("SELECT COUNT(*) as activeMenus FROM footer_menu WHERE active = 1");
    $activeMenus = $stmt->fetch()['activeMenus'];

    $stmt = $db->query("SELECT COUNT(*) as inactiveMenus FROM footer_menu WHERE active = 0");
    $inactiveMenus = $stmt->fetch()['inactiveMenus'];

    $stmt = $db->query("SELECT COUNT(*) as todayMenus FROM footer_menu WHERE DATE(created_at) = CURDATE()");
    $todayMenus = $stmt->fetch()['todayMenus'];

    $stmt = $db->query("SELECT COUNT(*) as todayUpdates FROM footer_menu WHERE DATE(updated_at) = CURDATE()");
    $todayUpdates = $stmt->fetch()['todayUpdates'];

    $stmt = $db->query("SELECT AVG(sira) as avgOrder FROM footer_menu");
    $avgOrder = round($stmt->fetch()['avgOrder'] ?? 0, 1);

} catch (Exception $e) {
    $totalMenus = 0;
    $activeMenus = 0;
    $inactiveMenus = 0;
    $todayMenus = 0;
    $todayUpdates = 0;
    $avgOrder = 0;
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_menu_item':
                try {
                    $baslik = trim($_POST['baslik']);
                    $link = trim($_POST['link']);
                    $sira = (int)$_POST['sira'];
                    $active = isset($_POST['active']) ? 1 : 0;
                    
                    if (empty($baslik) || empty($link)) {
                        $_SESSION['error'] = "Menü başlığı ve link gereklidir.";
                    } else {
                        $stmt = $db->prepare("INSERT INTO footer_menu (baslik, link, sira, active, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$baslik, $link, $sira, $active]);

                        // Aktivite logu
                        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$_SESSION['admin_id'], 'add_footer_menu', "Yeni footer menü öğesi eklendi: $baslik", $_SERVER['REMOTE_ADDR']]);

                        $_SESSION['success'] = "Yeni menü öğesi başarıyla eklendi.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Menü öğesi eklenirken hata oluştu: " . $e->getMessage();
                }
                break;

            case 'update_menu_item':
                try {
                    $id = (int)$_POST['id'];
                    $baslik = trim($_POST['baslik']);
                    $link = trim($_POST['link']);
                    $sira = (int)$_POST['sira'];
                    $active = isset($_POST['active']) ? 1 : 0;
                    
                    if (empty($baslik) || empty($link)) {
                        $_SESSION['error'] = "Menü başlığı ve link gereklidir.";
                    } else {
                        $stmt = $db->prepare("UPDATE footer_menu SET baslik = ?, link = ?, sira = ?, active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$baslik, $link, $sira, $active, $id]);

                        // Aktivite logu
                        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$_SESSION['admin_id'], 'update_footer_menu', "Footer menü öğesi güncellendi: $baslik", $_SERVER['REMOTE_ADDR']]);

                        $_SESSION['success'] = "Menü öğesi başarıyla güncellendi.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Menü öğesi güncellenirken hata oluştu: " . $e->getMessage();
                }
                break;

            case 'delete_menu_item':
                try {
                    $id = (int)$_POST['id'];
                    
                    $stmt = $db->prepare("DELETE FROM footer_menu WHERE id = ?");
                    $stmt->execute([$id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'delete_footer_menu', "Footer menü öğesi silindi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Menü öğesi başarıyla silindi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Menü öğesi silinirken hata oluştu: " . $e->getMessage();
                }
                break;

            case 'toggle_active':
                try {
                    $id = $_POST['id'];
                    $active = $_POST['active'];
                    
                    $stmt = $db->prepare("UPDATE footer_menu SET active = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$active, $id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'toggle_footer_menu', "Footer menü durumu değiştirildi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                break;
        }
    }
}

// Menü öğelerini getir
try {
    $stmt = $db->query("SELECT * FROM footer_menu ORDER BY sira ASC");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <i class="bi bi-list me-2"></i>
                    Footer Menü Ayarları
                </h1>
                <p class="mb-0 mt-2 opacity-75">Footer menü öğelerini yönetin</p>
            </div>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                <i class="bi bi-plus-circle me-2"></i>
                Yeni Menü Ekle
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

    <!-- Menu Items Table Card -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-list me-2"></i> Menü Öğeleri Listesi
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="menuTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Başlık</th>
                            <th>Link</th>
                            <th>Sıra</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menuItems as $item): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['baslik']); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" class="text-decoration-none">
                                    <?php echo htmlspecialchars($item['link']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $item['sira']; ?></span>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input toggle-active" type="checkbox" 
                                           data-id="<?php echo $item['id']; ?>"
                                           <?php echo $item['active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        <?php echo $item['active'] ? 'Aktif' : 'Pasif'; ?>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary edit-menu" 
                                        data-id="<?php echo $item['id']; ?>"
                                        data-baslik="<?php echo htmlspecialchars($item['baslik']); ?>"
                                        data-link="<?php echo htmlspecialchars($item['link']); ?>"
                                        data-active="<?php echo $item['active']; ?>"
                                        data-sira="<?php echo $item['sira']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Bu menü öğesini silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete_menu_item">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
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

<!-- Add Menu Modal -->
<div class="modal fade" id="addMenuModal" tabindex="-1" aria-labelledby="addMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMenuModalLabel">
                    <i class="bi bi-plus-circle me-2"></i> Yeni Menü Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_menu_item">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="baslik" class="form-label">
                                <i class="bi bi-tag me-1"></i> Başlık
                            </label>
                            <input type="text" class="form-control" id="baslik" name="baslik" required>
                        </div>
                        <div class="col-md-6">
                            <label for="sira" class="form-label">
                                <i class="bi bi-sort-numeric-up me-1"></i> Sıra
                            </label>
                            <input type="number" class="form-control" id="sira" name="sira" min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="link" class="form-label">
                            <i class="bi bi-link-45deg me-1"></i> Link
                        </label>
                        <input type="url" class="form-control" id="link" name="link" required>
                        <div class="form-text">Menü öğesinin yönlendireceği URL</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                            <label class="form-check-label" for="active">
                                <i class="bi bi-check-circle me-1"></i> Aktif
                            </label>
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

<!-- Edit Menu Modal -->
<div class="modal fade" id="editMenuModal" tabindex="-1" aria-labelledby="editMenuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMenuModalLabel">
                    <i class="bi bi-pencil-square me-2"></i> Menü Düzenle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editMenuForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_menu_item">
                    <input type="hidden" name="id" id="edit_menu_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_baslik" class="form-label">
                                <i class="bi bi-tag me-1"></i> Başlık
                            </label>
                            <input type="text" class="form-control" id="edit_baslik" name="baslik" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_sira" class="form-label">
                                <i class="bi bi-sort-numeric-up me-1"></i> Sıra
                            </label>
                            <input type="number" class="form-control" id="edit_sira" name="sira" min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_link" class="form-label">
                            <i class="bi bi-link-45deg me-1"></i> Link
                        </label>
                        <input type="url" class="form-control" id="edit_link" name="link" required>
                        <div class="form-text">Menü öğesinin yönlendireceği URL</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit_active" name="active">
                            <label class="form-check-label" for="edit_active">
                                <i class="bi bi-check-circle me-1"></i> Aktif
                            </label>
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

.form-text {
    color: var(--text-muted);
    font-size: 0.875rem;
}

/* Form Switch */
.form-check-input {
    background-color: var(--dark-bg);
    border-color: var(--border-color);
}

.form-check-input:checked {
    background-color: var(--success-color);
    border-color: var(--success-color);
}

.form-check-label {
    color: var(--text-light);
}

/* Badges */
.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
}

.bg-secondary {
    background-color: var(--secondary-color) !important;
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
        order: [[3, 'asc']], // Sort by order column
        columnDefs: [
            {
                targets: -1, // Last column (actions)
                orderable: false,
                searchable: false
            }
        ]
    };

    // Initialize DataTables
    $('#menuTable').DataTable(dataTableConfig);

    // Handle edit menu button clicks
    document.querySelectorAll('.edit-menu').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const baslik = this.getAttribute('data-baslik');
            const link = this.getAttribute('data-link');
            const active = this.getAttribute('data-active') === '1';
            const sira = this.getAttribute('data-sira');

            // Populate edit modal
            document.getElementById('edit_menu_id').value = id;
            document.getElementById('edit_baslik').value = baslik;
            document.getElementById('edit_link').value = link;
            document.getElementById('edit_sira').value = sira;
            document.getElementById('edit_active').checked = active;

            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('editMenuModal'));
            editModal.show();
        });
    });

    // Handle toggle active switches
    document.querySelectorAll('.toggle-active').forEach(switchElement => {
        switchElement.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const active = this.checked ? 1 : 0;
            const label = this.nextElementSibling;

            // Send AJAX request
            fetch('footer_menu_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_active&id=${id}&active=${active}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    label.textContent = active ? 'Aktif' : 'Pasif';
                } else {
                    // Revert the switch if there was an error
                    this.checked = !this.checked;
                    alert('Durum değiştirilirken hata oluştu: ' + (data.error || 'Bilinmeyen hata'));
                }
            })
            .catch(error => {
                // Revert the switch if there was an error
                this.checked = !this.checked;
                alert('Durum değiştirilirken hata oluştu: ' + error.message);
            });
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 