<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Drakon API Ayarları";

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
if (!isset($_SESSION['role_id'])) {
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

// If user is admin (role_id = 1), grant full access
$isAdmin = ($_SESSION['role_id'] == 1);

if (!$isAdmin) {
    $stmt = $db->prepare("
        SELECT ap.* 
        FROM admin_permissions ap 
        WHERE ap.role_id = ? AND ap.menu_item = 'settings' AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Get statistics for dashboard
try {
    // Total settings count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM drakon_api_settings");
    $stmt->execute();
    $totalSettings = $stmt->fetch()['total'];
    
    // Check if Agent Token is configured
    $stmt = $db->prepare("SELECT COUNT(*) as configured FROM drakon_api_settings WHERE agent_token IS NOT NULL AND agent_token != ''");
    $stmt->execute();
    $agentTokenConfigured = $stmt->fetch()['configured'];
    
    // Check if Agent Secret Key is configured
    $stmt = $db->prepare("SELECT COUNT(*) as configured FROM drakon_api_settings WHERE agent_secret_key IS NOT NULL AND agent_secret_key != ''");
    $stmt->execute();
    $agentSecretKeyConfigured = $stmt->fetch()['configured'];
    
    // Check if Agent Code is configured
    $stmt = $db->prepare("SELECT COUNT(*) as configured FROM drakon_api_settings WHERE agent_code IS NOT NULL AND agent_code != ''");
    $stmt->execute();
    $agentCodeConfigured = $stmt->fetch()['configured'];
    
    // Check if settings are complete
    $stmt = $db->prepare("SELECT COUNT(*) as complete FROM drakon_api_settings WHERE agent_token IS NOT NULL AND agent_token != '' AND agent_secret_key IS NOT NULL AND agent_secret_key != '' AND agent_code IS NOT NULL AND agent_code != ''");
    $stmt->execute();
    $settingsComplete = $stmt->fetch()['complete'];
    
    // Get last update time
    $stmt = $db->prepare("SELECT MAX(updated_at) as last_update FROM drakon_api_settings");
    $stmt->execute();
    $lastUpdate = $stmt->fetch()['last_update'];
    
    // Count recent updates (last 7 days)
    $stmt = $db->prepare("SELECT COUNT(*) as recent FROM drakon_api_settings WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recentUpdates = $stmt->fetch()['recent'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalSettings = $agentTokenConfigured = $agentSecretKeyConfigured = $agentCodeConfigured = $settingsComplete = $recentUpdates = 0;
    $lastUpdate = null;
}

// Create table if not exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS drakon_api_settings (
            id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            agent_token varchar(255) NOT NULL,
            agent_secret_key varchar(255) NOT NULL,
            agent_code varchar(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Check if settings exist, if not insert default values
    $stmt = $db->query("SELECT COUNT(*) FROM drakon_api_settings");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $db->exec("
            INSERT INTO drakon_api_settings (agent_token, agent_secret_key, agent_code) VALUES
            ('xxxd2tclvBMGsSy75qABM2SpdfjGMwmV', '8bk1AfZaKtqSd2cLwyMbZ5PleaVQymQJ', 'garsbetlive')
        ");
    }
} catch (PDOException $e) {
    // Log the error
    error_log('Drakon API Settings Table Error: ' . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        if (!$isAdmin && (!isset($userPermissions['settings']['edit']) || !$userPermissions['settings']['edit'])) {
            echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.']);
            exit();
        }
        
        $stmt = $db->prepare("
            UPDATE drakon_api_settings 
            SET " . $_POST['key'] . " = ?
            WHERE id = 1
        ");
        
        try {
            $stmt->execute([$_POST['value']]);
            echo json_encode(['status' => 'success', 'message' => 'Ayar başarıyla güncellendi.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Ayar güncellenirken bir hata oluştu.']);
        }
        exit();
    }
}

// Get settings
$stmt = $db->prepare("SELECT * FROM drakon_api_settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: Print settings (remove in production)
if ($isAdmin) {
    echo "<!-- Debug: Settings loaded: " . print_r($settings, true) . " -->";
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
        
        /* Corporate Gradients - Dark to Light Blue Theme */
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        --secondary-gradient: linear-gradient(135deg, #1e40af 0%, #60a5fa 100%);
        --tertiary-gradient: linear-gradient(135deg, #1e3a8a 0%, #93c5fd 100%);
        --quaternary-gradient: linear-gradient(135deg, #1e40af 0%, #dbeafe 100%);
        --light-gradient: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        --corporate-gradient: linear-gradient(135deg, #1e3a8a 0%, #60a5fa 50%, #dbeafe 100%);
        
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
        --shadow-blue: 0 0 20px rgba(30, 64, 175, 0.2);
        --shadow-success: 0 0 20px rgba(5, 150, 105, 0.2);
        --shadow-warning: 0 0 20px rgba(217, 119, 6, 0.2);
        --shadow-danger: 0 0 20px rgba(220, 38, 38, 0.2);
        
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

    .dashboard-header {
        margin-bottom: 2rem;
        position: relative;
        padding: 2rem;
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-md);
        border-top: 4px solid var(--primary-blue-dark);
    }
    
    .greeting {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.8rem;
        color: var(--text-heading);
        position: relative;
        font-family: 'Inter', sans-serif;
        letter-spacing: -0.5px;
    }
    
    .dashboard-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
        margin-bottom: 0;
        font-weight: 400;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        position: relative;
        border: 1px solid var(--card-border);
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .stat-card.total::after {
        background: var(--primary-gradient);
    }
    
    .stat-card.token::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.secret::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.code::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.complete::after {
        background: var(--primary-gradient);
    }

    .stat-card.status::after {
        background: var(--secondary-gradient);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        position: relative;
        color: white;
    }
    
    .stat-card.total .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.token .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.secret .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.code .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.complete .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.status .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-heading);
        font-family: 'Inter', monospace;
    }
    
    .stat-title {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .settings-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
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

    .settings-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .settings-header h6 {
        margin: 0;
        color: var(--text-heading);
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .settings-header h6 i {
        color: var(--primary-blue-light);
        margin-right: 0.5rem;
    }

    .settings-body {
        padding: 2rem;
    }

    .setting-item {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border: 1px solid var(--card-border);
        transition: all 0.3s ease;
    }

    .setting-item:hover {
        box-shadow: var(--shadow-sm);
        transform: translateY(-2px);
    }

    .setting-item:last-child {
        margin-bottom: 0;
    }

    .setting-label {
        font-weight: 600;
        color: var(--text-heading);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.1rem;
    }

    .setting-label i {
        color: var(--primary-blue-light);
        font-size: 1.2rem;
    }

    .setting-value {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .setting-value .form-control {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        color: var(--text-primary);
        border-radius: var(--border-radius);
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        flex: 1;
    }

    .setting-value .form-control:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        outline: none;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .alert {
        border-radius: var(--border-radius);
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1rem;
        position: relative;
        animation: fadeIn 0.5s ease;
    }

    .alert-info {
        background: var(--light-blue);
        border-left: 4px solid var(--info-blue);
        color: var(--primary-blue-dark);
    }

    .alert-info i {
        color: var(--primary-blue-light);
    }

    .text-muted {
        color: var(--text-secondary) !important;
    }

    .animate-fade-in {
        animation: fadeIn 0.8s ease forwards;
    }

    /* Container responsive design */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem;
        }
        
        .greeting {
            font-size: 1.5rem;
        }
        
        .stat-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .settings-body {
            padding: 1.5rem;
        }
        
        .setting-value {
            flex-direction: column;
            align-items: stretch;
        }
        
        .setting-value .form-control {
            margin-bottom: 1rem;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-dragon me-2"></i>
        Drakon API Ayarları
    </h1>
    <p class="dashboard-subtitle">Drakon API entegrasyon ayarlarını yönetin ve yapılandırın.</p>
</div>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-gear"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalSettings); ?></div>
        <div class="stat-title">Toplam Ayar</div>
    </div>
    
    <div class="stat-card token">
        <div class="stat-icon">
            <i class="bi bi-key-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($agentTokenConfigured); ?></div>
        <div class="stat-title">Agent Token Yapılandırıldı</div>
    </div>
    
    <div class="stat-card secret">
        <div class="stat-icon">
            <i class="bi bi-shield-lock"></i>
        </div>
        <div class="stat-number"><?php echo number_format($agentSecretKeyConfigured); ?></div>
        <div class="stat-title">Secret Key Yapılandırıldı</div>
    </div>
    
    <div class="stat-card code">
        <div class="stat-icon">
            <i class="bi bi-code-slash"></i>
        </div>
        <div class="stat-number"><?php echo number_format($agentCodeConfigured); ?></div>
        <div class="stat-title">Agent Code Yapılandırıldı</div>
    </div>
    
    <div class="stat-card complete">
        <div class="stat-icon">
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="stat-number"><?php echo number_format($settingsComplete); ?></div>
        <div class="stat-title">Tam Yapılandırılmış</div>
    </div>
</div>

<div class="settings-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="settings-header">
        <h6><i class="bi bi-dragon"></i>Drakon API Entegrasyon Ayarları</h6>
        <div class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Son güncelleme: <?php echo $lastUpdate ? date('d.m.Y H:i', strtotime($lastUpdate)) : 'Henüz güncelleme yok'; ?>
        </div>
    </div>
    <div class="settings-body">
        <div class="setting-item">
            <div class="setting-label">
                <i class="bi bi-key-fill"></i>
                Agent Token
            </div>
            <div class="setting-value">
                <input type="text" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($settings['agent_token']); ?>" 
                       id="agent_token"
                       placeholder="Drakon agent token'ınızı buraya girin"
                       <?php if (!$isAdmin): ?>readonly<?php endif; ?>>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="updateSetting('agent_token')">
                    <i class="bi bi-save me-1"></i>Kaydet
                </button>
                <?php endif; ?>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Drakon API'ye erişim için gerekli agent token. Bu token, API isteklerini yetkilendirmek için kullanılır.
            </div>
        </div>

        <div class="setting-item">
            <div class="setting-label">
                <i class="bi bi-shield-lock-fill"></i>
                Agent Secret Key
            </div>
            <div class="setting-value">
                <input type="text" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($settings['agent_secret_key']); ?>" 
                       id="agent_secret_key"
                       placeholder="Drakon agent secret key'inizi buraya girin"
                       <?php if (!$isAdmin): ?>readonly<?php endif; ?>>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="updateSetting('agent_secret_key')">
                    <i class="bi bi-save me-1"></i>Kaydet
                </button>
                <?php endif; ?>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                API isteklerini imzalamak için kullanılan gizli anahtar. Bu anahtar, güvenli iletişim için gereklidir.
            </div>
        </div>

        <div class="setting-item">
            <div class="setting-label">
                <i class="bi bi-code-slash"></i>
                Agent Code
            </div>
            <div class="setting-value">
                <input type="text" 
                       class="form-control" 
                       value="<?php echo htmlspecialchars($settings['agent_code']); ?>" 
                       id="agent_code"
                       placeholder="Drakon agent kodunuzu buraya girin"
                       <?php if (!$isAdmin): ?>readonly<?php endif; ?>>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="updateSetting('agent_code')">
                    <i class="bi bi-save me-1"></i>Kaydet
                </button>
                <?php endif; ?>
            </div>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Agent kodu. Bu kod, Drakon sisteminde sizi tanımlamak için kullanılır.
            </div>
        </div>
    </div>
</div>

<script>
function updateSetting(key) {
    const value = document.getElementById(key).value;
    
    $.ajax({
        url: 'drakon_api_settings.php',
        type: 'POST',
        data: {
            action: 'edit',
            key: key,
            value: value
        },
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı!',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                Swal.fire('Hata!', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Hata!', 'Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 