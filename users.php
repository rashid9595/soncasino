<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Kullanıcı Yönetimi";

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
    // Get user's role_id if not set in session
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

// Check if user is Super Admin (role_id = 1)
$isSuperAdmin = ($_SESSION['role_id'] == 1);

$stmt = $db->prepare("
    SELECT ap.* 
    FROM admin_permissions ap 
    WHERE ap.role_id = ? AND ap.menu_item = 'users' AND ap.can_view = 1
");
$stmt->execute([$_SESSION['role_id']]);
if (!$stmt->fetch() && !$isSuperAdmin) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
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

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        // Check if user has permission to add users
        $canAdd = $isSuperAdmin || (isset($userPermissions['users']['create']) && $userPermissions['users']['create']);
        if (!$canAdd) {
            $error = "Kullanıcı ekleme izniniz yok";
        } else {
            // Validate input
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            $role_id = (int)$_POST['role_id'];
            
            if (empty($username) || empty($email) || empty($password) || empty($role_id)) {
                $error = "Tüm alanları doldurun";
            } elseif (!validatePassword($password, $passwordPolicy)) {
                $error = "Şifre politikası gereksinimlerini karşılamıyor: " . getPasswordPolicyDescription($passwordPolicy);
            } else {
                // Check if username or email already exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM administrators WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Bu kullanıcı adı veya e-posta adresi zaten kullanılıyor";
                } else {
                    // Generate 2FA secret key
                    $secret_key = \OTPHP\TOTP::create()->getSecret();
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $stmt = $db->prepare("
                        INSERT INTO administrators (username, email, password, role_id, secret_key, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    if ($stmt->execute([$username, $email, $hashed_password, $role_id, $secret_key])) {
                        // Log activity
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs (admin_id, action, description, created_at) 
                            VALUES (?, 'create', ?, NOW())
                        ");
                        $stmt->execute([$_SESSION['admin_id'], "Yeni kullanıcı oluşturuldu: $username"]);
                        
                        $success = "Kullanıcı başarıyla eklendi";
                    } else {
                        $error = "Kullanıcı eklenirken bir hata oluştu";
                    }
                }
            }
        }
    } elseif (isset($_POST['edit_user'])) {
        // Check if trying to edit a Super Admin user while not being Super Admin
        $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch();
        
        if ($targetUser && $targetUser['role_id'] == 1 && !$isSuperAdmin) {
            $error = "Super Admin kullanıcılarını düzenleme yetkiniz yok";
        } else {
            // Check if user has permission to edit users
            $canEdit = $isSuperAdmin || (isset($userPermissions['users']['edit']) && $userPermissions['users']['edit']);
            if (!$canEdit) {
                $error = "Kullanıcı düzenleme izniniz yok";
            } else {
                // Validate input
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $role_id = (int)$_POST['role_id'];
                $password = trim($_POST['password']);
                
                if (empty($username) || empty($email) || empty($role_id)) {
                    $error = "Kullanıcı adı, e-posta ve rol gereklidir";
                } else {
                    // Check if username or email already exists for other users
                    $stmt = $db->prepare("SELECT COUNT(*) FROM administrators WHERE (username = ? OR email = ?) AND id != ?");
                    $stmt->execute([$username, $email, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Bu kullanıcı adı veya e-posta adresi başka bir kullanıcı tarafından kullanılıyor";
                    } else {
                        // Update user
                        if (!empty($password)) {
                            // Validate password against policy
                            if (!validatePassword($password, $passwordPolicy)) {
                                $error = "Şifre politikası gereksinimlerini karşılamıyor: " . getPasswordPolicyDescription($passwordPolicy);
                            } else {
                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $db->prepare("
                                    UPDATE administrators 
                                    SET username = ?, email = ?, password = ?, role_id = ? 
                                    WHERE id = ?
                                ");
                                $stmt->execute([$username, $email, $hashed_password, $role_id, $id]);
                                
                                // Log activity
                                $stmt = $db->prepare("
                                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                                    VALUES (?, 'update', ?, NOW())
                                ");
                                $stmt->execute([$_SESSION['admin_id'], "Kullanıcı güncellendi: $username"]);
                                
                                $success = "Kullanıcı başarıyla güncellendi";
                            }
                        } else {
                            $stmt = $db->prepare("
                                UPDATE administrators 
                                SET username = ?, email = ?, role_id = ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$username, $email, $role_id, $id]);
                            
                            // Log activity
                            $stmt = $db->prepare("
                                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                                VALUES (?, 'update', ?, NOW())
                            ");
                            $stmt->execute([$_SESSION['admin_id'], "Kullanıcı güncellendi: $username"]);
                            
                            $success = "Kullanıcı başarıyla güncellendi";
                        }
                    }
                }
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        // Check if trying to delete a Super Admin user while not being Super Admin
        $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch();
        
        if ($targetUser && $targetUser['role_id'] == 1 && !$isSuperAdmin) {
            $error = "Super Admin kullanıcılarını silme yetkiniz yok";
        } else {
            // Check if user has permission to delete users
            $canDelete = $isSuperAdmin || (isset($userPermissions['users']['delete']) && $userPermissions['users']['delete']);
            if (!$canDelete) {
                $error = "Kullanıcı silme izniniz yok";
            } else {
                // Prevent self-deletion
                if ($id === $_SESSION['admin_id']) {
                    $error = "Kendi hesabınızı silemezsiniz";
                } else {
                    // Get username for logging
                    $stmt = $db->prepare("SELECT username FROM administrators WHERE id = ?");
                    $stmt->execute([$id]);
                    $username = $stmt->fetchColumn();
                    
                    // Delete user
                    $stmt = $db->prepare("DELETE FROM administrators WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        // Log activity
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs (admin_id, action, description, created_at) 
                            VALUES (?, 'delete', ?, NOW())
                        ");
                        $stmt->execute([$_SESSION['admin_id'], "Kullanıcı silindi: $username"]);
                        
                        $success = "Kullanıcı başarıyla silindi";
                    } else {
                        $error = "Kullanıcı silinirken bir hata oluştu";
                    }
                }
            }
        }
    } elseif (isset($_POST['reset_2fa'])) {
        // Generate new 2FA secret key
        $secret_key = \OTPHP\TOTP::create()->getSecret();
        
        // Update user's secret key
        $stmt = $db->prepare("UPDATE administrators SET secret_key = ? WHERE id = ?");
        if ($stmt->execute([$secret_key, $id])) {
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "2FA sıfırlandı: ID $id"]);
            
            $success = "2FA başarıyla sıfırlandı";
        } else {
            $error = "2FA sıfırlanırken bir hata oluştu";
        }
    }
}

// Get all roles for dropdown
$stmt = $db->prepare("SELECT id, name FROM admin_roles ORDER BY name");
$stmt->execute();
$roles = $stmt->fetchAll();

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

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 2rem;
        color: var(--text-heading);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
    }

    .page-title::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 60px;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: 2px;
    }

    .page-title i {
        color: var(--primary-blue-light);
        font-size: 1.8rem;
    }

    .users-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .add-user-btn {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
    }

    .add-user-btn i {
        font-size: 1.1rem;
    }

    .users-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .user-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
    }

    .user-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--primary-gradient);
    }

    .user-info {
        padding: 1.5rem;
    }

    .user-header {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
    }

    .user-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 700;
        color: white;
        margin-right: 1rem;
        box-shadow: var(--shadow-sm);
    }

    .user-details h4 {
        margin: 0 0 0.25rem 0;
        color: var(--text-heading);
        font-weight: 600;
        font-size: 1.1rem;
    }

    .user-email {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin: 0;
    }

    .user-meta {
        margin-bottom: 1rem;
    }

    .user-role {
        display: inline-block;
        background: var(--secondary-gradient);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .user-created {
        color: var(--text-secondary);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .user-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .action-btn {
        padding: 0.4rem 0.8rem;
        border-radius: var(--border-radius-sm);
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        transition: all 0.2s ease;
    }

    .btn-edit {
        background: transparent;
        color: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .btn-delete {
        background: transparent;
        color: var(--error-red);
        border-color: var(--error-red);
    }

    .btn-reset {
        background: transparent;
        color: var(--warning-orange);
        border-color: var(--warning-orange);
    }

    .form-section {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        position: relative;
    }

    .form-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .form-title {
        color: var(--text-heading);
        font-weight: 700;
        font-size: 1.4rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-title i {
        color: var(--primary-blue-light);
        font-size: 1.6rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        color: var(--text-secondary);
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-control, .form-select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius);
        background: var(--white);
        color: var(--text-primary);
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: var(--shadow-sm);
    }

    .btn-secondary {
        background: transparent;
        color: var(--text-secondary);
        border: 1px solid var(--card-border);
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-danger {
        background: transparent;
        color: var(--error-red);
        border: 1px solid var(--error-red);
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }

    .alert-success {
        background: var(--light-blue);
        border-left-color: var(--success-green);
        color: var(--primary-blue-dark);
    }

    .alert-danger {
        background: #fee2e2;
        border-left-color: var(--error-red);
        color: #7f1d1d;
    }

    .form-text {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .col-md-6 {
        flex: 1;
        min-width: 250px;
    }

    /* Edit User Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .modal-content {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--card-border);
    }

    .modal-title {
        color: var(--text-heading);
        font-weight: 700;
        font-size: 1.25rem;
        margin: 0;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }

    .modal-buttons {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid var(--card-border);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .users-grid {
            grid-template-columns: 1fr;
        }
        
        .users-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .page-title {
            font-size: 1.5rem;
        }
        
        .user-actions {
            justify-content: center;
        }
        
        .row {
            flex-direction: column;
        }
        
        .col-md-6 {
            min-width: auto;
        }
    }

    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .user-card {
        animation: fadeIn 0.5s ease;
    }

    .form-section {
        animation: fadeIn 0.6s ease;
    }
</style>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="users-header">
    <h1 class="page-title">
        <i class="bi bi-people-fill"></i>
        Kullanıcı Yönetimi
    </h1>
    
    <?php if ($isSuperAdmin): ?>
    <a href="#" class="add-user-btn" onclick="showAddUserForm()">
        <i class="bi bi-plus-lg"></i>
        Yeni Kullanıcı Ekle
    </a>
    <?php endif; ?>
</div>

<!-- Add User Form -->
<?php if ($action === 'add' && $isSuperAdmin): ?>
<div class="form-section">
    <h2 class="form-title">
        <i class="bi bi-person-plus"></i>
        Yeni Kullanıcı Ekle
    </h2>
    
    <form method="POST">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">E-posta Adresi</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="password" class="form-control" required>
                    <div class="form-text">
                        <?php echo getPasswordPolicyDescription($passwordPolicy); ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Rol</label>
                    <select name="role_id" class="form-select" required>
                        <option value="">Rol Seçin</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div style="display: flex; gap: 1rem;">
            <button type="submit" name="add_user" class="btn-primary">
                <i class="bi bi-check-lg me-1"></i>
                Kullanıcı Ekle
            </button>
            <a href="users.php" class="btn-secondary">
                <i class="bi bi-x-lg me-1"></i>
                İptal
            </a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Edit User Form -->
<?php if ($action === 'edit' && $id > 0): ?>
<?php
// Get user data for editing
$stmt = $db->prepare("SELECT a.*, r.name as role_name FROM administrators a LEFT JOIN admin_roles r ON a.role_id = r.id WHERE a.id = ?");
$stmt->execute([$id]);
$editUser = $stmt->fetch();

if ($editUser):
?>
<div class="form-section">
    <h2 class="form-title">
        <i class="bi bi-pencil-square"></i>
        Kullanıcı Düzenle: <?php echo htmlspecialchars($editUser['username']); ?>
    </h2>
    
    <form method="POST">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($editUser['username']); ?>" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">E-posta Adresi</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Yeni Şifre (Opsiyonel)</label>
                    <input type="password" name="password" class="form-control">
                    <div class="form-text">
                        Boş bırakırsanız mevcut şifre korunur.
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Rol</label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $editUser['role_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div style="display: flex; gap: 1rem;">
            <button type="submit" name="edit_user" class="btn-primary">
                <i class="bi bi-check-lg me-1"></i>
                Güncelle
            </button>
            <a href="users.php" class="btn-secondary">
                <i class="bi bi-x-lg me-1"></i>
                İptal
            </a>
        </div>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Users Grid -->
<div class="users-grid">
    <?php
    // Get all users with their roles
    $stmt = $db->prepare("
        SELECT a.*, r.name as role_name 
        FROM administrators a 
        LEFT JOIN admin_roles r ON a.role_id = r.id 
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    foreach ($users as $user):
    ?>
    <div class="user-card">
        <div class="user-info">
            <div class="user-header">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            
            <div class="user-meta">
                <div class="user-role">
                    <?php echo htmlspecialchars($user['role_name'] ?: 'Rol Atanmamış'); ?>
                </div>
                <div class="user-created">
                    <i class="bi bi-calendar3"></i>
                    <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                </div>
            </div>
            
            <div class="user-actions">
                <?php if ($isSuperAdmin || ($user['role_id'] != 1)): ?>
                    <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="action-btn btn-edit">
                        <i class="bi bi-pencil"></i>
                        Düzenle
                    </a>
                    
                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                        <a href="#" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="action-btn btn-delete">
                            <i class="bi bi-trash"></i>
                            Sil
                        </a>
                    <?php endif; ?>
                    
                    <a href="#" onclick="confirmReset2FA(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="action-btn btn-reset">
                        <i class="bi bi-shield-x"></i>
                        2FA Sıfırla
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function showAddUserForm() {
    window.location.href = 'users.php?action=add';
}

function confirmDelete(userId, username) {
    if (confirm(`"${username}" kullanıcısını silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `users.php?id=${userId}`;
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_user';
        input.value = '1';
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function confirmReset2FA(userId, username) {
    if (confirm(`"${username}" kullanıcısının 2FA'sını sıfırlamak istediğinizden emin misiniz?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `users.php?id=${userId}`;
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'reset_2fa';
        input.value = '1';
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>