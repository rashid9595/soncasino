<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Get system settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get site settings for logo and favicon
try {
    $stmt = $db->query("SELECT * FROM site_settings WHERE id = 1");
    $siteSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$siteSettings) {
        // Default settings if no site settings exist
        $siteSettings = [
            'logo_path' => '/cdn/logo-SeninSiten.png',
            'site_title' => 'Kurumsal Yönetim Paneli'
        ];
    }
} catch (PDOException $e) {
    // Default settings in case of error
    $siteSettings = [
        'logo_path' => '/cdn/logo-SeninSiten.png',
        'site_title' => 'Kurumsal Yönetim Paneli'
    ];
}

// Get max login attempts from settings or use default
$maxLoginAttempts = isset($settings['login_attempts']) ? (int)$settings['login_attempts'] : 5;

$error = '';

// Check if the IP is blocked
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $db->prepare("SELECT COUNT(*) as attempt_count FROM activity_logs WHERE action = 'Login Error' AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
$stmt->execute([$ip]);
$attemptCount = $stmt->fetch(PDO::FETCH_ASSOC)['attempt_count'];

// Check if a timeout message should be displayed
$timeoutMessage = '';
// TEMPORARY: Disabled timeout message
/*
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $timeoutMessage = "Oturumunuzun süresi doldu. Güvenliğiniz için yeniden giriş yapmalısınız.";
}
*/

// If IP has too many failed attempts, block login
$isBlocked = false;
// TEMPORARY: IP blocking functionality disabled
/* 
if ($attemptCount >= $maxLoginAttempts) {
    $isBlocked = true;
    // Don't set error message here, we'll show it only when a login attempt is made
}
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $db->prepare("SELECT * FROM administrators WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['last_activity'] = time(); // For session timeout
            
            // Log the login attempt
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'Login', 'Successful login attempt', ?)");
            $stmt->execute([$user['id'], $ip]);
            
            // Check if 2FA is enabled
            if (!empty($user['secret_key']) && $user['secret_key'] !== 'yok') {
                $_SESSION['2fa_verified'] = false;
                header("Location: 2fa.php?verify=1");
                exit();
            } else {
                $_SESSION['2fa_verified'] = true;
                header("Location: index.php");
                exit();
            }
        } else {
            // Check if IP is blocked only when password is incorrect
            if ($isBlocked) {
                $error = "Çok fazla başarısız giriş denemesi. Lütfen 30 dakika sonra tekrar deneyin.";
            } else {
                $error = "Geçersiz kullanıcı adı veya şifre";
            }
            
            // Log failed login attempt with IP address
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (0, 'Login Error', ?, ?)");
            $stmt->execute(["Failed login attempt for username: " . $username, $ip]);
        }
    } else {
        $error = 'Lütfen tüm alanları doldurun';
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isBlocked) {
    // Only show blocking message when user attempts to login
    $error = "Çok fazla başarısız giriş denemesi. Lütfen 30 dakika sonra tekrar deneyin.";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteSettings['site_title']); ?> - Güvenli Giriş</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #059669;
            --warning-color: #d97706;
            --error-color: #dc2626;
            --background-color: #f8fafc;
            --surface-color: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background-color);
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(37, 99, 235, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(59, 130, 246, 0.03) 0%, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .login-info {
            padding: 2rem;
        }

        .company-logo {
            margin-bottom: 2rem;
        }

        .company-logo img {
            max-height: 60px;
            width: auto;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .welcome-subtitle {
            font-size: 1.125rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .features-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .features-list li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .features-list li i {
            color: var(--primary-color);
            margin-right: 0.75rem;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .security-badge i {
            margin-right: 0.5rem;
        }

        .login-container {
            background: var(--surface-color);
            border-radius: var(--border-radius-lg);
            padding: 3rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: var(--shadow-lg);
        }

        .login-icon img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        .login-icon i {
            font-size: 2rem;
            color: white;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: var(--text-primary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            z-index: 2;
        }

        .form-control {
            background: var(--surface-color);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            font-size: 1rem;
            padding: 1rem 1rem 1rem 3rem;
            transition: var(--transition);
            width: 100%;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .btn-login {
            background: var(--primary-color);
            border: none;
            border-radius: var(--border-radius);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            padding: 1rem 2rem;
            width: 100%;
            transition: var(--transition);
            cursor: pointer;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: var(--border-radius);
            color: var(--error-color);
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .success-message {
            background: rgba(5, 150, 105, 0.1);
            border: 1px solid rgba(5, 150, 105, 0.2);
            border-radius: var(--border-radius);
            color: var(--success-color);
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .session-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .session-info small {
            color: var(--text-muted);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .footer-info {
            position: fixed;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            color: var(--text-muted);
            font-size: 0.75rem;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                gap: 2rem;
                max-width: 500px;
            }
            
            .login-info {
                text-align: center;
                order: 2;
            }
            
            .login-container {
                order: 1;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .login-wrapper {
                padding: 1rem;
            }
            
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .login-icon {
                width: 60px;
                height: 60px;
            }
            
            .login-icon img {
                width: 35px;
                height: 35px;
            }
            
            .login-icon i {
                font-size: 1.5rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .form-control {
                padding: 0.875rem 0.875rem 0.875rem 2.75rem;
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .btn-login {
                padding: 0.875rem 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem 1rem;
            }
            
            .login-icon {
                width: 50px;
                height: 50px;
            }
            
            .login-icon img {
                width: 30px;
                height: 30px;
            }
            
            .login-icon i {
                font-size: 1.25rem;
            }
            
            .login-title {
                font-size: 1.25rem;
            }
            
            .form-control {
                padding: 0.75rem 0.75rem 0.75rem 2.5rem;
            }
            
            .input-icon {
                left: 0.75rem;
                font-size: 1rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Company Information Section -->
        <div class="login-info">
            <div class="company-logo">
                <?php if (!empty($siteSettings['logo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>" 
                         alt="<?php echo htmlspecialchars($siteSettings['site_title']); ?>">
                <?php endif; ?>
            </div>
            
            <p class="welcome-subtitle">
                yönetim paneline güvenli erişim sağlayın. <br>
                Sisteminizi profesyonel bir şekilde yönetin.
            </p>
            
            <ul class="features-list">
                <li>
                    <i class="fas fa-shield-alt"></i>
                    <span>Güvenli kimlik doğrulama</span>
                </li>
                <li>
                    <i class="fas fa-chart-line"></i>
                    <span>Gerçek zamanlı analitik</span>
                </li>
                <li>
                    <i class="fas fa-users"></i>
                    <span>Kullanıcı yönetimi</span>
                </li>
                <li>
                    <i class="fas fa-cog"></i>
                    <span>Gelişmiş sistem ayarları</span>
                </li>
                <li>
                    <i class="fas fa-bell"></i>
                    <span>Anlık bildirimler</span>
                </li>
            </ul>
            
            <div class="security-badge">
                <i class="fas fa-lock"></i>
                <span>SSL Şifreli Bağlantı</span>
            </div>
        </div>

        <!-- Login Form Section -->
        <div class="login-container">
            <div class="login-header">
                <div class="login-icon">
                    <?php if (!empty($siteSettings['logo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($siteSettings['logo_path']); ?>" 
                             alt="<?php echo htmlspecialchars($siteSettings['site_title']); ?>" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <?php else: ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="Kullanıcı adınızı girin" required autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Şifre</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Şifrenizi girin" required autocomplete="current-password">
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Giriş Yap</span>
                </button>
            </form>
        </div>
    </div>

    <div class="footer-info">
        <p>&copy; 2021 - 2025 l SeninSiten.com</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = document.querySelectorAll('.form-control');
            const submitBtn = document.querySelector('.btn-login');
            
            // Enhanced form interactions
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-1px)';
                    this.parentElement.style.transition = 'transform 0.2s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
                
                // Add validation feedback
                input.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        this.style.borderColor = 'var(--primary-color)';
                    } else {
                        this.style.borderColor = 'var(--border-color)';
                    }
                });
            });
            
            // Enhanced submit button with loading state
            form.addEventListener('submit', function(e) {
                const buttonSpan = submitBtn.querySelector('span');
                const buttonIcon = submitBtn.querySelector('i');
                
                buttonIcon.className = 'fas fa-spinner fa-spin';
                buttonSpan.textContent = 'Giriş yapılıyor...';
                submitBtn.disabled = true;
                
                // Add a slight delay for better UX
                setTimeout(() => {
                    // The form will submit naturally after this
                }, 300);
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Enter key to submit form
                if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                    e.preventDefault();
                    form.submit();
                }
            });
            
            // Auto-focus username field
            const usernameInput = document.getElementById('username');
            if (usernameInput) {
                setTimeout(() => {
                    usernameInput.focus();
                }, 500);
            }
            
            // Add subtle animations
            const container = document.querySelector('.login-container');
            const infoSection = document.querySelector('.login-info');
            
            // Stagger animation for info section elements
            const infoElements = infoSection.querySelectorAll('h1, p, ul, div');
            infoElements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html> 