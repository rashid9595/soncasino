<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['2fa_verified'])) {
    header("Location: login.php");
    exit();
}

// Get user permissions
$stmt = $db->prepare("
    SELECT ap.* 
    FROM admin_permissions ap 
    WHERE ap.role_id = ?
");
$stmt->execute([$_SESSION['role_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);

// Get user info
$stmt = $db->prepare("SELECT * FROM administrators WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="assets/css/themes.css">
    
    <style>
        :root {
            --sidebar-width: 250px;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            padding: 1rem;
            transition: all 0.3s;
            border-right: 1px solid var(--border-color);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        .nav-link {
            color: var(--text-secondary);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: var(--accent-color);
            color: var(--text-primary);
            transform: translateX(5px);
        }

        .nav-link.active {
            background-color: var(--accent-color);
            color: var(--text-primary);
        }

        .top-bar {
            background: var(--header-bg);
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-text);
        }

        /* Theme Switcher in Header */
        .header-theme-switcher {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 25px;
            margin-left: 1rem;
        }

        .header-theme-option {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .header-theme-option:hover {
            transform: scale(1.1);
            border-color: var(--accent-color);
        }

        .header-theme-option.active {
            border-color: var(--accent-color);
            box-shadow: 0 0 10px var(--accent-color);
        }

        .header-theme-option.minimal {
            background: linear-gradient(45deg, #ffffff, #fafafa);
        }

        .header-theme-option.gradient {
            background: linear-gradient(45deg, #ffffff, #8b5cf6);
        }

        .header-theme-option.glass {
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.9), #06b6d4);
        }

        .header-theme-option.neon {
            background: linear-gradient(45deg, #ffffff, #ff0080);
        }

        .theme-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .header-theme-switcher {
                margin-left: 0;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3 class="mb-4">Admin Panel</h3>
        <nav class="nav flex-column">
            <?php if (isset($permissions['users']) && $permissions['users']['can_view']): ?>
            <a class="nav-link" href="users.php">
                <i class="bi bi-people"></i> Users
            </a>
            <?php endif; ?>
            
            <?php if (isset($permissions['roles']) && $permissions['roles']['can_view']): ?>
            <a class="nav-link" href="roles.php">
                <i class="bi bi-shield-lock"></i> Roles
            </a>
            <?php endif; ?>
            
            <?php if (isset($permissions['settings']) && $permissions['settings']['can_view']): ?>
            <a class="nav-link" href="settings.php">
                <i class="bi bi-gear"></i> Settings
            </a>
            <?php endif; ?>
            
            <a class="nav-link" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <button class="btn btn-dark d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="d-flex align-items-center">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <div><?php echo htmlspecialchars($user['username']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                    </div>
                </div>
                        <!-- Notification Center -->
                        <div class="notification-center ms-3">
                            <button class="notification-btn" id="notificationBtn">
                                <i class="bi bi-bell"></i>
                                <span class="notification-badge" id="notificationBadge">3</span>
                            </button>
                            <div class="notification-dropdown" id="notificationDropdown">
                                <div class="notification-header">
                                    <h6 class="mb-0">Bildirimler</h6>
                                    <button class="btn btn-sm btn-outline-primary" id="markAllRead">Tümünü Okundu İşaretle</button>
                                </div>
                                <div class="notification-list" id="notificationList">
                                    <!-- Notifications will be loaded here -->
                                </div>
                            </div>
                        </div>
        </div>
    </div>
    
    <!-- Theme System Script -->
    <script src="assets/js/theme-system.js"></script>
</body>
</html> 