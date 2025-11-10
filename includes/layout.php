<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fix for tournament pages using $content instead of $pageContent
if (isset($content) && !isset($pageContent)) {
    $pageContent = $content;
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get system settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
$stmt->execute();
$siteSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get session timeout from settings or use default (30 minutes)
$sessionTimeout = isset($siteSettings['session_timeout']) ? (int)$siteSettings['session_timeout'] * 60 : 1800; // Convert minutes to seconds

// TEMPORARY: Session timeout check disabled
/*
// Check if session has timed out
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Session has expired, destroy session and redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
*/

// Update last activity time
$_SESSION['last_activity'] = time();

// Get site title from settings or use default
$siteTitle = isset($siteSettings['site_title']) ? $siteSettings['site_title'] : 'Yönetici Paneli';

// Get user information
$stmt = $db->prepare("SELECT a.*, r.name as role_name FROM administrators a 
                      JOIN admin_roles r ON a.role_id = r.id 
                      WHERE a.id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();

// Ensure role_id is set in session
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != $user['role_id']) {
    $_SESSION['role_id'] = $user['role_id'];
}

// Check if user has 2FA enabled, and if so, check if verification is complete
if (!empty($user['secret_key']) && $user['secret_key'] !== 'yok') {
    // User has 2FA enabled, check if verified
    if (!isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true) {
        // Only redirect to 2fa.php if we're not already on that page
        if (basename($_SERVER['PHP_SELF']) !== '2fa.php') {
            header("Location: 2fa.php");
            exit();
        }
    }
} else {
    // User does not have 2FA enabled, mark as verified
    $_SESSION['2fa_verified'] = true;
}

// Get user permissions
$stmt = $db->prepare("SELECT * FROM admin_permissions WHERE role_id = ?");
$stmt->execute([$user['role_id']]);
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

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Define page groups
$adminPages = ['users', 'roles', 'settings', 'activity_logs', 'profile'];
$userPages = ['site_users', 'user_details', '2fa', 'profile', 'password_reset_logs'];
$chatPages = ['chat_users', 'chat_messages', 'chat_bans'];
$bonusPages = ['bonus_management', 'promotion_menu', 'bonus_requests', 'bonus_talep_loglar'];
$vipSettingsPages = ['vip_advantages', 'vip_faqs', 'vip_levels_settings', 'vip_banner_settings']; // Remove rakeback_settings
$vipConfigPages = ['rakeback_settings', 'vip_cash_bonus', 'vip_store_settings', 'vip_levelup_bonus_settings', 'vip_kazananlar']; // Add vip_kazananlar
$turnuvaPages = ['tournaments', 'tournaments_add', 'tournament_edit', 'tournament_details', 'tournament_games', 'tournament_leaderboard', 'tournament_participants'];
$transactionPages = ['transactions_history'];
$menuSettingsPages = ['banners', 'slider_alt_bolum', 'site_settings_menu', 'social_media_settings', 'page_contents', 'kazandiran_oyunlar', 'ozelsaglayicioyun', 'populer_slotlar', 'populer_saglayicilar', 'populer_canli_casino', 'encokkazananlar', 'footer_settings', 'footer_popular_games', 'oyunlar_menu_yonetimi']; // Added oyunlar_menu_yonetimi
$bahisPages = ['bahis_approval']; // Bahis yönetimi sayfası tanımı

// Check section permissions
$isAdminMenuActive = false; // Changed from: in_array($currentPage, $adminPages);
$isUserMenuActive = false; // Changed from: in_array($currentPage, $userPages);
$isChatMenuActive = false; // Changed from: in_array($currentPage, $chatPages);
$isBonusMenuActive = false; // Changed from: in_array($currentPage, $bonusPages);
$isVipSettingsMenuActive = false; // Changed from: in_array($currentPage, $vipSettingsPages);
$isVipConfigMenuActive = false; // Changed from: in_array($currentPage, $vipConfigPages);
$isTurnuvaMenuActive = false; // Changed from: in_array($currentPage, $turnuvaPages);
$isTransactionMenuActive = false; // Changed from: in_array($currentPage, $transactionPages);
$isMenuSettingsActive = false; // Changed from: in_array($currentPage, $menuSettingsPages);
$isBahisMenuActive = in_array($currentPage, $bahisPages); // Bahis yönetimi sayfası aktiflik kontrolü

// Check if user is admin
$isAdmin = ($_SESSION['role_id'] == 1);

// Expand permissions checks for all sections
$hasAdminSectionPermission = $isAdmin || 
    ($userPermissions['users']['view'] ?? false) || 
    ($userPermissions['roles']['view'] ?? false) || 
    ($userPermissions['settings']['view'] ?? false) || 
    ($userPermissions['activity_logs']['view'] ?? false);

$hasUserSectionPermission = $isAdmin || 
    ($userPermissions['kullanicilar']['view'] ?? false) || 
    ($userPermissions['user_details']['view'] ?? false) || 
    ($userPermissions['2fa']['view'] ?? false) ||
    ($userPermissions['password_reset_logs']['view'] ?? false);

$hasChatSectionPermission = $isAdmin || ($userPermissions['chat']['view'] ?? false);
$hasBonusSectionPermission = $isAdmin || ($userPermissions['bonus_management']['view'] ?? false);
$hasTurnuvaSectionPermission = $isAdmin || ($userPermissions['tournaments']['view'] ?? false);
$hasVisitorCitiesPermission = $isAdmin || ($userPermissions['visitor_cities']['view'] ?? false);
$hasVipConfigPermission = $isAdmin || ($userPermissions['vip_config']['view'] ?? false);
$hasComm100Permission = $isAdmin || ($userPermissions['comm100']['view'] ?? false);
$hasDrakonApiPermission = $isAdmin || ($userPermissions['drakon_api']['view'] ?? false);
$hasXPaySettingsPermission = $isAdmin || ($userPermissions['xpay_settings']['view'] ?? false);
$hasSettingsPermission = $isAdmin || ($userPermissions['settings']['view'] ?? false);
$hasActivityLogsPermission = $isAdmin || ($userPermissions['activity_logs']['view'] ?? false);
$hasUsersPermission = $isAdmin || ($userPermissions['users']['view'] ?? false);
$hasRolesPermission = $isAdmin || ($userPermissions['roles']['view'] ?? false);
$hasBahisSectionPermission = $isAdmin || ($userPermissions['bahis']['view'] ?? false); // Bahis bölümü izni kontrolü

// Define helper variables for each sub-menu permission
$hasVipUsersPermission = $isAdmin || ($userPermissions['vip_users']['view'] ?? false);
$hasVipLevelsPermission = $isAdmin || ($userPermissions['vip_levels']['view'] ?? false);
$hasVipPointsPermission = $isAdmin || ($userPermissions['vip_points']['view'] ?? false);
$hasVipStorePermission = $isAdmin || ($userPermissions['vip_store']['view'] ?? false);
$hasRakebackPermission = $isAdmin || ($userPermissions['rakeback']['view'] ?? false);
$hasRakebackHistoryPermission = $isAdmin || ($userPermissions['rakeback_history']['view'] ?? false);
$hasLevelupHistoryPermission = $isAdmin || ($userPermissions['levelup_history']['view'] ?? false);
$hasVipLogsPermission = $isAdmin || ($userPermissions['vip_logs']['view'] ?? false);
$hasVipCashBonusLogsPermission = $isAdmin || ($userPermissions['vip_cash_bonus_logs']['view'] ?? false);

// VIP Sayfa Ayarları alt menü yetkileri
$hasVipAdvantagesPermission = $isAdmin || ($userPermissions['vip_advantages']['view'] ?? false);
$hasVipFaqsPermission = $isAdmin || ($userPermissions['vip_faqs']['view'] ?? false);
$hasVipLevelsSettingsPermission = $isAdmin || ($userPermissions['vip_levels_settings']['view'] ?? false);
$hasVipBannerSettingsPermission = $isAdmin || ($userPermissions['vip_banner_settings']['view'] ?? false);

// VIP Config alt menü yetkileri
$hasRakebackSettingsPermission = $isAdmin || ($userPermissions['rakeback_settings']['view'] ?? false);
$hasVipCashBonusPermission = $isAdmin || ($userPermissions['vip_cash_bonus']['view'] ?? false);
$hasVipStoreSettingsPermission = $isAdmin || ($userPermissions['vip_store_settings']['view'] ?? false);
$hasVipLevelupBonusSettingsPermission = $isAdmin || ($userPermissions['vip_levelup_bonus_settings']['view'] ?? false);
$hasVipKazananlarPermission = $isAdmin || ($userPermissions['vip_kazananlar']['view'] ?? false);

// Define helper variables for Chat sub-menu permissions
$hasChatMessagesPermission = $isAdmin || ($userPermissions['chat_messages']['view'] ?? false);
$hasChatUsersPermission = $isAdmin || ($userPermissions['chat_users']['view'] ?? false);
$hasChatBansPermission = $isAdmin || ($userPermissions['chat_bans']['view'] ?? false);

// Define helper variables for Bonus sub-menu permissions
$hasBonusManagementMenuPermission = $isAdmin || ($userPermissions['bonus_management_menu']['view'] ?? false);
$hasPromotionMenuPermission = $isAdmin || ($userPermissions['promotion_menu']['view'] ?? false);
$hasBonusRequestsPermission = $isAdmin || ($userPermissions['bonus_requests']['view'] ?? false);

// Define helper variables for Tournament sub-menu permissions
$hasTournamentsListPermission = $isAdmin || ($userPermissions['tournaments_list']['view'] ?? false);
$hasTournamentsAddPermission = $isAdmin || ($userPermissions['tournaments_add']['view'] ?? false);
$hasTournamentLeaderboardPermission = $isAdmin || ($userPermissions['tournament_leaderboard']['view'] ?? false);
$hasTournamentParticipantsPermission = $isAdmin || ($userPermissions['tournament_participants']['view'] ?? false);

// Define helper variables for Site Users sub-menu permissions
$hasSiteUsersPermission = $isAdmin || ($userPermissions['site_users']['view'] ?? false);
$hasRiskyUsersMenuPermission = $isAdmin || ($userPermissions['risky_users_menu']['view'] ?? false) || ($userPermissions['risky_users']['view'] ?? false);
$hasUserDetailsPermission = $isAdmin || ($userPermissions['user_details']['view'] ?? false);
$hasBonusTalepLoglarPermission = $isAdmin || ($userPermissions['bonus_talep_loglar']['view'] ?? false);
$hasPasswordResetLogsMenuPermission = $isAdmin || ($userPermissions['password_reset_logs_menu']['view'] ?? false);
$hasUserMessagesPermission = $isAdmin || ($userPermissions['user_messages']['view'] ?? false);

// Define helper variables for Menu Settings sub-menu permissions
$hasBannersPermission = $isAdmin || ($userPermissions['banners']['view'] ?? false);
$hasSliderAltBolumPermission = $isAdmin || ($userPermissions['slider_alt_bolum']['view'] ?? false);
$hasSiteSettingsMenuPermission = $isAdmin || ($userPermissions['site_settings_menu']['view'] ?? false);
$hasSocialMediaSettingsPermission = $isAdmin || ($userPermissions['social_media_settings']['view'] ?? false);
$hasPageContentsPermission = $isAdmin || ($userPermissions['page_contents']['view'] ?? false);
$hasKazandiranOyunlarPermission = $isAdmin || ($userPermissions['kazandiran_oyunlar']['view'] ?? false);
$hasOzelSaglayiciOyunPermission = $isAdmin || ($userPermissions['ozelsaglayicioyun']['view'] ?? false);
$hasPopulerSlotlarPermission = $isAdmin || ($userPermissions['populer_slotlar']['view'] ?? false);
$hasPopulerSaglayicilarPermission = $isAdmin || ($userPermissions['populer_saglayicilar']['view'] ?? false);
$hasPopulerCanliCasinoPermission = $isAdmin || ($userPermissions['populer_canli_casino']['view'] ?? false);
$hasEnCokKazananlarPermission = $isAdmin || ($userPermissions['encokkazananlar']['view'] ?? false);
$hasFooterSettingsPermission = $isAdmin || ($userPermissions['footer_settings']['view'] ?? false);
$hasFooterPopularGamesPermission = $isAdmin || ($userPermissions['footer_popular_games']['view'] ?? false);
$hasOyunlarMenuYonetimiPermission = $isAdmin || ($userPermissions['oyunlar_menu_yonetimi']['view'] ?? false);

// Define helper variables for Sidebar Menu Settings sub-menu permissions
$hasSidebarMenuSettingsPermission = $isAdmin || ($userPermissions['sidebar_menu_settings']['view'] ?? false);
$hasFinancialMenuSettingsPermission = $isAdmin || ($userPermissions['financial_menu_settings']['view'] ?? false);
$hasSidebarExtraSettingsPermission = $isAdmin || ($userPermissions['sidebar_extra_settings']['view'] ?? false);
$hasSidebarSlotMenuPermission = $isAdmin || ($userPermissions['sidebar_slot_menu']['view'] ?? false);
$hasMobileSidebarMenuSettingsPermission = $isAdmin || ($userPermissions['mobile_sidebar_menu_settings']['view'] ?? false);

// Add permission variable for footer menu settings
$hasFooterMenuSettingsPermission = $isAdmin || ($userPermissions['footer_menu_settings']['view'] ?? false);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?><?php echo isset($pageTitle) ? ' - ' . htmlspecialchars($pageTitle) : ''; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/themes.css">
    <link rel="stylesheet" href="assets/css/dynamic-theme.css">
    <?php if (isset($extraHeaderContent)) echo $extraHeaderContent; ?>
    <style>
        :root {
            /* Corporate Color Palette */
            --primary-blue: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #3b82f6;
            --secondary-blue: #60a5fa;
            --accent-blue: #2563eb;
            
            /* Professional Grays */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Status Colors */
            --success-green: #059669;
            --warning-amber: #d97706;
            --danger-red: #dc2626;
            --info-blue: #0891b2;
            
            /* Corporate Gradients */
            --primary-gradient: linear-gradient(135deg, #1e40af, #3b82f6);
            --secondary-gradient: linear-gradient(135deg, #60a5fa, #93c5fd);
            --success-gradient: linear-gradient(135deg, #059669, #10b981);
            --warning-gradient: linear-gradient(135deg, #d97706, #f59e0b);
            --danger-gradient: linear-gradient(135deg, #dc2626, #ef4444);
            --info-gradient: linear-gradient(135deg, #0891b2, #06b6d4);
            
            /* Layout Variables */
            --sidebar-width: 250px;
            --header-height: 60px;
            --border-radius: 6px;
            --border-radius-sm: 4px;
            --border-radius-lg: 8px;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            --shadow-md: 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 4px 8px -2px rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
            --shadow-2xl: 0 12px 24px -6px rgba(0, 0, 0, 0.15);
            
            /* Transitions */
            --transition-fast: 0.15s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
            
            /* Typography */
            --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --font-size-xs: 0.7rem;
            --font-size-sm: 0.8rem;
            --font-size-base: 0.9rem;
            --font-size-lg: 1rem;
            --font-size-xl: 1.1rem;
            --font-size-2xl: 1.3rem;
            --font-size-3xl: 1.5rem;
            --font-size-4xl: 1.8rem;
            
            /* Spacing */
            --spacing-1: 0.2rem;
            --spacing-2: 0.4rem;
            --spacing-3: 0.6rem;
            --spacing-4: 0.8rem;
            --spacing-5: 1rem;
            --spacing-6: 1.2rem;
            --spacing-8: 1.6rem;
            --spacing-10: 2rem;
            --spacing-12: 2.4rem;
            --spacing-16: 3.2rem;
            --spacing-20: 4rem;
        }

        body {
            background: #f5f5f5;
            color: var(--gray-800);
            font-family: var(--font-family);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            line-height: 1.5;
            position: relative;
            font-size: var(--font-size-base);
            font-weight: 400;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }

        /* Corporate Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: #ffffff;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: var(--shadow-md);
            z-index: 1050;
            transition: all var(--transition-normal) ease;
            flex-shrink: 0;
            border-right: 1px solid var(--gray-300);
        }

        .sidebar .logo-container {
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid var(--gray-300);
            background: var(--primary-blue);
            position: relative;
            box-shadow: var(--shadow-sm);
        }
        
        .sidebar .logo-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.15), transparent);
            transform: translateX(-100%);
            animation: shimmer 4s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }

        .sidebar .logo {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            z-index: 2;
            font-family: var(--font-family);
        }

        .sidebar .menu {
            padding: var(--spacing-4) 0;
        }

        .sidebar .menu-item {
            padding: var(--spacing-3) var(--spacing-5);
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            color: var(--gray-700);
            text-decoration: none;
            transition: all var(--transition-fast) ease;
            border-left: 2px solid transparent;
            margin: var(--spacing-1) var(--spacing-2);
            position: relative;
            border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;
            font-weight: 500;
            font-size: var(--font-size-sm);
        }
        
        .sidebar .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, var(--primary-blue), transparent);
            opacity: 0;
            transition: opacity var(--transition-normal) ease;
            z-index: -1;
        }
        
        .sidebar .menu-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary-gradient);
            transform: translateX(-100%);
            transition: transform var(--transition-normal) ease;
        }

        .sidebar .menu-item:hover,
        .sidebar .menu-item.active {
            background: rgba(30, 64, 175, 0.08);
            color: var(--primary-blue);
            border-left-color: var(--primary-blue);
        }
        
        .sidebar .menu-item:hover::before,
        .sidebar .menu-item.active::before {
            opacity: 0.1;
        }
        
        .sidebar .menu-item:hover::after,
        .sidebar .menu-item.active::after {
            transform: translateX(0);
        }

        .sidebar .menu-item.active {
            background-color: rgba(30, 64, 175, 0.2);
            font-weight: 600;
            color: var(--primary-blue);
        }

        .sidebar .menu-item.active i {
            color: var(--primary-blue);
        }

        .sidebar .menu-item i {
            font-size: var(--font-size-lg);
            width: 24px;
            text-align: center;
            transition: all var(--transition-fast) ease;
        }
        
        .sidebar .menu-item:hover i {
            color: var(--primary-blue);
        }

        /* Corporate Header Styles */
        .header {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--header-height);
            background: #ffffff;
            z-index: 1020;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 var(--spacing-6);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast) ease;
            border-bottom: 1px solid var(--gray-300);
        }

        .header .logo {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--primary-blue);
            letter-spacing: 0.3px;
            font-family: var(--font-family);
        }

        .header .user-menu .dropdown-toggle {
            color: var(--gray-800);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-2) var(--spacing-4);
            border-radius: var(--border-radius-sm);
            transition: all var(--transition-fast) ease;
            background: var(--gray-100);
            border: 1px solid var(--gray-300);
            font-weight: 500;
            font-size: var(--font-size-sm);
        }

        .header .user-menu .dropdown-toggle::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left var(--transition-slow) ease;
        }

        .header .user-menu .dropdown-toggle:hover::before {
            left: 100%;
        }

        .header .user-menu .dropdown-toggle:hover {
            background: var(--gray-200);
            color: var(--primary-blue);
            border-color: var(--gray-400);
        }

        .header .user-menu .dropdown-menu {
            background-color: #ffffff;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-1);
            margin-top: var(--spacing-1);
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header .user-menu .dropdown-item {
            color: var(--gray-700);
            padding: var(--spacing-2) var(--spacing-3);
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            transition: all var(--transition-fast) ease;
            font-size: var(--font-size-sm);
        }

        .header .user-menu .dropdown-item:hover {
            background-color: var(--gray-100);
            color: var(--primary-blue);
        }
        
        .header .user-menu .dropdown-item i {
            font-size: var(--font-size-base);
            color: var(--primary-blue);
            transition: transform var(--transition-fast) ease;
        }
        
        .header .user-menu .dropdown-item:hover i {
            transform: scale(1.2);
        }

        .header .user-menu .dropdown-divider {
            border-color: var(--gray-200);
            margin: var(--spacing-2) 0;
            opacity: 0.5;
        }

        /* Header Center Section */
        .header-center {
            display: flex;
            align-items: center;
            gap: var(--spacing-8);
            flex: 1;
            justify-content: center;
        }

        .live-clock {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            background: var(--gray-100);
            padding: var(--spacing-2) var(--spacing-4);
            border-radius: 20px;
            border: 1px solid var(--gray-300);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast) ease;
            position: relative;
        }

        .live-clock::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left var(--transition-slow) ease;
        }

        .live-clock:hover::before {
            left: 100%;
        }

        .live-clock:hover {
            background: var(--gray-200);
            border-color: var(--gray-400);
        }

        .live-clock i {
            color: var(--primary-blue);
            font-size: var(--font-size-base);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .live-clock #currentTime {
            font-weight: 600;
            color: var(--gray-800);
            font-size: var(--font-size-sm);
            font-family: 'Courier New', monospace;
        }

        .live-clock #currentDate {
            color: var(--gray-600);
            font-size: var(--font-size-xs);
            font-weight: 500;
        }

        .theme-features-link {
            display: flex;
            align-items: center;
        }

        .theme-features-link .btn {
            border-radius: 25px;
            padding: var(--spacing-3) var(--spacing-6);
            transition: all var(--transition-normal) ease;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            background: linear-gradient(135deg, transparent, rgba(30, 64, 175, 0.05));
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .theme-features-link .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left var(--transition-slow) ease;
        }

        .theme-features-link .btn:hover::before {
            left: 100%;
        }

        .theme-features-link .btn:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        @media (max-width: 768px) {
            .header-center {
                gap: 1rem;
            }
            
            .live-clock {
                padding: 0.4rem 0.8rem;
            }
            
            .live-clock #currentDate {
                display: none;
            }
        }

        /* Content wrapper to properly position content */
        .content-wrapper {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            width: calc(100% - var(--sidebar-width));
            position: relative;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            transition: margin-left var(--transition-normal) ease, width var(--transition-normal) ease;
        }

        /* Main Content Styles */
        .main-content {
            padding: 0;
            min-height: calc(100vh - var(--header-height));
            margin-top: var(--header-height);
            background: transparent;
            position: relative;
            width: 100%;
            flex-grow: 1;
            overflow-x: hidden;
            animation: fadeIn 0.5s ease;
        }

        /* Advanced Page Header */
        .page-header {
            background: #ffffff;
            border-bottom: 1px solid var(--gray-300);
            padding: var(--spacing-4) var(--spacing-6);
            margin-bottom: var(--spacing-6);
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
        }

        .page-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--spacing-4);
        }

        .page-title-section {
            flex: 1;
        }

        .page-title {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 var(--spacing-1) 0;
            font-family: var(--font-family);
        }

        .page-subtitle {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        .page-subtitle i {
            color: var(--primary-blue);
            font-size: var(--font-size-xs);
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
        }

        .quick-stats {
            display: flex;
            gap: var(--spacing-4);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-2) var(--spacing-4);
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            transition: all var(--transition-normal) ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--primary-blue);
        }

        .stat-item i {
            color: var(--primary-blue);
        }

        /* Content Area */
        .content-area {
            padding: 0 var(--spacing-6) var(--spacing-6) var(--spacing-6);
            min-height: calc(100vh - var(--header-height) - 100px);
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1rem;
            }
            
            .page-header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .content-area {
                padding: 0 1rem 1rem 1rem;
            }
            
            .quick-stats {
                width: 100%;
                justify-content: space-between;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Fix for content overlay issues */
        .main-content > * {
            position: relative;
            z-index: 10;
            width: 100%;
        }

        /* Card Styles */
        .card {
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-fast) ease;
            background: #ffffff;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            position: relative;
            margin-bottom: var(--spacing-5);
        }
        
        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-gradient);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-300);
            padding: var(--spacing-4) var(--spacing-5);
            position: relative;
        }
        
        .card-header i {
            margin-right: 10px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: var(--font-size-lg);
        }
        
        .card-body {
            padding: var(--spacing-5);
        }

        /* Form Styles */
        .form-control, .form-select {
            background-color: #ffffff;
            border: 1px solid var(--gray-400);
            color: var(--gray-800);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-2) var(--spacing-3);
            transition: all var(--transition-fast);
            font-size: var(--font-size-sm);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1);
            outline: none;
        }
        
        .form-label {
            color: var(--gray-800);
            font-weight: 500;
            margin-bottom: var(--spacing-3);
            font-size: var(--font-size-sm);
        }

        /* Button Styles */
        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            padding: var(--spacing-2) var(--spacing-4);
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm);
            font-size: var(--font-size-sm);
            border: none;
            cursor: pointer;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all var(--transition-slow) ease;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-gradient);
            color: white;
        }
        
        .btn-info {
            background: var(--info-gradient);
            color: white;
        }

        /* Table Styles */
        .table {
            color: var(--gray-800);
            margin-bottom: 0;
            background: #ffffff;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius-sm);
            overflow: hidden;
        }

        .table thead th {
            background: var(--gray-100);
            color: var(--gray-700);
            font-weight: 600;
            border-bottom: 1px solid var(--gray-300);
            padding: var(--spacing-3) var(--spacing-4);
            text-transform: uppercase;
            font-size: var(--font-size-xs);
            letter-spacing: 0.3px;
        }

        .table-hover tbody tr {
            transition: all var(--transition-fast);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table-hover tbody tr:hover {
            background-color: var(--gray-50);
        }

        /* Badge styles */
        .badge {
            padding: var(--spacing-2) var(--spacing-3);
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-sm);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-xs);
        }
        
        .badge:hover {
            box-shadow: var(--shadow-md);
        }
        
        .badge.bg-success {
            background: var(--success-gradient) !important;
            color: white;
        }
        
        .badge.bg-primary {
            background: var(--primary-gradient) !important;
            color: white;
        }
        
        .badge.bg-danger {
            background: var(--danger-gradient) !important;
            color: white;
        }
        
        .badge.bg-warning {
            background: var(--warning-gradient) !important;
            color: white;
        }
        
        .badge.bg-info {
            background: var(--info-gradient) !important;
            color: white;
        }
        
        .badge.bg-purple {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6) !important;
            color: white;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #6b7280, #9ca3af) !important;
            color: white;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1060;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .header {
                left: 0;
                width: 100%;
                padding: 0 var(--spacing-3);
            }
            
            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }
            
            .toggle-sidebar {
                display: block !important;
            }
            
            .header-center {
                gap: var(--spacing-4);
            }
            
            .live-clock {
                padding: var(--spacing-2) var(--spacing-4);
            }
            
            .live-clock #currentDate {
                display: none;
            }
            
            .theme-features-link .btn span {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 0 var(--spacing-3);
            }
            
            .header .logo {
                font-size: var(--font-size-xl);
            }
            
            .header-center {
                gap: var(--spacing-2);
            }
            
            .live-clock {
                padding: var(--spacing-2) var(--spacing-3);
                font-size: var(--font-size-sm);
            }
            
            .live-clock i {
                font-size: var(--font-size-base);
            }
            
            .live-clock #currentTime {
                font-size: var(--font-size-base);
            }
            
            .user-menu .dropdown-toggle {
                padding: var(--spacing-2) var(--spacing-4);
                font-size: var(--font-size-sm);
            }
            
            .page-header {
                padding: var(--spacing-4);
            }
            
            .page-title {
                font-size: var(--font-size-2xl);
            }
            
            .content-area {
                padding: 0 var(--spacing-3) var(--spacing-4) var(--spacing-3);
            }
            
            .quick-stats {
                flex-direction: column;
                gap: var(--spacing-2);
            }
            
            .stat-item {
                padding: var(--spacing-2) var(--spacing-3);
                font-size: var(--font-size-sm);
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 0 var(--spacing-2);
            }
            
            .header .logo {
                font-size: var(--font-size-lg);
            }
            
            .header-center {
                gap: var(--spacing-1);
            }
            
            .live-clock {
                padding: var(--spacing-1) var(--spacing-2);
                font-size: var(--font-size-xs);
            }
            
            .live-clock i {
                font-size: var(--font-size-sm);
            }
            
            .live-clock #currentTime {
                font-size: var(--font-size-sm);
            }
            
            .user-menu .dropdown-toggle {
                padding: var(--spacing-2) var(--spacing-3);
                font-size: var(--font-size-xs);
            }
            
            .user-menu .dropdown-toggle i {
                font-size: var(--font-size-base);
            }
            
            .page-header {
                padding: var(--spacing-3);
            }
            
            .page-title {
                font-size: var(--font-size-xl);
            }
            
            .page-subtitle {
                font-size: var(--font-size-sm);
            }
            
            .content-area {
                padding: 0 var(--spacing-2) var(--spacing-3) var(--spacing-2);
            }
            
            .quick-stats {
                gap: var(--spacing-1);
            }
            
            .stat-item {
                padding: var(--spacing-1) var(--spacing-2);
                font-size: var(--font-size-xs);
            }
            
            .stat-item i {
                font-size: var(--font-size-sm);
            }
        }

        .toggle-sidebar {
            display: none;
            background: none;
            border: none;
            color: var(--gray-700);
            font-size: var(--font-size-2xl);
            cursor: pointer;
            padding: var(--spacing-2);
            border-radius: var(--border-radius-sm);
            transition: all var(--transition-normal) ease;
            position: relative;
            overflow: hidden;
        }

        .toggle-sidebar:hover {
            background-color: rgba(30, 64, 175, 0.1);
            color: var(--primary-blue);
            transform: rotate(90deg);
        }
        
        /* Admin Menu Styles */
        .admin-menu-toggle {
            margin-bottom: var(--spacing-2);
        }

        .admin-menu-toggle .menu-item {
            padding: var(--spacing-3) var(--spacing-6);
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            color: var(--gray-600);
            text-decoration: none;
            transition: all var(--transition-normal) ease;
            border-left: 3px solid transparent;
            margin: var(--spacing-1) 0;
            position: relative;
            overflow: hidden;
        }

        .admin-menu-toggle .menu-item:hover {
            background-color: rgba(30, 64, 175, 0.1);
            color: var(--gray-800);
            border-left-color: var(--primary-blue);
        }

        .admin-menu-toggle .menu-item.active {
            background-color: rgba(30, 64, 175, 0.15);
            color: var(--primary-blue);
            border-left-color: var(--primary-blue);
            font-weight: 500;
        }

        .admin-menu-toggle .menu-item i.bi-chevron-down {
            transition: transform var(--transition-normal) ease;
        }

        .admin-menu-toggle .menu-item[aria-expanded="true"] i.bi-chevron-down {
            transform: rotate(180deg);
        }

        .collapse {
            transition: all var(--transition-normal) ease;
        }

        .ps-3 {
            padding-left: var(--spacing-8) !important;
        }

        .ps-3 .menu-item {
            padding: var(--spacing-3) var(--spacing-6);
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            color: var(--gray-600);
            text-decoration: none;
            transition: all var(--transition-normal) ease;
            border-left: 3px solid transparent;
            margin: var(--spacing-1) 0;
            position: relative;
            overflow: hidden;
            padding-left: var(--spacing-10);
        }

        .ps-3 .menu-item:hover {
            background-color: rgba(30, 64, 175, 0.1);
            color: var(--gray-800);
            border-left-color: var(--primary-blue);
        }

        .ps-3 .menu-item.active {
            background-color: rgba(30, 64, 175, 0.15);
            color: var(--primary-blue);
            border-left-color: var(--primary-blue);
            font-weight: 500;
        }

        .ps-3 .menu-item i {
            font-size: var(--font-size-base);
            width: 20px;
            text-align: center;
        }

        /* Alert styles */
        .alert {
            border-radius: var(--border-radius);
            padding: var(--spacing-4) var(--spacing-5);
            border: 1px solid transparent;
            margin-bottom: var(--spacing-6);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-normal) ease;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 4px;
            background: currentColor;
            opacity: 0.5;
        }
        
        .alert-dark {
            background-color: rgba(31, 41, 55, 0.1);
            border-color: rgba(31, 41, 55, 0.2);
            color: var(--gray-800);
        }
        
        .alert-info {
            background-color: rgba(8, 145, 178, 0.1);
            border-color: rgba(8, 145, 178, 0.2);
            color: var(--info-blue);
        }
        
        .alert:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Dropdown animations */
        .dropdown-menu.show {
            animation: fadeInUp 0.3s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Tooltip styling */
        .tooltip {
            --bs-tooltip-bg: var(--gray-700);
            --bs-tooltip-color: white;
        }
        
        /* Custom scrollbar for specific elements */
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: var(--gray-300) var(--gray-100);
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: var(--gray-300);
            border-radius: 6px;
        }
        
        /* Sidebar Tema Değiştirici */
        .sidebar-theme-switcher {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin: 1rem;
        }

        .theme-switcher-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .theme-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }

        .theme-option {
            background: none;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .theme-option:hover {
            transform: scale(1.05);
            border-color: var(--accent-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .theme-option.active {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px var(--accent-color), 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .theme-preview {
            width: 100%;
            height: 25px;
            border-radius: 4px;
            transition: all 0.3s ease;
            position: relative;
        }

        .theme-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            border-radius: inherit;
        }

        /* Tema Önizleme Renkleri */
        .theme-preview.default {
            background: linear-gradient(45deg, #ffffff, #f8f9fa);
        }

        .theme-preview.blue {
            background: linear-gradient(45deg, #e3f2fd, #2196f3);
        }

        .theme-preview.green {
            background: linear-gradient(45deg, #e8f5e8, #4caf50);
        }

        .theme-preview.purple {
            background: linear-gradient(45deg, #f3e5f5, #9c27b0);
        }

        .theme-preview.orange {
            background: linear-gradient(45deg, #fff3e0, #ff9800);
        }

        .theme-preview.red {
            background: linear-gradient(45deg, #ffebee, #f44336);
        }

        .theme-preview.pink {
            background: linear-gradient(45deg, #fce4ec, #e91e63);
        }

        .theme-preview.teal {
            background: linear-gradient(45deg, #e0f2f1, #009688);
        }

        .theme-preview.indigo {
            background: linear-gradient(45deg, #e8eaf6, #3f51b5);
        }

        .theme-preview.amber {
            background: linear-gradient(45deg, #fff8e1, #ffc107);
        }

        .theme-preview.cyan {
            background: linear-gradient(45deg, #e0f7fa, #00bcd4);
        }

        /* Tema Etiketleri */
        .theme-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 0.25rem;
            font-weight: 500;
        }

        /* Responsive Tasarım */
        @media (max-width: 768px) {
            .theme-options {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .theme-preview {
                height: 20px;
            }
            
            .theme-label {
                font-size: 0.65rem;
            }
        }

        @media (max-width: 480px) {
            .theme-options {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .theme-preview {
                height: 18px;
            }
            
            .theme-label {
                font-size: 0.6rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar container -->
    <aside class="sidebar">
        <div class="logo-container">
            <div class="logo">Yönetici Paneli</div>
        </div>
        <div class="menu">
            <a href="index.php" class="menu-item <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Gösterge Paneli
            </a>
            
            <!-- Admin Kontrol Yönetimi -->
            <?php if ($hasAdminSectionPermission): ?>
            <div class="admin-menu-toggle">
                <a href="#adminMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" aria-expanded="false">
                    <div>
                        <i class="bi bi-shield-lock"></i> Admin Kontrol Yönetimi
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
            </div>
            
            <div class="collapse" id="adminMenu">
                <div class="ps-3">
                    <?php if ($hasUsersPermission): ?>
                    <a href="users.php" class="menu-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                        <i class="bi bi-people"></i> Admin Kullanıcılar
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($hasRolesPermission): ?>
                    <a href="roles.php" class="menu-item <?php echo $currentPage === 'roles' ? 'active' : ''; ?>">
                        <i class="bi bi-shield-lock"></i> Roller
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($hasSettingsPermission): ?>
                    <a href="settings.php" class="menu-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                        <i class="bi bi-gear"></i> Ayarlar
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($hasSettingsPermission): ?>
                    <a href="smtp_settings.php" class="menu-item <?php echo $currentPage === 'smtp_settings' ? 'active' : ''; ?>">
                        <i class="bi bi-envelope"></i> SMTP Ayarları
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($hasActivityLogsPermission): ?>
                    <a href="activity_logs.php" class="menu-item <?php echo $currentPage === 'activity_logs' ? 'active' : ''; ?>">
                        <i class="bi bi-activity"></i> Son Aktiviteler
                    </a>
                    <?php endif; ?>
                    
                    <a href="profile.php" class="menu-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                        <i class="bi bi-person-circle"></i> Profil
                    </a>
                </div>
            </div>
            <?php else: ?>
                <?php if ($hasSettingsPermission): ?>
                <a href="settings.php" class="menu-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i> Ayarlar
                </a>
                <?php endif; ?>
                
                <a href="profile.php" class="menu-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <i class="bi bi-person-circle"></i> Profil
                </a>
            <?php endif; ?>
            
            <!-- Kullanıcılar Yönetimi -->
            <?php if ($hasUserSectionPermission): ?>
            <div class="admin-menu-toggle">
                <a href="#kullanicilarMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="kullanicilarMenu">
                    <div>
                        <i class="bi bi-people-fill"></i> Kullanıcılar
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="kullanicilarMenu">
                    <div class="ps-3">
                        <?php if ($hasSiteUsersPermission): ?>
                        <a href="site_users.php" class="menu-item <?php echo $currentPage === 'site_users' ? 'active' : ''; ?>">
                            <i class="bi bi-person-fill"></i> Site Kullanıcıları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasRiskyUsersMenuPermission): ?>
                        <a href="risky_users.php" class="menu-item <?php echo $currentPage === 'risky_users' ? 'active' : ''; ?>">
                            <i class="bi bi-exclamation-triangle-fill"></i> Riskli Kullanıcılar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasPasswordResetLogsMenuPermission): ?>
                        <a href="password_reset_logs.php" class="menu-item <?php echo $currentPage === 'password_reset_logs' ? 'active' : ''; ?>">
                            <i class="bi bi-key-fill"></i> Şifre Değiştirme Logları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasUserMessagesPermission): ?>
                        <a href="user_messages.php" class="menu-item <?php echo $currentPage === 'user_messages' ? 'active' : ''; ?>">
                            <i class="bi bi-envelope"></i> Kullanıcı Mesajları
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- VIP Sayfa Ayarları -->
            <?php if (isset($userPermissions['vip_settings']['view']) && $userPermissions['vip_settings']['view'] || $isAdmin): ?>
            <div class="admin-menu-toggle">
                <a href="#vipSettingsMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="vipSettingsMenu">
                    <div>
                        <i class="bi bi-window"></i> VIP Sayfa Ayarları
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="vipSettingsMenu">
                    <div class="ps-3">
                        <?php if ($hasVipAdvantagesPermission): ?>
                        <a href="vip_advantages.php" class="menu-item <?php echo $currentPage === 'vip_advantages' ? 'active' : ''; ?>">
                            <i class="bi bi-star-half"></i> VIP Avantajları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipFaqsPermission): ?>
                        <a href="vip_faqs.php" class="menu-item <?php echo $currentPage === 'vip_faqs' ? 'active' : ''; ?>">
                            <i class="bi bi-question-circle"></i> VIP SSS
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipLevelsSettingsPermission): ?>
                        <a href="vip_levels_settings.php" class="menu-item <?php echo $currentPage === 'vip_levels_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-trophy"></i> VIP Seviye Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipBannerSettingsPermission): ?>
                        <a href="vip_banner_settings.php" class="menu-item <?php echo $currentPage === 'vip_banner_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-image"></i> VIP Banner Ayarları
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Bahis Yönetimi -->
            <?php if ($hasBahisSectionPermission || $isAdmin): ?>
            <a href="bahis_approval.php" class="menu-item <?php echo $currentPage === 'bahis_approval' ? 'active' : ''; ?>">
                <i class="bi bi-dice-6"></i> Bahis Yönetimi
            </a>
            <?php endif; ?>

            <!-- Comm100 Canlı Destek Ayarları -->
            <?php if ($hasComm100Permission): ?>
            <a href="comm100_settings.php" class="menu-item <?php echo $currentPage === 'comm100_settings' ? 'active' : ''; ?>">
                <i class="bi bi-chat-dots"></i> Comm100 Canlı Destek
            </a>
            <?php endif; ?>
            
            <!-- Financial Transactions -->
            <?php if ($isAdmin || (isset($userPermissions['financial_transactions']['view']) && $userPermissions['financial_transactions']['view'])): ?>
            <a href="financial_transactions.php" class="menu-item <?php echo $currentPage === 'financial_transactions' ? 'active' : ''; ?>">
                <i class="bi bi-cash-coin"></i> Finansal İşlemler
            </a>
            <?php endif; ?>
            
            <!-- Transactions History -->
            <?php if ($isAdmin || (isset($userPermissions['transactions_history']['view']) && $userPermissions['transactions_history']['view'])): ?>
            <a href="transactions_history.php" class="menu-item <?php echo $currentPage === 'transactions_history' ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i> Oyun İşlem Geçmişi
            </a>
            <?php endif; ?>
            
            <!-- VIP Kullanıcılar Yönetimi -->
            <?php if (isset($userPermissions['vip']['view']) && $userPermissions['vip']['view'] || $isAdmin): ?>
            <div class="admin-menu-toggle">
                <a href="#vipMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="vipMenu">
                    <div>
                        <i class="bi bi-star-fill"></i> VIP Yönetimi
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="vipMenu">
                    <div class="ps-3">
                        <?php if ($hasVipUsersPermission): ?>
                        <a href="vip_users.php" class="menu-item <?php echo $currentPage === 'vip_users' ? 'active' : ''; ?>">
                            <i class="bi bi-person-badge"></i> VIP Kullanıcılar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipPointsPermission): ?>
                        <a href="vip_points.php" class="menu-item <?php echo $currentPage === 'vip_points' ? 'active' : ''; ?>">
                            <i class="bi bi-coin"></i> VIP Puanları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipStorePermission): ?>
                        <a href="vip_store.php" class="menu-item <?php echo $currentPage === 'vip_store' ? 'active' : ''; ?>">
                            <i class="bi bi-shop"></i> VIP Mağaza
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasRakebackPermission): ?>
                        <a href="rakeback.php" class="menu-item <?php echo $currentPage === 'rakeback' ? 'active' : ''; ?>">
                            <i class="bi bi-arrow-repeat"></i> Rakeback
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasRakebackHistoryPermission): ?>
                        <a href="rakeback_history.php" class="menu-item <?php echo $currentPage === 'rakeback_history' ? 'active' : ''; ?>">
                            <i class="bi bi-clock-history"></i> Rakeback Geçmişi
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasLevelupHistoryPermission): ?>
                        <a href="levelup_history.php" class="menu-item <?php echo $currentPage === 'levelup_history' ? 'active' : ''; ?>">
                            <i class="bi bi-graph-up-arrow"></i> Seviye Atlama Bonusları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipLogsPermission): ?>
                        <a href="vip_logs.php" class="menu-item <?php echo $currentPage === 'vip_logs' ? 'active' : ''; ?>">
                            <i class="bi bi-journal-text"></i> VIP Kullanıcı Logları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipCashBonusLogsPermission): ?>
                        <a href="vip_cash_bonus_logs.php" class="menu-item <?php echo $currentPage === 'vip_cash_bonus_logs' ? 'active' : ''; ?>">
                            <i class="bi bi-cash"></i> VIP Nakit Bonus Logları
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- VIP Ayarları -->
            <?php if ($hasVipConfigPermission): ?>
            <div class="admin-menu-toggle">
                <a href="#vipConfigMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="vipConfigMenu">
                    <div>
                        <i class="bi bi-gear-fill"></i> VIP Ayarları
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="vipConfigMenu">
                    <div class="ps-3">
                        <?php if ($hasRakebackSettingsPermission): ?>
                        <a href="rakeback_settings.php" class="menu-item <?php echo $currentPage === 'rakeback_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-arrow-repeat"></i> Rakeback Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipCashBonusPermission): ?>
                        <a href="vip_cash_bonus.php" class="menu-item <?php echo $currentPage === 'vip_cash_bonus' ? 'active' : ''; ?>">
                            <i class="bi bi-cash-coin"></i> VIP Nakit Bonus Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipStoreSettingsPermission): ?>
                        <a href="vip_store_settings.php" class="menu-item <?php echo $currentPage === 'vip_store_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-shop-window"></i> VIP Mağaza Bonus Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipLevelupBonusSettingsPermission): ?>
                        <a href="vip_levelup_bonus_settings.php" class="menu-item <?php echo $currentPage === 'vip_levelup_bonus_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-arrow-up-circle"></i> Seviye Atlama Bonus Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasVipKazananlarPermission): ?>
                        <a href="vip_kazananlar.php" class="menu-item <?php echo $currentPage === 'vip_kazananlar' ? 'active' : ''; ?>">
                            <i class="bi bi-trophy-fill"></i> VIP Kazananlar
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Chat Yönetimi -->
            <?php if ($hasChatSectionPermission): ?>
            <div class="admin-menu-toggle">
                <a href="#chatMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="chatMenu">
                    <div>
                        <i class="bi bi-chat-dots-fill"></i> Chat Yönetimi
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="chatMenu">
                    <div class="ps-3">
                        <?php if ($hasChatMessagesPermission): ?>
                        <a href="chat_messages.php" class="menu-item <?php echo $currentPage === 'chat_messages' ? 'active' : ''; ?>">
                            <i class="bi bi-chat-text"></i> Mesajlar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasChatUsersPermission): ?>
                        <a href="chat_users.php" class="menu-item <?php echo $currentPage === 'chat_users' ? 'active' : ''; ?>">
                            <i class="bi bi-person-badge"></i> Chat Kullanıcıları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasChatBansPermission): ?>
                        <a href="chat_bans.php" class="menu-item <?php echo $currentPage === 'chat_bans' ? 'active' : ''; ?>">
                            <i class="bi bi-slash-circle"></i> Ban Yönetimi
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Ziyaretçi Şehirleri -->
            <?php if ($hasVisitorCitiesPermission): ?>
            <a href="visitor_cities.php" class="menu-item <?php echo $currentPage === 'visitor_cities' ? 'active' : ''; ?>">
                <i class="bi bi-geo-alt-fill"></i> Ziyaretçi Şehirleri
            </a>
            <?php endif; ?>
            
            <!-- Bonus Yönetimi -->
            <?php if ($hasBonusSectionPermission): ?>
            <div class="admin-menu-toggle">
                <a href="#bonusMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="bonusMenu">
                    <div>
                        <i class="bi bi-gift-fill"></i> Bonus Yönetimi
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="bonusMenu">
                    <div class="ps-3">
                        <?php if ($hasBonusManagementMenuPermission): ?>
                        <a href="bonus_management.php" class="menu-item <?php echo $currentPage === 'bonus_management' ? 'active' : ''; ?>">
                            <i class="bi bi-gift"></i> Bonus ve Promosyonlar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasPromotionMenuPermission): ?>
                        <a href="promotion_menu.php" class="menu-item <?php echo $currentPage === 'promotion_menu' ? 'active' : ''; ?>">
                            <i class="bi bi-list"></i> Promosyon Menü
                        </a>
                        <?php endif; ?>
                        

                        
                        <?php if ($isAdmin || (isset($userPermissions['bonus_talep_loglar']['view']) && $userPermissions['bonus_talep_loglar']['view'])): ?>
                        <a href="bonus_talep_loglar.php" class="menu-item <?php echo $currentPage === 'bonus_talep_loglar' ? 'active' : ''; ?>">
                            <i class="bi bi-journal-text"></i> Bonus Talep Logları
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Turnuva Yönetimi -->
            <?php if ($hasTurnuvaSectionPermission): ?>
            <div class="admin-menu-toggle">
                <a href="#turnuvaMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="turnuvaMenu">
                    <div>
                        <i class="bi bi-trophy-fill"></i> Turnuva Yönetimi
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="turnuvaMenu">
                    <div class="ps-3">
                        <?php if ($hasTournamentsListPermission): ?>
                        <a href="tournaments.php" class="menu-item <?php echo $currentPage === 'tournaments' ? 'active' : ''; ?>">
                            <i class="bi bi-list-task"></i> Tüm Turnuvalar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasTournamentsAddPermission): ?>
                        <a href="tournaments_add.php" class="menu-item <?php echo $currentPage === 'tournaments_add' ? 'active' : ''; ?>">
                            <i class="bi bi-plus-circle"></i> Yeni Turnuva Ekle
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasTournamentLeaderboardPermission): ?>
                        <a href="tournament_leaderboard.php" class="menu-item <?php echo $currentPage === 'tournament_leaderboard' ? 'active' : ''; ?>">
                            <i class="bi bi-list-ol"></i> Sıralama
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasTournamentParticipantsPermission): ?>
                        <a href="tournament_participants.php" class="menu-item <?php echo $currentPage === 'tournament_participants' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i> Katılımcılar
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Ana Menü Ayarları -->
            <?php if ($isAdmin || (isset($userPermissions['menu_settings']['view']) && $userPermissions['menu_settings']['view'])): ?>
            <div class="admin-menu-toggle">
                <a href="#menuSettingsMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="menuSettingsMenu">
                    <div>
                        <i class="bi bi-list"></i> Ana Menü Ayarları
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="menuSettingsMenu">
                    <div class="ps-3">
                        <?php if ($hasBannersPermission): ?>
                        <a href="banners.php" class="menu-item <?php echo $currentPage === 'banners' ? 'active' : ''; ?>">
                            <i class="bi bi-images"></i> Banners Slider Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasSliderAltBolumPermission): ?>
                        <a href="slider_alt_bolum.php" class="menu-item <?php echo $currentPage === 'slider_alt_bolum' ? 'active' : ''; ?>">
                            <i class="bi bi-image"></i> Slider Alt Bölüm Düzen
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasSiteSettingsMenuPermission): ?>
                        <a href="site_settings_menu.php" class="menu-item <?php echo $currentPage === 'site_settings_menu' ? 'active' : ''; ?>">
                            <i class="bi bi-gear-wide-connected"></i> Site Adı ve Logo Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasSocialMediaSettingsPermission): ?>
                        <a href="social_media_settings.php" class="menu-item <?php echo $currentPage === 'social_media_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-share"></i> Sosyal Medya Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasPageContentsPermission): ?>
                        <a href="page_contents.php" class="menu-item <?php echo $currentPage === 'page_contents' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i> Sayfa İçerikleri
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasKazandiranOyunlarPermission): ?>
                        <a href="kazandiran_oyunlar.php" class="menu-item <?php echo $currentPage === 'kazandiran_oyunlar' ? 'active' : ''; ?>">
                            <i class="bi bi-controller"></i> Kazandıran Oyunlar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasOzelSaglayiciOyunPermission): ?>
                        <a href="ozelsaglayicioyun.php" class="menu-item <?php echo $currentPage === 'ozelsaglayicioyun' ? 'active' : ''; ?>">
                            <i class="bi bi-controller"></i> Özel Sağlayıcı Oyunları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasPopulerSlotlarPermission): ?>
                        <a href="populer_slotlar.php" class="menu-item <?php echo $currentPage === 'populer_slotlar' ? 'active' : ''; ?>">
                            <i class="bi bi-controller"></i> Popüler Slotlar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasPopulerSaglayicilarPermission): ?>
                        <a href="populer_saglayicilar.php" class="menu-item <?php echo $currentPage === 'populer_saglayicilar' ? 'active' : ''; ?>">
                            <i class="bi bi-controller"></i> Popüler Sağlayıcılar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasPopulerCanliCasinoPermission): ?>
                        <a href="populer_canli_casino.php" class="menu-item <?php echo $currentPage === 'populer_canli_casino' ? 'active' : ''; ?>">
                            <i class="bi bi-controller"></i> Popüler Canlı Casino
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasOyunlarMenuYonetimiPermission): ?>
                        <a href="oyunlar_menu_yonetimi.php" class="menu-item <?php echo $currentPage === 'oyunlar_menu_yonetimi' ? 'active' : ''; ?>">
                            <i class="bi bi-grid-3x3-gap"></i> Casino Canlı Casino Sayfa Yönetimi
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasEnCokKazananlarPermission): ?>
                        <a href="encokkazananlar.php" class="menu-item <?php echo $currentPage === 'encokkazananlar' ? 'active' : ''; ?>">
                            <i class="bi bi-trophy"></i> En Çok Kazananlar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasFooterSettingsPermission): ?>
                        <a href="footer_settings.php" class="menu-item <?php echo $currentPage === 'footer_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i> Footer Ayarları
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasFooterPopularGamesPermission): ?>
                        <a href="footer_popular_games.php" class="menu-item <?php echo $currentPage === 'footer_popular_games' ? 'active' : ''; ?>">
                            <i class="bi bi-joystick"></i> Footer Popüler Oyunlar
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasFooterMenuSettingsPermission): ?>
                        <a href="footer_menu_settings.php" class="menu-item <?php echo $currentPage === 'footer_menu_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-link-45deg"></i> Kısa Yollar
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Sidebar Menü Ayarları -->
            <?php if ($isAdmin || (isset($userPermissions['menu_settings']['view']) && $userPermissions['menu_settings']['view'])): ?>
            <div class="admin-menu-toggle">
                <a href="#sidebarSettingsMenu" class="menu-item d-flex justify-content-between align-items-center" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarSettingsMenu">
                    <div>
                        <i class="bi bi-menu-button-wide"></i> Sidebar Menü Ayarları
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </a>
                
                <div class="collapse" id="sidebarSettingsMenu">
                    <div class="ps-3">
                        <?php if ($hasSidebarMenuSettingsPermission): ?>
                        <a href="sidebar_menu_settings.php" class="menu-item <?php echo $currentPage === 'sidebar_menu_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-list"></i> Ana Sayfa Sidebar Menü
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasFinancialMenuSettingsPermission): ?>
                        <a href="financial_menu_settings.php" class="menu-item <?php echo $currentPage === 'financial_menu_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-currency-exchange"></i> Finansal İşlemler Menü
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasSidebarExtraSettingsPermission): ?>
                        <a href="sidebar_extra_settings.php" class="menu-item <?php echo $currentPage === 'sidebar_extra_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-list-stars"></i> Sidebar Extra Menü
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasSidebarSlotMenuPermission): ?>
                        <a href="sidebar_slot_menu.php" class="menu-item <?php echo $currentPage === 'sidebar_slot_menu' ? 'active' : ''; ?>">
                            <i class="bi bi-joystick"></i> Oyunlar Menüsü
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($hasMobileSidebarMenuSettingsPermission): ?>
                        <a href="mobile_sidebar_menu_settings.php" class="menu-item <?php echo $currentPage === 'mobile_sidebar_menu_settings' ? 'active' : ''; ?>">
                            <i class="bi bi-phone"></i> Mobil Sidebar Menü
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <a href="logout.php" class="menu-item">
                <i class="bi bi-box-arrow-right"></i> Çıkış
            </a>
        </div>
    </aside>

    <!-- Main content container -->
    <div class="content-wrapper">
        <header class="header">
            <button class="toggle-sidebar">
                <i class="bi bi-list"></i>
            </button>
            <div class="logo">Yönetici Paneli</div>
            
        
            
            <div class="user-menu">
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profil</a></li>
                        <li><a class="dropdown-item" href="theme_features.php"><i class="bi bi-palette"></i> Tema Özellikleri</a></li>
                        <li><a class="dropdown-item" href="2fa.php"><i class="bi bi-shield-lock"></i> 2FA Ayarları</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Çıkış</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="main-content">
            <!-- Advanced Page Header -->
            <div class="page-header">
                <div class="page-header-content">
                    <div class="page-title-section">
                        <h1 class="page-title">
                            <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Yönetici Paneli'; ?>
                        </h1>
                        <div class="page-subtitle">
                            <i class="bi bi-house"></i>
                            <span>Ana Sayfa</span>
                            <?php if (isset($pageTitle)): ?>
                                <i class="bi bi-chevron-right"></i>
                                <span><?php echo htmlspecialchars($pageTitle); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="page-actions">
                        <div class="quick-stats">
                            <div class="stat-item">
                                <i class="bi bi-clock"></i>
                                <span id="pageLoadTime">0ms</span>
                            </div>
                            <div class="stat-item">
                                <i class="bi bi-eye"></i>
                                <span id="pageViews">1</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="content-area">
                <?php if (isset($pageContent)) { echo $pageContent; } ?>
            </div>
        </main>
    </div>

    <!-- Main Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Modal Backdrop Fix Script -->
    <script src="includes/modal-fix.js"></script>
    
    <!-- Theme System Script -->
    <script src="assets/js/theme-system.js"></script>

    <!-- Corporate Menu State Management -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Live Clock Function
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('tr-TR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('tr-TR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            
            const timeElement = document.getElementById('currentTime');
            const dateElement = document.getElementById('currentDate');
            
            if (timeElement) timeElement.textContent = timeString;
            if (dateElement) dateElement.textContent = dateString;
        }
        
        // Update clock every second
        updateClock();
        setInterval(updateClock, 1000);
        
        // Page Load Time Tracking
        const pageLoadStart = performance.now();
        window.addEventListener('load', function() {
            const pageLoadTime = Math.round(performance.now() - pageLoadStart);
            const loadTimeElement = document.getElementById('pageLoadTime');
            if (loadTimeElement) {
                loadTimeElement.textContent = pageLoadTime + 'ms';
            }
        });
        
        // Page Views Tracking
        const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
        let pageViews = parseInt(localStorage.getItem(`page_views_${currentPage}`) || '0');
        pageViews++;
        localStorage.setItem(`page_views_${currentPage}`, pageViews.toString());
        
        const pageViewsElement = document.getElementById('pageViews');
        if (pageViewsElement) {
            pageViewsElement.textContent = pageViews;
        }
        
        // Theme Change Handler for Header and Sidebar
        function updateHeaderAndSidebarTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'default';
            
            // Update sidebar logo container gradient based on theme
            const logoContainer = document.querySelector('.sidebar .logo-container');
            if (logoContainer) {
                const gradients = {
                    'default': 'linear-gradient(135deg, #007bff, #0056b3)',
                    'blue': 'linear-gradient(135deg, #2196f3, #1976d2)',
                    'green': 'linear-gradient(135deg, #4caf50, #388e3c)',
                    'purple': 'linear-gradient(135deg, #9c27b0, #7b1fa2)',
                    'orange': 'linear-gradient(135deg, #ff9800, #f57c00)',
                    'red': 'linear-gradient(135deg, #f44336, #d32f2f)',
                    'pink': 'linear-gradient(135deg, #e91e63, #c2185b)',
                    'teal': 'linear-gradient(135deg, #009688, #00796b)',
                    'indigo': 'linear-gradient(135deg, #3f51b5, #303f9f)',
                    'amber': 'linear-gradient(135deg, #ffc107, #ff8f00)',
                    'cyan': 'linear-gradient(135deg, #00bcd4, #0097a7)'
                };
                logoContainer.style.background = gradients[currentTheme] || gradients['default'];
            }
            
            // Update header elements with theme-specific animations
            const header = document.querySelector('.header');
            const sidebar = document.querySelector('.sidebar');
            
            if (header && sidebar) {
                // Add theme transition effect
                header.style.transition = 'all 0.5s ease';
                sidebar.style.transition = 'all 0.5s ease';
                
                // Add subtle animation
                header.style.transform = 'scale(1.02)';
                sidebar.style.transform = 'scale(1.01)';
                
                setTimeout(() => {
                    header.style.transform = 'scale(1)';
                    sidebar.style.transform = 'scale(1)';
                }, 300);
            }
            
            // Update menu items with theme-specific hover effects
            const menuItems = document.querySelectorAll('.sidebar .menu-item');
            menuItems.forEach(item => {
                item.style.transition = 'all 0.3s ease';
            });
            
            // Update user menu dropdown with theme colors
            const userMenuToggle = document.querySelector('.header .user-menu .dropdown-toggle');
            if (userMenuToggle) {
                const themeColors = {
                    'default': 'rgba(0, 123, 255, 0.1)',
                    'blue': 'rgba(33, 150, 243, 0.1)',
                    'green': 'rgba(76, 175, 80, 0.1)',
                    'purple': 'rgba(156, 39, 176, 0.1)',
                    'orange': 'rgba(255, 152, 0, 0.1)',
                    'red': 'rgba(244, 67, 54, 0.1)',
                    'pink': 'rgba(233, 30, 99, 0.1)',
                    'teal': 'rgba(0, 150, 136, 0.1)',
                    'indigo': 'rgba(63, 81, 181, 0.1)',
                    'amber': 'rgba(255, 193, 7, 0.1)',
                    'cyan': 'rgba(0, 188, 212, 0.1)'
                };
                userMenuToggle.style.background = `linear-gradient(135deg, ${themeColors[currentTheme]}, ${themeColors[currentTheme].replace('0.1', '0.05')})`;
            }
        }
        
        // Listen for theme changes
        document.addEventListener('themeChanged', function(e) {
            updateHeaderAndSidebarTheme();
        });
        
        // Initial theme update
        updateHeaderAndSidebarTheme();
        
        // Automatically expand menus with active items
        function expandParentMenus() {
            // First, create a map of parent-child relationships for menus
            const menuHierarchy = {
                'adminMenu': ['users', 'roles', 'settings', 'activity_logs', 'profile'],
                'kullanicilarMenu': ['site_users', 'risky_users', 'user_details', 'password_reset_logs', 'user_messages'],
                'vipMenu': ['vip_users', 'vip_levels', 'vip_points', 'vip_store', 'rakeback', 'rakeback_history', 'levelup_history', 'vip_logs', 'vip_cash_bonus_logs'],
                'vipSettingsMenu': ['vip_advantages', 'vip_faqs', 'vip_levels_settings', 'vip_banner_settings'],
                'vipConfigMenu': ['rakeback_settings', 'vip_cash_bonus', 'vip_store_settings', 'vip_levelup_bonus_settings', 'vip_kazananlar'],
                'chatMenu': ['chat_messages', 'chat_users', 'chat_bans'],
                'bonusMenu': ['bonus_management_menu', 'promotion_menu', 'bonus_requests', 'bonus_talep_loglar'],
                'turnuvaMenu': ['tournaments_list', 'tournaments_add', 'tournament_leaderboard', 'tournament_participants', 'tournament_games', 'tournament_details'],
                'menuSettingsMenu': ['banners', 'slider_alt_bolum', 'site_settings_menu', 'social_media_settings', 'page_contents', 'kazandiran_oyunlar', 'ozelsaglayicioyun', 'populer_slotlar', 'populer_saglayicilar', 'populer_canli_casino', 'encokkazananlar', 'footer_settings', 'footer_popular_games', 'footer_menu_settings'],
                'sidebarSettingsMenu': ['sidebar_menu_settings', 'financial_menu_settings', 'sidebar_extra_settings', 'sidebar_slot_menu']
            };

            // Get the current page
            const currentPage = '<?php echo $currentPage; ?>';
            
            // Map current page to its parent menu
            const pageToMenuMap = {};
            for (const [parentMenuId, childPages] of Object.entries(menuHierarchy)) {
                for (const childPage of childPages) {
                    pageToMenuMap[childPage] = parentMenuId;
                }
            }
            
            // Only expand the menu that contains the current page
            if (pageToMenuMap[currentPage]) {
                const menuId = pageToMenuMap[currentPage];
                const menu = document.getElementById(menuId);
                if (menu) {
                    menu.classList.add('show');
                    const trigger = document.querySelector(`[data-bs-toggle="collapse"][href="#${menuId}"]`);
                    if (trigger) {
                        trigger.setAttribute('aria-expanded', 'true');
                        // Also add active class to parent menu
                        trigger.classList.add('active');
                    }
                }
            }
            
            // Add 'active' class to the current page's menu item
            const currentMenuItem = document.querySelector(`.menu-item[href="${currentPage}.php"]`);
            if (currentMenuItem) {
                currentMenuItem.classList.add('active');
                
                // Also highlight the parent menu item if it exists
                const parentCollapse = currentMenuItem.closest('.collapse');
                if (parentCollapse) {
                    const parentMenuItem = parentCollapse.previousElementSibling;
                    if (parentMenuItem && parentMenuItem.classList.contains('menu-item')) {
                        parentMenuItem.classList.add('active');
                    }
                }
            }
        }
        
        // Expand parent menus on initial load
        expandParentMenus();
        
        // Handle menu item clicks with enhanced animations
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(trigger => {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                
                // Add click animation
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
                
                // Toggle menu state
                if (isExpanded) {
                    target.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    target.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            });
        });
        
        // Enhanced sidebar toggle for mobile
        const toggleSidebarBtn = document.querySelector('.toggle-sidebar');
        if (toggleSidebarBtn) {
            toggleSidebarBtn.addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                sidebar.classList.toggle('active');
                
                // Add toggle animation
                this.style.transform = 'rotate(90deg)';
                setTimeout(() => {
                    this.style.transform = 'rotate(0deg)';
                }, 300);
            });
        }
        
        // Add hover effects to all interactive elements
        function addHoverEffects() {
            const interactiveElements = document.querySelectorAll('.menu-item, .dropdown-toggle, .btn, .live-clock');
            
            interactiveElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    this.style.transform = this.style.transform + ' scale(1.02)';
                });
                
                element.addEventListener('mouseleave', function() {
                    this.style.transform = this.style.transform.replace(' scale(1.02)', '');
                });
            });
        }
        
        // Initialize hover effects
        addHoverEffects();
        
        // Theme switcher functionality
        const themeOptions = document.querySelectorAll('.theme-option');
        themeOptions.forEach(option => {
            option.addEventListener('click', function() {
                const theme = this.dataset.theme;
                
                // Remove active class from all options
                themeOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active class to clicked option
                this.classList.add('active');
                
                // Set theme using the new theme system
                if (window.themeSystem) {
                    window.themeSystem.switchTheme(theme);
                } else {
                    // Fallback if theme system is not loaded
                    document.documentElement.setAttribute('data-theme', theme);
                    localStorage.setItem('theme', theme);
                    
                    // Trigger theme change event
                    document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
                    
                    // Show notification
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Tema Güncellendi!',
                            text: `${theme.charAt(0).toUpperCase() + theme.slice(1)} teması aktif edildi.`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }
                }
            });
        });
        
        // Set active theme on load
        const savedTheme = localStorage.getItem('theme') || 'default';
        document.documentElement.setAttribute('data-theme', savedTheme);
        const activeThemeOption = document.querySelector(`[data-theme="${savedTheme}"]`);
        if (activeThemeOption) {
            activeThemeOption.classList.add('active');
        }
        
        // Initialize theme system
        if (window.themeSystem) {
            window.themeSystem.init();
        }
    });
    </script>
    
    <!-- Tema Sistemi JavaScript -->
    <script src="assets/js/theme-system.js"></script>
</body>
</html>