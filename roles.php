<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Rol Yönetimi";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Get user permissions
if (!isset($_SESSION['role_id'])) {
    // Get user's role_id if not set in session
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

$stmt = $db->prepare("SELECT * FROM admin_permissions WHERE role_id = ?");
$stmt->execute([$_SESSION['role_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert permissions to a more usable format
$userPermissions = [];
foreach ($permissions as $permission) {
    $userPermissions[$permission['menu_item']] = [
        'view' => $permission['can_view'],
        'create' => $permission['can_create'],
        'edit' => $permission['can_edit'],
        'delete' => $permission['can_delete']
    ];
}

// Check if user has permission to view roles
if (!isset($userPermissions['roles']['view']) || !$userPermissions['roles']['view']) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

// Initialize variables
$action = $_GET['action'] ?? 'list';
$roleId = $_GET['id'] ?? 0;
$success = '';
$error = '';

// Define menu items for permissions
$menuItems = [
    // Admin Ana Menüler
    'dashboard' => 'Gösterge Paneli',
    'users' => 'Admin Kullanıcılar',
    'kullanicilar' => 'Site Kullanıcıları',
    'roles' => 'Roller',
    'settings' => 'Ayarlar',
    'activity_logs' => 'Aktivite Logları',
    'transactions_history' => 'Oyun İşlem Geçmişi',
    'bonus_management' => 'Bonus Yönetimi',
    'chat' => 'Sohbet Yönetimi',
    'vip' => 'VIP Yönetimi',
    'vip_settings' => 'VIP Sayfa Ayarları',
    'vip_config' => 'VIP Ayarları',
    'tournaments' => 'Turnuvalar',
    'menu_settings' => 'Menü Ayarları',
    'password_reset_logs' => 'Şifre Sıfırlama Logları',
    // Note: 'risky_users' is kept for backward compatibility with risky_users.php permission check
    'risky_users' => 'Riskli Kullanıcılar',
    'visitor_cities' => 'Ziyaretçi Şehirleri',
    'comm100' => 'Comm100 Canlı Destek',
    'drakon_api' => 'Drakon API',
    
    // Kullanıcılar Alt Menüleri
    'site_users' => 'Site Kullanıcıları',
    // Note: 'risky_users_menu' is the current standard for the menu item in layout.php
    'risky_users_menu' => 'Riskli Kullanıcılar',
    'user_details' => 'Kullanıcı Detayları',
    'bonus_talep_loglar' => 'Bonus Talep Logları',
    'password_reset_logs_menu' => 'Şifre Değiştirme Logları',
    'user_messages' => 'Kullanıcı Mesajları',
    
    // VIP Yönetimi Alt Menüleri
    'vip_users' => 'VIP Kullanıcılar',
    'vip_levels' => 'VIP Seviyeleri',
    'vip_points' => 'VIP Puanları',
    'vip_store' => 'VIP Mağaza',
    'rakeback' => 'Rakeback',
    'rakeback_history' => 'Rakeback Geçmişi',
    'levelup_history' => 'Seviye Atlama Bonusları',
    'vip_logs' => 'VIP Kullanıcı Logları',
    'vip_cash_bonus_logs' => 'VIP Nakit Bonus Logları',
    
    // VIP Sayfa Ayarları Alt Menüleri
    'vip_advantages' => 'VIP Avantajları',
    'vip_faqs' => 'VIP SSS',
    'vip_levels_settings' => 'VIP Seviye Ayarları',
    'vip_banner_settings' => 'VIP Banner Ayarları',
    
    // VIP Config Alt Menüleri
    'rakeback_settings' => 'Rakeback Ayarları',
    'vip_cash_bonus' => 'VIP Nakit Bonus Ayarları',
    'vip_store_settings' => 'VIP Mağaza Bonus Ayarları',
    'vip_levelup_bonus_settings' => 'Seviye Atlama Bonus Ayarları',
    'vip_kazananlar' => 'VIP Kazananlar',
    
    // Chat Yönetimi Alt Menüleri
    'chat_messages' => 'Chat Mesajları',
    'chat_users' => 'Chat Kullanıcıları',
    'chat_bans' => 'Chat Ban Yönetimi',
    
    // Bonus Yönetimi Alt Menüleri
    'bonus_management_menu' => 'Bonus ve Promosyonlar',
    'promotion_menu' => 'Promosyon Menü',
    'bonus_requests' => 'Bonus Talepleri',
    
    // Turnuva Yönetimi Alt Menüleri
    'tournaments_list' => 'Tüm Turnuvalar',
    'tournaments_add' => 'Yeni Turnuva Ekle',
    'tournament_leaderboard' => 'Turnuva Sıralama',
    'tournament_participants' => 'Turnuva Katılımcıları',
    
    // Ana Menü Ayarları Alt Menüleri
    'banners' => 'Banners Slider Ayarları',
    'slider_alt_bolum' => 'Slider Alt Bölüm Düzen',
    'site_settings_menu' => 'Site Adı ve Logo Ayarları',
    'social_media_settings' => 'Sosyal Medya Ayarları',
    'page_contents' => 'Sayfa İçerikleri',
    'kazandiran_oyunlar' => 'Kazandıran Oyunlar',
    'ozelsaglayicioyun' => 'Özel Sağlayıcı Oyunları',
    'populer_slotlar' => 'Popüler Slotlar',
    'populer_saglayicilar' => 'Popüler Sağlayıcılar',
    'populer_canli_casino' => 'Popüler Canlı Casino',
    'encokkazananlar' => 'En Çok Kazananlar',
    'footer_settings' => 'Footer Ayarları',
    'footer_popular_games' => 'Footer Popüler Oyunlar',
    'footer_menu_settings' => 'Kısa Yollar',
    
    // Sidebar Menü Ayarları Alt Menüleri
    'sidebar_menu_settings' => 'Ana Sayfa Sidebar Menü',
    'financial_menu_settings' => 'Finansal İşlemler Menü',
    'sidebar_extra_settings' => 'Sidebar Extra Menü',
    'sidebar_slot_menu' => 'Oyunlar Menüsü',
    'mobile_sidebar_menu_settings' => 'Mobil Sidebar Menü'
];

// Define menu structure for grouping in the permissions table
$menuStructure = [
    'dashboard' => [
        'label' => 'Gösterge Paneli',
        'icon' => 'speedometer2',
        'is_parent' => true,
        'children' => []
    ],
    'kullanicilar' => [
        'label' => 'Site Kullanıcıları',
        'icon' => 'people-fill',
        'is_parent' => true,
        'children' => [
            'site_users', 'risky_users_menu', 'user_details', 
            'password_reset_logs_menu', 'user_messages'
        ]
    ],
    'vip' => [
        'label' => 'VIP Yönetimi',
        'icon' => 'star-fill',
        'is_parent' => true,
        'children' => [
            'vip_users', 'vip_levels', 'vip_points', 'vip_store', 'rakeback',
            'rakeback_history', 'levelup_history', 'vip_logs', 'vip_cash_bonus_logs'
        ]
    ],
    'vip_settings' => [
        'label' => 'VIP Sayfa Ayarları',
        'icon' => 'window',
        'is_parent' => true,
        'children' => [
            'vip_advantages', 'vip_faqs', 'vip_levels_settings', 'vip_banner_settings'
        ]
    ],
    'vip_config' => [
        'label' => 'VIP Ayarları',
        'icon' => 'gear-fill',
        'is_parent' => true,
        'children' => [
            'rakeback_settings', 'vip_cash_bonus', 'vip_store_settings',
            'vip_levelup_bonus_settings', 'vip_kazananlar'
        ]
    ],
    'chat' => [
        'label' => 'Chat Yönetimi',
        'icon' => 'chat-dots-fill',
        'is_parent' => true,
        'children' => [
            'chat_messages', 'chat_users', 'chat_bans'
        ]
    ],
    'visitor_cities' => [
        'label' => 'Ziyaretçi Şehirleri',
        'icon' => 'geo-alt-fill',
        'is_parent' => true,
        'children' => []
    ],
    'users' => [
        'label' => 'Admin Kullanıcılar',
        'icon' => 'people',
        'is_parent' => true,
        'children' => []
    ],
    'roles' => [
        'label' => 'Roller',
        'icon' => 'shield-lock',
        'is_parent' => true,
        'children' => []
    ],
    'settings' => [
        'label' => 'Ayarlar',
        'icon' => 'gear',
        'is_parent' => true,
        'children' => []
    ],
    'activity_logs' => [
        'label' => 'Aktivite Logları',
        'icon' => 'activity',
        'is_parent' => true,
        'children' => []
    ],
    'comm100' => [
        'label' => 'Comm100 Canlı Destek',
        'icon' => 'chat-dots',
        'is_parent' => true,
        'children' => []
    ],
    'drakon_api' => [
        'label' => 'Drakon API',
        'icon' => 'key',
        'is_parent' => true,
        'children' => []
    ],
    'menu_settings' => [
        'label' => 'Ana Menü Ayarları',
        'icon' => 'list',
        'is_parent' => true,
        'children' => [
            'banners', 'slider_alt_bolum', 'site_settings_menu', 'social_media_settings',
            'page_contents', 'kazandiran_oyunlar', 'ozelsaglayicioyun', 'populer_slotlar',
            'populer_saglayicilar', 'populer_canli_casino', 'encokkazananlar',
            'footer_settings', 'footer_popular_games', 'footer_menu_settings'
        ]
    ],
    'sidebar_settings' => [
        'label' => 'Sidebar Menü Ayarları',
        'icon' => 'menu-button-wide',
        'is_parent' => true,
        'children' => [
            'sidebar_menu_settings', 'financial_menu_settings', 
            'sidebar_extra_settings', 'sidebar_slot_menu', 'mobile_sidebar_menu_settings'
        ]
    ],
    'bonus_management' => [
        'label' => 'Bonus Yönetimi',
        'icon' => 'gift-fill',
        'is_parent' => true,
        'children' => [
            'bonus_management_menu', 'promotion_menu', 'bonus_requests', 'bonus_talep_loglar'
        ]
    ],
    'tournaments' => [
        'label' => 'Turnuvalar',
        'icon' => 'trophy',
        'is_parent' => true,
        'children' => [
            'tournaments_list', 'tournaments_add', 'tournament_leaderboard', 'tournament_participants'
        ]
    ],
    'transactions_history' => [
        'label' => 'Oyun İşlem Geçmişi',
        'icon' => 'clock-history',
        'is_parent' => true,
        'children' => []
    ]
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new role
    if (isset($_POST['add_role']) && isset($userPermissions['roles']['create']) && $userPermissions['roles']['create']) {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        // Validate input
        if (empty($name)) {
            $error = 'Rol adı gereklidir';
        } else {
            // Check if role name already exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM admin_roles WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Bu rol adı zaten mevcut';
            } else {
                // Insert new role
                $stmt = $db->prepare("INSERT INTO admin_roles (name, description) VALUES (?, ?)");
                if ($stmt->execute([$name, $description])) {
                    // Get the new role ID
                    $newRoleId = $db->lastInsertId();
                    
                    // Set default permissions
                    $defaultMenuItems = ['dashboard', 'users', 'kullanicilar', 'roles', 'activity_logs', 'settings'];

                    // For Super Admin role (usually ID=1), enable all permissions by default
                    if ($name === 'Super Admin') {
                        foreach ($defaultMenuItems as $menuItem) {
                        $stmt = $db->prepare("INSERT INTO admin_permissions (role_id, menu_item, can_view, can_create, can_edit, can_delete) 
                                                 VALUES (?, ?, 1, 1, 1, 1)");
                        $stmt->execute([$newRoleId, $menuItem]);
                        }
                    } else {
                        // For other roles, start with no permissions
                        foreach ($defaultMenuItems as $menuItem) {
                            // For site users (kullanicilar), grant view permission by default
                            $canView = ($menuItem === 'kullanicilar') ? 1 : 0;
                            $canCreate = 0;
                            $canEdit = 0;
                            $canDelete = 0;
                            
                            $stmt = $db->prepare("INSERT INTO admin_permissions (role_id, menu_item, can_view, can_create, can_edit, can_delete) 
                                                 VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$newRoleId, $menuItem, $canView, $canCreate, $canEdit, $canDelete]);
                        }
                    }
                    
                    // Log activity
                    $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Rol Eklendi', ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], "Yeni rol eklendi: $name"]);
                    
                    $success = 'Rol başarıyla eklendi';
                    $action = 'list'; // Switch back to list view
                } else {
                    $error = 'Rol eklenirken bir hata oluştu';
                }
            }
        }
    }
    
    // Edit role
    if (isset($_POST['edit_role']) && isset($userPermissions['roles']['edit']) && $userPermissions['roles']['edit']) {
        $roleId = $_POST['role_id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        // Check if trying to edit Super Admin role and user is not Super Admin
        $isSuperAdmin = ($_SESSION['role_id'] == 1);
        if ($roleId == 1 && !$isSuperAdmin) {
            $error = 'Super Admin rolünü düzenleme yetkiniz yok';
        } else {
            // Validate input
            if (empty($name)) {
                $error = 'Rol adı gereklidir';
            } else {
                // Check if role name already exists for other roles
                $stmt = $db->prepare("SELECT COUNT(*) FROM admin_roles WHERE name = ? AND id != ?");
                $stmt->execute([$name, $roleId]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Bu rol adı başka bir rol için zaten kullanılıyor';
                } else {
                    // Update role
                    $stmt = $db->prepare("UPDATE admin_roles SET name = ?, description = ? WHERE id = ?");
                    if ($stmt->execute([$name, $description, $roleId])) {
                        // Log activity
                        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, 'Rol Güncellendi', ?)");
                        $stmt->execute([$_SESSION['admin_id'], "Rol güncellendi: $name"]);
                        
                        $success = 'Rol başarıyla güncellendi';
                        $action = 'list'; // Switch back to list view
                    } else {
                        $error = 'Rol güncellenirken bir hata oluştu';
                    }
                }
            }
        }
    }
    
    // Delete role
    if (isset($_POST['delete_role']) && isset($userPermissions['roles']['delete']) && $userPermissions['roles']['delete']) {
        $roleId = $_POST['role_id'] ?? 0;
        
        // Check if trying to delete Super Admin role
        if ($roleId == 1) {
            $error = 'Super Admin rolünü silemezsiniz';
        } else {
            // Check if role is in use
            $stmt = $db->prepare("SELECT COUNT(*) FROM administrators WHERE role_id = ?");
            $stmt->execute([$roleId]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Kullanıcılara atanmış bir rolü silemezsiniz';
            } else {
                // Get role name for log
                $stmt = $db->prepare("SELECT name FROM admin_roles WHERE id = ?");
                $stmt->execute([$roleId]);
                $roleName = $stmt->fetchColumn();
                
                // Delete role permissions first
                $stmt = $db->prepare("DELETE FROM admin_permissions WHERE role_id = ?");
                $stmt->execute([$roleId]);
                
                // Delete role
                $stmt = $db->prepare("DELETE FROM admin_roles WHERE id = ?");
                if ($stmt->execute([$roleId])) {
                    // Log activity
                    $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, 'Rol Silindi', ?)");
                    $stmt->execute([$_SESSION['admin_id'], "Rol silindi: $roleName"]);
                    
                    $success = 'Rol başarıyla silindi';
                    $action = 'list'; // Switch back to list view
                } else {
                    $error = 'Rol silinirken bir hata oluştu';
                }
            }
        }
    }
    
    // Update permissions
    if (isset($_POST['update_permissions']) && isset($userPermissions['roles']['edit']) && $userPermissions['roles']['edit']) {
        $roleId = $_POST['role_id'] ?? 0;
        $permissions = $_POST['permissions'] ?? [];
        
        // Prevent updating Super Admin permissions if not Super Admin
        $isSuperAdmin = ($_SESSION['role_id'] == 1);
        if ($roleId == 1 && !$isSuperAdmin) {
            $error = 'Super Admin rolünün izinlerini düzenleme yetkiniz yok';
        } else {
            // Update permissions
            $menuItemsList = array_keys($menuItems);
            foreach ($menuItemsList as $menuItem) {
                $canView = isset($permissions[$menuItem]['view']) ? 1 : 0;
                $canCreate = isset($permissions[$menuItem]['create']) ? 1 : 0;
                $canEdit = isset($permissions[$menuItem]['edit']) ? 1 : 0;
                $canDelete = isset($permissions[$menuItem]['delete']) ? 1 : 0;
                
                // For Super Admin role, always ensure all permissions are enabled
                if ($roleId == 1) {
                    $canView = 1;
                    $canCreate = 1;
                    $canEdit = 1;
                    $canDelete = 1;
                }
                
                // Check if permission record exists
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM admin_permissions WHERE role_id = ? AND menu_item = ?");
                $checkStmt->execute([$roleId, $menuItem]);
                $permissionExists = $checkStmt->fetchColumn() > 0;
                
                if ($permissionExists) {
                    // Update existing permission
                    $stmt = $db->prepare("UPDATE admin_permissions SET can_view = ?, can_create = ?, can_edit = ?, can_delete = ? 
                                         WHERE role_id = ? AND menu_item = ?");
                    $stmt->execute([$canView, $canCreate, $canEdit, $canDelete, $roleId, $menuItem]);
                } else {
                    // Insert new permission
                    $stmt = $db->prepare("INSERT INTO admin_permissions (role_id, menu_item, can_view, can_create, can_edit, can_delete) 
                                         VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$roleId, $menuItem, $canView, $canCreate, $canEdit, $canDelete]);
                }
            }
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'İzinler Güncellendi', ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], "Rol ID: $roleId için izinler güncellendi"]);
            
            $success = 'İzinler başarıyla güncellendi';
            $action = 'list'; // Switch back to list view
        }
    }
}

