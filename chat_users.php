<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Chat Kullanıcıları";

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

// Process color update
if (isset($_POST['action']) && $_POST['action'] == 'update_color') {
    $userId = (int)$_POST['user_id'];
    $color = trim($_POST['color']);
    
    try {
        $stmt = $db->prepare("UPDATE chat_users SET color = ? WHERE id = ?");
        $stmt->execute([$color, $userId]);
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['admin_id'],
            'update',
            $_SERVER['REMOTE_ADDR'],
            "Chat kullanıcısı rengi güncellendi (ID: $userId)"
        ]);
        
        $success = "Kullanıcı rengi başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error = "Kullanıcı rengi güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// Get statistics for dashboard
try {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_users");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['total'];
    
    // Online users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_online_users");
    $stmt->execute();
    $onlineUsers = $stmt->fetch()['total'];
    
    // New users today
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_users WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $newUsersToday = $stmt->fetch()['total'];
    
    // Active users (with messages today)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as total FROM chat_messages WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $activeUsersToday = $stmt->fetch()['total'];
    
    // Total messages
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_messages");
    $stmt->execute();
    $totalMessages = $stmt->fetch()['total'];
    
    // Banned users
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chat_bans WHERE ban_time + INTERVAL ban_duration DAY > NOW()");
    $stmt->execute();
    $bannedUsers = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalUsers = $onlineUsers = $newUsersToday = $activeUsersToday = $totalMessages = $bannedUsers = 0;
}

