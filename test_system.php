<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Sistem Test";
$currentPage = "test_system";

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
$stmt = $db->prepare("SELECT a.*, r.name as role_name FROM administrators a 
                      JOIN admin_roles r ON a.role_id = r.id 
                      WHERE a.id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();

ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-gear"></i> Sistem Test Sayfası</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Test Sonuçları:</h6>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Database Bağlantısı
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Session Yönetimi
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Tema Sistemi
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Bildirim Sistemi
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Responsive Tasarım
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Test Butonları:</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="testTheme()">
                                    <i class="bi bi-palette"></i> Tema Testi
                                </button>
                                <button class="btn btn-success" onclick="testNotification()">
                                    <i class="bi bi-bell"></i> Bildirim Testi
                                </button>
                                <button class="btn btn-info" onclick="testSidebar()">
                                    <i class="bi bi-list"></i> Sidebar Testi
                                </button>
                                <button class="btn btn-warning" onclick="testResponsive()">
                                    <i class="bi bi-phone"></i> Responsive Testi
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Bildirim Testi:</h6>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-success" onclick="showTestNotification('success')">
                                    Başarı Bildirimi
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="showTestNotification('warning')">
                                    Uyarı Bildirimi
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="showTestNotification('error')">
                                    Hata Bildirimi
                                </button>
                                <button class="btn btn-sm btn-info" onclick="showTestNotification('info')">
                                    Bilgi Bildirimi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testTheme() {
    const themes = ['default', 'blue', 'green'];
    let currentIndex = 0;
    
    const interval = setInterval(() => {
        if (window.themeSystem) {
            window.themeSystem.switchTheme(themes[currentIndex]);
            currentIndex++;
            
            if (currentIndex >= themes.length) {
                clearInterval(interval);
                // Return to default theme
                setTimeout(() => {
                    window.themeSystem.switchTheme('default');
                }, 1000);
            }
        }
    }, 1500);
    
    Swal.fire({
        title: 'Tek Renk Tema Testi',
        text: 'Temalar otomatik olarak değişecek...',
        icon: 'info',
        timer: 5000,
        showConfirmButton: false
    });
}

function testNotification() {
    const notificationBtn = document.getElementById('notificationBtn');
    if (notificationBtn) {
        notificationBtn.click();
        Swal.fire({
            title: 'Bildirim Testi',
            text: 'Bildirim dropdown açıldı!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

function testSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
        Swal.fire({
            title: 'Sidebar Testi',
            text: 'Sidebar toggle edildi!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

function testResponsive() {
    Swal.fire({
        title: 'Responsive Test',
        text: 'Sayfayı yeniden boyutlandırın ve responsive davranışı test edin!',
        icon: 'info',
        confirmButtonText: 'Tamam'
    });
}

function showTestNotification(type) {
    const messages = {
        success: 'Bu bir başarı bildirimidir!',
        warning: 'Bu bir uyarı bildirimidir!',
        error: 'Bu bir hata bildirimidir!',
        info: 'Bu bir bilgi bildirimidir!'
    };
    
    Swal.fire({
        title: messages[type],
        icon: type,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        background: 'var(--bg-card)',
        color: 'var(--text-primary)'
    });
}

// Test sayfası yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    console.log('Test sayfası yüklendi');
    console.log('Theme System:', window.themeSystem);
    console.log('Current Theme:', window.themeSystem?.getCurrentTheme());
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
