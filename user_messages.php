<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Kullanıcı Mesajları";

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
        WHERE ap.role_id = ? AND (ap.menu_item = 'user_messages' OR ap.menu_item = 'messages') AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

$message = '';
$error = '';

// Handle individual message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    
    if (empty($userId) || empty($subject) || empty($content)) {
        $error = "Kullanıcı, başlık ve içerik alanları zorunludur.";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO mesajlar (user_id, baslik, icerik, okundu, silinmis, gonderim_tarihi) VALUES (?, ?, ?, 0, 0, NOW())");
            $stmt->execute([$userId, $subject, $content]);
            $message = "Mesaj başarıyla gönderildi.";
        } catch (PDOException $e) {
            $error = "Mesaj gönderilirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Handle mass message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_mass_message'])) {
    $subject = isset($_POST['mass_subject']) ? trim($_POST['mass_subject']) : '';
    $content = isset($_POST['mass_content']) ? trim($_POST['mass_content']) : '';
    $userIds = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    
    if (empty($subject) || empty($content) || empty($userIds)) {
        $error = "Başlık, içerik ve en az bir kullanıcı seçilmelidir.";
    } else {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO mesajlar (user_id, baslik, icerik, okundu, silinmis, gonderim_tarihi) VALUES (?, ?, ?, 0, 0, NOW())");
            
            $successCount = 0;
            foreach ($userIds as $userId) {
                $stmt->execute([(int)$userId, $subject, $content]);
                $successCount++;
            }
            
            $db->commit();
            $message = $successCount . " kullanıcıya mesaj başarıyla gönderildi.";
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Toplu mesaj gönderilirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Handle message deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $messageId = (int)$_GET['delete'];
    
    try {
        // First check if the message exists
        $checkStmt = $db->prepare("SELECT id FROM mesajlar WHERE id = ?");
        $checkStmt->execute([$messageId]);
        
        if ($checkStmt->rowCount() > 0) {
            // Message exists, permanently delete it from the database
            $stmt = $db->prepare("DELETE FROM mesajlar WHERE id = ?");
            $stmt->execute([$messageId]);
            
            $message = "Mesaj başarıyla silindi.";
        } else {
            $error = "Mesaj bulunamadı.";
        }
        
        // Redirect to remove the delete parameter from URL to prevent accidental refresh issues
        header("Location: user_messages.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error = "Mesaj silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Show success message if coming from a redirect after deletion
if (isset($_GET['success']) && $_GET['success'] == 1 && empty($error)) {
    $message = "Mesaj başarıyla silindi.";
}

// Get statistics
try {
    // Total messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM mesajlar");
    $stmt->execute();
    $totalMessages = $stmt->fetch()['total'];
    
    // Read messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM mesajlar WHERE okundu = 1");
    $stmt->execute();
    $readMessages = $stmt->fetch()['total'];
    
    // Unread messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM mesajlar WHERE okundu = 0");
    $stmt->execute();
    $unreadMessages = $stmt->fetch()['total'];
    
    // Today's messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM mesajlar WHERE DATE(gonderim_tarihi) = CURDATE()");
    $stmt->execute();
    $todayMessages = $stmt->fetch()['total'];
    
    // This week's messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM mesajlar WHERE gonderim_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $weekMessages = $stmt->fetch()['total'];
    
    // This month's messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM mesajlar WHERE gonderim_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $monthMessages = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalMessages = $readMessages = $unreadMessages = $todayMessages = $weekMessages = $monthMessages = 0;
}

// Get messages
try {
    $stmt = $db->query("
        SELECT m.*, k.username 
        FROM mesajlar m 
        JOIN kullanicilar k ON m.user_id = k.id 
        ORDER BY m.gonderim_tarihi DESC
    ");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Mesajlar yüklenirken bir hata oluştu: " . $e->getMessage();
    $messages = [];
}

// Get users for dropdowns
try {
    $stmt = $db->query("SELECT id, username FROM kullanicilar ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Kullanıcılar yüklenirken bir hata oluştu: " . $e->getMessage();
    $users = [];
}

// Set current page for sidebar
$currentPage = 'user_messages';

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
    
    .stat-card.read::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.unread::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.today::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.week::after {
        background: var(--primary-gradient);
    }

    .stat-card.month::after {
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
    
    .stat-card.read .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.unread .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.today .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.week .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.month .stat-icon {
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

    .messages-table-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        border: 1px solid var(--card-border);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .messages-table-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .messages-table-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .messages-table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-heading);
        display: flex;
        align-items: center;
    }
    
    .messages-table-title i {
        margin-right: 0.5rem;
        color: var(--primary-blue-light);
    }

    .messages-table-body {
        padding: 1.5rem;
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

    .btn-secondary {
        background: var(--secondary-gradient);
        color: var(--white);
    }

    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .btn-info {
        background: var(--info-blue);
        color: var(--white);
    }

    .btn-info:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-blue);
        color: var(--white);
    }

    .btn-danger {
        background: var(--error-red);
        color: var(--white);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-danger);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    .alert {
        border-radius: var(--border-radius);
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        position: relative;
        animation: fadeIn 0.5s ease;
    }

    .alert-success {
        background: #dcfce7;
        border-left: 4px solid var(--success-green);
        color: #166534;
    }

    .alert-danger {
        background: #fee2e2;
        border-left: 4px solid var(--error-red);
        color: #7f1d1d;
    }

    .table {
        background: var(--card-bg) !important;
        border-radius: var(--border-radius) !important;
        overflow: hidden !important;
    }

    .table thead th {
        background: var(--bg-secondary) !important;
        color: var(--text-secondary) !important;
        font-weight: 600 !important;
        border-bottom: 2px solid var(--card-border) !important;
        padding: 1rem !important;
        font-size: 0.9rem !important;
    }

    .table tbody td {
        color: var(--text-primary) !important;
        border-bottom: 1px solid var(--card-border) !important;
        padding: 1rem !important;
        font-size: 0.9rem !important;
    }

    .table tbody tr {
        transition: all 0.3s ease !important;
    }

    .table tbody tr:hover {
        background-color: var(--ultra-light-blue) !important;
        transform: translateY(-1px) !important;
    }

    .badge {
        padding: 6px 12px !important;
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
    }

    .badge.bg-success {
        background: var(--success-green) !important;
        color: var(--white) !important;
    }

    .badge.bg-danger {
        background: var(--error-red) !important;
        color: var(--white) !important;
    }

    .badge.bg-warning {
        background: var(--warning-orange) !important;
        color: var(--white) !important;
    }

    .badge.bg-secondary {
        background: var(--dark-gray) !important;
        color: var(--white) !important;
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
        
        .messages-table-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-envelope-fill me-2"></i>
        Kullanıcı Mesajları
    </h1>
    <p class="dashboard-subtitle">Kullanıcılara mesaj gönderin ve mesaj durumlarını takip edin.</p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success animate-fade-in">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger animate-fade-in">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card read">
        <div class="stat-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($readMessages); ?></div>
        <div class="stat-title">Okunmuş</div>
    </div>
    
    <div class="stat-card unread">
        <div class="stat-icon">
            <i class="bi bi-envelope-fill"></i>
        </div>
        <div class="stat-number"><?php echo number_format($unreadMessages); ?></div>
        <div class="stat-title">Okunmamış</div>
    </div>
    
    <div class="stat-card today">
        <div class="stat-icon">
            <i class="bi bi-calendar-day"></i>
        </div>
        <div class="stat-number"><?php echo number_format($todayMessages); ?></div>
        <div class="stat-title">Bugün</div>
    </div>
    
    <div class="stat-card week">
        <div class="stat-icon">
            <i class="bi bi-calendar-week"></i>
        </div>
        <div class="stat-number"><?php echo number_format($weekMessages); ?></div>
        <div class="stat-title">Bu Hafta</div>
    </div>
    
    <div class="stat-card month">
        <div class="stat-icon">
            <i class="bi bi-calendar-month"></i>
        </div>
        <div class="stat-number"><?php echo number_format($monthMessages); ?></div>
        <div class="stat-title">Bu Ay</div>
    </div>
</div>

<div class="messages-table-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="messages-table-header">
        <h5 class="messages-table-title">
            <i class="bi bi-table"></i>
            Mesaj Listesi
        </h5>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-funnel-fill me-1"></i> Filtrele
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                    <li>
                        <button class="dropdown-item filter-messages" data-filter="all">
                            <i class="bi bi-collection-fill me-2"></i>Tüm Mesajlar
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item filter-messages" data-filter="unread">
                            <i class="bi bi-envelope-fill me-2"></i>Okunmamış Mesajlar
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item filter-messages" data-filter="read">
                            <i class="bi bi-check-circle-fill me-2"></i>Okunmuş Mesajlar
                        </button>
                    </li>
                </ul>
            </div>
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                <i class="bi bi-send me-1"></i> Yeni Mesaj
            </button>
            <button class="btn btn-info btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#sendMassMessageModal">
                <i class="bi bi-send-check me-1"></i> Toplu Mesaj
            </button>
        </div>
    </div>
    <div class="messages-table-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover" id="messagesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı</th>
                        <th>Başlık</th>
                        <th>İçerik</th>
                        <th class="text-center">Durumu</th>
                        <th class="text-center">Gönderim Tarihi</th>
                        <th class="text-center">Okunma Tarihi</th>
                        <th class="text-center">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="8" class="text-center">Henüz mesaj gönderilmemiş.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <tr class="<?php echo $msg['okundu'] ? '' : 'table-warning bg-opacity-50'; ?>">
                            <td><?php echo $msg['id']; ?></td>
                            <td>
                                <a href="user_details.php?id=<?php echo $msg['user_id']; ?>" style="color: var(--primary-blue-light); text-decoration: none; font-weight: 600;">
                                    <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($msg['username']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($msg['baslik']); ?></span>
                                <?php if (!$msg['okundu']): ?>
                                <span class="badge bg-danger ms-2">Yeni</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width: 200px;">
                                    <?php echo htmlspecialchars($msg['icerik']); ?>
                                </span>
                                <button class="btn btn-sm btn-outline-primary p-0 ms-2" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewContentModal" 
                                        data-id="<?php echo $msg['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($msg['username']); ?>"
                                        data-title="<?php echo htmlspecialchars($msg['baslik']); ?>"
                                        data-content="<?php echo htmlspecialchars($msg['icerik']); ?>"
                                        data-date="<?php echo date('d.m.Y H:i', strtotime($msg['gonderim_tarihi'])); ?>">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </td>
                            <td class="text-center">
                                <?php if ($msg['okundu']): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle-fill me-1"></i> Okundu
                                </span>
                                <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="bi bi-envelope-fill me-1"></i> Okunmadı
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?php echo date('d.m.Y', strtotime($msg['gonderim_tarihi'])); ?>
                                </span>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo date('H:i', strtotime($msg['gonderim_tarihi'])); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($msg['okunma_tarihi']): ?>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    <?php echo date('d.m.Y', strtotime($msg['okunma_tarihi'])); ?>
                                </span>
                                <span class="badge bg-secondary">
                                    <i class="bi bi-clock-history me-1"></i>
                                    <?php echo date('H:i', strtotime($msg['okunma_tarihi'])); ?>
                                </span>
                                <?php else: ?>
                                <span class="badge bg-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-danger delete-message" data-id="<?php echo $msg['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for sending individual message -->
<div class="modal fade" id="sendMessageModal" tabindex="-1" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                <h5 class="modal-title" id="sendMessageModalLabel">
                    <i class="bi bi-send me-2"></i> Yeni Mesaj Gönder
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="mb-4">
                                <label for="user_id" class="form-label fw-bold" style="color: var(--text-heading);">
                                    <i class="bi bi-person-circle me-2" style="color: var(--primary-blue-light);"></i> Kullanıcı
                                </label>
                                <select class="form-control" id="user_id" name="user_id" required>
                                    <option value="">Kullanıcı Seçin</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="alert alert-info border-0 rounded-3 mt-2 py-2" style="background: var(--light-blue); color: var(--primary-blue-dark);">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                                        <span>Mesaj göndermek istediğiniz kullanıcıyı listeden seçiniz</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="subject" class="form-label fw-bold" style="color: var(--text-heading);">
                                    <i class="bi bi-tag-fill me-2" style="color: var(--primary-blue-light);"></i> Başlık
                                </label>
                                <input type="text" class="form-control" id="subject" name="subject" required placeholder="Mesaj başlığını girin">
                            </div>
                            <div class="mb-2">
                                <label for="content" class="form-label fw-bold" style="color: var(--text-heading);">
                                    <i class="bi bi-card-text me-2" style="color: var(--primary-blue-light);"></i> İçerik
                                </label>
                                <textarea class="form-control" id="content" name="content" rows="7" required placeholder="Mesaj içeriğini yazın..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> İptal
                    </button>
                    <button type="submit" name="send_message" class="btn btn-primary">
                        <i class="bi bi-send-fill me-1"></i> Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for sending mass message -->
<div class="modal fade" id="sendMassMessageModal" tabindex="-1" aria-labelledby="sendMassMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--info-blue); color: white;">
                <h5 class="modal-title" id="sendMassMessageModalLabel">
                    <i class="bi bi-send-check me-2"></i> Toplu Mesaj Gönder
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body p-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="mb-4">
                                <label for="user_ids" class="form-label fw-bold" style="color: var(--text-heading);">
                                    <i class="bi bi-people-fill me-2" style="color: var(--primary-blue-light);"></i> Kullanıcılar
                                </label>
                                <select class="form-control" id="user_ids" name="user_ids[]" multiple required>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="alert alert-info border-0 rounded-3 mt-2 py-2" style="background: var(--light-blue); color: var(--primary-blue-dark);">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                                        <span>Ctrl tuşu ile birden fazla kullanıcı seçebilirsiniz</span>
                                    </div>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-primary select-all-users">
                                        <i class="bi bi-check-all me-1"></i> Tümünü Seç
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary deselect-all-users">
                                        <i class="bi bi-x-lg me-1"></i> Seçimi Temizle
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="mass_subject" class="form-label fw-bold" style="color: var(--text-heading);">
                                    <i class="bi bi-tag-fill me-2" style="color: var(--primary-blue-light);"></i> Başlık
                                </label>
                                <input type="text" class="form-control" id="mass_subject" name="mass_subject" required placeholder="Mesaj başlığını girin">
                            </div>
                            <div class="mb-2">
                                <label for="mass_content" class="form-label fw-bold" style="color: var(--text-heading);">
                                    <i class="bi bi-card-text me-2" style="color: var(--primary-blue-light);"></i> İçerik
                                </label>
                                <textarea class="form-control" id="mass_content" name="mass_content" rows="7" required placeholder="Mesaj içeriğini yazın..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> İptal
                    </button>
                    <button type="submit" name="send_mass_message" class="btn btn-info">
                        <i class="bi bi-send-fill me-1"></i> Tüm Seçili Kullanıcılara Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for viewing message content -->
<div class="modal fade" id="viewContentModal" tabindex="-1" aria-labelledby="viewContentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                <h5 class="modal-title" id="viewContentModalLabel">
                    <i class="bi bi-envelope-open me-2"></i> Mesaj Detayı
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header" style="background: var(--ultra-light-blue);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold" id="modal-sender-name" style="color: var(--text-heading);"></span>
                                <span class="text-muted ms-2" id="modal-message-date"></span>
                            </div>
                            <div>
                                <span class="badge" id="modal-message-id" style="background: var(--primary-blue-light); color: white;"></span>
                                <span class="badge bg-success d-none" id="modal-read-status"><i class="bi bi-check-circle-fill me-1"></i>Okundu</span>
                                <span class="badge bg-warning d-none" id="modal-unread-status"><i class="bi bi-envelope-fill me-1"></i>Okunmadı</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title" id="modal-message-title" style="color: var(--text-heading);"></h5>
                        <hr>
                        <div class="card-text p-3 rounded" id="content-display" style="background: var(--light-gray);"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i> Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add console logs for debugging
    console.log('DOM loaded, initializing DataTables');
    
    // Check if jQuery and DataTables are available
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded');
        return;
    }
    
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables is not loaded');
        return;
    }
    
    console.log('jQuery and DataTables are available');
    
    // Verify and fix table structure if needed
    const fixTableStructure = function() {
        const headerCols = $('#messagesTable thead th').length;
        
        // Check each row to ensure it has the right number of columns
        $('#messagesTable tbody tr').each(function() {
            const rowCols = $(this).find('td').length;
            if (rowCols !== headerCols) {
                console.warn(`Row column count mismatch: ${rowCols} vs expected ${headerCols}`);
                
                // If this is an empty data message spanning all columns, leave it
                if (rowCols === 1 && $(this).find('td').attr('colspan') == headerCols) {
                    console.log('This is a valid empty data row with colspan');
                    return;
                }
                
                // Fix: If there are too few columns, add empty ones
                if (rowCols < headerCols) {
                    for (let i = rowCols; i < headerCols; i++) {
                        $(this).append('<td></td>');
                        console.log('Added missing column to row');
                    }
                }
                
                // Fix: If there are too many columns, remove extras
                if (rowCols > headerCols) {
                    $(this).find('td').slice(headerCols).remove();
                    console.log('Removed extra columns from row');
                }
            }
        });
    };
    
    // Apply the fix
    fixTableStructure();
    
    // Destroy existing DataTable if it exists (prevents duplicate initialization)
    if ($.fn.dataTable.isDataTable('#messagesTable')) {
        console.log('Destroying existing DataTable instance');
        $('#messagesTable').DataTable().destroy();
    }
    
    // Initialize DataTable with minimal configuration to avoid column count issues
    try {
 // 1) DataTables uyarı modunu kapat
  $.fn.dataTable.ext.errMode = 'none';

  document.addEventListener('DOMContentLoaded', function() {
    // ...
    try {
      const dt = $('#messagesTable').DataTable({
        order: [[5, 'desc']],
        language: {
          url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        responsive: true,
        autoWidth: false,
        columnDefs: [
          { orderable: true, searchable: true, targets: [0,1,2,4,5,6] },
          { orderable: false, searchable: true, targets: 3 },
          { orderable: false, searchable: false, targets: 7 }
        ],
        dom: '<"d-flex justify-content-between mb-3"<"d-flex"l><"d-flex"f>>rtip',
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,"Tümü"]],
        pagingType: 'full_numbers',
        // Hata callback’i
        error: function(settings, helpPage, message) {
          console.error('DataTables initialization error:', message);
        }
      });
      console.log('DataTable başarılı şekilde başlatıldı.');
      // Stil düzeltmeleri...
    } catch (error) {
      console.error('DataTable oluşturulurken hata oluştu:', error);
    }
    // ...
  });
        console.log('DataTable initialized successfully');
        
        // Add custom styling for DataTables elements to make them white
        $('.dataTables_info, .dataTables_length, .dataTables_filter label, .dataTables_paginate .paginate_button').addClass('text-white');
        $('.dataTables_filter input, .dataTables_length select').addClass('bg-dark text-white border-secondary');
    } catch (error) {
        console.error('Error initializing DataTable:', error);
    }
    
    // Enhanced view message content modal
    $('#viewContentModal').on('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const messageId = button.getAttribute('data-id');
        const username = button.getAttribute('data-username');
        const title = button.getAttribute('data-title');
        const content = button.getAttribute('data-content');
        const date = button.getAttribute('data-date');
        const row = button.closest('tr');
        const isUnread = row.classList.contains('table-warning');
        
        document.getElementById('modal-message-id').innerText = '#' + messageId;
        document.getElementById('modal-sender-name').innerText = username;
        document.getElementById('modal-message-title').innerText = title;
        document.getElementById('modal-message-date').innerText = date;
        
        // Hide all status badges first
        document.getElementById('modal-read-status').classList.add('d-none');
        document.getElementById('modal-unread-status').classList.add('d-none');
        
        // Show the appropriate status badge
        if (isUnread) {
            document.getElementById('modal-unread-status').classList.remove('d-none');
        } else {
            document.getElementById('modal-read-status').classList.remove('d-none');
        }
        
        // Format content with line breaks
        const formattedContent = content.replace(/\n/g, '<br>');
        document.getElementById('content-display').innerHTML = formattedContent;
    });
    
    // Delete message confirmation with enhanced SweetAlert
    document.querySelectorAll('.delete-message').forEach(button => {
        button.addEventListener('click', function() {
            const messageId = this.getAttribute('data-id');
            
            Swal.fire({
                title: 'Emin misiniz?',
                html: "<b>Bu mesaj silinecektir!</b><br><small>Bu işlem geri alınamaz.</small>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="bi bi-trash me-1"></i> Evet, sil!',
                cancelButtonText: '<i class="bi bi-x-circle me-1"></i> İptal',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'İşlem yapılıyor...',
                        text: 'Mesaj siliniyor, lütfen bekleyin.',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Redirect to delete URL
                    window.location.href = `user_messages.php?delete=${messageId}`;
                }
            });
        });
    });
    
    // Select/deselect all users for mass message
    document.querySelector('.select-all-users').addEventListener('click', function() {
        const options = document.querySelectorAll('#user_ids option');
        options.forEach(option => option.selected = true);
    });
    
    document.querySelector('.deselect-all-users').addEventListener('click', function() {
        const options = document.querySelectorAll('#user_ids option');
        options.forEach(option => option.selected = false);
    });
    
    // Message filtering functionality
    document.querySelectorAll('.filter-messages').forEach(button => {
        button.addEventListener('click', function() {
            const filterType = this.getAttribute('data-filter');
            const table = $('#messagesTable').DataTable();
            
            // Set active filter button
            document.querySelectorAll('.filter-messages').forEach(btn => {
                btn.classList.remove('active', 'bg-primary', 'text-white');
            });
            this.classList.add('active', 'bg-primary', 'text-white');
            
            // Update filter dropdown text
            let filterButtonText = '<i class="bi bi-funnel-fill me-1"></i> ';
            switch(filterType) {
                case 'active':
                    filterButtonText += 'Aktif Mesajlar';
                    break;
                case 'deleted':
                    filterButtonText += 'Silinmiş Mesajlar';
                    break;
                case 'unread':
                    filterButtonText += 'Okunmamış Mesajlar';
                    break;
                case 'read':
                    filterButtonText += 'Okunmuş Mesajlar';
                    break;
                case 'deleted-read':
                    filterButtonText += 'Okunup Silinmiş';
                    break;
                case 'deleted-unread':
                    filterButtonText += 'Okunmadan Silinmiş';
                    break;
                default:
                    filterButtonText += 'Tüm Mesajlar';
            }
            document.getElementById('filterDropdown').innerHTML = filterButtonText;
            
            // Apply filter to DataTable
            table.draw();
        });
    });
    
    // Custom filtering function for DataTables
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, row) {
        const currentFilter = document.querySelector('.filter-messages.active')?.getAttribute('data-filter') || 'all';
        if (currentFilter === 'all') return true;
        
        const row_el = $(settings.aoData[dataIndex].nTr);
        const isUnread = row_el.hasClass('table-warning');
        const isRead = !isUnread;
        
        switch(currentFilter) {
            case 'unread':
                return isUnread;
            case 'read':
                return isRead;
            default:
                return true;
        }
    });
    
    // Set initial filter to 'all'
    document.querySelector('.filter-messages[data-filter="all"]').classList.add('active', 'bg-primary', 'text-white');
});
</script>

<style>
/* Add custom styles to make DataTables pagination information white */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_processing,
.dataTables_wrapper .dataTables_paginate {
    color: #fff !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: #fff !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    color: #fff !important;
    background: #0d6efd !important;
    border-color: #0d6efd !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    color: #fff !important;
    background: #343a40 !important;
    border-color: #6c757d !important;
}

.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    background-color: #212529 !important;
    color: #fff !important;
    border: 1px solid #495057 !important;
}
</style>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 