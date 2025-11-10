<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Chat Mesajları";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check permissions
$isAdmin = ($_SESSION['role_id'] == 1);
if (!$isAdmin) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

// Process form submissions
$error = "";
$success = "";

// Process message deletion
if (isset($_POST['action']) && $_POST['action'] == 'delete_message') {
    $messageId = (int)$_POST['message_id'];
    
    try {
        $stmt = $db->prepare("DELETE FROM chat_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            'delete',
            $_SERVER['REMOTE_ADDR'],
            "Chat mesajı silindi (ID: $messageId)"
        ]);
        
        $success = "Mesaj başarıyla silindi.";
    } catch (PDOException $e) {
        $error = "Mesaj silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Process user ban
if (isset($_POST['action']) && $_POST['action'] == 'ban_user') {
    $banUserId = (int)$_POST['ban_user_id'];
    $reason = trim($_POST['ban_reason']);
    $duration = (int)$_POST['ban_duration'];
    
    try {
        $stmt = $db->prepare("INSERT INTO chat_bans (user_id, banned_by, reason, ban_time, ban_duration) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$banUserId, $_SESSION['admin_id'], $reason, $duration]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            'create',
            $_SERVER['REMOTE_ADDR'],
            "Kullanıcı chat'ten banlandı (ID: $banUserId)"
        ]);
        
        $success = "Kullanıcı başarıyla banlandı.";
    } catch (PDOException $e) {
        $error = "Kullanıcı banlanırken bir hata oluştu: " . $e->getMessage();
    }
}

// Get statistics for dashboard
try {
    // Total messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_messages");
    $stmt->execute();
    $totalMessages = $stmt->fetch()['total'];
    
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_users");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['total'];
    
    // Today's messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_messages WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayMessages = $stmt->fetch()['total'];
    
    // Active users today
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as total FROM chat_messages WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $activeUsersToday = $stmt->fetch()['total'];
    
    // Average messages per user
    $stmt = $db->prepare("SELECT AVG(message_count) as avg_messages FROM (SELECT user_id, COUNT(*) as message_count FROM chat_messages GROUP BY user_id) as user_messages");
    $stmt->execute();
    $avgMessagesPerUser = $stmt->fetch()['avg_messages'] ?? 0;
    
    // Banned users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_bans WHERE ban_time + INTERVAL ban_duration DAY > NOW()");
    $stmt->execute();
    $bannedUsers = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalMessages = $totalUsers = $todayMessages = $activeUsersToday = $avgMessagesPerUser = $bannedUsers = 0;
}

// Initialize filter variables
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

// Build query based on filters
$query = "
    SELECT 
        cm.*,
        cu.username,
        cu.color
    FROM 
        chat_messages cm
    JOIN 
        chat_users cu ON cm.user_id = cu.id
    WHERE 1=1
";

$queryParams = [];

if ($userId) {
    $query .= " AND cm.user_id = ?";
    $queryParams[] = $userId;
}

if ($dateFrom) {
    $query .= " AND DATE(cm.created_at) >= ?";
    $queryParams[] = $dateFrom;
}

if ($dateTo) {
    $query .= " AND DATE(cm.created_at) <= ?";
    $queryParams[] = $dateTo;
}

if ($keyword) {
    $query .= " AND cm.message LIKE ?";
    $queryParams[] = "%$keyword%";
}

$query .= " ORDER BY cm.created_at DESC";

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($queryParams);
$messages = $stmt->fetchAll();

// Get all chat users for filter dropdown
$stmt = $db->prepare("SELECT id, username FROM chat_users ORDER BY username");
$stmt->execute();
$chatUsers = $stmt->fetchAll();

ob_start();
?>

