<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Sistem Ayarları";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Get user info
$stmt = $db->prepare("SELECT * FROM administrators WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();

// Get user permissions
$stmt = $db->prepare("SELECT * FROM admin_permissions WHERE role_id = ?");
$stmt->execute([$_SESSION['role_id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userPermissions = [];
foreach ($permissions as $permission) {
    $userPermissions[$permission['menu_item']] = [
        'view' => $permission['can_view'],
        'create' => $permission['can_create'],
        'edit' => $permission['can_edit'],
        'delete' => $permission['can_delete']
    ];
}

$isAdmin = ($_SESSION['role_id'] == 1);
$canViewSettings = $isAdmin || ($userPermissions['settings']['view'] ?? false);
$canEditSettings = $isAdmin || ($userPermissions['settings']['edit'] ?? false);

if (!$canViewSettings) {
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEditSettings) {
    $success_message = "";
    $error_message = "";
    
    try {
        // Update system settings
        $settings_to_update = [
            'site_name' => $_POST['site_name'] ?? '',
            'site_description' => $_POST['site_description'] ?? '',
            'contact_email' => $_POST['contact_email'] ?? '',
            'contact_phone' => $_POST['contact_phone'] ?? '',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'maintenance_message' => $_POST['maintenance_message'] ?? '',
            'max_login_attempts' => $_POST['max_login_attempts'] ?? 5,
            'session_timeout' => $_POST['session_timeout'] ?? 30,
            'dashboard_welcome' => $_POST['dashboard_welcome'] ?? 'Hoş Geldiniz',
            'currency_symbol' => $_POST['currency_symbol'] ?? '₺',
            'min_deposit' => $_POST['min_deposit'] ?? 10,
            'max_deposit' => $_POST['max_deposit'] ?? 10000,
            'min_withdrawal' => $_POST['min_withdrawal'] ?? 50,
            'max_withdrawal' => $_POST['max_withdrawal'] ?? 5000,
            'withdrawal_fee' => $_POST['withdrawal_fee'] ?? 0,
            'deposit_bonus_percentage' => $_POST['deposit_bonus_percentage'] ?? 0,
            'auto_approve_deposits' => isset($_POST['auto_approve_deposits']) ? 1 : 0,
            'auto_approve_withdrawals' => isset($_POST['auto_approve_withdrawals']) ? 1 : 0,
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? 587,
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls'
        ];
        
        foreach ($settings_to_update as $key => $value) {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        // Log the activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'update', 'Sistem ayarları güncellendi', ?)");
        $stmt->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
        
        $success_message = "Ayarlar başarıyla güncellendi!";
        
    } catch (Exception $e) {
        $error_message = "Ayarlar güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
$stmt->execute();
$current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Set default values if not exists
$default_settings = [
    'site_name' => 'Yönetim Paneli',
    'site_description' => 'Güvenli yönetim paneli',
    'contact_email' => 'admin@example.com',
    'contact_phone' => '+90 555 123 4567',
    'maintenance_mode' => 0,
    'maintenance_message' => 'Sistem bakımda, lütfen daha sonra tekrar deneyin.',
    'max_login_attempts' => 5,
    'session_timeout' => 30,
    'dashboard_welcome' => 'Hoş Geldiniz',
    'currency_symbol' => '₺',
    'min_deposit' => 10,
    'max_deposit' => 10000,
    'min_withdrawal' => 50,
    'max_withdrawal' => 5000,
    'withdrawal_fee' => 0,
    'deposit_bonus_percentage' => 0,
    'auto_approve_deposits' => 0,
    'auto_approve_withdrawals' => 0,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls'
];

$settings = array_merge($default_settings, $current_settings);

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
        
        /* Corporate Gradients */
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        --secondary-gradient: linear-gradient(135deg, #1e40af 0%, #60a5fa 100%);
        --tertiary-gradient: linear-gradient(135deg, #1e3a8a 0%, #93c5fd 100%);
        
        /* Corporate Theme */
        --bg-primary: var(--light-gray);
        --bg-secondary: var(--ultra-light-blue);
        --card-bg: var(--white);
        --card-border: var(--medium-gray);
        --text-primary: var(--text-gray);
        --text-secondary: var(--dark-gray);
        --text-heading: var(--primary-blue-dark);
        
        /* Corporate Shadows */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
        
        /* Layout */
        --border-radius: 8px;
        --border-radius-lg: 12px;
        --border-radius-sm: 6px;
    }

    body {
        background: var(--bg-primary);
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: var(--text-primary);
        line-height: 1.6;
    }

    @keyframes fadeIn {
        from { 
            opacity: 0; 
            transform: translateY(20px);
        }
        to { 
            opacity: 1; 
            transform: translateY(0);
        }
    }

    .settings-header {
        margin-bottom: 2rem;
        position: relative;
        padding: 2rem;
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-md);
        border-top: 4px solid var(--primary-blue-dark);
    }
    
    .settings-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.8rem;
        color: var(--text-heading);
        position: relative;
        font-family: 'Inter', sans-serif;
        letter-spacing: -0.5px;
    }
    
    .settings-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
        margin-bottom: 0;
        font-weight: 400;
    }

    .settings-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .settings-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .settings-card-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
    }

    .settings-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        margin: 0;
    }

    .settings-card-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .settings-card-body {
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text-heading);
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: var(--white);
        color: var(--text-primary);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-control:disabled {
        background: var(--light-gray);
        color: var(--text-secondary);
        cursor: not-allowed;
    }

    .form-text {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .form-check {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
    }

    .form-check-input {
        margin-right: 0.5rem;
        width: 1.2rem;
        height: 1.2rem;
        accent-color: var(--primary-blue);
    }

    .form-check-label {
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-secondary {
        background: var(--light-gray);
        color: var(--text-primary);
        border: 1px solid var(--card-border);
    }

    .btn-secondary:hover {
        background: var(--medium-gray);
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border: 1px solid;
    }

    .alert-success {
        background: rgba(5, 150, 105, 0.1);
        border-color: var(--success-green);
        color: var(--success-green);
    }

    .alert-danger {
        background: rgba(220, 38, 38, 0.1);
        border-color: var(--error-red);
        color: var(--error-red);
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .settings-section {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
    }

    .settings-section h4 {
        color: var(--text-heading);
        margin-bottom: 1rem;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .settings-section h4 i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .animate-fade-in {
        animation: fadeIn 0.8s ease forwards;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .btn-group {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    @media (max-width: 768px) {
        .settings-header {
            padding: 1.5rem;
        }
        
        .settings-title {
            font-size: 1.5rem;
        }
        
        .settings-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .btn-group {
            flex-direction: column;
        }
    }
</style>

<div class="settings-header animate-fade-in">
    <h1 class="settings-title">
        <i class="bi bi-gear-fill"></i> Sistem Ayarları
    </h1>
    <p class="settings-subtitle">Sistem genelinde kullanılan ayarları yönetin ve yapılandırın.</p>
</div>

<?php if (isset($success_message) && !empty($success_message)): ?>
    <div class="alert alert-success animate-fade-in">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message) && !empty($error_message)): ?>
    <div class="alert alert-danger animate-fade-in">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<form method="POST" action="" class="animate-fade-in" style="animation-delay: 0.1s">
    <!-- General Settings -->
    <div class="settings-card">
        <div class="settings-card-header">
            <h3 class="settings-card-title">
                <i class="bi bi-globe"></i> Genel Ayarlar
            </h3>
        </div>
        <div class="settings-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Site Adı</label>
                    <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Sitenizin görünen adı</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Site Açıklaması</label>
                    <input type="text" class="form-control" name="site_description" value="<?php echo htmlspecialchars($settings['site_description']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Sitenizin kısa açıklaması</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">İletişim E-posta</label>
                    <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Sistem iletişim e-posta adresi</div>
                </div>
                <div class="form-group">
                    <label class="form-label">İletişim Telefon</label>
                    <input type="text" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Sistem iletişim telefon numarası</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Para Birimi Sembolü</label>
                    <input type="text" class="form-control" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Sistemde kullanılacak para birimi sembolü (₺, $, €)</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Dashboard Karşılama Mesajı</label>
                    <input type="text" class="form-control" name="dashboard_welcome" value="<?php echo htmlspecialchars($settings['dashboard_welcome']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Dashboard'da görünecek karşılama mesajı</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Settings -->
    <div class="settings-card">
        <div class="settings-card-header">
            <h3 class="settings-card-title">
                <i class="bi bi-shield-lock"></i> Güvenlik Ayarları
            </h3>
        </div>
        <div class="settings-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Maksimum Giriş Denemesi</label>
                    <input type="number" class="form-control" name="max_login_attempts" value="<?php echo htmlspecialchars($settings['max_login_attempts']); ?>" min="1" max="10" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Hesap kilitleme öncesi maksimum giriş denemesi</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Oturum Zaman Aşımı (Dakika)</label>
                    <input type="number" class="form-control" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout']); ?>" min="5" max="480" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Oturum otomatik kapanma süresi</div>
                </div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="maintenance_mode" id="maintenance_mode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="maintenance_mode">Bakım Modu</label>
                </div>
                <div class="form-text">Aktif olduğunda sadece yöneticiler siteye erişebilir</div>
            </div>

            <div class="form-group">
                <label class="form-label">Bakım Modu Mesajı</label>
                <textarea class="form-control" name="maintenance_message" rows="3" <?php echo !$canEditSettings ? 'disabled' : ''; ?>><?php echo htmlspecialchars($settings['maintenance_message']); ?></textarea>
                <div class="form-text">Bakım modunda görünecek mesaj</div>
            </div>
        </div>
    </div>

    <!-- Financial Settings -->
    <div class="settings-card">
        <div class="settings-card-header">
            <h3 class="settings-card-title">
                <i class="bi bi-cash-stack"></i> Finansal Ayarlar
            </h3>
        </div>
        <div class="settings-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Minimum Para Yatırma</label>
                    <input type="number" class="form-control" name="min_deposit" value="<?php echo htmlspecialchars($settings['min_deposit']); ?>" min="1" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Kullanıcıların yatırabileceği minimum tutar</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Maksimum Para Yatırma</label>
                    <input type="number" class="form-control" name="max_deposit" value="<?php echo htmlspecialchars($settings['max_deposit']); ?>" min="1" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Kullanıcıların yatırabileceği maksimum tutar</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Minimum Para Çekme</label>
                    <input type="number" class="form-control" name="min_withdrawal" value="<?php echo htmlspecialchars($settings['min_withdrawal']); ?>" min="1" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Kullanıcıların çekebileceği minimum tutar</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Maksimum Para Çekme</label>
                    <input type="number" class="form-control" name="max_withdrawal" value="<?php echo htmlspecialchars($settings['max_withdrawal']); ?>" min="1" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Kullanıcıların çekebileceği maksimum tutar</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Para Çekme Ücreti (%)</label>
                    <input type="number" class="form-control" name="withdrawal_fee" value="<?php echo htmlspecialchars($settings['withdrawal_fee']); ?>" min="0" max="10" step="0.1" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Para çekme işlemlerinde alınacak ücret yüzdesi</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Para Yatırma Bonusu (%)</label>
                    <input type="number" class="form-control" name="deposit_bonus_percentage" value="<?php echo htmlspecialchars($settings['deposit_bonus_percentage']); ?>" min="0" max="100" step="0.1" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">Para yatırma işlemlerinde verilecek bonus yüzdesi</div>
                </div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="auto_approve_deposits" id="auto_approve_deposits" <?php echo $settings['auto_approve_deposits'] ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="auto_approve_deposits">Para Yatırma İşlemlerini Otomatik Onayla</label>
                </div>
                <div class="form-text">Aktif olduğunda para yatırma işlemleri otomatik onaylanır</div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="auto_approve_withdrawals" id="auto_approve_withdrawals" <?php echo $settings['auto_approve_withdrawals'] ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="auto_approve_withdrawals">Para Çekme İşlemlerini Otomatik Onayla</label>
                </div>
                <div class="form-text">Aktif olduğunda para çekme işlemleri otomatik onaylanır</div>
            </div>
        </div>
    </div>

    <!-- Email Settings -->
    <div class="settings-card">
        <div class="settings-card-header">
            <h3 class="settings-card-title">
                <i class="bi bi-envelope"></i> E-posta Ayarları
            </h3>
        </div>
        <div class="settings-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">SMTP Sunucu</label>
                    <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">SMTP sunucu adresi (örn: smtp.gmail.com)</div>
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Port</label>
                    <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" min="1" max="65535" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">SMTP port numarası (genellikle 587 veya 465)</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">SMTP Kullanıcı Adı</label>
                    <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">SMTP e-posta hesabı kullanıcı adı</div>
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Şifre</label>
                    <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">SMTP e-posta hesabı şifresi</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">SMTP Şifreleme</label>
                <select class="form-control" name="smtp_encryption" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>Şifreleme Yok</option>
                </select>
                <div class="form-text">SMTP bağlantı şifreleme türü</div>
            </div>
        </div>
    </div>

    <?php if ($canEditSettings): ?>
    <div class="btn-group">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Ayarları Kaydet
        </button>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
    <?php else: ?>
    <div class="btn-group">
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
    <?php endif; ?>
</form>

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

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 