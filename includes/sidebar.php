<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: /admincik/login.php");
    exit();
}

// Check permissions for menu items
$isAdmin = ($_SESSION['role_id'] == 1);

// Get user permissions if they exist
$userPermissions = [];
$stmt = $db->prepare("SELECT * FROM admin_permissions WHERE role_id = ?");
$stmt->execute([$_SESSION['role_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($permissions as $permission) {
    $userPermissions[$permission['menu_item']] = [
        'view' => $permission['can_view'],
        'create' => $permission['can_create'],
        'edit' => $permission['can_edit'],
        'delete' => $permission['can_delete']
    ];
}

// Define page groups
$adminPages = ['users', 'roles', 'settings', 'activity_logs', 'profile'];
$userPages = ['site_users', 'user_details', '2fa', 'profile'];
$chatPages = ['chat_users', 'chat_messages', 'chat_bans'];

// Check section permissions
$isAdminMenuActive = in_array($currentPage, $adminPages);
$isUserMenuActive = in_array($currentPage, $userPages);
$isChatMenuActive = in_array($currentPage, $chatPages);

$hasAdminSectionPermission = $isAdmin || 
    ($userPermissions['users']['view'] ?? false) || 
    ($userPermissions['roles']['view'] ?? false) || 
    ($userPermissions['settings']['view'] ?? false) || 
    ($userPermissions['activity_logs']['view'] ?? false);

$hasUserSectionPermission = $isAdmin || 
    ($userPermissions['kullanicilar']['view'] ?? false) || 
    ($userPermissions['user_details']['view'] ?? false) || 
    ($userPermissions['2fa']['view'] ?? false);

$hasChatSectionPermission = $isAdmin || ($userPermissions['chat']['view'] ?? false);
?>

<!-- Enhanced Mobile-Responsive Sidebar -->
<aside class="sidebar" id="mainSidebar">
    <!-- Sidebar Header with Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="bi bi-shield-lock-fill"></i>
            <span class="logo-text">Admin Panel</span>
        </div>
        <button class="sidebar-close" id="sidebarClose">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- User Profile Section -->
    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="bi bi-person-circle"></i>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user['username'] ?? 'Admin'); ?></div>
            <div class="user-role"><?php echo htmlspecialchars($user['role_name'] ?? 'Administrator'); ?></div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="index.php" class="nav-item <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
            <div class="nav-indicator"></div>
        </a>

        <!-- Users Management -->
        <?php if ($isAdmin || (isset($userPermissions['kullanicilar']['view']) && $userPermissions['kullanicilar']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#usersMenu">
                <i class="bi bi-people-fill"></i>
                <span>Users Management</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['site_users', 'risky_users', 'bonus_talep_loglar']) ? 'show' : ''; ?>" id="usersMenu">
                <a href="site_users.php" class="nav-item <?php echo $currentPage === 'site_users' ? 'active' : ''; ?>">
                    <i class="bi bi-person-fill"></i>
                    <span>Site Users</span>
                </a>
                <?php if ($isAdmin || (isset($userPermissions['risky_users']['view']) && $userPermissions['risky_users']['view'])): ?>
                <a href="risky_users.php" class="nav-item <?php echo $currentPage === 'risky_users' ? 'active' : ''; ?>">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Risky Users</span>
                </a>
                <?php endif; ?>
                <a href="bonus_talep_loglar.php" class="nav-item <?php echo $currentPage === 'bonus_talep_loglar' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>Bonus Request Logs</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- VIP Management -->
        <?php if ($isAdmin || (isset($userPermissions['vip']['view']) && $userPermissions['vip']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#vipMenu">
                <i class="bi bi-star-fill"></i>
                <span>VIP Management</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['vip_users', 'vip_levels', 'vip_points', 'vip_store', 'rakeback', 'rakeback_history', 'vip_logs', 'levelup_history']) ? 'show' : ''; ?>" id="vipMenu">
                <a href="vip_users.php" class="nav-item <?php echo $currentPage === 'vip_users' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>VIP Users</span>
                </a>
                <a href="vip_levels.php" class="nav-item <?php echo $currentPage === 'vip_levels' ? 'active' : ''; ?>">
                    <i class="bi bi-trophy"></i>
                    <span>VIP Levels</span>
                </a>
                <a href="vip_points.php" class="nav-item <?php echo $currentPage === 'vip_points' ? 'active' : ''; ?>">
                    <i class="bi bi-coin"></i>
                    <span>VIP Points</span>
                </a>
                <a href="vip_store.php" class="nav-item <?php echo $currentPage === 'vip_store' ? 'active' : ''; ?>">
                    <i class="bi bi-shop"></i>
                    <span>VIP Store</span>
                </a>
                <a href="rakeback.php" class="nav-item <?php echo $currentPage === 'rakeback' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Rakeback</span>
                </a>
                <a href="rakeback_history.php" class="nav-item <?php echo $currentPage === 'rakeback_history' ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i>
                    <span>Rakeback History</span>
                </a>
                <a href="levelup_history.php" class="nav-item <?php echo $currentPage === 'levelup_history' ? 'active' : ''; ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Level Up Bonuses</span>
                </a>
                <a href="vip_logs.php" class="nav-item <?php echo $currentPage === 'vip_logs' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>VIP User Logs</span>
                </a>
                <a href="vip_cash_bonus_logs.php" class="nav-item <?php echo $currentPage === 'vip_cash_bonus_logs' ? 'active' : ''; ?>">
                    <i class="bi bi-cash"></i>
                    <span>VIP Cash Bonus Logs</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- VIP Settings -->
        <?php if ($isAdmin || (isset($userPermissions['vip_settings']['view']) && $userPermissions['vip_settings']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#vipSettingsMenu">
                <i class="bi bi-window"></i>
                <span>VIP Page Settings</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['vip_advantages']) ? 'show' : ''; ?>" id="vipSettingsMenu">
                <a href="vip_advantages.php" class="nav-item <?php echo $currentPage === 'vip_advantages' ? 'active' : ''; ?>">
                    <i class="bi bi-star-half"></i>
                    <span>VIP Advantages</span>
                </a>
                <a href="vip_faqs.php" class="nav-item <?php echo $currentPage === 'vip_faqs' ? 'active' : ''; ?>">
                    <i class="bi bi-question-circle"></i>
                    <span>VIP FAQ</span>
                </a>
                <a href="vip_levels_settings.php" class="nav-item <?php echo $currentPage === 'vip_levels_settings' ? 'active' : ''; ?>">
                    <i class="bi bi-trophy"></i>
                    <span>VIP Level Settings</span>
                </a>
                <a href="vip_banner_settings.php" class="nav-item <?php echo $currentPage === 'vip_banner_settings' ? 'active' : ''; ?>">
                    <i class="bi bi-image"></i>
                    <span>VIP Banner Settings</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- VIP Configuration -->
        <?php if ($isAdmin || (isset($userPermissions['vip_settings']['view']) && $userPermissions['vip_settings']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#vipConfigMenu">
                <i class="bi bi-gear-fill"></i>
                <span>VIP Configuration</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['rakeback_settings', 'vip_cash_bonus', 'vip_store_settings', 'vip_levelup_bonus_settings', 'vip_kazananlar']) ? 'show' : ''; ?>" id="vipConfigMenu">
                <a href="rakeback_settings.php" class="nav-item <?php echo $currentPage === 'rakeback_settings' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Rakeback Settings</span>
                </a>
                <a href="vip_cash_bonus.php" class="nav-item <?php echo $currentPage === 'vip_cash_bonus' ? 'active' : ''; ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>VIP Cash Bonus Settings</span>
                </a>
                <a href="vip_store_settings.php" class="nav-item <?php echo $currentPage === 'vip_store_settings' ? 'active' : ''; ?>">
                    <i class="bi bi-shop"></i>
                    <span>VIP Store Settings</span>
                </a>
                <a href="vip_levelup_bonus_settings.php" class="nav-item <?php echo $currentPage === 'vip_levelup_bonus_settings' ? 'active' : ''; ?>">
                    <i class="bi bi-arrow-up-circle"></i>
                    <span>Level Up Bonus Settings</span>
                </a>
                <a href="vip_kazananlar.php" class="nav-item <?php echo $currentPage === 'vip_kazananlar' ? 'active' : ''; ?>">
                    <i class="bi bi-trophy-fill"></i>
                    <span>VIP Winners</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bonus Management -->
        <?php if ($isAdmin || (isset($userPermissions['bonus_management']['view']) && $userPermissions['bonus_management']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#bonusMenu">
                <i class="bi bi-gift"></i>
                <span>Bonus Management</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['bonus_management', 'promotion_menu', 'bonus_requests']) ? 'show' : ''; ?>" id="bonusMenu">
                <a href="bonus_management.php" class="nav-item <?php echo $currentPage === 'bonus_management' ? 'active' : ''; ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>Bonus Management</span>
                </a>
                <a href="promotion_menu.php" class="nav-item <?php echo $currentPage === 'promotion_menu' ? 'active' : ''; ?>">
                    <i class="bi bi-megaphone"></i>
                    <span>Promotions</span>
                </a>
                <a href="bonus_requests.php" class="nav-item <?php echo $currentPage === 'bonus_requests' ? 'active' : ''; ?>">
                    <i class="bi bi-list-check"></i>
                    <span>Bonus Requests</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chat Management -->
        <?php if ($isAdmin || (isset($userPermissions['chat']['view']) && $userPermissions['chat']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#chatMenu">
                <i class="bi bi-chat-dots-fill"></i>
                <span>Chat Management</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['chat_users', 'chat_messages', 'chat_bans']) ? 'show' : ''; ?>" id="chatMenu">
                <a href="chat_users.php" class="nav-item <?php echo $currentPage === 'chat_users' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i>
                    <span>Chat Users</span>
                </a>
                <a href="chat_messages.php" class="nav-item <?php echo $currentPage === 'chat_messages' ? 'active' : ''; ?>">
                    <i class="bi bi-chat-text"></i>
                    <span>Messages</span>
                </a>
                <a href="chat_bans.php" class="nav-item <?php echo $currentPage === 'chat_bans' ? 'active' : ''; ?>">
                    <i class="bi bi-slash-circle"></i>
                    <span>Banned Users</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tournament Management -->
        <?php if ($isAdmin || (isset($userPermissions['tournaments']['view']) && $userPermissions['tournaments']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#tournamentsMenu">
                <i class="bi bi-trophy"></i>
                <span>Tournaments</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['tournaments', 'tournaments_add', 'tournament_edit', 'tournament_details', 'tournament_games', 'tournament_leaderboard', 'tournament_participants']) ? 'show' : ''; ?>" id="tournamentsMenu">
                <a href="tournaments.php" class="nav-item <?php echo $currentPage === 'tournaments' ? 'active' : ''; ?>">
                    <i class="bi bi-list-check"></i>
                    <span>Tournament List</span>
                </a>
                <a href="tournaments_add.php" class="nav-item <?php echo $currentPage === 'tournaments_add' ? 'active' : ''; ?>">
                    <i class="bi bi-plus-circle"></i>
                    <span>Add Tournament</span>
                </a>
                <a href="tournament_games.php" class="nav-item <?php echo $currentPage === 'tournament_games' ? 'active' : ''; ?>">
                    <i class="bi bi-controller"></i>
                    <span>Tournament Games</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Visitor Cities -->
        <?php if ($isAdmin): ?>
        <a href="visitor_cities.php" class="nav-item <?php echo $currentPage === 'visitor_cities' ? 'active' : ''; ?>">
            <i class="bi bi-geo-alt-fill"></i>
            <span>Visitor Cities</span>
            <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>

        <!-- Admin Users and Roles -->
        <?php if ($isAdmin || (isset($userPermissions['users']['view']) && $userPermissions['users']['view']) || (isset($userPermissions['roles']['view']) && $userPermissions['roles']['view'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#adminUsersMenu">
                <i class="bi bi-people-fill"></i>
                <span>Admin Users</span>
                <i class="bi bi-chevron-down nav-arrow"></i>
            </div>
            <div class="nav-group-content collapse <?php echo in_array($currentPage, ['users', 'roles']) ? 'show' : ''; ?>" id="adminUsersMenu">
                <?php if ($isAdmin || (isset($userPermissions['users']['view']) && $userPermissions['users']['view'])): ?>
                <a href="users.php" class="nav-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Users</span>
                </a>
                <?php endif; ?>
                
                <?php if ($isAdmin || (isset($userPermissions['roles']['view']) && $userPermissions['roles']['view'])): ?>
                <a href="roles.php" class="nav-item <?php echo $currentPage === 'roles' ? 'active' : ''; ?>">
                    <i class="bi bi-shield"></i>
                    <span>Roles</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Settings -->
        <?php if ($isAdmin || (isset($userPermissions['settings']['view']) && $userPermissions['settings']['view'])): ?>
        <a href="settings.php" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
            <i class="bi bi-gear-fill"></i>
            <span>System Settings</span>
            <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>

        <!-- Activity Logs -->
        <?php if ($isAdmin || (isset($userPermissions['activity_logs']['view']) && $userPermissions['activity_logs']['view'])): ?>
        <a href="activity_logs.php" class="nav-item <?php echo $currentPage === 'activity_logs' ? 'active' : ''; ?>">
            <i class="bi bi-clock-history"></i>
            <span>Activity Logs</span>
            <div class="nav-indicator"></div>
        </a>
        <?php endif; ?>

        <!-- User Profile -->
        <a href="profile.php" class="nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
            <i class="bi bi-person-circle"></i>
            <span>My Profile</span>
            <div class="nav-indicator"></div>
        </a>

        <!-- Logout -->
        <a href="logout.php" class="nav-item logout-item">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
            <div class="nav-indicator"></div>
        </a>
    </nav>
</aside>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Enhanced Sidebar Styles -->
<style>
/* Enhanced Mobile-Responsive Sidebar */
.sidebar {
    width: 280px;
    height: 100vh;
    background: var(--sidebar-bg);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 9999;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
    pointer-events: auto;
    backdrop-filter: blur(10px);
}

/* Ensure all sidebar elements are clickable */
.sidebar * {
    pointer-events: auto;
}

/* Sidebar Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
    position: relative;
    overflow: hidden;
}

.sidebar-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    50% { transform: translateX(100%); }
    100% { transform: translateX(100%); }
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: white;
    font-weight: 700;
    font-size: 1.25rem;
    z-index: 2;
}

.sidebar-logo i {
    font-size: 1.5rem;
}

.logo-text {
    letter-spacing: 1px;
}

.sidebar-close {
    display: none;
    background: none;
    border: none;
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    z-index: 2;
    min-width: 48px;
    min-height: 48px;
    touch-action: manipulation;
}

.sidebar-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

/* User Profile Section */
.sidebar-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-secondary);
    pointer-events: auto;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.user-role {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.user-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #10b981;
    animation: pulse 2s infinite;
}

.status-indicator.online {
    background: #10b981;
}

.status-indicator.offline {
    background: #6b7280;
}

.status-text {
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 500;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
    pointer-events: auto;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
    pointer-events: auto;
    border-left: 3px solid transparent;
    margin: 0.25rem 0;
    z-index: 1;
    min-height: 48px;
    touch-action: manipulation;
}

.nav-item:hover {
    background: rgba(59, 130, 246, 0.1);
    color: var(--text-primary);
    border-left-color: var(--accent-color);
    transform: translateX(5px);
}

.nav-item.active {
    background: rgba(59, 130, 246, 0.15);
    color: var(--accent-color);
    border-left-color: var(--accent-color);
    font-weight: 600;
}

.nav-item i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
    transition: transform 0.3s ease;
}

.nav-item:hover i {
    transform: scale(1.2);
}

.nav-indicator {
    position: absolute;
    right: 1rem;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--accent-color);
    opacity: 0;
    transition: all 0.3s ease;
}

.nav-item.active .nav-indicator {
    opacity: 1;
    transform: scale(1.5);
}

/* Navigation Groups */
.nav-group {
    margin-bottom: 0.5rem;
}

.nav-group-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    position: relative;
    pointer-events: auto;
    z-index: 1;
    user-select: none;
    min-height: 48px;
    touch-action: manipulation;
}

.nav-group-header:hover {
    background: rgba(59, 130, 246, 0.1);
    color: var(--text-primary);
}

.nav-group-header i:first-child {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.nav-arrow {
    margin-left: auto;
    transition: transform 0.3s ease;
}

.nav-group-header[aria-expanded="true"] .nav-arrow {
    transform: rotate(180deg);
}

.nav-group-content {
    background: var(--bg-secondary);
    border-left: 3px solid var(--accent-color);
    margin-left: 1.5rem;
    overflow: hidden;
    transition: all 0.3s ease;
    max-height: 0;
    opacity: 0;
}

.nav-group-content.show {
    max-height: 1000px;
    opacity: 1;
}

.nav-group-content .nav-item {
    padding-left: 2.5rem;
    font-size: 0.9rem;
}

/* Logout Item */
.logout-item {
    margin-top: auto;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-left-color: #ef4444;
}

.logout-item:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    background: var(--bg-secondary);
    pointer-events: auto;
    z-index: 1;
}

.sidebar-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.sidebar-action-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 48px;
    touch-action: manipulation;
    cursor: pointer;
    pointer-events: auto;
    z-index: 1;
}

.sidebar-action-btn:hover {
    background: var(--accent-color);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    text-decoration: none;
}

.sidebar-action-btn i {
    font-size: 1.1rem;
}

/* Mobile Sidebar Overlay */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
    backdrop-filter: blur(5px);
}

.sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

/* Enhanced Responsive Design */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
        width: 100%;
        max-width: 280px;
        z-index: 9999;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
        min-width: 48px;
        min-height: 48px;
        touch-action: manipulation;
    }
    
    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
        backdrop-filter: blur(5px);
    }
    
    /* Hide sidebar logo text on smaller screens */
    .sidebar-logo .logo-text {
        display: none;
    }
    
    /* Adjust user info for mobile */
    .user-info {
        display: none;
    }
    
    .sidebar-user {
        justify-content: center;
        padding: 1rem;
    }
    
    /* Adjust navigation items for mobile */
    .nav-item {
        padding: 1rem 1.25rem;
        font-size: 0.95rem;
        min-height: 50px;
        touch-action: manipulation;
    }
    
    .nav-group-header {
        padding: 1rem 1.25rem;
        font-size: 0.95rem;
        min-height: 50px;
        touch-action: manipulation;
    }
    
    .nav-group-content .nav-item {
        padding-left: 2rem;
        font-size: 0.9rem;
    }
}

/* Desktop sidebar - always visible */
@media (min-width: 993px) {
    .sidebar {
        transform: translateX(0) !important;
        width: 280px;
    }
    
    .sidebar-close {
        display: none !important;
    }
    
    .sidebar-overlay {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        max-width: 280px;
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-header {
        padding: 1rem;
    }
    
    .sidebar-user {
        padding: 0.75rem;
    }
    
    .sidebar-nav {
        padding: 0.5rem 0;
    }
    
    .nav-item {
        padding: 0.875rem 1rem;
        margin: 0.125rem 0;
    }
    
    .nav-group-header {
        padding: 0.875rem 1rem;
    }
    
    .nav-group-content {
        margin-left: 1rem;
    }
    
    .nav-group-content .nav-item {
        padding-left: 1.75rem;
    }
    
    .sidebar-footer {
        padding: 0.75rem 1rem;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 100%;
        max-width: none;
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-header {
        padding: 0.75rem;
    }
    
    .sidebar-logo i {
        font-size: 1.25rem;
    }
    
    .sidebar-user {
        padding: 0.5rem;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        font-size: 1.25rem;
    }
    
    .nav-item {
        padding: 0.75rem 0.875rem;
        font-size: 0.9rem;
        min-height: 44px;
    }
    
    .nav-item i {
        font-size: 1rem;
        width: 18px;
    }
    
    .nav-group-header {
        padding: 0.75rem 0.875rem;
        font-size: 0.9rem;
        min-height: 44px;
    }
    
    .nav-group-header i:first-child {
        font-size: 1rem;
        width: 18px;
    }
    
    .nav-group-content {
        margin-left: 0.75rem;
    }
    
    .nav-group-content .nav-item {
        padding-left: 1.5rem;
        font-size: 0.85rem;
    }
    
    .sidebar-footer {
        padding: 0.5rem 0.75rem;
    }
    
    .sidebar-action-btn {
        min-height: 44px;
        padding: 0.75rem 0.875rem;
    }
}

/* Touch-friendly interactions for mobile */
@media (hover: none) and (pointer: coarse) {
    .nav-item {
        min-height: 48px;
        padding: 1rem 1.25rem;
        touch-action: manipulation;
    }
    
    .nav-group-header {
        min-height: 48px;
        padding: 1rem 1.25rem;
        touch-action: manipulation;
    }
    
    .sidebar-close {
        min-width: 48px;
        min-height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        touch-action: manipulation;
    }
    
    .sidebar-action-btn {
        min-height: 48px;
        touch-action: manipulation;
    }
    
    /* Improve touch feedback */
    .nav-item:active, .nav-group-header:active {
        transform: scale(0.98);
        transition: transform 0.1s ease;
    }
}

/* Scrollbar Styling */
.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 2px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: var(--accent-color);
}

/* Animation Enhancements */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.nav-item {
    animation: slideIn 0.3s ease forwards;
}

.nav-item:nth-child(1) { animation-delay: 0.1s; }
.nav-item:nth-child(2) { animation-delay: 0.2s; }
.nav-item:nth-child(3) { animation-delay: 0.3s; }
.nav-item:nth-child(4) { animation-delay: 0.4s; }
.nav-item:nth-child(5) { animation-delay: 0.5s; }
.nav-item:nth-child(6) { animation-delay: 0.6s; }
.nav-item:nth-child(7) { animation-delay: 0.7s; }
.nav-item:nth-child(8) { animation-delay: 0.8s; }
.nav-item:nth-child(9) { animation-delay: 0.9s; }
.nav-item:nth-child(10) { animation-delay: 1s; }

/* Theme-specific enhancements */
[data-theme="minimal"] .sidebar {
    background: linear-gradient(180deg, #fafafa 0%, #f5f5f5 100%);
}

[data-theme="gradient"] .sidebar {
    background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
}

[data-theme="glass"] .sidebar {
    background: linear-gradient(180deg, rgba(241, 245, 249, 0.9) 0%, rgba(226, 232, 240, 0.9) 100%);
    backdrop-filter: blur(10px);
}

[data-theme="neon"] .sidebar {
    background: linear-gradient(180deg, #fafafa 0%, #f5f5f5 100%);
}
</style>

<script>
// Enhanced Sidebar JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarClose = document.getElementById('sidebarClose');
    const toggleSidebarBtn = document.querySelector('.toggle-sidebar');
    
    // Mobile sidebar toggle
    function toggleSidebar() {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
    
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', toggleSidebar);
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', toggleSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }
    
    // Theme switcher
    const themeOptions = document.querySelectorAll('.theme-option');
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.dataset.theme;
            
            // Remove active class from all options
            themeOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');
            
            // Set theme
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('selectedTheme', theme);
            
            // Trigger theme change event
            document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
            
            // Show notification
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Theme Updated!',
                    text: `${theme.charAt(0).toUpperCase() + theme.slice(1)} theme activated.`,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    });
    
    // Set active theme on load
    const savedTheme = localStorage.getItem('selectedTheme') || 'minimal';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const activeThemeOption = document.querySelector(`[data-theme="${savedTheme}"]`);
    if (activeThemeOption) {
        activeThemeOption.classList.add('active');
    }
    
    // Enhanced navigation interactions
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px) scale(1.02)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0) scale(1)';
        });
    });
    
    // Auto-expand parent menus for active items
    const activeNavItem = document.querySelector('.nav-item.active');
    if (activeNavItem) {
        const parentGroup = activeNavItem.closest('.nav-group');
        if (parentGroup) {
            const groupHeader = parentGroup.querySelector('.nav-group-header');
            const groupContent = parentGroup.querySelector('.nav-group-content');
            if (groupHeader && groupContent) {
                groupHeader.setAttribute('aria-expanded', 'true');
                groupContent.classList.add('show');
            }
        }
    }
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            toggleSidebar();
        }
    });
});
</script> 
