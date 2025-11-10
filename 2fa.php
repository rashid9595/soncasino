<?php
// Tüm hataları ekrana yazdır
error_reporting(E_ALL); // Tüm hatalar
ini_set('display_errors', 1); // Hata mesajlarını ekrana yazdır

session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "2FA Ayarları";

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get user's current 2FA status
$stmt = $db->prepare("SELECT * FROM administrators WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();

// Ensure role_id is set in session
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != $user['role_id']) {
    $_SESSION['role_id'] = $user['role_id'];
}

$has2FA = !empty($user['secret_key']) && $user['secret_key'] !== 'yok';
$justEnabled2FA = isset($_SESSION['just_enabled_2fa']) && $_SESSION['just_enabled_2fa'] === true;

// If user has 2FA disabled, mark as verified
if (!$has2FA && !$justEnabled2FA) {
    $_SESSION['2fa_verified'] = true;
}

// Handle form submissions for enabling/disabling 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enable_2fa'])) {
        // Generate new secret key in standard format (16 characters)
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret_key = '';
        for ($i = 0; $i < 16; $i++) {
            $secret_key .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Update user's secret key
        $stmt = $db->prepare("UPDATE administrators SET secret_key = ? WHERE id = ?");
        if ($stmt->execute([$secret_key, $_SESSION['admin_id']])) {
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', '2FA etkinleştirildi', NOW())
            ");
            $stmt->execute([$_SESSION['admin_id']]);
            
            $success = "2FA başarıyla etkinleştirildi";
            $has2FA = true;
            $user['secret_key'] = $secret_key;
            
            // When enabling 2FA, force verification
            $_SESSION['2fa_verified'] = false;
            $_SESSION['just_enabled_2fa'] = true;
            
            // Create QR code immediately after enabling 2FA
            $totp = \OTPHP\TOTP::create($secret_key);
            $totp->setLabel('admin@trendxgaming.com');
            $totp->setIssuer('Admin Panel');
            $provisioning_uri = $totp->getProvisioningUri();
            
            // Use QR Server API
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($provisioning_uri);
            
            // Save QR code to file
            $qrCodePath = 'qr_codes/' . $_SESSION['admin_id'] . '_qr.png';
            
            // Try to download the QR code
            $qrCodeData = @file_get_contents($qrCodeUrl);
            if ($qrCodeData !== false) {
                file_put_contents($qrCodePath, $qrCodeData);
                $qrCodeUrl = $qrCodePath;
            }
        } else {
            $error = "2FA etkinleştirilirken bir hata oluştu";
        }
    } elseif (isset($_POST['disable_2fa'])) {
        // Verify current password
        $password = $_POST['current_password'];
        $code = trim($_POST['auth_code']);
        
        if (empty($code)) {
            $error = "Doğrulama kodu gereklidir";
        } else if (password_verify($password, $user['password'])) {
            // Verify 2FA code
            $totp = \OTPHP\TOTP::create($user['secret_key']);
            
            // Daha esnek doğrulama için zaman penceresini genişlet
            if ($totp->verify($code, null, 1)) {
                // Disable 2FA by setting secret_key to 'yok'
                $stmt = $db->prepare("UPDATE administrators SET secret_key = 'yok' WHERE id = ?");
                if ($stmt->execute([$_SESSION['admin_id']])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'update', '2FA devre dışı bırakıldı', NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id']]);
                    
                    $success = "2FA başarıyla devre dışı bırakıldı";
                    $has2FA = false;
                    $user['secret_key'] = 'yok';
                    
                    // When disabling 2FA, mark as verified
                    $_SESSION['2fa_verified'] = true;
                } else {
                    $error = "2FA devre dışı bırakılırken bir hata oluştu";
                }
            } else {
                $error = "Geçersiz doğrulama kodu. Lütfen tekrar deneyin.";
            }
        } else {
            $error = "Geçersiz şifre";
        }
    } elseif (isset($_POST['verify_code'])) {
        $code = trim($_POST['auth_code']);
        
        if (empty($code)) {
            $error = "Doğrulama kodu gereklidir";
        } else {
            // Debug bilgisi ekleyelim
            error_log("Verifying code: " . $code . " for user: " . $_SESSION['admin_id']);
            error_log("User secret key: " . $user['secret_key']);
            
            $totp = \OTPHP\TOTP::create($user['secret_key']);
            
            // Daha esnek doğrulama için zaman penceresini genişlet
            // 2 önceki ve 2 sonraki zaman penceresini kabul et (toplam 5 pencere)
            if ($totp->verify($code, null, 2)) {
                $_SESSION['2fa_verified'] = true;
                
                // Kullanıcı bilgilerini tekrar al ve role_id'yi güncelle
                $stmt = $db->prepare("SELECT * FROM administrators WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $userData = $stmt->fetch();
                $_SESSION['role_id'] = $userData['role_id'];
                
                $success = "2FA doğrulaması başarılı!";
                
                // Doğrulama başarılı olduğunda just_enabled_2fa durumunu kaldır
                if (isset($_SESSION['just_enabled_2fa'])) {
                    unset($_SESSION['just_enabled_2fa']);
                }
                
                // Eğer giriş yaparken 2FA doğrulaması yapılıyorsa, index.php'ye yönlendir
                if (isset($_GET['verify']) && $_GET['verify'] == 1) {
                    header("Location: index.php");
                    exit();
                }
            } else {
                // Hata durumunda daha fazla bilgi ekleyelim
                error_log("Code verification failed for user: " . $_SESSION['admin_id']);
                $error = "Geçersiz doğrulama kodu. Lütfen tekrar deneyin.";
            }
        }
    }
}