// Start output buffering
ob_start();
?>

<style>
    :root {
        /* Corporate Blue Color Palette */
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --secondary-blue: #60a5fa;
        --accent-blue: #93c5fd;
        --light-blue: #dbeafe;
        --ultra-light-blue: #eff6ff;
        
        /* Corporate Gradients - Dark to Light Blue Theme */
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        --secondary-gradient: linear-gradient(135deg, #1e40af 0%, #60a5fa 100%);
        --tertiary-gradient: linear-gradient(135deg, #1e3a8a 0%, #93c5fd 100%);
        --quaternary-gradient: linear-gradient(135deg, #1e40af 0%, #dbeafe 100%);
        --light-gradient: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        --corporate-gradient: linear-gradient(135deg, #1e3a8a 0%, #60a5fa 50%, #dbeafe 100%);
        
        /* Corporate Whites and Grays */
        --white: #ffffff;
        --light-gray: #f8fafc;
        --medium-gray: #e2e8f0;
        --dark-gray: #64748b;
        --text-gray: #475569;
        
        /* Status Colors */
        --success-green: #059669;
        --error-red: #dc2626;
        --warning-orange: #d97706;
        --info-blue: var(--primary-blue-light);
        
        /* Corporate Theme */
        --bg-primary: var(--light-gray);
        --bg-secondary: var(--ultra-light-blue);
        --card-bg: var(--white);
        --card-border: var(--medium-gray);
        --text-primary: var(--text-gray);
        --text-secondary: var(--dark-gray);
        --text-heading: var(--primary-blue-dark);
        
        /* Layout */
        --border-radius: 15px;
        --border-radius-sm: 10px;
        --shadow-neon: 0 0 20px rgba(0, 255, 255, 0.3), 0 0 40px rgba(0, 255, 255, 0.1);
        --shadow-neon-pink: 0 0 20px rgba(255, 0, 128, 0.3), 0 0 40px rgba(255, 0, 128, 0.1);
        --shadow-neon-purple: 0 0 20px rgba(128, 0, 255, 0.3), 0 0 40px rgba(128, 0, 255, 0.1);
        --shadow-neon-green: 0 0 20px rgba(0, 255, 128, 0.3), 0 0 40px rgba(0, 255, 128, 0.1);
        --shadow-neon-orange: 0 0 20px rgba(255, 64, 0, 0.3), 0 0 40px rgba(255, 64, 0, 0.1);
        --shadow-neon-violet: 0 0 20px rgba(255, 0, 255, 0.3), 0 0 40px rgba(255, 0, 255, 0.1);
    }

    /* Enhanced Roles Page Custom Styles */
    .role-card {
        background: var(--card-bg);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border-radius: var(--border-radius);
        border: 2px solid transparent;
        background-clip: padding-box;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        margin-bottom: 1.5rem;
        animation: role-card-float 6s ease-in-out infinite;
    }

    /* Different role card variants */
    .role-card:nth-child(1) {
        border-color: var(--card-border);
        box-shadow: var(--shadow-neon-purple);
        animation-delay: 0s;
    }

    .role-card:nth-child(2) {
        border-color: rgba(255, 0, 128, 0.15);
        box-shadow: var(--shadow-neon-pink);
        animation-delay: 1s;
    }

    .role-card:nth-child(3) {
        border-color: rgba(0, 255, 128, 0.15);
        box-shadow: var(--shadow-neon-green);
        animation-delay: 2s;
    }

    .role-card:nth-child(4) {
        border-color: rgba(255, 64, 0, 0.15);
        box-shadow: var(--shadow-neon-orange);
        animation-delay: 3s;
    }

    .role-card:nth-child(5) {
        border-color: rgba(255, 0, 255, 0.15);
        box-shadow: var(--shadow-neon-violet);
        animation-delay: 4s;
    }

    @keyframes role-card-float {
        0%, 100% { 
            transform: translateY(0px);
            box-shadow: inherit;
        }
        25% { 
            transform: translateY(-5px);
        }
        50% { 
            transform: translateY(-8px);
            box-shadow: inherit, 0 0 30px rgba(0, 255, 255, 0.2);
        }
        75% { 
            transform: translateY(-5px);
        }
    }

    .role-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--rainbow-gradient);
        animation: rainbow-slide 3s linear infinite, neon-line-pulse 2s ease-in-out infinite alternate;
        z-index: 2;
    }

    @keyframes rainbow-slide {
        0% { 
            background-position: 0% 50%;
        }
        100% { 
            background-position: 200% 50%;
        }
    }

    @keyframes neon-line-pulse {
        0% { 
            opacity: 0.6; 
            filter: blur(0px);
        }
        100% { 
            opacity: 1; 
            filter: blur(1px);
        }
    }

    .role-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(128, 0, 255, 0.05), transparent);
        transition: left 0.5s ease;
        z-index: 1;
    }
    
    .role-card:hover {
        transform: translateY(-10px) scale(1.03);
        box-shadow: var(--shadow-neon-purple), 0 0 50px rgba(128, 0, 255, 0.4);
        border-color: var(--neon-purple);
        animation: none;
    }

    .role-card:hover::before {
        left: 100%;
    }
    
    .role-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: var(--neon-purple);
        background: rgba(0, 0, 0, 0.4);
        border: 2px solid var(--neon-purple);
        box-shadow: var(--shadow-neon-purple);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        margin-right: 15px;
        position: relative;
        z-index: 2;
        text-shadow: 0 0 10px currentColor;
    }
    
    .role-icon:hover {
        transform: scale(1.15) rotate(10deg);
        animation: neon-icon-pulse 1s ease-in-out infinite;
    }

    @keyframes neon-icon-pulse {
        0%, 100% { 
            box-shadow: var(--shadow-neon-purple);
        }
        50% { 
            box-shadow: var(--shadow-neon-purple), 0 0 30px var(--neon-purple);
        }
    }
    
    .role-info {
        padding: 1.25rem;
    }
    
    .role-name {
    font-weight: 600;
    font-size: 1.2rem;
    margin-bottom: 0.3rem;
    color: var(--text-neon);
    font-family: 'Orbitron', sans-serif;
    letter-spacing: 1px;
    position: relative;
    z-index: 2;
    color: #2d6ced;
    }
    
    .role-description {
        color: var(--text-secondary);
        font-size: 0.9rem;
        position: relative;
        z-index: 2;
    }
    
    .role-actions {
        border-top: 1px solid var(--card-border);
        padding: 1rem 1.25rem;
        background: rgba(0, 0, 0, 0.2);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        position: relative;
        z-index: 2;
        backdrop-filter: blur(10px);
    }
    
    .permissions-table {
        margin-top: 1.5rem;
        overflow: hidden;
        border-radius: var(--card-radius);
        box-shadow: var(--card-shadow);
    }
    
    .permissions-table thead th {
        background: linear-gradient(90deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.9));
        color: var(--blue-200);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
        padding: 1rem;
    }
    
    .permissions-table tbody tr {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.6), rgba(30, 41, 59, 0.4));
        transition: all 0.3s ease;
    }
    
    .permissions-table tbody tr:hover {
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.5));
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .permissions-table td {
        padding: 1rem;
        border-top: 1px solid rgba(71, 85, 105, 0.2);
    }
    
    .form-check-input {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        margin-top: 0.2rem;
        background-color: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(71, 85, 105, 0.5);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .form-check-input:checked {
        background-color: var(--blue-500);
        border-color: var(--blue-600);
        box-shadow: 0 0 0 0.15rem rgba(59, 130, 246, 0.35);
    }
    
    .form-check-input:hover {
        transform: scale(1.1);
    }
    
    .role-form {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.6), rgba(30, 41, 59, 0.4));
        border-radius: var(--card-radius);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        border-left: 4px solid var(--purple-500);
    }
    
    .role-form .form-label {
        color: var(--blue-300);
        font-weight: 600;
        margin-bottom: 0.75rem;
    }
    
    .form-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: var(--blue-200);
        display: flex;
        align-items: center;
    }
    
    .form-title i {
        margin-right: 10px;
        background: linear-gradient(135deg, var(--purple-400), var(--purple-500));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-size: 1.8rem;
    }
    
    .add-role-btn {
        background: linear-gradient(135deg, var(--purple-500), var(--purple-600));
        color: white;
        border: none;
        padding: 0.65rem 1.25rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .add-role-btn i {
        margin-right: 8px;
        font-size: 1.1rem;
    }
    
    .add-role-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
    }
    
    .action-btn {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
    }
    
    .action-btn i {
        margin-right: 6px;
        font-size: 1rem;
    }
    
    .action-btn:hover {
        transform: translateY(-3px);
    }
    
    .btn-permission {
        background: linear-gradient(135deg, var(--blue-400), var(--blue-600));
        border: none;
        color: white;
    }
    
    .btn-edit {
        background: linear-gradient(135deg, var(--blue-400), var(--blue-600));
        border: none;
        color: white;
    }
    
    .btn-delete {
        background: linear-gradient(135deg, var(--red-400), var(--red-600));
        border: none;
        color: white;
    }
    
    .page-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: var(--blue-200);
        position: relative;
        display: inline-block;
    }
    
    .page-title::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 60%;
        height: 3px;
        background: linear-gradient(90deg, var(--purple-500), transparent);
    }
    
    .menu-label {
        font-weight: 600;
        font-size: 1.1rem;
        color: var(--blue-300);
    }
    
    .animate-fade-in {
        animation: fadeIn 0.5s ease-out forwards;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<?php
// Display success/error messages
if (!empty($success)) {
    echo '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
            ' . $success . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

if (!empty($error)) {
    echo '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
            ' . $error . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

// Display different content based on action
if ($action === 'add' && isset($userPermissions['roles']['create']) && $userPermissions['roles']['create']) {
    // Display add role form
    ?>
    <div class="role-form animate-fade-in">
        <h4 class="form-title"><i class="bi bi-shield-plus"></i> Yeni Rol Ekle</h4>
        <form method="post">
            <div class="mb-3">
                        <label for="name" class="form-label">Rol Adı</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
            <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
            <div class="d-flex justify-content-end mt-4">
                <a href="roles.php" class="btn btn-secondary me-2">İptal</a>
                <button type="submit" name="add_role" class="btn btn-success">
                    <i class="bi bi-shield-plus me-1"></i> Rol Ekle
                        </button>
                </div>
            </form>
    </div>
    <?php
} elseif ($action === 'edit' && isset($userPermissions['roles']['edit']) && $userPermissions['roles']['edit']) {
    // Get role details
    $stmt = $db->prepare("SELECT * FROM admin_roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if ($role) {
        // Display edit role form
        ?>
        <div class="role-form animate-fade-in">
            <h4 class="form-title"><i class="bi bi-shield-fill-check"></i> Rol Düzenle</h4>
            <form method="post">
                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                <div class="mb-3">
                            <label for="name" class="form-label">Rol Adı</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($role['name']); ?>" required>
                        </div>
                <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($role['description']); ?></textarea>
                        </div>
                <div class="d-flex justify-content-end mt-4">
                    <a href="roles.php" class="btn btn-secondary me-2">İptal</a>
                            <button type="submit" name="edit_role" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Değişiklikleri Kaydet
                            </button>
                    </div>
                </form>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-danger">Rol bulunamadı</div>';
    }
} elseif ($action === 'permissions' && isset($userPermissions['roles']['edit']) && $userPermissions['roles']['edit']) {
    // Get role details
    $stmt = $db->prepare("SELECT * FROM admin_roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    // Check if trying to edit Super Admin role while not being Super Admin
    $isSuperAdmin = ($_SESSION['role_id'] == 1);
    if ($role['id'] == 1 && !$isSuperAdmin) {
        $error = 'Super Admin rolünün izinlerini düzenleme yetkiniz yok';
        $action = 'list';
        
        // Refresh page to show error
        echo '<script>window.location.href = "roles.php?error=' . urlencode($error) . '";</script>';
        exit();
    }
    
    if ($role) {
        // Get existing permissions
        $stmt = $db->prepare("SELECT * FROM admin_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a more usable structure
        $rolePermissions = [];
        foreach ($permissions as $permission) {
            $rolePermissions[$permission['menu_item']] = [
                'view' => $permission['can_view'],
                'create' => $permission['can_create'],
                'edit' => $permission['can_edit'],
                'delete' => $permission['can_delete']
            ];
        }
        
        // Display permissions form
        ?>
        <div class="role-form animate-fade-in">
            <h4 class="form-title"><i class="bi bi-shield-lock"></i> İzinleri Düzenle: <?php echo htmlspecialchars($role['name']); ?></h4>
            <p class="text-muted mb-4"><?php echo htmlspecialchars($role['description']); ?></p>
            
            <?php if ($role['id'] == 1): ?>
            <div class="alert alert-primary mb-4">
                <i class="bi bi-info-circle-fill me-2"></i> Super Admin rolü her zaman tüm izinlere sahiptir. Bu izinler değiştirilemez.
            </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                
                <div class="permissions-table">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <button type="button" id="toggleAllSections" class="toggle-all-btn">
                            <i class="bi bi-arrows-expand"></i> <span>Tüm Bölümleri Aç</span>
                        </button>
                        <div class="btn-group" role="group">
                            <button type="button" id="checkAllPermissions" class="btn btn-success">
                                <i class="bi bi-check-all me-1"></i> Tüm İzinleri Aç
                            </button>
                            <button type="button" id="uncheckAllPermissions" class="btn btn-danger">
                                <i class="bi bi-x-lg me-1"></i> Tüm İzinleri Kaldır
                            </button>
                        </div>
                    </div>
                    <table class="table table-borderless">
                        <thead>
                            <tr>
                                <th>Menü</th>
                                <th class="text-center">Görüntüleme</th>
                                <th class="text-center">Oluşturma</th>
                                <th class="text-center">Düzenleme</th>
                                <th class="text-center">Silme</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        // Iterate through the menu structure to display permissions in a hierarchical manner
                        foreach ($menuStructure as $menuKey => $menuItem): 
                            // Display parent menu item
                        ?>
                        <tr class="parent-menu-row">
                            <td class="menu-label">
                                <div class="d-flex align-items-center">
                                    <div class="menu-icon me-2">
                                        <i class="bi bi-<?php echo $menuItem['icon']; ?>"></i>
                                    </div>
                                    <strong><?php echo $menuItem['label']; ?></strong>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input parent-view" id="<?php echo $menuKey; ?>-view" type="checkbox" 
                                        name="permissions[<?php echo $menuKey; ?>][view]" 
                                        <?php echo (isset($rolePermissions[$menuKey]['view']) && $rolePermissions[$menuKey]['view']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $menuKey; ?>-view">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input parent-create" id="<?php echo $menuKey; ?>-create" type="checkbox" 
                                        name="permissions[<?php echo $menuKey; ?>][create]" 
                                        <?php echo (isset($rolePermissions[$menuKey]['create']) && $rolePermissions[$menuKey]['create']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $menuKey; ?>-create">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input parent-edit" id="<?php echo $menuKey; ?>-edit" type="checkbox" 
                                        name="permissions[<?php echo $menuKey; ?>][edit]" 
                                        <?php echo (isset($rolePermissions[$menuKey]['edit']) && $rolePermissions[$menuKey]['edit']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $menuKey; ?>-edit">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input parent-delete" id="<?php echo $menuKey; ?>-delete" type="checkbox" 
                                        name="permissions[<?php echo $menuKey; ?>][delete]" 
                                        <?php echo (isset($rolePermissions[$menuKey]['delete']) && $rolePermissions[$menuKey]['delete']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $menuKey; ?>-delete">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        
                        <?php 
                            // Display child menu items if any
                            if (!empty($menuItem['children'])):
                                foreach ($menuItem['children'] as $childKey):
                                    if (!isset($menuItems[$childKey])) continue; // Skip if child doesn't exist
                        ?>
                        <tr class="child-menu-row" data-parent="<?php echo $menuKey; ?>">
                            <td class="menu-label ps-5">
                                <div class="d-flex align-items-center">
                                    <div class="menu-icon-small me-2">
                                        <i class="bi bi-dash"></i>
                                    </div>
                                    <?php echo $menuItems[$childKey]; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input child-view" id="<?php echo $childKey; ?>-view" type="checkbox" 
                                        data-parent="<?php echo $menuKey; ?>"
                                        name="permissions[<?php echo $childKey; ?>][view]" 
                                        <?php echo (isset($rolePermissions[$childKey]['view']) && $rolePermissions[$childKey]['view']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $childKey; ?>-view">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input child-create" id="<?php echo $childKey; ?>-create" type="checkbox" 
                                        data-parent="<?php echo $menuKey; ?>"
                                        name="permissions[<?php echo $childKey; ?>][create]" 
                                        <?php echo (isset($rolePermissions[$childKey]['create']) && $rolePermissions[$childKey]['create']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $childKey; ?>-create">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input child-edit" id="<?php echo $childKey; ?>-edit" type="checkbox" 
                                        data-parent="<?php echo $menuKey; ?>"
                                        name="permissions[<?php echo $childKey; ?>][edit]" 
                                        <?php echo (isset($rolePermissions[$childKey]['edit']) && $rolePermissions[$childKey]['edit']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $childKey; ?>-edit">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="perm-check">
                                    <input class="perm-check-input child-delete" id="<?php echo $childKey; ?>-delete" type="checkbox" 
                                        data-parent="<?php echo $menuKey; ?>"
                                        name="permissions[<?php echo $childKey; ?>][delete]" 
                                        <?php echo (isset($rolePermissions[$childKey]['delete']) && $rolePermissions[$childKey]['delete']) ? 'checked' : ''; ?> 
                                        <?php echo ($role['id'] == 1) ? 'checked disabled' : ''; ?>>
                                    <label class="perm-check-label" for="<?php echo $childKey; ?>-delete">
                                        <span class="perm-switch"></span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <?php 
                                endforeach; 
                            endif;
                        endforeach; 
                        ?>
                        </tbody>
                    </table>
                </div>
                
                <style>
                    /* Enhanced styles for menu hierarchy */
                    .parent-menu-row {
                        background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.5)) !important;
                        margin-bottom: 6px;
                        border-radius: 8px;
                        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
                        overflow: hidden;
                    }
                    
                    .child-menu-row {
                        background: linear-gradient(145deg, rgba(15, 23, 42, 0.4), rgba(30, 41, 59, 0.2)) !important;
                        border-top: none !important;
                        border-left: 2px solid rgba(99, 102, 241, 0.4);
                        margin-left: 15px;
                        transition: all 0.3s ease;
                    }
                    
                    .menu-icon {
                        width: 38px;
                        height: 38px;
                        background: linear-gradient(135deg, var(--purple-600), var(--purple-800));
                        border-radius: 10px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 1.2rem;
                        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
                        margin-right: 12px;
                        transition: all 0.3s ease;
                    }
                    
                    .parent-menu-row:hover .menu-icon {
                        transform: scale(1.1);
                        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.35);
                    }
                    
                    .menu-icon-small {
                        width: 24px;
                        height: 24px;
                        background: linear-gradient(135deg, rgba(99, 102, 241, 0.6), rgba(99, 102, 241, 0.8));
                        border-radius: 6px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 0.9rem;
                        margin-right: 10px;
                        transition: all 0.2s ease;
                    }
                    
                    /* Enhanced collapse/expand functionality */
                    .parent-menu-row {
                        cursor: pointer;
                        position: relative;
                        transition: all 0.3s ease;
                    }
                    
                    .parent-menu-row:hover {
                        transform: translateY(-2px);
                    }
                    
                    .parent-menu-row td:first-child {
                        position: relative;
                    }
                    
                    .parent-menu-row td:first-child::after {
                        content: '\F282';
                        font-family: bootstrap-icons !important;
                        position: absolute;
                        right: 20px;
                        top: 50%;
                        transform: translateY(-50%);
                        transition: transform 0.3s ease;
                        background: rgba(99, 102, 241, 0.2);
                        width: 28px;
                        height: 28px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 0.8rem;
                        color: var(--blue-300);
                    }
                    
                    .parent-menu-row.collapsed td:first-child::after {
                        transform: translateY(-50%) rotate(-90deg);
                        background: rgba(99, 102, 241, 0.1);
                    }
                    
                    /* Enhanced switch styling */
                    .perm-check {
                        position: relative;
                        display: inline-block;
                        height: 26px;
                        width: 50px;
                    }
                    
                    .perm-check-input {
                        opacity: 0;
                        height: 0;
                        width: 0;
                        position: absolute;
                    }
                    
                    .perm-check-label {
                        position: relative;
                        display: inline-block;
                        width: 50px;
                        height: 26px;
                        margin: 0;
                        cursor: pointer;
                    }
                    
                    .perm-switch {
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: rgba(15, 23, 42, 0.6);
                        border-radius: 26px;
                        transition: 0.4s;
                        border: 1px solid rgba(71, 85, 105, 0.3);
                        box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.2);
                    }
                    
                    .perm-switch:before {
                        position: absolute;
                        content: "";
                        height: 18px;
                        width: 18px;
                        left: 3px;
                        bottom: 3px;
                        background-color: #fff;
                        border-radius: 50%;
                        transition: 0.4s;
                        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
                    }
                    
                    .perm-check-input:checked + .perm-check-label .perm-switch {
                        background: linear-gradient(135deg, var(--blue-500), var(--blue-600));
                    }
                    
                    .perm-check-input:checked + .perm-check-label .perm-switch:before {
                        transform: translateX(24px);
                    }
                    
                    .perm-check-input:focus + .perm-check-label .perm-switch {
                        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3), inset 0 2px 6px rgba(0, 0, 0, 0.2);
                    }
                    
                    /* Section styling */
                    .permissions-table {
                        margin-top: 1.5rem;
                        overflow: hidden;
                        border-radius: 12px;
                        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
                    }
                    
                    .permissions-table thead th {
                        background: linear-gradient(90deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.9));
                        color: var(--blue-200);
                        font-weight: 600;
                        text-transform: uppercase;
                        font-size: 0.8rem;
                        letter-spacing: 1px;
                        padding: 1.2rem 1rem;
                        position: sticky;
                        top: 0;
                        z-index: 10;
                    }
                    
                    /* Badges for permission info */
                    .permission-info-box {
                        background: linear-gradient(145deg, rgba(15, 23, 42, 0.6), rgba(30, 41, 59, 0.4));
                        border-radius: 10px;
                        overflow: hidden;
                        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                        border-left: 4px solid var(--blue-500);
                    }
                    
                    .permission-info-title {
                        background: linear-gradient(90deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.6));
                        padding: 0.8rem 1.2rem;
                        color: var(--blue-300);
                        font-weight: 600;
                        font-size: 1rem;
                        border-bottom: 1px solid rgba(71, 85, 105, 0.2);
                    }
                    
                    .permission-info-content {
                        padding: 1.2rem;
                    }
                    
                    .perm-info-item {
                        display: flex;
                        align-items: center;
                        margin-bottom: 0.8rem;
                    }
                    
                    .perm-badge {
                        display: inline-block;
                        font-size: 0.75rem;
                        font-weight: 600;
                        padding: 0.25rem 0.6rem;
                        border-radius: 5px;
                        margin-right: 0.8rem;
                        min-width: 80px;
                        text-align: center;
                    }
                    
                    .view-badge {
                        background: linear-gradient(135deg, var(--blue-500), var(--blue-600));
                        color: white;
                        box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
                    }
                    
                    .create-badge {
                        background: linear-gradient(135deg, var(--green-500), var(--green-600));
                        color: white;
                        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
                    }
                    
                    .edit-badge {
                        background: linear-gradient(135deg, var(--amber-500), var(--amber-600));
                        color: white;
                        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
                    }
                    
                    .delete-badge {
                        background: linear-gradient(135deg, var(--red-500), var(--red-600));
                        color: white;
                        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
                    }
                    
                    .perm-desc {
                        font-size: 0.85rem;
                        color: var(--medium-text);
                    }
                    
                    /* Toggle All Sections Button */
                    .toggle-all-btn {
                        background: linear-gradient(135deg, var(--blue-500), var(--blue-700));
                        color: white;
                        border: none;
                        padding: 0.5rem 1rem;
                        border-radius: 8px;
                        display: flex;
                        align-items: center;
                        margin-bottom: 1rem;
                        font-weight: 600;
                        transition: all 0.3s ease;
                        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
                    }
                    
                    .toggle-all-btn i {
                        margin-right: 8px;
                    }
                    
                    .toggle-all-btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
                    }
                    
                    /* Section counter badge */
                    .section-counter {
                        position: absolute;
                        right: 60px;
                        top: 50%;
                        transform: translateY(-50%);
                        background: rgba(99, 102, 241, 0.2);
                        color: var(--blue-300);
                        border-radius: 12px;
                        padding: 0.2rem 0.5rem;
                        font-size: 0.75rem;
                        font-weight: 600;
                    }
                </style>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Check All Permissions Button
                        document.getElementById('checkAllPermissions').addEventListener('click', function() {
                            document.querySelectorAll('.perm-check-input').forEach(checkbox => {
                                if (!checkbox.disabled) {
                                    checkbox.checked = true;
                                }
                            });
                        });
                        
                        // Uncheck All Permissions Button
                        document.getElementById('uncheckAllPermissions').addEventListener('click', function() {
                            document.querySelectorAll('.perm-check-input').forEach(checkbox => {
                                if (!checkbox.disabled) {
                                    checkbox.checked = false;
                                }
                            });
                        });
                        
                        // Collapse all parent menu rows by default
                        document.querySelectorAll('.parent-menu-row').forEach(row => {
                            row.classList.add('collapsed');
                        });
                        
                        // Hide all child rows by default
                        document.querySelectorAll('.child-menu-row').forEach(childRow => {
                            childRow.style.display = 'none';
                        });
                        
                        // Add child item counters to parent rows
                        document.querySelectorAll('.parent-menu-row').forEach(row => {
                            const menuKey = row.querySelector('.perm-check-input').id.split('-')[0];
                            const childRows = document.querySelectorAll(`.child-menu-row[data-parent="${menuKey}"]`);
                            
                            if (childRows.length > 0) {
                                const counterSpan = document.createElement('span');
                                counterSpan.className = 'section-counter';
                                counterSpan.textContent = childRows.length + ' öğe';
                                row.querySelector('td:first-child').appendChild(counterSpan);
                            }
                        });
                        
                        // Toggle All Sections button
                        const toggleAllBtn = document.getElementById('toggleAllSections');
                        let allExpanded = false;
                        
                        toggleAllBtn.addEventListener('click', function() {
                            allExpanded = !allExpanded;
                            
                            document.querySelectorAll('.parent-menu-row').forEach(row => {
                                const menuKey = row.querySelector('.perm-check-input').id.split('-')[0];
                                const childRows = document.querySelectorAll(`.child-menu-row[data-parent="${menuKey}"]`);
                                
                                if (allExpanded) {
                                    row.classList.remove('collapsed');
                                    childRows.forEach(childRow => childRow.style.display = '');
                                } else {
                                    row.classList.add('collapsed');
                                    childRows.forEach(childRow => childRow.style.display = 'none');
                                }
                            });
                            
                            // Update button text
                            this.querySelector('span').textContent = allExpanded ? 'Tüm Bölümleri Kapat' : 'Tüm Bölümleri Aç';
                            this.querySelector('i').className = allExpanded ? 'bi bi-arrows-collapse' : 'bi bi-arrows-expand';
                        });
                        
                        // Parent row click to toggle children
                        document.querySelectorAll('.parent-menu-row').forEach(row => {
                            row.addEventListener('click', function(e) {
                                if (e.target.tagName === 'INPUT' || e.target.closest('.perm-check')) return; // Don't toggle when clicking checkboxes
                                
                                const menuKey = this.querySelector('.perm-check-input').id.split('-')[0];
                                const childRows = document.querySelectorAll(`.child-menu-row[data-parent="${menuKey}"]`);
                                
                                this.classList.toggle('collapsed');
                                childRows.forEach(childRow => {
                                    childRow.style.display = this.classList.contains('collapsed') ? 'none' : '';
                                });
                            });
                        });
                        
                        // Auto-expand menus that have checked child items
                        document.querySelectorAll('.parent-menu-row').forEach(row => {
                            const menuKey = row.querySelector('.perm-check-input').id.split('-')[0];
                            const childRows = document.querySelectorAll(`.child-menu-row[data-parent="${menuKey}"]`);
                            let hasCheckedChild = false;
                            
                            childRows.forEach(childRow => {
                                const childCheckboxes = childRow.querySelectorAll('.perm-check-input:checked');
                                if (childCheckboxes.length > 0) {
                                    hasCheckedChild = true;
                                }
                            });
                            
                            // If any child is checked, expand the parent
                            if (hasCheckedChild) {
                                row.classList.remove('collapsed');
                                childRows.forEach(childRow => {
                                    childRow.style.display = '';
                                });
                            }
                        });
                        
                        // When saving permissions for risky_users, also save for risky_users_menu and vice versa
                        document.querySelector('form').addEventListener('submit', function(e) {
                            // Check if risky_users is checked
                            const riskyUsersView = document.getElementById('risky_users-view');
                            const riskyUsersMenuView = document.getElementById('risky_users_menu-view');
                            
                            if (riskyUsersView && riskyUsersMenuView) {
                                // Sync the view permission
                                if (riskyUsersView.checked) riskyUsersMenuView.checked = true;
                                if (riskyUsersMenuView.checked) riskyUsersView.checked = true;
                                
                                // Sync the create permission
                                const riskyUsersCreate = document.getElementById('risky_users-create');
                                const riskyUsersMenuCreate = document.getElementById('risky_users_menu-create');
                                if (riskyUsersCreate && riskyUsersMenuCreate) {
                                    if (riskyUsersCreate.checked) riskyUsersMenuCreate.checked = true;
                                    if (riskyUsersMenuCreate.checked) riskyUsersCreate.checked = true;
                                }
                                
                                // Sync the edit permission
                                const riskyUsersEdit = document.getElementById('risky_users-edit');
                                const riskyUsersMenuEdit = document.getElementById('risky_users_menu-edit');
                                if (riskyUsersEdit && riskyUsersMenuEdit) {
                                    if (riskyUsersEdit.checked) riskyUsersMenuEdit.checked = true;
                                    if (riskyUsersMenuEdit.checked) riskyUsersEdit.checked = true;
                                }
                                
                                // Sync the delete permission
                                const riskyUsersDelete = document.getElementById('risky_users-delete');
                                const riskyUsersMenuDelete = document.getElementById('risky_users_menu-delete');
                                if (riskyUsersDelete && riskyUsersMenuDelete) {
                                    if (riskyUsersDelete.checked) riskyUsersMenuDelete.checked = true;
                                    if (riskyUsersMenuDelete.checked) riskyUsersDelete.checked = true;
                                }
                            }
                        });
                        
                        // Parent checkbox controls all children
                        document.querySelectorAll('.parent-view, .parent-create, .parent-edit, .parent-delete').forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                const menuKey = this.id.split('-')[0];
                                const permType = this.id.split('-')[1]; // view, create, edit, delete
                                
                                document.querySelectorAll(`.child-${permType}[data-parent="${menuKey}"]`).forEach(childCheckbox => {
                                    if (!childCheckbox.disabled) {
                                        childCheckbox.checked = this.checked;
                                    }
                                });
                            });
                        });
                        
                        // When all children are checked/unchecked, update parent
                        document.querySelectorAll('.child-view, .child-create, .child-edit, .child-delete').forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                const parentKey = this.getAttribute('data-parent');
                                const permType = this.id.split('-')[1]; // view, create, edit, delete
                                
                                const allChildCheckboxes = document.querySelectorAll(`.child-${permType}[data-parent="${parentKey}"]`);
                                const checkedChildCheckboxes = document.querySelectorAll(`.child-${permType}[data-parent="${parentKey}"]:checked`);
                                
                                const parentCheckbox = document.querySelector(`#${parentKey}-${permType}`);
                                if (!parentCheckbox.disabled) {
                                    // If all children are checked, check parent, otherwise uncheck it
                                    parentCheckbox.checked = (allChildCheckboxes.length === checkedChildCheckboxes.length);
                                }
                                
                                // If parent view is unchecked, uncheck all operation checkboxes for children
                                if (permType === 'view' && !this.checked) {
                                    document.querySelectorAll(`#${this.id.split('-')[0]}-create, #${this.id.split('-')[0]}-edit, #${this.id.split('-')[0]}-delete`).forEach(opCheckbox => {
                                        if (!opCheckbox.disabled) {
                                            opCheckbox.checked = false;
                                        }
                                    });
                                }
                            });
                        });
                        
                        // If "view" is unchecked, disable/uncheck other operations
                        document.querySelectorAll('.perm-check-input[id$="-view"]').forEach(viewCheckbox => {
                            viewCheckbox.addEventListener('change', function() {
                                const baseId = this.id.replace('-view', '');
                                const createCheckbox = document.getElementById(`${baseId}-create`);
                                const editCheckbox = document.getElementById(`${baseId}-edit`);
                                const deleteCheckbox = document.getElementById(`${baseId}-delete`);
                                
                                if (!this.checked) {
                                    [createCheckbox, editCheckbox, deleteCheckbox].forEach(checkbox => {
                                        if (checkbox && !checkbox.disabled) {
                                            checkbox.checked = false;
                                        }
                                    });
                                }
                            });
                        });
                        
                        // Apply subtle animations to checkboxes
                        document.querySelectorAll('.perm-check-input').forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                const label = this.nextElementSibling;
                                label.style.transition = 'transform 0.2s ease';
                                label.style.transform = 'scale(1.1)';
                                
                                setTimeout(() => {
                                    label.style.transform = 'scale(1)';
                                }, 200);
                            });
                        });
                    });
                </script>
                
                <div class="permission-info-box mt-4">
                    <div class="permission-info-title">
                        <i class="bi bi-info-circle-fill me-2"></i> İzin Açıklamaları
                    </div>
                    <div class="permission-info-content">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="perm-info-item">
                                    <span class="perm-badge view-badge">Görüntüleme</span>
                                    <span class="perm-desc">Kullanıcı bu bölümü görüntüleyebilir</span>
                                </div>
                                <div class="perm-info-item">
                                    <span class="perm-badge create-badge">Oluşturma</span>
                                    <span class="perm-desc">Kullanıcı bu bölümde yeni kayıt oluşturabilir</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="perm-info-item">
                                    <span class="perm-badge edit-badge">Düzenleme</span>
                                    <span class="perm-desc">Kullanıcı bu bölümdeki kayıtları düzenleyebilir</span>
                                </div>
                                <div class="perm-info-item">
                                    <span class="perm-badge delete-badge">Silme</span>
                                    <span class="perm-desc">Kullanıcı bu bölümden kayıt silebilir</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mt-4">
                    <a href="roles.php" class="btn btn-secondary me-2">
                        <i class="bi bi-x-circle me-1"></i> İptal
                    </a>
                    <?php if ($role['id'] != 1 || $isSuperAdmin): ?>
                    <button type="submit" name="update_permissions" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> İzinleri Kaydet
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-danger">Rol bulunamadı</div>';
    }
} else {
    // Display list of roles
    $stmt = $db->prepare("SELECT * FROM admin_roles ORDER BY name");
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
    // Check if any roles are in use
    $roleUsage = [];
    foreach ($roles as $role) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM administrators WHERE role_id = ?");
        $stmt->execute([$role['id']]);
        $roleUsage[$role['id']] = $stmt->fetchColumn();
    }
    ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
        <h1 class="page-title">Rol Yönetimi</h1>
            <?php if (isset($userPermissions['roles']['create']) && $userPermissions['roles']['create']): ?>
        <a href="roles.php?action=add" class="add-role-btn">
            <i class="bi bi-shield-plus"></i> Yeni Rol
            </a>
            <?php endif; ?>
        </div>
    
    <div class="row">
        <?php
        $index = 0;
        foreach ($roles as $role):
            $index++;
            $isSuperAdminRole = ($role['id'] == 1);
            $isSuperAdmin = ($_SESSION['role_id'] == 1);
        ?>
        <div class="col-lg-6 mb-4">
            <div class="role-card animate-fade-in" style="animation-delay: <?php echo $index * 0.1; ?>s <?php echo $isSuperAdminRole ? '; border-left: 4px solid var(--gold-500)' : ''; ?>">
                <div class="role-info d-flex">
                    <div class="role-icon" <?php echo $isSuperAdminRole ? 'style="background: linear-gradient(135deg, var(--amber-500), var(--amber-700));"' : ''; ?>>
                        <i class="bi bi-shield"></i>
                    </div>
                    <div>
                        <div class="role-name"><?php echo htmlspecialchars($role['name']); ?> 
                            <?php if ($isSuperAdminRole): ?>
                                <span class="badge bg-warning text-dark ms-2">Super Admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="role-description"><?php echo htmlspecialchars($role['description'] ?: 'Açıklama yok'); ?></div>
                        <div class="mt-2 text-light">
                            <small>
                                <i class="bi bi-people me-1"></i> 
                                <?php echo $roleUsage[$role['id']]; ?> kullanıcı bu role sahip
                            </small>
                        </div>
                    </div>
                </div>
                <div class="role-actions">
                    <?php if ((isset($userPermissions['roles']['edit']) && $userPermissions['roles']['edit']) && (!$isSuperAdminRole || $isSuperAdmin)): ?>
                    <a href="roles.php?action=permissions&id=<?php echo $role['id']; ?>" class="action-btn btn-permission">
                        <i class="bi bi-shield-lock"></i> İzinler
                    </a>
                                    
                    <a href="roles.php?action=edit&id=<?php echo $role['id']; ?>" class="action-btn btn-edit">
                        <i class="bi bi-pencil-square"></i> Düzenle
                    </a>
                    <?php endif; ?>
                                    
                    <?php if ((isset($userPermissions['roles']['delete']) && $userPermissions['roles']['delete']) && $roleUsage[$role['id']] === 0 && !$isSuperAdminRole): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Bu rolü silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                        <button type="submit" name="delete_role" class="action-btn btn-delete">
                            <i class="bi bi-trash"></i> Sil
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animation for elements
        const animateElements = document.querySelectorAll('.animate-fade-in');
        animateElements.forEach((element, index) => {
            const delay = parseFloat(element.style.animationDelay || '0s');
            element.style.opacity = '0';
            setTimeout(() => {
                element.style.opacity = '1';
            }, (delay * 1000) + 100);
        });
    });
    </script>
    <?php
}

$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 