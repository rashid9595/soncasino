<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "SMTP E-posta Ayarları";

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

// Get current SMTP settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
$stmt->execute();
$current_smtp_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Set default SMTP values
$default_smtp_settings = [
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'smtp_from_name' => 'Sistem Yönetimi',
    'smtp_from_email' => 'noreply@example.com',
    'smtp_reply_to' => 'support@example.com',
    'smtp_timeout' => 30,
    'smtp_verify_peer' => 1,
    'smtp_verify_peer_name' => 1,
    'smtp_allow_self_signed' => 0
];

$smtp_settings = array_merge($default_smtp_settings, $current_smtp_settings);

$success_message = "";
$error_message = "";
$test_result = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEditSettings) {
    if (isset($_POST['save_smtp'])) {
        try {
            // Update SMTP settings
            $smtp_settings_to_update = [
                'smtp_host' => $_POST['smtp_host'] ?? '',
                'smtp_port' => $_POST['smtp_port'] ?? 587,
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'smtp_password' => $_POST['smtp_password'] ?? '',
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'smtp_from_name' => $_POST['smtp_from_name'] ?? 'Sistem Yönetimi',
                'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
                'smtp_reply_to' => $_POST['smtp_reply_to'] ?? '',
                'smtp_timeout' => $_POST['smtp_timeout'] ?? 30,
                'smtp_verify_peer' => isset($_POST['smtp_verify_peer']) ? 1 : 0,
                'smtp_verify_peer_name' => isset($_POST['smtp_verify_peer_name']) ? 1 : 0,
                'smtp_allow_self_signed' => isset($_POST['smtp_allow_self_signed']) ? 1 : 0
            ];
            
            foreach ($smtp_settings_to_update as $key => $value) {
                $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            // Log the activity
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, ip_address) VALUES (?, 'update', 'SMTP ayarları güncellendi', ?)");
            $stmt->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
            
            $success_message = "SMTP ayarları başarıyla güncellendi!";
            
            // Refresh settings
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
            $stmt->execute();
            $current_smtp_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $smtp_settings = array_merge($default_smtp_settings, $current_smtp_settings);
            
        } catch (Exception $e) {
            $error_message = "SMTP ayarları güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
    
    // Handle test email
    if (isset($_POST['test_email'])) {
        $test_email = $_POST['test_email'] ?? '';
        $test_subject = $_POST['test_subject'] ?? 'SMTP Test E-postası';
        $test_message = $_POST['test_message'] ?? 'Bu bir test e-postasıdır. SMTP ayarlarınız başarıyla çalışıyor!';
        
        try {
            // Test SMTP connection and send email
            $test_result = testSMTPConnection($smtp_settings, $test_email, $test_subject, $test_message);
        } catch (Exception $e) {
            $test_result = "Test e-postası gönderilirken hata oluştu: " . $e->getMessage();
        }
    }
}

// Function to test SMTP connection
function testSMTPConnection($settings, $to_email, $subject, $message) {
    try {
        require_once 'vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $mail->SMTPSecure = $settings['smtp_encryption'];
        $mail->Port = $settings['smtp_port'];
        $mail->Timeout = $settings['smtp_timeout'];
        
        // SSL/TLS settings
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => $settings['smtp_verify_peer'],
                'verify_peer_name' => $settings['smtp_verify_peer_name'],
                'allow_self_signed' => $settings['smtp_allow_self_signed']
            ]
        ];
        
        // Recipients
        $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
        $mail->addAddress($to_email);
        $mail->addReplyTo($settings['smtp_reply_to']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return "Test e-postası başarıyla gönderildi! E-posta adresi: " . $to_email;
        
    } catch (Exception $e) {
        return "E-posta gönderilemedi: " . $mail->ErrorInfo;
    }
}

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

    .smtp-header {
        margin-bottom: 2rem;
        position: relative;
        padding: 2rem;
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-md);
        border-top: 4px solid var(--primary-blue-dark);
    }
    
    .smtp-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.8rem;
        color: var(--text-heading);
        position: relative;
        font-family: 'Inter', sans-serif;
        letter-spacing: -0.5px;
    }
    
    .smtp-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
        margin-bottom: 0;
        font-weight: 400;
    }

    .smtp-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .smtp-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .smtp-card-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
    }

    .smtp-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        margin: 0;
    }

    .smtp-card-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .smtp-card-body {
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

    .btn-success {
        background: linear-gradient(135deg, var(--success-green) 0%, #10b981 100%);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .btn-success:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #f59e0b 100%);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .btn-warning:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
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

    .alert-info {
        background: rgba(59, 130, 246, 0.1);
        border-color: var(--info-blue);
        color: var(--info-blue);
    }

    .smtp-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .smtp-section {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
    }

    .smtp-section h4 {
        color: var(--text-heading);
        margin-bottom: 1rem;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .smtp-section h4 i {
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
        flex-wrap: wrap;
    }

    .test-result {
        background: var(--light-gray);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-top: 1rem;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        white-space: pre-wrap;
    }

    .connection-status {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
    }

    .status-connected {
        background: rgba(5, 150, 105, 0.1);
        color: var(--success-green);
        border: 1px solid var(--success-green);
    }

    .status-disconnected {
        background: rgba(220, 38, 38, 0.1);
        color: var(--error-red);
        border: 1px solid var(--error-red);
    }

    .status-unknown {
        background: rgba(217, 119, 6, 0.1);
        color: var(--warning-orange);
        border: 1px solid var(--warning-orange);
    }

    .smtp-info {
        background: var(--ultra-light-blue);
        border: 1px solid var(--light-blue);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .smtp-info h5 {
        color: var(--text-heading);
        margin-bottom: 0.5rem;
        font-size: 1rem;
        font-weight: 600;
    }

    .smtp-info p {
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .smtp-info ul {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 0;
        padding-left: 1.5rem;
    }

    @media (max-width: 768px) {
        .smtp-header {
            padding: 1.5rem;
        }
        
        .smtp-title {
            font-size: 1.5rem;
        }
        
        .smtp-grid {
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

<div class="smtp-header animate-fade-in">
    <h1 class="smtp-title">
        <i class="bi bi-envelope-fill"></i> SMTP E-posta Ayarları
    </h1>
    <p class="smtp-subtitle">E-posta gönderimi için SMTP sunucu ayarlarını yapılandırın ve test edin.</p>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success animate-fade-in">
        <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger animate-fade-in">
        <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($test_result)): ?>
    <div class="alert alert-info animate-fade-in">
        <i class="bi bi-info-circle-fill"></i> <?php echo htmlspecialchars($test_result); ?>
    </div>
<?php endif; ?>

<div class="smtp-info animate-fade-in" style="animation-delay: 0.1s">
    <h5><i class="bi bi-info-circle"></i> SMTP Ayarları Hakkında</h5>
    <p>SMTP (Simple Mail Transfer Protocol) ayarları, sisteminizin e-posta gönderebilmesi için gereklidir.</p>
    <ul>
        <li><strong>SMTP Sunucu:</strong> E-posta sağlayıcınızın SMTP sunucu adresi (örn: smtp.gmail.com)</li>
        <li><strong>Port:</strong> SMTP port numarası (genellikle 587, 465 veya 25)</li>
        <li><strong>Şifreleme:</strong> Bağlantı güvenliği için TLS veya SSL kullanın</li>
        <li><strong>Kimlik Doğrulama:</strong> E-posta hesabınızın kullanıcı adı ve şifresi</li>
    </ul>
</div>

<form method="POST" action="" class="animate-fade-in" style="animation-delay: 0.2s">
    <!-- SMTP Server Settings -->
    <div class="smtp-card">
        <div class="smtp-card-header">
            <h3 class="smtp-card-title">
                <i class="bi bi-server"></i> SMTP Sunucu Ayarları
            </h3>
        </div>
        <div class="smtp-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">SMTP Sunucu Adresi</label>
                    <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($smtp_settings['smtp_host']); ?>" placeholder="smtp.gmail.com" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">E-posta sağlayıcınızın SMTP sunucu adresi</div>
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP Port</label>
                    <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($smtp_settings['smtp_port']); ?>" min="1" max="65535" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">SMTP port numarası (587, 465, 25)</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars($smtp_settings['smtp_username']); ?>" placeholder="your-email@gmail.com" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">E-posta hesabınızın kullanıcı adı</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Şifre</label>
                    <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars($smtp_settings['smtp_password']); ?>" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">E-posta hesabınızın şifresi veya uygulama şifresi</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Şifreleme Türü</label>
                <select class="form-control" name="smtp_encryption" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <option value="tls" <?php echo $smtp_settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (Önerilen)</option>
                    <option value="ssl" <?php echo $smtp_settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo $smtp_settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>Şifreleme Yok</option>
                </select>
                <div class="form-text">SMTP bağlantı güvenliği</div>
            </div>
        </div>
    </div>

    <!-- Email Configuration -->
    <div class="smtp-card">
        <div class="smtp-card-header">
            <h3 class="smtp-card-title">
                <i class="bi bi-envelope"></i> E-posta Yapılandırması
            </h3>
        </div>
        <div class="smtp-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Gönderen Adı</label>
                    <input type="text" class="form-control" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp_settings['smtp_from_name']); ?>" placeholder="Sistem Yönetimi" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">E-postalarda görünecek gönderen adı</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Gönderen E-posta</label>
                    <input type="email" class="form-control" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp_settings['smtp_from_email']); ?>" placeholder="noreply@example.com" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <div class="form-text">E-postaların gönderileceği adres</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Yanıt Adresi</label>
                <input type="email" class="form-control" name="smtp_reply_to" value="<?php echo htmlspecialchars($smtp_settings['smtp_reply_to']); ?>" placeholder="support@example.com" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                <div class="form-text">E-postalara yanıt verilecek adres</div>
            </div>
        </div>
    </div>

    <!-- Advanced Settings -->
    <div class="smtp-card">
        <div class="smtp-card-header">
            <h3 class="smtp-card-title">
                <i class="bi bi-gear"></i> Gelişmiş Ayarlar
            </h3>
        </div>
        <div class="smtp-card-body">
            <div class="form-group">
                <label class="form-label">Bağlantı Zaman Aşımı (Saniye)</label>
                <input type="number" class="form-control" name="smtp_timeout" value="<?php echo htmlspecialchars($smtp_settings['smtp_timeout']); ?>" min="10" max="300" <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                <div class="form-text">SMTP bağlantısı için maksimum bekleme süresi</div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="smtp_verify_peer" id="smtp_verify_peer" <?php echo $smtp_settings['smtp_verify_peer'] ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="smtp_verify_peer">SSL Sertifikasını Doğrula</label>
                </div>
                <div class="form-text">SMTP sunucusunun SSL sertifikasını doğrular</div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="smtp_verify_peer_name" id="smtp_verify_peer_name" <?php echo $smtp_settings['smtp_verify_peer_name'] ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="smtp_verify_peer_name">Sunucu Adını Doğrula</label>
                </div>
                <div class="form-text">SMTP sunucusunun adını SSL sertifikasında doğrular</div>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="smtp_allow_self_signed" id="smtp_allow_self_signed" <?php echo $smtp_settings['smtp_allow_self_signed'] ? 'checked' : ''; ?> <?php echo !$canEditSettings ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="smtp_allow_self_signed">Kendi İmzalı Sertifikalara İzin Ver</label>
                </div>
                <div class="form-text">Kendi imzalı SSL sertifikalarını kabul eder (güvenlik riski)</div>
            </div>
        </div>
    </div>

    <!-- Test Email -->
    <div class="smtp-card">
        <div class="smtp-card-header">
            <h3 class="smtp-card-title">
                <i class="bi bi-send"></i> Test E-postası Gönder
            </h3>
        </div>
        <div class="smtp-card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Test E-posta Adresi</label>
                    <input type="email" class="form-control" name="test_email" placeholder="test@example.com" required>
                    <div class="form-text">Test e-postasının gönderileceği adres</div>
                </div>
                <div class="form-group">
                    <label class="form-label">E-posta Konusu</label>
                    <input type="text" class="form-control" name="test_subject" value="SMTP Test E-postası" required>
                    <div class="form-text">Test e-postasının konusu</div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">E-posta İçeriği</label>
                <textarea class="form-control" name="test_message" rows="4" required>Bu bir test e-postasıdır. SMTP ayarlarınız başarıyla çalışıyor!

Gönderen: <?php echo htmlspecialchars($smtp_settings['smtp_from_name']); ?>
Tarih: <?php echo date('d.m.Y H:i:s'); ?>
Sunucu: <?php echo htmlspecialchars($smtp_settings['smtp_host']); ?>:<?php echo htmlspecialchars($smtp_settings['smtp_port']); ?></textarea>
                <div class="form-text">Test e-postasının içeriği</div>
            </div>

            <div class="btn-group">
                <button type="submit" name="test_email" class="btn btn-warning">
                    <i class="bi bi-send"></i> Test E-postası Gönder
                </button>
            </div>
        </div>
    </div>

    <?php if ($canEditSettings): ?>
    <div class="btn-group">
        <button type="submit" name="save_smtp" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> SMTP Ayarlarını Kaydet
        </button>
        <a href="settings.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Ayarlara Dön
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-house"></i> Ana Sayfa
        </a>
    </div>
    <?php else: ?>
    <div class="btn-group">
        <a href="settings.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Ayarlara Dön
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-house"></i> Ana Sayfa
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

    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const testEmail = document.querySelector('input[name="test_email"]');
        if (testEmail && testEmail.value && !isValidEmail(testEmail.value)) {
            e.preventDefault();
            alert('Lütfen geçerli bir e-posta adresi girin.');
            testEmail.focus();
        }
    });

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 