<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Profil Yönetimi";

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$userId = $_SESSION['admin_id'];
$stmt = $db->prepare("SELECT * FROM administrators WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Handle invalid user ID
    header("Location: logout.php");
    exit();
}

// Get system settings
$stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get password policy from settings or use default
$passwordPolicy = isset($settings['password_policy']) ? $settings['password_policy'] : 'medium';

// Function to validate password based on policy
function validatePassword($password, $policy) {
    switch ($policy) {
        case 'high':
            // At least 10 characters, 1 uppercase, 1 lowercase, 1 number, 1 special char
            return (strlen($password) >= 10 && 
                    preg_match('/[A-Z]/', $password) && 
                    preg_match('/[a-z]/', $password) && 
                    preg_match('/[0-9]/', $password) && 
                    preg_match('/[^A-Za-z0-9]/', $password));
        
        case 'medium':
            // At least 8 characters, 1 uppercase, 1 number
            return (strlen($password) >= 8 && 
                    preg_match('/[A-Z]/', $password) && 
                    preg_match('/[0-9]/', $password));
        
        case 'low':
        default:
            // At least 6 characters
            return (strlen($password) >= 6);
    }
}

// Get password policy description
function getPasswordPolicyDescription($policy) {
    switch ($policy) {
        case 'high':
            return "Şifre en az 10 karakter içermeli ve en az 1 büyük harf, 1 küçük harf, 1 sayı ve 1 özel karakter içermelidir.";
        case 'medium':
            return "Şifre en az 8 karakter içermeli ve en az 1 büyük harf ve 1 sayı içermelidir.";
        case 'low':
        default:
            return "Şifre en az 6 karakter içermelidir.";
    }
}

// Get login history
$stmt = $db->prepare("
    SELECT * FROM activity_logs 
    WHERE admin_id = ? AND action = 'login' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$userId]);
$loginHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize messages
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Password change form
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "Tüm alanları doldurunuz.";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = "Mevcut şifre hatalı.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Yeni şifreler eşleşmiyor.";
        } elseif (!validatePassword($newPassword, $passwordPolicy)) {
            $error = "Şifre politikası gereksinimlerini karşılamıyor: " . getPasswordPolicyDescription($passwordPolicy);
        } else {
            // Update password in database
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE administrators SET password = ? WHERE id = ?");
            
            if ($stmt->execute([$hashedPassword, $userId])) {
                // Log password change
                $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, 'update', 'Şifre güncellendi')");
                $stmt->execute([$userId]);
                
                $success = "Şifreniz başarıyla güncellendi.";
            } else {
                $error = "Şifre güncellenirken bir hata oluştu.";
            }
        }
    }
    
    // Profile update form
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validate input
        if (empty($name) || empty($email)) {
            $error = "Ad ve e-posta alanları gereklidir.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Geçerli bir e-posta adresi giriniz.";
        } else {
            // Check if email is already in use by another user
            $stmt = $db->prepare("SELECT id FROM administrators WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Bu e-posta adresi zaten kullanılıyor.";
            } else {
                // Update profile information
                $stmt = $db->prepare("UPDATE administrators SET name = ?, email = ? WHERE id = ?");
                
                if ($stmt->execute([$name, $email, $userId])) {
                    // Log profile update
                    $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description) VALUES (?, 'update', 'Profil bilgileri güncellendi')");
                    $stmt->execute([$userId]);
                    
                    // Update session variables
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['admin_email'] = $email;
                    
                    // Update local user variable
                    $user['name'] = $name;
                    $user['email'] = $email;
                    
                    $success = "Profil bilgileriniz başarıyla güncellendi.";
                } else {
                    $error = "Profil güncellenirken bir hata oluştu.";
                }
            }
        }
    }
}

// Start output buffering
ob_start();
?>