<style>
    :root {
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --secondary-blue: #60a5fa;
        --accent-blue: #dbeafe;
        --success-green: #10b981;
        --warning-orange: #f59e0b;
        --danger-red: #ef4444;
        --info-cyan: #06b6d4;
        --dark-gray: #1f2937;
        --medium-gray: #374151;
        --light-gray: #6b7280;
        --white: #ffffff;
        --gradient-primary: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
        --gradient-secondary: linear-gradient(135deg, var(--secondary-blue) 0%, var(--accent-blue) 100%);
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --border-radius: 12px;
        --transition: all 0.3s ease;
    }

    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--dark-gray);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }

    .dashboard-header {
        background: var(--gradient-primary);
        color: var(--white);
        padding: 2rem 0;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
    }

    .greeting {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .dashboard-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 0;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .stat-card.messages::after { background: var(--gradient-primary); }
    .stat-card.users::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.today::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.active::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.avg::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.banned::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        background: var(--gradient-secondary);
        color: var(--primary-blue);
    }

    .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin-bottom: 0.5rem;
    }

    .stat-card .stat-label {
        color: var(--light-gray);
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .chat-messages-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .chat-messages-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .chat-messages-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chat-messages-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .chat-messages-body {
        padding: 2rem;
    }

    .filter-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        margin-bottom: 2rem;
    }

    .filter-header {
        background: var(--gradient-primary);
        color: var(--white);
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-body {
        padding: 1.5rem;
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    .table th {
        background: #f8fafc;
        color: var(--dark-gray);
        font-weight: 600;
        padding: 1rem;
        border-bottom: 2px solid #e5e7eb;
        text-align: left;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: #f8fafc;
    }

    .user-color-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }

    .message-cell {
        max-width: 400px;
        overflow: hidden;
    }

    .message-content {
        max-height: 80px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .chat-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-blue);
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: var(--transition);
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: var(--gradient-primary);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%);
        color: var(--white);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%);
        color: var(--white);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .text-muted {
        color: var(--light-gray) !important;
    }

    .text-success {
        color: var(--success-green) !important;
    }

    .text-danger {
        color: var(--danger-red) !important;
    }

    .text-warning {
        color: var(--warning-orange) !important;
    }

    .text-info {
        color: var(--info-cyan) !important;
    }

    .fw-bold {
        font-weight: 700;
    }

    .small {
        font-size: 0.875rem;
    }

    .animate-fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Modal styles */
    .modal-content {
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        background: var(--gradient-primary);
        color: var(--white);
        border-bottom: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .form-label {
        color: var(--dark-gray);
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-control {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: var(--transition);
        background: var(--white);
    }

    .form-control:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    .input-group-text {
        background: #f8fafc;
        border: 2px solid #e5e7eb;
        border-left: none;
        color: var(--light-gray);
        font-weight: 600;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem 0;
        }

        .greeting {
            font-size: 1.5rem;
        }

        .stat-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .chat-messages-body {
            padding: 1rem;
        }

        .table-responsive {
            font-size: 0.85rem;
        }

        .table th, .table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="greeting">
            <i class="bi bi-chat-text"></i>
            Chat Mesajları
        </div>
        <div class="dashboard-subtitle">
            Chat mesajlarını yönetin ve kullanıcıları takip edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card messages">
            <div class="stat-icon">
                <i class="bi bi-chat-text"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalMessages); ?></div>
            <div class="stat-label">Toplam Mesaj</div>
        </div>
        
        <div class="stat-card users">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
            <div class="stat-label">Toplam Kullanıcı</div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayMessages); ?></div>
            <div class="stat-label">Bugünkü Mesaj</div>
        </div>
        
        <div class="stat-card active">
            <div class="stat-icon">
                <i class="bi bi-person-check"></i>
            </div>
            <div class="stat-value"><?php echo number_format($activeUsersToday); ?></div>
            <div class="stat-label">Aktif Kullanıcı</div>
        </div>
        
        <div class="stat-card banned">
            <div class="stat-icon">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="stat-value"><?php echo number_format($bannedUsers); ?></div>
            <div class="stat-label">Banlı Kullanıcı</div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-header">
            <i class="bi bi-funnel"></i>
            Mesaj Filtreleri
        </div>
        <div class="filter-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Kullanıcı</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Tüm Kullanıcılar</option>
                        <?php foreach ($chatUsers as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-3">
                    <label for="keyword" class="form-label">Anahtar Kelime</label>
                    <input type="text" class="form-control" id="keyword" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Mesaj içinde ara...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                        Filtrele
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Chat Messages Management -->
    <div class="chat-messages-card">
        <div class="chat-messages-header">
            <div class="chat-messages-title">
                <i class="bi bi-table"></i>
                Chat Mesajları Yönetimi
            </div>
            <a href="chat_bans.php" class="btn btn-warning">
                <i class="bi bi-shield-exclamation"></i>
                Ban Yönetimi
            </a>
        </div>
        <div class="chat-messages-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="messagesTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-person"></i> Kullanıcı</th>
                            <th><i class="bi bi-chat-text"></i> Mesaj</th>
                            <th><i class="bi bi-calendar"></i> Tarih</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">Henüz mesaj yok.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo $message['id']; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-color-dot" style="background-color: <?php echo $message['color'] ? $message['color'] : '#777'; ?>"></div>
                                        <a href="?user_id=<?php echo $message['user_id']; ?>" class="ms-2 text-decoration-none">
                                            <span class="chat-badge">
                                                <?php echo htmlspecialchars($message['username']); ?>
                                            </span>
                                        </a>
                                    </div>
                                </td>
                                <td class="message-cell">
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i:s', strtotime($message['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteMessage(<?php echo $message['id']; ?>, '<?php echo htmlspecialchars($message['username']); ?>', '<?php echo htmlspecialchars($message['message']); ?>')">
                                            <i class="bi bi-trash"></i>
                                            Sil
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="confirmBanUser(<?php echo $message['user_id']; ?>, '<?php echo htmlspecialchars($message['username']); ?>', '<?php echo htmlspecialchars($message['message']); ?>')">
                                            <i class="bi bi-shield-exclamation"></i>
                                            Banla
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable initialization
    $('#messagesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[0, "desc"]],
        "pageLength": 25,
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "columnDefs": [
            { "targets": -1, "orderable": false }
        ]
    });
});

