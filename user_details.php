<?php
// Start session and include necessary files
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Kullanıcı Detayları";

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
        WHERE ap.role_id = ? AND (ap.menu_item = 'user_details' OR ap.menu_item = 'kullanicilar') AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: site_users.php?error=invalid');
    exit();
}

$user_id = (int)$_GET['id'];

// Get user details
try {
    $stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: site_users.php?error=not_found');
        exit();
    }

    // Check if user is banned
    $stmt = $db->prepare("SELECT * FROM banned_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $ban_info = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log error and redirect
    $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Error', ?, NOW())");
    $stmt->execute([$_SESSION['admin_id'], 'DB Error: ' . $e->getMessage()]);
    header('Location: site_users.php?error=db');
    exit();
}

// Get user permissions for edit capabilities
$userCanEdit = $isAdmin;
if (!$isAdmin) {
    $stmt = $db->prepare("
        SELECT ap.* 
        FROM admin_permissions ap 
        WHERE ap.role_id = ? AND (ap.menu_item = 'user_details' OR ap.menu_item = 'kullanicilar') AND ap.can_edit = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    $userCanEdit = $stmt->fetch() ? true : false;
}

// Process form submissions
$success_message = '';
$error_message = '';

// Process ban user
if (isset($_POST['ban_user']) && $userCanEdit) {
    $ban_reason = trim($_POST['ban_reason'] ?? '');
    
    if (empty($ban_reason)) {
        $error_message = 'Ban sebebi belirtilmelidir.';
    } else {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Update user status
            $stmt = $db->prepare("UPDATE kullanicilar SET is_banned = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Add to banned_users table
            $stmt = $db->prepare("INSERT INTO banned_users (user_id, reason, banned_by, banned_at) 
                                  VALUES (?, ?, ?, NOW()) 
                                  ON DUPLICATE KEY UPDATE reason = ?, banned_by = ?, banned_at = NOW()");
            $stmt->execute([$user_id, $ban_reason, $_SESSION['admin_id'], $ban_reason, $_SESSION['admin_id']]);
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Ban User', ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'Banned user ID: ' . $user_id . ' - Reason: ' . $ban_reason]);
            
            // Commit transaction
            $db->commit();
            
            $success_message = 'Kullanıcı başarıyla banlandı.';
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM banned_users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $ban_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            // Rollback transaction
            $db->rollBack();
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Error', ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'DB Error in ban user: ' . $e->getMessage()]);
            $error_message = 'İşlem sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Process unban user
if (isset($_POST['unban_user']) && $userCanEdit) {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Update user status
        $stmt = $db->prepare("UPDATE kullanicilar SET is_banned = 0 WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Remove from banned_users table
        $stmt = $db->prepare("DELETE FROM banned_users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Unban User', ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'Unbanned user ID: ' . $user_id]);
        
        // Commit transaction
        $db->commit();
        
        $success_message = 'Kullanıcının banı başarıyla kaldırıldı.';
        
        // Refresh user data
        $stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT * FROM banned_users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $ban_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Rollback transaction
        $db->rollBack();
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Error', ?, NOW())");
        $stmt->execute([$_SESSION['admin_id'], 'DB Error in unban user: ' . $e->getMessage()]);
        $error_message = 'İşlem sırasında bir hata oluştu: ' . $e->getMessage();
    }
}

// Process password reset
if (isset($_POST['reset_password']) && $userCanEdit) {
    $new_password = trim($_POST['new_password'] ?? '');
    
    if (strlen($new_password) < 6) {
        $error_message = 'Şifre en az 6 karakter uzunluğunda olmalıdır.';
    } else {
        try {
            // Hash password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $db->prepare("UPDATE kullanicilar SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Reset Password', ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'Reset password for user ID: ' . $user_id]);
            
            $success_message = 'Kullanıcının şifresi başarıyla sıfırlandı.';
            
        } catch (PDOException $e) {
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Error', ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'DB Error in reset password: ' . $e->getMessage()]);
            $error_message = 'İşlem sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Process balance update
if (isset($_POST['update_balance']) && $userCanEdit) {
    $balance_type = $_POST['balance_type'] ?? '';
    $new_balance = floatval($_POST['new_balance'] ?? 0);
    $balance_reason = trim($_POST['balance_reason'] ?? '');
    
    // Validate inputs
    if (empty($balance_type) || $new_balance < 0 || empty($balance_reason)) {
        $error_message = 'Tüm alanlar geçerli değerlere sahip olmalıdır.';
    } else {
        // Check if balance type exists in database
        $valid_balance_types = ['ana_bakiye', 'spor_bonus', 'casino_bonus'];
        
        if (!in_array($balance_type, $valid_balance_types)) {
            $error_message = 'Geçersiz bakiye türü.';
        } else {
            try {
                // Get current balance
                $old_balance = $user[$balance_type] ?? 0;
                $balance_diff = $new_balance - $old_balance;
                
                // Start transaction
                $db->beginTransaction();
                
                // Update user balance
                $stmt = $db->prepare("UPDATE kullanicilar SET $balance_type = ? WHERE id = ?");
                $stmt->execute([$new_balance, $user_id]);
                
                // Log balance change in transactions table if exists
                if ($db->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0) {
                    $stmt = $db->prepare("INSERT INTO transactions (user_id, amount, balance_type, description, admin_id, created_at) 
                                           VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $user_id, 
                        $balance_diff, 
                        $balance_type, 
                        'Admin tarafından bakiye güncellendi: ' . $balance_reason, 
                        $_SESSION['admin_id']
                    ]);
                }
                
                // Log activity
                $action_details = "Updated {$balance_type} for user ID: {$user_id} from {$old_balance} to {$new_balance} - Reason: {$balance_reason}";
                $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Update Balance', ?, NOW())");
                $stmt->execute([$_SESSION['admin_id'], $action_details]);
                
                // Commit transaction
                $db->commit();
                
                $success_message = 'Kullanıcının bakiyesi başarıyla güncellendi.';
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                // Rollback transaction
                $db->rollBack();
                $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Error', ?, NOW())");
                $stmt->execute([$_SESSION['admin_id'], 'DB Error in update balance: ' . $e->getMessage()]);
                $error_message = 'İşlem sırasında bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// Process send email
if (isset($_POST['send_email']) && $userCanEdit) {
    $email_subject = trim($_POST['email_subject'] ?? '');
    $email_message = trim($_POST['email_message'] ?? '');
    
    if (empty($email_subject) || empty($email_message)) {
        $error_message = 'E-posta konusu ve içeriği boş olamaz.';
    } else {
        try {
            // Get system settings for email configuration
            $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'email_from', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass')");
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Send email using PHPMailer or mail() function
            // This is a placeholder for the email sending logic
            // For actual implementation, you would use a library like PHPMailer
            
            // For now, just log that an email would be sent
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Email', ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], "Sent email to user ID: {$user_id} - Subject: {$email_subject}"]);
            
            $success_message = 'E-posta başarıyla gönderildi. (Email functionality simulated)';
            
        } catch (Exception $e) {
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, description, created_at) VALUES (?, 'Error', ?, NOW())");
            $stmt->execute([$_SESSION['admin_id'], 'Email Error: ' . $e->getMessage()]);
            $error_message = 'E-posta gönderilirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Start output buffering
ob_start();
?>

<style>
    .details-card {
        background: linear-gradient(145deg, var(--dark-card), var(--dark-secondary));
        border-radius: var(--card-radius);
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
        overflow: hidden;
        border: 1px solid rgba(71, 85, 105, 0.3);
    }

    .details-header {
background: linear-gradient(90deg, rgb(0 74 255), rgb(1 46 94));
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
    display: flex
;
    justify-content: space-between;
    align-items: center;
    color: #ffffff;
    }

    .details-body {
        padding: 1.5rem;
    }

    .detail-item {
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(71, 85, 105, 0.2);
        display: flex;
        justify-content: space-between;
    }

    .detail-label {
        font-weight: 600;
        color: var(--light-text);
    }

.detail-value {
    color: #000000;       /* Yazı rengi siyah */
    font-weight: 600;     /* Yazıyı biraz daha kalın yapar */
}
.custom-button {
    background-color: #007bff;  /* Mavi arka plan */
    color: #ffffff;             /* Beyaz yazı */
    padding: 10px 20px;         /* Buton iç boşluğu */
    border: none;               /* Kenarlık yok */
    border-radius: 5px;         /* Yuvarlatılmış köşeler */
    cursor: pointer;            /* Fareyi üzerine getirdiğinde el simgesi */
    font-weight: 600;           /* Yazıyı biraz kalın yapar */
    text-decoration: none;      /* Link buton ise alt çizgi yok */
    display: inline-block;      /* Yan yana durabilmesi için */
    transition: background 0.3s; /* Hover efektini yumuşatır */
}

.custom-button:hover {
    background-color: #0056b3; /* Hover efekti için koyu mavi */
}

    
    .balance-positive {
        color: var(--green-500);
        font-weight: 600;
    }
    
    .balance-negative {
        color: var(--red-500);
        font-weight: 600;
    }
    
    .actions-card {
        margin-top: 1.5rem;
    }
    
    .tab-content {
        padding-top: 1.5rem;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kullanıcı Detayları</h1>
        <a href="site_users.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Geri Dön
        </a>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="details-card">
                <div class="details-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-person-circle me-2"></i> 
                        <?php echo htmlspecialchars($user['username']); ?>
                        <?php if ($user['is_banned'] == 1): ?>
                            <span class="badge bg-danger ms-2">Banlı Kullanıcı</span>
                        <?php endif; ?>
                    </h5>
                    
                    <div>
                        <?php if ($userCanEdit): // Only show if user has edit permission ?>
                            <?php if ($user['is_banned'] == 0): ?>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#banUserModal">
                                <i class="bi bi-shield-x"></i> Kullanıcıyı Banla
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#unbanUserModal">
                                <i class="bi bi-shield-check"></i> Banı Kaldır
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="details-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Kullanıcı ID:</div>
                                <div class="detail-value"><?php echo $user['id']; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Kullanıcı Adı:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['username']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">E-posta:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Telefon:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['phone'] ?? 'Belirtilmemiş'); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-item">
                                <div class="detail-label">Kayıt Tarihi:</div>
                                <div class="detail-value"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Son Aktivite:</div>
                                <div class="detail-value"><?php echo isset($user['last_activity']) ? date('d.m.Y H:i', strtotime($user['last_activity'])) : 'Bilgi Yok'; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Ana Bakiye:</div>
                                <div class="detail-value balance-positive"><?php echo number_format($user['ana_bakiye'] ?? 0, 2); ?> ₺</div>
                            </div>
                            <?php if (isset($user['spor_bonus'])): ?>
                            <div class="detail-item">
                                <div class="detail-label">Spor Bonus:</div>
                                <div class="detail-value balance-positive"><?php echo number_format($user['spor_bonus'], 2); ?> ₺</div>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($user['casino_bonus'])): ?>
                            <div class="detail-item">
                                <div class="detail-label">Casino Bonus:</div>
                                <div class="detail-value balance-positive"><?php echo number_format($user['casino_bonus'], 2); ?> ₺</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($user['is_banned'] && $ban_info): ?>
                    <div class="alert alert-danger mt-3">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i> Ban Bilgisi</h5>
                        <p><strong>Sebep:</strong> <?php echo htmlspecialchars($ban_info['reason']); ?></p>
                        <p><strong>Ban Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($ban_info['banned_at'])); ?></p>
                        <?php
                        // Get admin name who banned
                        try {
                            $stmt = $db->prepare("SELECT username FROM administrators WHERE id = ?");
                            $stmt->execute([$ban_info['banned_by']]);
                            $banned_by = $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            $banned_by = 'Unknown';
                        }
                        ?>
                        <p><strong>Banlayan Admin:</strong> <?php echo htmlspecialchars($banned_by); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($userCanEdit): ?>
            <div class="details-card actions-card">
                <div class="details-header">
                    <h5 class="mb-0"><i class="bi bi-gear-fill me-2"></i> Kullanıcı İşlemleri</h5>
                </div>
                <div class="details-body">
                    <ul class="nav nav-tabs" id="actionTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="balance-tab" data-bs-toggle="tab" data-bs-target="#balance" type="button" role="tab" aria-controls="balance" aria-selected="true">Bakiye</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">Şifre</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">E-posta Gönder</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="actionTabsContent">
                        <div class="tab-pane fade show active" id="balance" role="tabpanel" aria-labelledby="balance-tab">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="balance_type" class="form-label">Bakiye Türü</label>
                                    <select class="form-select" id="balance_type" name="balance_type" required>
                                        <option value="">Seçiniz</option>
                                        <option value="ana_bakiye">Ana Bakiye</option>
                                        <option value="spor_bonus">Spor Bonus</option>
                                        <option value="casino_bonus">Casino Bonus</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="new_balance" class="form-label">Yeni Bakiye</label>
                                    <input type="number" class="form-control" id="new_balance" name="new_balance" step="0.01" min="0" required>
                                </div>
                                <div class="mb-3">
                                    <label for="balance_reason" class="form-label">İşlem Nedeni</label>
                                    <textarea class="form-control" id="balance_reason" name="balance_reason" rows="3" required></textarea>
                                </div>
                                <button type="submit" name="update_balance" class="btn btn-primary">Bakiyeyi Güncelle</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Yeni Şifre</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Şifre en az 6 karakter uzunluğunda olmalıdır.</div>
                                </div>
                                <button type="submit" name="reset_password" class="btn btn-warning">Şifreyi Sıfırla</button>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="email_subject" class="form-label">E-posta Konusu</label>
                                    <input type="text" class="form-control" id="email_subject" name="email_subject" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email_message" class="form-label">E-posta İçeriği</label>
                                    <textarea class="form-control" id="email_message" name="email_message" rows="5" required></textarea>
                                </div>
                                <button type="submit" name="send_email" class="btn btn-info">E-posta Gönder</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <div class="details-card">
                <div class="details-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> Hızlı Bilgiler</h5>
                </div>
                <div class="details-body">
                    <?php
                    // Get transaction statistics
                    try {
                        $stmt = $db->prepare("SELECT 
                            COUNT(id) as total_transactions,
                            SUM(CASE WHEN type = 'win' THEN type_money ELSE 0 END) as total_win,
                            SUM(CASE WHEN type = 'bet' THEN amount ELSE 0 END) as total_bet
                            FROM transactions WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $stats = [
                            'total_transactions' => 0,
                            'total_win' => 0,
                            'total_bet' => 0
                        ];
                    }
                    
                    $netWin = ($stats['total_win'] ?? 0) - ($stats['total_bet'] ?? 0);
                    ?>
                    <div class="detail-item">
                        <div class="detail-label">Toplam İşlem Sayısı:</div>
                        <div class="detail-value"><?php echo number_format($stats['total_transactions'] ?? 0); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Toplam Bahis:</div>
                        <div class="detail-value balance-negative"><?php echo number_format($stats['total_bet'] ?? 0, 2); ?> ₺</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Toplam Kazanç:</div>
                        <div class="detail-value balance-positive"><?php echo number_format($stats['total_win'] ?? 0, 2); ?> ₺</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Net Kazanç/Kayıp:</div>
                        <div class="detail-value <?php echo $netWin >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                            <?php echo number_format($netWin, 2); ?> ₺
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="details-card mt-4">
                <div class="details-header">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i> Hızlı Bağlantılar</h5>
                </div>
                <div class="details-body">
                    <div class="d-grid gap-2">
                        <a href="transactions_history.php?user_id=<?php echo $user_id; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-clock-history me-2"></i> İşlem Geçmişi
                        </a>
                        <a href="site_users.php" class="btn btn-outline-secondary">
                            <i class="bi bi-people me-2"></i> Tüm Kullanıcılar
                        </a>
                        <?php if ($user['is_banned'] == 0): ?>
                        <a href="#" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#banUserModal">
                            <i class="bi bi-shield-x me-2"></i> Kullanıcıyı Banla
                        </a>
                        <?php else: ?>
                        <a href="#" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#unbanUserModal">
                            <i class="bi bi-shield-check me-2"></i> Banı Kaldır
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ban User Modal -->
<div class="modal fade" id="banUserModal" tabindex="-1" aria-labelledby="banUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="banUserModalLabel">Kullanıcıyı Banla</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong> kullanıcısını banlamak üzeresiniz. Bu işlem kullanıcının hesabını kilitleyecektir.
                    </div>
                    <div class="mb-3">
                        <label for="ban_reason" class="form-label">Ban Sebebi</label>
                        <textarea class="form-control" id="ban_reason" name="ban_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="ban_user" class="btn btn-danger">Kullanıcıyı Banla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Unban User Modal -->
<div class="modal fade" id="unbanUserModal" tabindex="-1" aria-labelledby="unbanUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="unbanUserModalLabel">Ban Kaldır</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i> 
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong> kullanıcısının banını kaldırmak üzeresiniz. Bu işlem kullanıcının hesabını tekrar aktif edecektir.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <form method="post" action="">
                    <button type="submit" name="unban_user" class="btn btn-success">Banı Kaldır</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 