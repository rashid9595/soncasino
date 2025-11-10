<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Bildirimler";
$currentPage = "notifications";

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

ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-bell"></i> Bildirim Yönetimi</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary" id="markAllRead">
                                    <i class="bi bi-check-all"></i> Tümünü Okundu İşaretle
                                </button>
                                <button class="btn btn-outline-danger" id="deleteAll">
                                    <i class="bi bi-trash"></i> Tümünü Sil
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-2 justify-content-end">
                                <select class="form-select" id="filterType" style="width: auto;">
                                    <option value="">Tüm Bildirimler</option>
                                    <option value="success">Başarı</option>
                                    <option value="warning">Uyarı</option>
                                    <option value="error">Hata</option>
                                    <option value="info">Bilgi</option>
                                </select>
                                <select class="form-select" id="filterStatus" style="width: auto;">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="unread">Okunmamış</option>
                                    <option value="read">Okunmuş</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="notifications-container" id="notificationsContainer">
                        <!-- Bildirimler buraya gelecek -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notifications-container {
    max-height: 600px;
    overflow-y: auto;
}

.notification-item-page {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    background: var(--bg-card);
}

.notification-item-page:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.notification-item-page.unread {
    background: rgba(59, 130, 246, 0.05);
    border-color: var(--accent-color);
}

.notification-icon-page {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.notification-icon-page.success { background: var(--notification-success); }
.notification-icon-page.warning { background: var(--notification-warning); }
.notification-icon-page.error { background: var(--notification-error); }
.notification-icon-page.info { background: var(--notification-info); }

.notification-content-page {
    flex: 1;
}

.notification-title-page {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.notification-message-page {
    color: var(--text-secondary);
    line-height: 1.5;
    margin-bottom: 0.5rem;
}

.notification-meta-page {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.notification-actions-page {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

.empty-notifications {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.empty-notifications i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Örnek bildirimler
    let notifications = [
        {
            id: 1,
            type: 'success',
            title: 'Yeni Kullanıcı Kaydı',
            message: 'Ahmet Yılmaz sisteme başarıyla kayıt oldu. Kullanıcı bilgileri doğrulandı ve hesap aktif edildi.',
            time: '2 dakika önce',
            unread: true
        },
        {
            id: 2,
            type: 'warning',
            title: 'Sistem Uyarısı',
            message: 'Sunucu yükü %85\'e ulaştı. Performans optimizasyonu önerilir.',
            time: '5 dakika önce',
            unread: true
        },
        {
            id: 3,
            type: 'info',
            title: 'Güncelleme Tamamlandı',
            message: 'Sistem güncellemesi başarıyla tamamlandı. Yeni özellikler aktif edildi.',
            time: '10 dakika önce',
            unread: true
        },
        {
            id: 4,
            type: 'error',
            title: 'Hata Bildirimi',
            message: 'Veritabanı bağlantısında geçici sorun tespit edildi. Sistem otomatik olarak yeniden bağlanmaya çalışıyor.',
            time: '15 dakika önce',
            unread: false
        },
        {
            id: 5,
            type: 'success',
            title: 'Yedekleme Tamamlandı',
            message: 'Günlük veritabanı yedeklemesi başarıyla tamamlandı.',
            time: '1 saat önce',
            unread: false
        }
    ];
    
    function renderNotifications() {
        const container = document.getElementById('notificationsContainer');
        const filterType = document.getElementById('filterType').value;
        const filterStatus = document.getElementById('filterStatus').value;
        
        let filteredNotifications = notifications;
        
        if (filterType) {
            filteredNotifications = filteredNotifications.filter(n => n.type === filterType);
        }
        
        if (filterStatus) {
            filteredNotifications = filteredNotifications.filter(n => 
                filterStatus === 'unread' ? n.unread : !n.unread
            );
        }
        
        if (filteredNotifications.length === 0) {
            container.innerHTML = `
                <div class="empty-notifications">
                    <i class="bi bi-bell-slash"></i>
                    <h5>Bildirim Bulunamadı</h5>
                    <p>Seçilen kriterlere uygun bildirim bulunmuyor.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = filteredNotifications.map(notification => `
            <div class="notification-item-page ${notification.unread ? 'unread' : ''}" data-id="${notification.id}">
                <div class="notification-icon-page ${notification.type}">
                    <i class="bi bi-${getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content-page">
                    <div class="notification-title-page">${notification.title}</div>
                    <div class="notification-message-page">${notification.message}</div>
                    <div class="notification-meta-page">
                        <span>${notification.time}</span>
                        <div class="notification-actions-page">
                            ${notification.unread ? 
                                `<button class="btn btn-sm btn-outline-primary mark-read" data-id="${notification.id}">
                                    <i class="bi bi-check"></i> Okundu
                                </button>` : ''
                            }
                            <button class="btn btn-sm btn-outline-danger delete-notification" data-id="${notification.id}">
                                <i class="bi bi-trash"></i> Sil
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Event listeners
        document.querySelectorAll('.mark-read').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = parseInt(this.dataset.id);
                markAsRead(id);
            });
        });
        
        document.querySelectorAll('.delete-notification').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = parseInt(this.dataset.id);
                deleteNotification(id);
            });
        });
    }
    
    function getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            warning: 'exclamation-triangle',
            error: 'x-circle',
            info: 'info-circle'
        };
        return icons[type] || 'bell';
    }
    
    function markAsRead(id) {
        const notification = notifications.find(n => n.id === id);
        if (notification) {
            notification.unread = false;
            renderNotifications();
            showToast('Bildirim okundu olarak işaretlendi!', 'success');
        }
    }
    
    function deleteNotification(id) {
        if (confirm('Bu bildirimi silmek istediğinizden emin misiniz?')) {
            notifications = notifications.filter(n => n.id !== id);
            renderNotifications();
            showToast('Bildirim silindi!', 'success');
        }
    }
    
    function markAllAsRead() {
        notifications.forEach(n => n.unread = false);
        renderNotifications();
        showToast('Tüm bildirimler okundu olarak işaretlendi!', 'success');
    }
    
    function deleteAllNotifications() {
        if (confirm('Tüm bildirimleri silmek istediğinizden emin misiniz?')) {
            notifications = [];
            renderNotifications();
            showToast('Tüm bildirimler silindi!', 'success');
        }
    }
    
    function showToast(message, type = 'info') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: message,
                icon: type,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                background: 'var(--bg-card)',
                color: 'var(--text-primary)'
            });
        }
    }
    
    // Event listeners
    document.getElementById('markAllRead').addEventListener('click', markAllAsRead);
    document.getElementById('deleteAll').addEventListener('click', deleteAllNotifications);
    document.getElementById('filterType').addEventListener('change', renderNotifications);
    document.getElementById('filterStatus').addEventListener('change', renderNotifications);
    
    // Initialize
    renderNotifications();
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <?php echo $pageContent; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