// Create QR code if 2FA is enabled
if ($has2FA) {
    // Create a TOTP object
    $totp = \OTPHP\TOTP::create($user['secret_key']);
    $totp->setLabel('admin@trendxgaming.com'); // Set the label for the QR code
    $totp->setIssuer('Admin Panel'); // Set the issuer
    
    // Generate the provisioning URI (otpauth://...)
    $provisioning_uri = $totp->getProvisioningUri();
    
    // Use QR Server API
    try {
        // Create QR code URL using QR Server API
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($provisioning_uri);
        
        // Save QR code to file
        $qrCodePath = 'qr_codes/' . $_SESSION['admin_id'] . '_qr.png';
        
        // Try to download the QR code
        $qrCodeData = @file_get_contents($qrCodeUrl);
        if ($qrCodeData !== false) {
            file_put_contents($qrCodePath, $qrCodeData);
            $qrCodeUrl = $qrCodePath;
        } else {
            // If download fails, use the direct URL
            error_log("Failed to download QR code, using direct URL");
        }
    } catch (Exception $e) {
        // If QR code generation failed, log the error
        error_log("QR Code generation failed: " . $e->getMessage());
        $qrCodeUrl = ''; // Set empty URL if generation fails
    }
}

// Start output buffering
ob_start();
?>