function confirmDeleteMessage(id, username, message) {
    Swal.fire({
        title: 'Emin misiniz?',
        html: `
            <div class="text-start">
                <p><strong>${username}</strong> kullanıcısının aşağıdaki mesajını silmek istediğinize emin misiniz?</p>
                <div class="alert alert-warning">
                    <small>${message}</small>
                </div>
                <p class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Bu işlem geri alınamaz!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_message">
                <input type="hidden" name="message_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function confirmBanUser(userId, username, message) {
    Swal.fire({
        title: 'Kullanıcıyı Banla',
        html: `
            <div class="text-start">
                <p><strong>${username}</strong> kullanıcısını chat'ten banlamak üzeresiniz.</p>
                <div class="alert alert-info">
                    <small><strong>Son Mesaj:</strong><br>${message}</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ban Sebebi</label>
                    <textarea id="ban-reason" class="form-control" rows="3" placeholder="Ban sebebini yazın..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ban Süresi (Gün)</label>
                    <input type="number" id="ban-duration" class="form-control" value="7" min="1" max="365">
                    <small class="text-muted">1-365 gün arası bir değer girin</small>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Banla',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const reason = document.getElementById('ban-reason').value;
            const duration = document.getElementById('ban-duration').value;
            
            if (!reason) {
                Swal.showValidationMessage('Lütfen ban sebebini yazın');
                return false;
            }
            
            if (!duration || duration < 1 || duration > 365) {
                Swal.showValidationMessage('Lütfen 1-365 arası bir süre girin');
                return false;
            }
            
            return { reason, duration };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { reason, duration } = result.value;
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="ban_user">
                <input type="hidden" name="ban_user_id" value="${userId}">
                <input type="hidden" name="ban_reason" value="${reason}">
                <input type="hidden" name="ban_duration" value="${duration}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
