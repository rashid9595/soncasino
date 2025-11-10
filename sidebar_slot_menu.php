<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Mobil Sidebar Menü Ayarları";

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
$isAdmin = ($_SESSION['role_id'] == 1);
$hasPermission = $isAdmin || (isset($_SESSION['permissions']['menu_settings']['view']) && $_SESSION['permissions']['menu_settings']['view']);

if (!$hasPermission) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// Get current mobile menu items
try {
    $stmt = $db->query("SELECT m1.*, m2.title as parent_title 
                        FROM mobile_menu_items m1 
                        LEFT JOIN mobile_menu_items m2 ON m1.parent_id = m2.id 
                        ORDER BY m1.parent_id, m1.order_num ASC");
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Mobil menü öğeleri yüklenirken bir hata oluştu: " . $e->getMessage();
    $menuItems = [];
    
    // Check if table exists
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'mobile_menu_items'");
        if ($stmt->rowCount() == 0) {
            // Create table if it doesn't exist
            $db->exec("CREATE TABLE `mobile_menu_items` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `parent_id` int(11) DEFAULT NULL,
                `title` varchar(100) NOT NULL,
                `link` varchar(255) NOT NULL,
                `icon` varchar(100) DEFAULT NULL,
                `icon_type` varchar(30) DEFAULT NULL COMMENT 'material-icons, fa, pg-icons, etc.',
                `order_num` int(11) NOT NULL DEFAULT 0,
                `is_new` tinyint(1) NOT NULL DEFAULT 0,
                `is_popular` tinyint(1) NOT NULL DEFAULT 0,
                `is_collapsible` tinyint(1) NOT NULL DEFAULT 0,
                `active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `parent_id` (`parent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            
            // Insert default data
            $db->exec("INSERT INTO `mobile_menu_items` (`id`, `parent_id`, `title`, `link`, `icon`, `icon_type`, `order_num`, `is_new`, `is_popular`, `is_collapsible`, `active`) VALUES
                (1, NULL, 'Ana sayfa', '/', 'home', 'material-icons', 1, 1, 0, 0, 1),
                (2, NULL, 'Bahis', '/bet/sports', 'champions-league', 'pg-icons', 2, 0, 0, 0, 1),
                (3, NULL, 'Canlı Casino', '/games/livecasino', 'cards-fill', 'pg-icons', 3, 0, 0, 0, 1),
                (4, NULL, 'Casino', '/games/casino', 'cherry', 'pg-icons', 4, 0, 0, 0, 1),
                (5, NULL, 'Drops&Wins', '#', 'pragmatic-icon-play', 'pg-icons', 5, 0, 0, 1, 1),
                (6, NULL, 'Popüler Casino', '/games/custom-categories', 'cherry', 'pg-icons', 6, 0, 0, 0, 1),
                (7, NULL, 'JetX', '/games/detail/casino/demo/13477', 'fa-rocket', 'fa', 7, 1, 0, 0, 1),
                (8, NULL, 'Crash Games', '#', 'fa-gamepad', 'fa', 8, 1, 0, 1, 1),
                (9, NULL, 'TV Games', '#', 'fa-television', 'fa', 9, 0, 0, 1, 1),
                (10, NULL, 'Promosyon', '/contents/promotions', 'redeem', 'material-icons', 10, 0, 0, 0, 1),
                (11, NULL, 'Turnuvalar', '/pages/ozel-turnuva', 'emoji_events', 'material-icons', 11, 0, 0, 0, 1),
                (12, 5, 'Drops&Wins Slot', '/games/casino/category/3195065', NULL, NULL, 1, 0, 0, 0, 1),
                (13, 5, 'Drops&Wins Live Casino', '/games/livecasino/category/3195065', NULL, NULL, 2, 0, 0, 0, 1),
                (14, 8, 'Cash or Crash', '/games/livecasino/detail/18378/evolution_CashOrCrash', 'fa-money', 'fa', 1, 0, 0, 0, 1),
                (15, 8, 'Aviator', '/games/detail/casino/demo/7787', 'fa-plane', 'fa', 2, 0, 0, 0, 1),
                (16, 8, 'Vecihi', '/games/detail/casino/demo/24245', 'fa-plane', 'fa', 3, 1, 0, 0, 1),
                (17, 8, 'JetX', '/games/detail/casino/demo/13477', 'fa-rocket', 'fa', 4, 1, 0, 0, 1),
                (18, 9, 'TVBET', '/games/tv-games', NULL, NULL, 1, 0, 1, 0, 1),
                (19, 9, 'Betongames', '/games/betongames', NULL, NULL, 2, 0, 0, 0, 1);");
                
            // Add foreign key constraint after data insertion
            $db->exec("ALTER TABLE `mobile_menu_items`
                ADD CONSTRAINT `fk_parent_menu` FOREIGN KEY (`parent_id`) REFERENCES `mobile_menu_items` (`id`) ON DELETE CASCADE;");
                
            // Get items after creation
            $stmt = $db->query("SELECT m1.*, m2.title as parent_title 
                                FROM mobile_menu_items m1 
                                LEFT JOIN mobile_menu_items m2 ON m1.parent_id = m2.id 
                                ORDER BY m1.parent_id, m1.order_num ASC");
            $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error .= " Tablo kontrolü sırasında hata: " . $e->getMessage();
    }
}

// Get parent menu items for dropdown
try {
    $stmt = $db->query("SELECT id, title FROM mobile_menu_items WHERE parent_id IS NULL ORDER BY order_num ASC");
    $parentMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error .= " Ana menü öğeleri yüklenirken hata: " . $e->getMessage();
    $parentMenuItems = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new menu item
    if (isset($_POST['add_menu_item'])) {
        $title = trim($_POST['title']);
        $link = trim($_POST['link']);
        $icon = trim($_POST['icon']) ?: null;
        $iconType = trim($_POST['icon_type']) ?: null;
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $orderNum = (int)$_POST['order_num'];
        $isNew = isset($_POST['is_new']) ? 1 : 0;
        $isPopular = isset($_POST['is_popular']) ? 1 : 0;
        $isCollapsible = isset($_POST['is_collapsible']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($title) || empty($link)) {
            $error = "Menü başlığı ve link alanları zorunludur.";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO mobile_menu_items (title, link, icon, icon_type, parent_id, order_num, is_new, is_popular, is_collapsible, active) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $link, $icon, $iconType, $parentId, $orderNum, $isNew, $isPopular, $isCollapsible, $active]);
                
                $message = "Yeni mobil menü öğesi başarıyla eklendi.";
                
                // Refresh menu items
                $stmt = $db->query("SELECT m1.*, m2.title as parent_title 
                                    FROM mobile_menu_items m1 
                                    LEFT JOIN mobile_menu_items m2 ON m1.parent_id = m2.id 
                                    ORDER BY m1.parent_id, m1.order_num ASC");
                $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Refresh parent menu items
                $stmt = $db->query("SELECT id, title FROM mobile_menu_items WHERE parent_id IS NULL ORDER BY order_num ASC");
                $parentMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = "Menü öğesi eklenirken bir hata oluştu: " . $e->getMessage();
            }
        }
    }
    
    // Update menu item
    if (isset($_POST['update_menu_item'])) {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title']);
        $link = trim($_POST['link']);
        $icon = trim($_POST['icon']) ?: null;
        $iconType = trim($_POST['icon_type']) ?: null;
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $orderNum = (int)$_POST['order_num'];
        $isNew = isset($_POST['is_new']) ? 1 : 0;
        $isPopular = isset($_POST['is_popular']) ? 1 : 0;
        $isCollapsible = isset($_POST['is_collapsible']) ? 1 : 0;
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($title) || empty($link)) {
            $error = "Menü başlığı ve link alanları zorunludur.";
        } else {
            try {
                // Check if we're trying to set a menu item as its own parent or as a child of its child
                if ($parentId == $id) {
                    $error = "Bir menü öğesi kendisinin alt öğesi olamaz.";
                } else {
                    // Check if selected parent is a child of this item (to prevent circular references)
                    $isChildOfThisItem = false;
                    if ($parentId !== null) {
                        $checkStmt = $db->prepare("WITH RECURSIVE menu_tree AS (
                            SELECT id FROM mobile_menu_items WHERE id = ?
                            UNION ALL
                            SELECT m.id FROM mobile_menu_items m JOIN menu_tree mt ON m.parent_id = mt.id
                        ) SELECT COUNT(*) FROM menu_tree WHERE id = ?");
                        $checkStmt->execute([$id, $parentId]);
                        $isChildOfThisItem = $checkStmt->fetchColumn() > 0;
                    }
                    
                    if ($isChildOfThisItem) {
                        $error = "Seçilen üst menü, bu öğenin bir alt öğesidir. Döngüsel referans oluşturulamaz.";
                    } else {
                        $stmt = $db->prepare("UPDATE mobile_menu_items 
                                             SET title = ?, link = ?, icon = ?, icon_type = ?, parent_id = ?, 
                                                 order_num = ?, is_new = ?, is_popular = ?, is_collapsible = ?, active = ? 
                                             WHERE id = ?");
                        $stmt->execute([$title, $link, $icon, $iconType, $parentId, $orderNum, $isNew, $isPopular, $isCollapsible, $active, $id]);
                        
                        $message = "Mobil menü öğesi başarıyla güncellendi.";
                        
                        // Refresh menu items
                        $stmt = $db->query("SELECT m1.*, m2.title as parent_title 
                                            FROM mobile_menu_items m1 
                                            LEFT JOIN mobile_menu_items m2 ON m1.parent_id = m2.id 
                                            ORDER BY m1.parent_id, m1.order_num ASC");
                        $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Refresh parent menu items
                        $stmt = $db->query("SELECT id, title FROM mobile_menu_items WHERE parent_id IS NULL ORDER BY order_num ASC");
                        $parentMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            } catch (PDOException $e) {
                $error = "Menü öğesi güncellenirken bir hata oluştu: " . $e->getMessage();
            }
        }
    }
    
    // Delete menu item
    if (isset($_POST['delete_menu_item'])) {
        $id = (int)$_POST['id'];
        
        try {
            // First check if this item has children
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM mobile_menu_items WHERE parent_id = ?");
            $checkStmt->execute([$id]);
            $hasChildren = $checkStmt->fetchColumn() > 0;
            
            if ($hasChildren) {
                $error = "Bu menü öğesinin alt öğeleri var. Önce alt öğeleri silmelisiniz.";
            } else {
                $stmt = $db->prepare("DELETE FROM mobile_menu_items WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = "Menü öğesi başarıyla silindi.";
                
                // Refresh menu items
                $stmt = $db->query("SELECT m1.*, m2.title as parent_title 
                                    FROM mobile_menu_items m1 
                                    LEFT JOIN mobile_menu_items m2 ON m1.parent_id = m2.id 
                                    ORDER BY m1.parent_id, m1.order_num ASC");
                $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Refresh parent menu items
                $stmt = $db->query("SELECT id, title FROM mobile_menu_items WHERE parent_id IS NULL ORDER BY order_num ASC");
                $parentMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = "Menü öğesi silinirken bir hata oluştu: " . $e->getMessage();
        }
    }
    
    // Toggle active status
    if (isset($_POST['toggle_active'])) {
        $id = (int)$_POST['id'];
        $currentActive = (int)$_POST['current_active'];
        $newActive = $currentActive ? 0 : 1;
        
        try {
            $stmt = $db->prepare("UPDATE mobile_menu_items SET active = ? WHERE id = ?");
            $stmt->execute([$newActive, $id]);
            
            $message = "Menü öğesi aktiflik durumu başarıyla değiştirildi.";
            
            // Refresh menu items
            $stmt = $db->query("SELECT m1.*, m2.title as parent_title 
                                FROM mobile_menu_items m1 
                                LEFT JOIN mobile_menu_items m2 ON m1.parent_id = m2.id 
                                ORDER BY m1.parent_id, m1.order_num ASC");
            $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Menü öğesi aktiflik durumu değiştirilirken bir hata oluştu: " . $e->getMessage();
        }
    }
} 

// Set current page for sidebar
$currentPage = 'mobile_sidebar_menu_settings';

// Start output buffering
ob_start();

// Add JavaScript for menu item management
$extraScripts = <<<SCRIPTS
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

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
// Global functions for click handlers
function editMenuItem(id, title, link, icon, iconType, parentId, orderNum, isNew, isPopular, isCollapsible, active) {
    console.log("Edit item clicked:", id, title);
    
    try {
        // Populate the edit modal form
        console.log("Setting form values...");
        $('#edit_id').val(id);
        $('#edit_title').val(title);
        $('#edit_link').val(link);
        $('#edit_icon').val(icon);
        $('#edit_icon_type').val(iconType);
        $('#edit_parent_id').val(parentId);
        $('#edit_order_num').val(orderNum);
        $('#edit_is_new').prop('checked', isNew == 1);
        $('#edit_is_popular').prop('checked', isPopular == 1);
        $('#edit_is_collapsible').prop('checked', isCollapsible == 1);
        $('#edit_active').prop('checked', active == 1);
        
        // Update icon preview
        console.log("Updating icon preview...");
        updateIconPreview('#edit_icon_preview', icon, iconType);
        
        // Show the edit modal
        console.log("Opening modal...");
        $('#editMenuItemModal').modal('show');
        console.log("Modal should be open now");
    } catch (error) {
        console.error("Error in editMenuItem function:", error);
        console.error("Function parameters:", {
            id, title, link, icon, iconType, parentId, orderNum, isNew, isPopular, isCollapsible, active
        });
        console.error("Stack trace:", error.stack);
        alert("Düzenleme işlemi sırasında bir hata oluştu: " + error.message);
    }
}

function deleteMenuItem(id, title) {
    console.log("Delete item clicked:", id, title);
    
    Swal.fire({
        title: 'Emin misiniz?',
        text: title + ' menü öğesini silmek istediğinizden emin misiniz?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal',
        background: '#2a2a2a',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            // Submit form to delete menu item
            const form = $('<form></form>')
                .attr('method', 'post')
                .attr('action', 'mobile_sidebar_menu_settings.php')
                .appendTo('body');
            
            $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'delete_menu_item')
                .attr('value', '1')
                .appendTo(form);
            
            $('<input>')
                .attr('type', 'hidden')
                .attr('name', 'id')
                .attr('value', id)
                .appendTo(form);
            
            form.submit();
        }
    });
}

function toggleActive(id, currentActive) {
    console.log("Toggle active clicked:", id, currentActive);
    
    // Submit form to toggle active status
    const form = $('<form></form>')
        .attr('method', 'post')
        .attr('action', 'mobile_sidebar_menu_settings.php')
        .appendTo('body');
    
    $('<input>')
        .attr('type', 'hidden')
        .attr('name', 'toggle_active')
        .attr('value', '1')
        .appendTo(form);
    
    $('<input>')
        .attr('type', 'hidden')
        .attr('name', 'id')
        .attr('value', id)
        .appendTo(form);
    
    $('<input>')
        .attr('type', 'hidden')
        .attr('name', 'current_active')
        .attr('value', currentActive)
        .appendTo(form);
    
    form.submit();
}

$(document).ready(function() {
    // Check if jQuery is loaded
    if (typeof jQuery == 'undefined') {
        console.error("jQuery is not loaded!");
        alert("jQuery yüklenemedi! Sayfayı yenileyin veya yönetici ile iletişime geçin.");
        return;
    }
    console.log("jQuery version:", $.fn.jquery);
    
    // Initialize DataTable for menu items
    $('#menuItemsTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json'
        },
        responsive: true,
        pageLength: 15,
        order: [[2, 'asc'], [3, 'asc']], // Sort by parent_id, then order_num
        columnDefs: [
            // Center align the ID, is_new, is_popular, is_collapsible, and active columns
            { className: "text-center", targets: [0, 7, 8, 9, 10] },
        ]
    });
    
    console.log("JavaScript loaded");
    
    // Update icon preview on input change
    $('#add_icon, #add_icon_type').on('input', function() {
        updateIconPreview('#add_icon_preview', $('#add_icon').val(), $('#add_icon_type').val());
    });
    
    $('#edit_icon, #edit_icon_type').on('input', function() {
        updateIconPreview('#edit_icon_preview', $('#edit_icon').val(), $('#edit_icon_type').val());
    });
    
    // Initialize icon previews
    updateIconPreview('#add_icon_preview', $('#add_icon').val(), $('#add_icon_type').val());
    updateIconPreview('#edit_icon_preview', $('#edit_icon').val(), $('#edit_icon_type').val());
});

// Function to update icon preview
function updateIconPreview(previewSelector, icon, iconType) {
    const preview = $(previewSelector);
    preview.empty();
    
    if (!icon || !iconType) {
        preview.html('<span class="text-muted">İkon önizlemesi</span>');
        return;
    }
    
    switch(iconType) {
        case 'material-icons':
            preview.html('<i class="material-icons">' + icon + '</i>');
            break;
        case 'fa':
            preview.html('<i class="fa ' + icon + '"></i>');
            break;
        case 'pg-icons':
            preview.html('<i class="pg-' + icon + '"></i>');
            break;
        default:
            preview.html('<span class="text-muted">Bilinmeyen ikon tipi</span>');
    }
}
</script>
SCRIPTS;

// Define extra header content for layout.php
$extraHeaderContent = $extraScripts;

// Display success/error messages
if (!empty($message)) {
    echo '<div class="alert alert-success">' . $message . '</div>';
}

if (!empty($error)) {
    echo '<div class="alert alert-danger">' . $error . '</div>';
}
?>
<div class="dashboard-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-0">
                    <i class="bi bi-list-nested me-2"></i>
                    Mobil Sidebar Extra Menü Ayarları
                </h1>
                <p class="mb-0 mt-2 opacity-75">Sidebar extra menü öğelerini yönetin</p>
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
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="menuItemsTable">
                            <thead>
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="15%">Başlık</th>
                                    <th width="10%">Üst Menü</th>
                                    <th width="5%">Sıra</th>
                                    <th width="20%">Link</th>
                                    <th width="10%">İkon</th>
                                    <th width="10%">İkon Tipi</th>
                                    <th width="5%">Yeni</th>
                                    <th width="5%">Popüler</th>
                                    <th width="5%">Açılır</th>
                                    <th width="5%">Aktif</th>
                                    <th width="10%">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($menuItems)): ?>
                                    <?php foreach ($menuItems as $menuItem): ?>
                                        <tr <?php if ($menuItem['parent_id']): ?>class="child-menu-item"<?php endif; ?>>
                                            <td><?php echo $menuItem['id']; ?></td>
                                            <td><?php echo htmlspecialchars($menuItem['title']); ?></td>
                                            <td>
                                                <?php echo $menuItem['parent_id'] ? htmlspecialchars($menuItem['parent_title']) : '<span class="badge bg-primary">Ana Menü</span>'; ?>
                                            </td>
                                            <td><?php echo $menuItem['order_num']; ?></td>
                                            <td><?php echo htmlspecialchars($menuItem['link']); ?></td>
                                            <td>
                                                <?php if ($menuItem['icon'] && $menuItem['icon_type']): ?>
                                                    <div class="icon-preview">
                                                        <?php if ($menuItem['icon_type'] === 'material-icons'): ?>
                                                            <i class="material-icons"><?php echo $menuItem['icon']; ?></i>
                                                        <?php elseif ($menuItem['icon_type'] === 'fa'): ?>
                                                            <i class="fa <?php echo $menuItem['icon']; ?>"></i>
                                                        <?php elseif ($menuItem['icon_type'] === 'pg-icons'): ?>
                                                            <i class="pg-<?php echo $menuItem['icon']; ?>"></i>
                                                        <?php else: ?>
                                                            <?php echo $menuItem['icon']; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Yok</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $menuItem['icon_type'] ? htmlspecialchars($menuItem['icon_type']) : '<span class="text-muted">Yok</span>'; ?></td>
                                            <td>
                                                <?php if ($menuItem['is_new']): ?>
                                                    <span class="badge bg-success">Evet</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Hayır</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($menuItem['is_popular']): ?>
                                                    <span class="badge bg-warning">Evet</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Hayır</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($menuItem['is_collapsible']): ?>
                                                    <span class="badge bg-info">Evet</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Hayır</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm action-buttons" 
                                                        onclick="toggleActive(<?php echo $menuItem['id']; ?>, <?php echo $menuItem['active']; ?>)">
                                                    <?php if ($menuItem['active']): ?>
                                                        <i class="bi bi-toggle-on text-success fs-5"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-toggle-off text-danger fs-5"></i>
                                                    <?php endif; ?>
                                                </button>
                                            </td>
                                            <td>
                                                <div class="d-grid gap-2 action-buttons">
                                                    <button type="button" class="btn btn-primary btn-sm editBtn" onclick="editMenuItem('<?php echo $menuItem['id']; ?>', '<?php echo htmlspecialchars($menuItem['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($menuItem['link'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($menuItem['icon'], ENT_QUOTES); ?>', '<?php echo $menuItem['icon_type']; ?>', '<?php echo $menuItem['parent_id']; ?>', '<?php echo $menuItem['order_num']; ?>', '<?php echo $menuItem['is_new']; ?>', '<?php echo $menuItem['is_popular']; ?>', '<?php echo $menuItem['is_collapsible']; ?>', '<?php echo $menuItem['active']; ?>');">
                                                        <i class="bi bi-pencil-square"></i> edit
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm w-100 deleteBtn"
                                                            onclick="deleteMenuItem(<?php echo $menuItem['id']; ?>, '<?php echo addslashes(htmlspecialchars($menuItem['title'])); ?>')">
                                                        <i class="bi bi-trash-fill"></i> SİL
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="12" class="text-center">Henüz hiç menü öğesi bulunmuyor.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Menu Item Modal -->
<div class="modal fade" id="addMenuItemModal" tabindex="-1" aria-labelledby="addMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="addMenuItemModalLabel">Yeni Mobil Menü Öğesi Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="mobile_sidebar_menu_settings.php">
                    <input type="hidden" name="add_menu_item" value="1">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_title" class="form-label">Başlık</label>
                            <input type="text" class="form-control" id="add_title" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_link" class="form-label">Link</label>
                            <input type="text" class="form-control" id="add_link" name="link" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="add_icon" class="form-label">İkon</label>
                            <input type="text" class="form-control" id="add_icon" name="icon" placeholder="home, fa-rocket, vb.">
                        </div>
                        <div class="col-md-4">
                            <label for="add_icon_type" class="form-label">İkon Tipi</label>
                            <select class="form-select" id="add_icon_type" name="icon_type">
                                <option value="">Seçiniz</option>
                                <option value="material-icons">Material Icons</option>
                                <option value="fa">Font Awesome</option>
                                <option value="pg-icons">PG Icons</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">İkon Önizleme</label>
                            <div id="add_icon_preview" class="form-control d-flex align-items-center justify-content-center">
                                <span class="text-muted">İkon önizlemesi</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="add_parent_id" class="form-label">Üst Menü</label>
                            <select class="form-select" id="add_parent_id" name="parent_id">
                                <option value="">Ana Menü Öğesi</option>
                                <?php foreach ($parentMenuItems as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="add_order_num" class="form-label">Sıra Numarası</label>
                            <input type="number" class="form-control" id="add_order_num" name="order_num" value="0" min="0">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add_is_new" name="is_new">
                                <label class="form-check-label" for="add_is_new">Yeni</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add_is_popular" name="is_popular">
                                <label class="form-check-label" for="add_is_popular">Popüler</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add_is_collapsible" name="is_collapsible">
                                <label class="form-check-label" for="add_is_collapsible">Açılır Menü</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add_active" name="active" checked>
                                <label class="form-check-label" for="add_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Menu Item Modal -->
<div class="modal fade" id="editMenuItemModal" tabindex="-1" aria-labelledby="editMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="editMenuItemModalLabel">Mobil Menü Öğesini Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="mobile_sidebar_menu_settings.php">
                    <input type="hidden" name="update_menu_item" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_title" class="form-label">Başlık</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_link" class="form-label">Link</label>
                            <input type="text" class="form-control" id="edit_link" name="link" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_icon" class="form-label">İkon</label>
                            <input type="text" class="form-control" id="edit_icon" name="icon" placeholder="home, fa-rocket, vb.">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_icon_type" class="form-label">İkon Tipi</label>
                            <select class="form-select" id="edit_icon_type" name="icon_type">
                                <option value="">Seçiniz</option>
                                <option value="material-icons">Material Icons</option>
                                <option value="fa">Font Awesome</option>
                                <option value="pg-icons">PG Icons</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">İkon Önizleme</label>
                            <div id="edit_icon_preview" class="form-control d-flex align-items-center justify-content-center">
                                <span class="text-muted">İkon önizlemesi</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_parent_id" class="form-label">Üst Menü</label>
                            <select class="form-select" id="edit_parent_id" name="parent_id">
                                <option value="">Ana Menü Öğesi</option>
                                <?php foreach ($parentMenuItems as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_order_num" class="form-label">Sıra Numarası</label>
                            <input type="number" class="form-control" id="edit_order_num" name="order_num" min="0">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_is_new" name="is_new">
                                <label class="form-check-label" for="edit_is_new">Yeni</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_is_popular" name="is_popular">
                                <label class="form-check-label" for="edit_is_popular">Popüler</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_is_collapsible" name="is_collapsible">
                                <label class="form-check-label" for="edit_is_collapsible">Açılır Menü</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_active" name="active">
                                <label class="form-check-label" for="edit_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 