<style>
    /* 2FA Page Custom Styles */
    .qr-code-container {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.6));
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        transition: all 0.4s ease;
        display: flex;
        justify-content: center;
        max-width: 300px;
        margin: 0 auto 2rem;
    }
    
    .qr-code-container:hover {
        transform: scale(1.05);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
    }
    
    .qr-code-container img {
        border: 8px solid white;
        border-radius: 8px;
    }
    
    .secret-key-container {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.6), rgba(30, 41, 59, 0.4));
        border-radius: 12px;
        padding: 15px;
        margin: 1.5rem 0;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        border-left: 4px solid var(--blue-500);
    }
    
    code {
        background-color: rgba(0, 0, 0, 0.2);
        color: var(--blue-300);
        padding: 4px 8px;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-weight: 600;
        letter-spacing: 1px;
    }
    
    .auth-input {
        font-size: 2rem;
        letter-spacing: 10px;
        text-align: center;
        font-weight: 700;
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.6));
        border: 2px solid rgba(59, 130, 246, 0.3);
        transition: all 0.3s ease;
    }
    
    .auth-input:focus {
        border-color: var(--blue-500);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2), 0 5px 15px rgba(59, 130, 246, 0.2);
        transform: translateY(-3px);
    }
    
    .status-icon {
        font-size: 4.5rem;
        height: 100px;
        width: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 auto 1.5rem;
        position: relative;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .status-icon.active {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
        color: var(--green-400);
        box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
    }
    
    .status-icon.active:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 15px 35px rgba(16, 185, 129, 0.4);
    }
    
    .status-icon.inactive {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.1));
        color: var(--red-400);
        box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
    }
    
    .status-icon.inactive:hover {
        transform: scale(1.1) rotate(-5deg);
        box-shadow: 0 15px 35px rgba(239, 68, 68, 0.4);
    }
    
    .status-icon::after {
        content: '';
        position: absolute;
        top: -5px;
        left: -5px;
        right: -5px;
        bottom: -5px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.1);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.5;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .status-badge {
        font-size: 1rem;
        padding: 0.6rem 1.2rem;
        border-radius: 30px;
        font-weight: 700;
        letter-spacing: 1px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
    }
    
    .status-badge.active {
        background: linear-gradient(135deg, var(--green-500), var(--green-600));
    }
    
    .status-badge.inactive {
        background: linear-gradient(135deg, var(--red-500), var(--red-600));
    }
    
    .info-card {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.4), rgba(30, 41, 59, 0.2));
        border-radius: 12px;
        margin-top: 2rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        position: relative;
        overflow: hidden;
    }
    
    .info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, var(--blue-400), var(--purple-400), var(--blue-400));
    }
    
    .info-card .card-header {
        background: linear-gradient(90deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.6));
        border-bottom: 1px solid rgba(71, 85, 105, 0.3);
    }
    
    .info-list {
        list-style-type: none;
        padding-left: 0;
    }
    
    .info-list li {
        padding: 0.6rem 0;
        border-bottom: 1px solid rgba(71, 85, 105, 0.2);
        transition: all 0.3s ease;
    }
    
    .info-list li:last-child {
        border-bottom: none;
    }
    
    .info-list li:hover {
        transform: translateX(5px);
        color: var(--blue-300);
    }
    
    .info-list li::before {
        content: '→';
        margin-right: 10px;
        color: var(--blue-500);
        font-weight: bold;
    }
    
    .app-list {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .app-item {
        background: linear-gradient(145deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.4));
        border-radius: 10px;
        padding: 0.8rem 1.2rem;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
        border-left: 3px solid var(--blue-500);
    }
    
    .app-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    
    .verify-container {
        background: linear-gradient(145deg, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.6));
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        margin-bottom: 2rem;
        border-left: 4px solid var(--blue-500);
    }
    
    .animate-fade-in {
        animation: fadeIn 0.5s ease-out forwards;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card animate-fade-in" style="animation-delay: 0.1s">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> <span style="color: rgb(13, 202, 240);">İki Faktörlü Kimlik Doğrulama (2FA)</span></h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                <div class="alert alert-success animate-fade-in"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger animate-fade-in"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="text-center mb-4 animate-fade-in" style="animation-delay: 0.2s">
                    <div class="status-icon <?php echo $has2FA ? 'active' : 'inactive'; ?>">
                        <i class="bi <?php echo $has2FA ? 'bi-shield-check' : 'bi-shield-x'; ?>"></i>
                    </div>
                    <h4 class="mb-3"><span style="color: rgb(13, 202, 240);">2FA Durumu:</span> 
                        <span class="status-badge <?php echo $has2FA ? 'active' : 'inactive'; ?>">
                            <?php echo $has2FA ? 'Aktif' : 'Pasif'; ?>
                        </span>
                    </h4>
                </div>
                
                <?php if ($has2FA && isset($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true): ?>
                <!-- 2FA Aktif ve Doğrulanmış -->
                <div class="text-center mb-4 animate-fade-in" style="animation-delay: 0.3s">
                    <p class="mb-3" style="color: rgb(13, 202, 240);">2FA aktif. Giriş yaparken Google Authenticator kodunuzu kullanmanız gerekecek.</p>
                    <div class="qr-code-container mb-3">
                        <?php if (!empty($qrCodeUrl)): ?>
                        <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" class="img-fluid">
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            QR kod oluşturulamadı. Lütfen aşağıdaki gizli anahtarı manuel olarak girin.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="secret-key-container">
                        <strong>Gizli Anahtar:</strong>
                        <code class="ms-2"><?php echo chunk_split($user['secret_key'], 4, ' '); ?></code>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Bu QR kodu veya gizli anahtarı güvenli bir yerde saklayın. Telefonunuzu kaybederseniz, 2FA'yı yeniden yapılandırmak için bunlara ihtiyacınız olacak.
                    </div>
                </div>
                
                <form method="post" class="needs-validation animate-fade-in" novalidate style="animation-delay: 0.4s">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">2FA'yı devre dışı bırakmak için mevcut şifrenizi girin:</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="disable_auth_code" class="form-label">2FA'yı devre dışı bırakmak için doğrulama kodunuzu girin:</label>
                        <input type="text" class="form-control auth-input" id="disable_auth_code" name="auth_code" 
                               placeholder="000000" maxlength="6" autocomplete="off" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="disable_2fa" class="btn btn-danger">
                            <i class="bi bi-shield-x me-2"></i>2FA'yı Devre Dışı Bırak
                        </button>
                    </div>
                </form>
                <?php elseif (!$has2FA): ?>
                <!-- 2FA Devre Dışı -->
                <div class="text-center mb-4 animate-fade-in" style="animation-delay: 0.3s">
                    <p>2FA şu anda devre dışı. Hesabınızı daha güvenli hale getirmek için 2FA'yı etkinleştirmenizi öneririz.</p>
                </div>
                
                <form method="post" class="animate-fade-in" style="animation-delay: 0.4s">
                    <div class="d-grid">
                        <button type="submit" name="enable_2fa" class="btn btn-success">
                            <i class="bi bi-shield-check me-2"></i>2FA'yı Etkinleştir
                        </button>
                    </div>
                </form>
                <?php elseif (isset($_GET['verify']) && $_GET['verify'] == 1): ?>
                <!-- 2FA Doğrulama Formu (Giriş Yaparken) -->
                <div class="verify-container animate-fade-in" style="animation-delay: 0.3s">
                    <div class="text-center mb-4">
                        <p class="mb-3">Lütfen Google Authenticator uygulamanızdan aldığınız 6 haneli kodu girin:</p>
                        
                        <?php if (isset($user['secret_key']) && !empty($user['secret_key'])): ?>
                        <div class="secret-key-container">
                            <p class="mb-2"><strong>Gizli Anahtarınız:</strong> <code><?php echo chunk_split($user['secret_key'], 4, ' '); ?></code></p>
                            <p class="mb-0">Eğer Google Authenticator'da bu anahtarı manuel olarak girdiyseniz, lütfen doğru girdiğinizden emin olun.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-4">
                            <label for="auth_code" class="form-label">Doğrulama Kodu</label>
                            <input type="text" class="form-control form-control-lg auth-input" id="auth_code" name="auth_code" 
                                placeholder="000000" maxlength="6" autocomplete="off" required>
                            <div class="form-text">Google Authenticator uygulamanızda görünen 6 haneli kodu girin.</div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="verify_code" class="btn btn-primary btn-lg">
                                <i class="bi bi-shield-check me-2"></i>Doğrula
                            </button>
                            <a href="login.php" class="btn btn-outline-secondary">Giriş Sayfasına Dön</a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="info-card animate-fade-in" style="animation-delay: 0.5s">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> 2FA Hakkında</h5>
            </div>
            <div class="card-body">
                <h6>İki Faktörlü Kimlik Doğrulama Nedir?</h6>
                <p>İki faktörlü kimlik doğrulama (2FA), hesabınıza ekstra bir güvenlik katmanı ekler. Giriş yaparken şifrenize ek olarak, telefonunuzdaki bir uygulama tarafından oluşturulan benzersiz bir kod gerekir.</p>
                
                <h6 class="mt-4">Nasıl Çalışır?</h6>
                <ol class="info-list">
                    <li>Google Authenticator gibi bir 2FA uygulaması indirin</li>
                    <li>QR kodu tarayın veya gizli anahtarı manuel olarak girin</li>
                    <li>Uygulama 30 saniyede bir yeni bir kod oluşturacak</li>
                    <li>Giriş yaparken bu kodu kullanın</li>
                </ol>
                
                <h6 class="mt-4">Önerilen 2FA Uygulamaları:</h6>
                <div class="app-list">
                    <div class="app-item">Google Authenticator</div>
                    <div class="app-item">Authy</div>
                    <div class="app-item">Microsoft Authenticator</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to elements
    const animateElements = document.querySelectorAll('.animate-fade-in');
    animateElements.forEach((element, index) => {
        const delay = parseFloat(element.style.animationDelay || '0s');
        element.style.opacity = '0';
        setTimeout(() => {
            element.style.opacity = '1';
        }, (delay * 1000) + 100);
    });
    
    // Focus and select text in authentication input fields
    const authInputs = document.querySelectorAll('.auth-input');
    authInputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.select();
        });
    });
});
</script>

<?php
// Get the buffered content
$pageContent = ob_get_clean();

// Include layout with the pageContent
include 'includes/layout.php';
?> 