// Get all chat users with online status
$stmt = $db->prepare("
    SELECT 
        cu.*, 
        CASE WHEN cou.user_id IS NOT NULL THEN 1 ELSE 0 END as is_online,
        cou.last_activity,
        (SELECT COUNT(*) FROM chat_messages WHERE user_id = cu.id) as message_count,
        (SELECT COUNT(*) FROM chat_bans WHERE user_id = cu.id) as ban_count
    FROM 
        chat_users cu
    LEFT JOIN 
        chat_online_users cou ON cu.id = cou.user_id
    ORDER BY 
        is_online DESC,
        cu.created_at DESC
");
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

    .stat-card.users::after { background: var(--gradient-primary); }
    .stat-card.online::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.new::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.active::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.messages::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
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

    .chat-users-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .chat-users-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .chat-users-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chat-users-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .chat-users-body {
        padding: 2rem;
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

    .color-preview {
        width: 16px;
        height: 16px;
        border-radius: 3px;
        display: inline-block;
        margin-right: 6px;
        vertical-align: middle;
        border: 1px solid rgba(0, 0, 0, 0.1);
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

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
    }

    .status-online {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-green);
    }

    .status-offline {
        background: rgba(107, 114, 128, 0.1);
        color: var(--light-gray);
    }

    .status-banned {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-red);
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

    .btn-info {
        background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%);
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

    .form-control-color {
        width: 60px;
        height: 40px;
        padding: 0;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
    }

    .chat-preview {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
    }

    .chat-username {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .chat-message {
        color: var(--light-gray);
        font-size: 0.9rem;
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

        .chat-users-body {
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
            <i class="bi bi-person-badge"></i>
            Chat Kullanıcıları
        </div>
        <div class="dashboard-subtitle">
            Chat kullanıcılarını yönetin ve takip edin
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
        <div class="stat-card users">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
            <div class="stat-label">Toplam Kullanıcı</div>
        </div>
        
        <div class="stat-card online">
            <div class="stat-icon">
                <i class="bi bi-circle-fill"></i>
            </div>
            <div class="stat-value"><?php echo number_format($onlineUsers); ?></div>
            <div class="stat-label">Çevrimiçi Kullanıcı</div>
        </div>
        
        <div class="stat-card new">
            <div class="stat-icon">
                <i class="bi bi-person-plus"></i>
            </div>
            <div class="stat-value"><?php echo number_format($newUsersToday); ?></div>
            <div class="stat-label">Bugün Yeni Kullanıcı</div>
        </div>
        
        <div class="stat-card active">
            <div class="stat-icon">
                <i class="bi bi-person-check"></i>
            </div>
            <div class="stat-value"><?php echo number_format($activeUsersToday); ?></div>
            <div class="stat-label">Bugün Aktif Kullanıcı</div>
        </div>
        
        <div class="stat-card messages">
            <div class="stat-icon">
                <i class="bi bi-chat-text"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalMessages); ?></div>
            <div class="stat-label">Toplam Mesaj</div>
        </div>
    </div>

    <!-- Chat Users Management -->
    <div class="chat-users-card">
        <div class="chat-users-header">
            <div class="chat-users-title">
                <i class="bi bi-table"></i>
                Chat Kullanıcıları Yönetimi
            </div>
        </div>
        <div class="chat-users-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-person"></i> Kullanıcı Adı</th>
                            <th><i class="bi bi-calendar"></i> Oluşturulma Tarihi</th>
                            <th><i class="bi bi-palette"></i> Renk</th>
                            <th><i class="bi bi-circle-fill"></i> Durum</th>
                            <th><i class="bi bi-clock"></i> Son Aktivite</th>
                            <th><i class="bi bi-chat-text"></i> Mesaj Sayısı</th>
                            <th><i class="bi bi-shield-exclamation"></i> Ban Sayısı</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($chatUsers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">Henüz kullanıcı yok.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($chatUsers as $user): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo $user['id']; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-color-dot" style="background-color: <?php echo $user['color'] ? $user['color'] : '#777'; ?>"></div>
                                        <span class="chat-badge ms-2">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="color-preview" style="background-color: <?php echo $user['color'] ? $user['color'] : '#777'; ?>"></div>
                                        <span class="small"><?php echo $user['color'] ? $user['color'] : 'Belirlenmemiş'; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['is_online']): ?>
                                        <span class="status-badge status-online">
                                            <i class="bi bi-circle-fill me-1"></i>
                                            Çevrimiçi
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-offline">
                                            <i class="bi bi-circle me-1"></i>
                                            Çevrimdışı
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['last_activity']): ?>
                                        <small class="text-muted">
                                            <?php echo date('d.m.Y H:i', strtotime($user['last_activity'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold"><?php echo number_format($user['message_count']); ?></span>
                                </td>
                                <td>
                                    <?php if ($user['ban_count'] > 0): ?>
                                        <span class="status-badge status-banned">
                                            <?php echo $user['ban_count']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-offline">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editUserColor(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['color'] ? $user['color'] : '#777777'; ?>')">
                                            <i class="bi bi-palette"></i>
                                            Renk
                                        </button>
                                        <a href="chat_messages.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-chat-text"></i>
                                            Mesajlar
                                        </a>
                                        <a href="chat_bans.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-shield-exclamation"></i>
                                            Banlar
                                        </a>
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
    $('#usersTable').DataTable({
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

function editUserColor(userId, username, currentColor) {
    Swal.fire({
        title: 'Kullanıcı Rengi Düzenle',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" class="form-control" value="${username}" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Renk</label>
                    <div class="d-flex align-items-center">
                        <input type="color" class="form-control-color" id="color-input" value="${currentColor}">
                        <span class="ms-3 fw-bold" id="color-code">${currentColor}</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Önizleme</label>
                    <div class="chat-preview">
                        <div class="chat-username" id="preview-username" style="color: ${currentColor}">
                            ${username}:
                        </div>
                        <div class="chat-message">
                            Merhaba, bu bir örnek mesajdır.
                        </div>
                    </div>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        didOpen: () => {
            const colorInput = document.getElementById('color-input');
            const colorCode = document.getElementById('color-code');
            const previewUsername = document.getElementById('preview-username');
            
            colorInput.addEventListener('input', function() {
                const newColor = this.value;
                colorCode.textContent = newColor;
                previewUsername.style.color = newColor;
            });
        },
        preConfirm: () => {
            return {
                color: document.getElementById('color-input').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { color } = result.value;
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_color">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="color" value="${color}">
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