<style>
    /* Corporate Blue Color Palette */
    :root {
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
        --primary-gradient: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        --secondary-gradient: linear-gradient(135deg, #60a5fa 0%, #93c5fd 100%);
        --light-gradient: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        --success-gradient: linear-gradient(135deg, #059669 0%, #10b981 100%);
        --warning-gradient: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        --danger-gradient: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        
        /* Corporate Shadows */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
        --shadow-blue: 0 0 20px rgba(30, 64, 175, 0.2);
        --shadow-success: 0 0 20px rgba(5, 150, 105, 0.2);
        --shadow-warning: 0 0 20px rgba(217, 119, 6, 0.2);
        --shadow-danger: 0 0 20px rgba(220, 38, 38, 0.2);
    }

    /* Global Styles */
    body {
        background: var(--light-gray);
        color: var(--text-gray);
        font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
        line-height: 1.6;
        min-height: 100vh;
    }

    /* Corporate Profile Cards */
    .profile-card {
        background: var(--white);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--medium-gray);
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }
    
    .profile-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-blue-light);
    }
    
    .profile-header {
        background: var(--primary-gradient);
        padding: 20px 25px;
        position: relative;
        overflow: hidden;
    }
    
    .profile-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s ease;
    }
    
    .profile-header:hover::before {
        left: 100%;
    }
    
    .profile-header h5 {
        margin: 0;
        font-weight: 600;
        color: var(--white);
        display: flex;
        align-items: center;
    }
    
    .profile-header i {
        margin-right: 10px;
        font-size: 1.4rem;
        color: var(--white);
        opacity: 0.9;
    }
    
    .profile-body {
        padding: 25px;
    }
    
    .profile-section {
        background: var(--white);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        border: 1px solid var(--medium-gray);
        transition: all 0.3s ease;
    }
    
    .profile-section:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-blue-light);
    }
    
    .profile-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-blue-dark);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--medium-gray);
        display: flex;
        align-items: center;
    }
    
    .profile-section-title i {
        margin-right: 10px;
        color: var(--primary-blue-light);
    }
    
    /* Corporate Form Elements */
    .form-control, .form-select {
        background-color: var(--white);
        border: 1px solid var(--medium-gray);
        color: var(--text-gray);
        border-radius: 8px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        background-color: var(--white);
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        color: var(--text-gray);
    }
    
    .form-label {
        font-weight: 500;
        color: var(--dark-gray);
        margin-bottom: 8px;
    }
    
    /* Corporate Avatar */
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto 20px;
        position: relative;
        border: 3px solid var(--light-blue);
        transition: all 0.3s ease;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-blue);
    }
    
    .profile-avatar:hover {
        transform: scale(1.05);
        border-color: var(--primary-blue-light);
    }
    
    .profile-avatar i {
        font-size: 3rem;
        color: var(--white);
    }
    
    .profile-info {
        text-align: center;
        margin-bottom: 25px;
    }
    
    .profile-name {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--primary-blue-dark);
        margin-bottom: 5px;
    }
    
    .profile-email {
        color: var(--dark-gray);
        margin-bottom: 15px;
    }
    
    .profile-role {
        display: inline-block;
        padding: 5px 15px;
        background: var(--secondary-gradient);
        border-radius: 20px;
        color: var(--white);
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    /* Corporate Buttons */
    .btn-update {
        background: var(--primary-gradient);
        border: none;
        border-radius: 8px;
        padding: 12px 25px;
        font-weight: 600;
        color: var(--white);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-blue);
    }
    
    .btn-update::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: all 0.5s ease;
    }
    
    .btn-update:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
        color: var(--white);
    }
    
    .btn-update:hover::before {
        left: 100%;
    }
    
    .btn-danger {
        background: var(--danger-gradient);
        border: none;
        border-radius: 8px;
        color: var(--white);
        transition: all 0.3s ease;
    }
    
    .btn-danger:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-danger);
        color: var(--white);
    }
    
    /* Password Strength Indicator */
    .password-strength {
        height: 5px;
        margin-top: 8px;
        border-radius: 3px;
        transition: all 0.3s ease;
        background-color: var(--medium-gray);
        overflow: hidden;
    }
    
    .password-strength-bar {
        height: 100%;
        width: 0;
        transition: all 0.3s ease;
        border-radius: 3px;
    }
    
    .password-strength-text {
        font-size: 0.85rem;
        color: var(--dark-gray);
        margin-top: 5px;
        transition: all 0.3s ease;
    }
    
    /* Corporate Table Styles */
    .login-history-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .login-history-table th {
        padding: 12px 15px;
        font-weight: 500;
        color: var(--dark-gray);
        text-align: left;
        border-bottom: 1px solid var(--medium-gray);
        background: var(--light-gray);
    }
    
    .login-history-table td {
        padding: 12px 15px;
        color: var(--text-gray);
        border-bottom: 1px solid var(--medium-gray);
    }
    
    .login-history-table tbody tr {
        transition: all 0.3s ease;
        cursor: default;
    }
    
    .login-history-table tbody tr:hover {
        background-color: var(--ultra-light-blue);
        transform: translateY(-1px);
    }
    
    .login-history-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    /* Corporate Badges */
    .login-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .login-badge.latest {
        background: var(--success-gradient);
        color: var(--white);
    }
    
    .login-badge.regular {
        background: var(--secondary-gradient);
        color: var(--white);
    }
    
    .badge {
        background: var(--secondary-gradient) !important;
        color: var(--white);
    }
    
    .badge.bg-primary {
        background: var(--primary-gradient) !important;
    }
    
    .badge.bg-secondary {
        background: var(--medium-gray) !important;
        color: var(--text-gray);
    }
    
    /* Corporate Alerts */
    .alert {
        border-radius: 8px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 20px;
        position: relative;
        animation: fadeIn 0.5s ease;
    }
    
    .alert-success {
        background: var(--light-blue);
        border-left: 4px solid var(--success-green);
        color: var(--primary-blue-dark);
    }
    
    .alert-danger {
        background: #fee2e2;
        border-left: 4px solid var(--error-red);
        color: #7f1d1d;
    }
    
    /* Corporate Input Groups */
    .input-group .btn-outline-secondary {
        border-color: var(--medium-gray);
        color: var(--dark-gray);
        background: var(--white);
    }
    
    .input-group .btn-outline-secondary:hover {
        background: var(--light-gray);
        border-color: var(--primary-blue-light);
        color: var(--primary-blue);
    }
    
    /* Text Colors */
    .text-info {
        color: var(--primary-blue) !important;
    }
    
    .text-muted {
        color: var(--dark-gray) !important;
    }
    
    .form-text {
        color: var(--primary-blue) !important;
    }
    
    /* Container Background */
    .container-fluid {
        background: var(--light-gray);
        min-height: 100vh;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .profile-card {
            margin: 0.75rem;
        }
        
        .profile-body {
            padding: 15px;
        }
        
        .profile-section {
            padding: 15px;
        }
    }
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Profile Information Section -->
        <div class="col-lg-4">
            <div class="profile-card animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                <div class="profile-header">
                    <h5><i class="bi bi-person-circle"></i> Profil Bilgileri</h5>
                </div>
                <div class="profile-body">
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        <div class="profile-role">
                            <?php 
                            // Check if role_id exists and get role name
                            $roleName = 'Yönetici';
                            $roleLevel = 'Standart';
                            $rolePermissions = [];
                            
                            if (isset($user['role_id'])) {
                                $stmt = $db->prepare("SELECT r.*, COUNT(p.id) as permission_count 
                                                    FROM admin_roles r 
                                                    LEFT JOIN admin_permissions p ON r.id = p.role_id 
                                                    WHERE r.id = ? 
                                                    GROUP BY r.id");
                                $stmt->execute([$user['role_id']]);
                                $roleData = $stmt->fetch();
                                
                                if ($roleData) {
                                    $roleName = $roleData['name'];
                                    
                                    // Define role level based on permission count or role type
                                    if ($roleData['id'] == 1) {
                                        $roleLevel = 'Süper Admin';
                                    } elseif ($roleData['permission_count'] > 15) {
                                        $roleLevel = 'Yüksek Seviye';
                                    } elseif ($roleData['permission_count'] > 10) {
                                        $roleLevel = 'Orta Seviye';
                                    } else {
                                        $roleLevel = 'Temel Seviye';
                                    }
                                    
                                    // Get permissions for this role
                                    $permStmt = $db->prepare("SELECT menu_item FROM admin_permissions WHERE role_id = ? AND can_view = 1 LIMIT 5");
                                    $permStmt->execute([$user['role_id']]);
                                    $rolePermissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
                                }
                            }
                            
                            echo htmlspecialchars($roleName);
                            ?>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h6 class="profile-section-title"><i class="bi bi-person-badge"></i> Rol Bilgileri</h6>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span style="color: var(--primary-blue);">Rol Seviyesi:</span>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($roleLevel); ?></span>
                            </div>
                            <?php if (!empty($rolePermissions)): ?>
                            <div class="mt-3">
                                <span style="color: var(--primary-blue);" class="d-block mb-2">Temel Erişim İzinleri:</span>
                                <div class="d-flex flex-wrap gap-1">
                                <?php foreach($rolePermissions as $perm): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($perm)); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($rolePermissions) >= 5): ?>
                                    <span class="badge bg-secondary">...</span>
                                <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-section">
                        <h6 class="profile-section-title"><i class="bi bi-person-lines-fill"></i> Profil Güncelle</h6>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Ad Soyad</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta Adresi</label>
                                <div class="input-group">
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="tooltip" title="E-posta adresiniz hesabınıza giriş ve bildirimler için kullanılır">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-envelope-check me-1"></i> <span>E-posta adresinizi değiştirirseniz, sistem yöneticilerine bildirim gidecektir.</span>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="update_profile" class="btn btn-update">
                                    <i class="bi bi-check-lg me-2"></i> Profili Güncelle
                                </button>
                                <a href="logout.php" class="btn btn-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i> Güvenli Çıkış Yap
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Password Change Section -->
        <div class="col-lg-4">
            <div class="profile-card animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                <div class="profile-header">
                    <h5><i class="bi bi-shield-lock"></i> Şifre Değiştir</h5>
                </div>
                <div class="profile-body">
                    <div class="profile-section">
                        <h6 class="profile-section-title"><i class="bi bi-key"></i> Şifre Güncelle</h6>
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mevcut Şifre</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Yeni Şifre</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i> <?php echo getPasswordPolicyDescription($passwordPolicy); ?>
                                </div>
                                <div class="password-strength">
                                    <div class="password-strength-bar"></div>
                                </div>
                                <div class="password-strength-text"></div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="change_password" class="btn btn-update">
                                    <i class="bi bi-shield-check me-2"></i> Şifreyi Güncelle
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="profile-section">
                        <h6 class="profile-section-title"><i class="bi bi-info-circle"></i> Şifre Güvenliği</h6>
                        <ul class="text-muted">
                            <?php if ($passwordPolicy == 'low'): ?>
                                <li style="color: var(--primary-blue);">En az 6 karakter uzunluğunda olmalı</li>
                            <?php elseif ($passwordPolicy == 'medium'): ?>
                                <li style="color: var(--primary-blue);">En az 8 karakter uzunluğunda olmalı</li>
                                <li style="color: var(--primary-blue);">En az bir büyük harf içermeli</li>
                                <li style="color: var(--primary-blue);">En az bir sayı içermeli</li>
                            <?php elseif ($passwordPolicy == 'high'): ?>
                                <li style="color: var(--primary-blue);">En az 10 karakter uzunluğunda olmalı</li>
                                <li style="color: var(--primary-blue);">En az bir büyük harf içermeli</li>
                                <li style="color: var(--primary-blue);">En az bir küçük harf içermeli</li>
                                <li style="color: var(--primary-blue);">En az bir sayı içermeli</li>
                                <li style="color: var(--primary-blue);">En az bir özel karakter içermeli (!@#$%...)</li>
                            <?php endif; ?>
                            <li style="color: var(--primary-blue);">Kolay tahmin edilebilir şifrelerden kaçının</li>
                            <li style="color: var(--primary-blue);">Şifrelerinizi düzenli olarak değiştirin</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Login History Section -->
        <div class="col-lg-4">
            <div class="profile-card animate__animated animate__fadeIn" style="animation-delay: 0.3s;">
                <div class="profile-header">
                    <h5><i class="bi bi-clock-history"></i> Giriş Geçmişi</h5>
                </div>
                <div class="profile-body">
                    <div class="profile-section">
                        <h6 class="profile-section-title"><i class="bi bi-list-check"></i> Son 10 Giriş</h6>
                        
                        <?php if (count($loginHistory) > 0): ?>
                            <div class="table-responsive">
                                <table class="login-history-table">
                                    <thead>
                                        <tr>
                                            <th>Tarih</th>
                                            <th>IP Adresi</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loginHistory as $index => $login): ?>
                                            <tr>
                                                <td><?php echo date('d.m.Y H:i', strtotime($login['created_at'])); ?></td>
                                                <td>
                                                    <?php 
                                                    // Get IP address from details JSON if available, otherwise show N/A
                                                    $details = json_decode($login['details'] ?? '{}', true);
                                                    echo $details['ip_address'] ?? 'N/A'; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($index === 0): ?>
                                                        <span class="login-badge latest">Son Giriş</span>
                                                    <?php else: ?>
                                                        <span class="login-badge regular">Giriş</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-exclamation-circle d-block mb-3" style="font-size: 2rem;"></i>
                                Giriş geçmişi bulunamadı.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-section">
                        <h6 class="profile-section-title"><i class="bi bi-shield-shaded"></i> Güvenlik Bilgisi</h6>
                        <p style="color: var(--primary-blue);" class="mb-0">
                            Şüpheli bir giriş fark ederseniz, hemen şifrenizi değiştirin ve sistem yöneticisine bildirin.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password strength checker
        const passwordInput = document.getElementById('new_password');
        const strengthBar = document.querySelector('.password-strength-bar');
        const strengthText = document.querySelector('.password-strength-text');
        
        // Get password policy level
        const passwordPolicy = '<?php echo $passwordPolicy; ?>';
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let requirements = 0;
                let meetsPolicy = false;
                
                // Base checks for all policies
                if (password.length >= 6) strength += 1;
                
                // Check against policy requirements
                if (passwordPolicy === 'low') {
                    // Low policy: at least 6 characters
                    requirements = 1;
                    meetsPolicy = password.length >= 6;
                } else if (passwordPolicy === 'medium') {
                    // Medium policy: at least 8 characters, 1 uppercase, 1 number
                    requirements = 3;
                    if (password.length >= 8) strength += 1;
                    if (password.match(/[A-Z]/)) strength += 1;
                    if (password.match(/[0-9]/)) strength += 1;
                    
                    meetsPolicy = password.length >= 8 && 
                                  password.match(/[A-Z]/) && 
                                  password.match(/[0-9]/);
                } else if (passwordPolicy === 'high') {
                    // High policy: at least 10 characters, 1 uppercase, 1 lowercase, 1 number, 1 special char
                    requirements = 5;
                    if (password.length >= 10) strength += 1;
                    if (password.match(/[A-Z]/)) strength += 1;
                    if (password.match(/[a-z]/)) strength += 1;
                    if (password.match(/[0-9]/)) strength += 1;
                    if (password.match(/[^A-Za-z0-9]/)) strength += 1;
                    
                    meetsPolicy = password.length >= 10 && 
                                  password.match(/[A-Z]/) && 
                                  password.match(/[a-z]/) && 
                                  password.match(/[0-9]/) && 
                                  password.match(/[^A-Za-z0-9]/);
                }
                
                // Calculate strength percentage based on requirements
                const strengthPercentage = requirements > 0 ? (strength / requirements) * 100 : 0;
                
                // Update the strength bar
                strengthBar.style.width = strengthPercentage + '%';
                
                // Set color and text based on strength
                if (password.length === 0) {
                    strengthBar.style.width = '0%';
                    strengthBar.style.backgroundColor = '#dc2626';
                    strengthText.textContent = '';
                } else if (strengthPercentage < 34) {
                    strengthBar.style.backgroundColor = '#dc2626';
                    strengthText.textContent = 'Zayıf';
                    strengthText.style.color = '#dc2626';
                } else if (strengthPercentage < 67) {
                    strengthBar.style.backgroundColor = '#eab308';
                    strengthText.textContent = 'Orta';
                    strengthText.style.color = '#eab308';
                } else if (strengthPercentage < 100) {
                    strengthBar.style.backgroundColor = '#22c55e';
                    strengthText.textContent = 'İyi';
                    strengthText.style.color = '#22c55e';
                } else {
                    strengthBar.style.backgroundColor = '#10b981';
                    strengthText.textContent = 'Güçlü' + (meetsPolicy ? ' ✓' : '');
                    strengthText.style.color = '#10b981';
                }
                
                // Add policy indicator
                if (password.length > 0) {
                    strengthText.textContent += meetsPolicy ? ' (Politikaya uygun)' : ' (Politikaya uygun değil)';
                }
            });
        }
        
        // Password confirmation checker
        const confirmInput = document.getElementById('confirm_password');
        if (confirmInput && passwordInput) {
            confirmInput.addEventListener('input', function() {
                if (this.value === passwordInput.value) {
                    this.style.borderColor = '#10b981';
                    this.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.25)';
                } else {
                    this.style.borderColor = '#ef4444';
                    this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.25)';
                }
            });
        }
        
        // Toggle password visibility
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetId);
                
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    this.querySelector('i').classList.remove('bi-eye');
                    this.querySelector('i').classList.add('bi-eye-slash');
                } else {
                    targetInput.type = 'password';
                    this.querySelector('i').classList.remove('bi-eye-slash');
                    this.querySelector('i').classList.add('bi-eye');
                }
            });
        });
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Add animation classes with delay
        const animateElements = document.querySelectorAll('.animate__animated');
        animateElements.forEach((element, index) => {
            const delay = element.style.animationDelay || '0s';
            element.style.opacity = '0';
            
            setTimeout(() => {
                element.style.opacity = '1';
                element.classList.add('animate__fadeIn');
            }, (parseFloat(delay) * 1000) + 100);
        });
    });
</script>

<?php
// Get the buffered content
$pageContent = ob_get_clean();

// Include layout with the pageContent
include 'includes/layout.php';
?>