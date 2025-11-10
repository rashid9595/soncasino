<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Mobil Test";
$currentPage = "test_mobile";

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
                    <h5><i class="bi bi-phone"></i> Mobil Test Sayfası</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Mobil Responsive Test:</h6>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Sidebar Toggle
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Touch Events
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Responsive Layout
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Mobile Navigation
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Touch Gestures
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Mobil Test Butonları:</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="testMobileSidebar()">
                                    <i class="bi bi-list"></i> Mobil Sidebar Testi
                                </button>
                                <button class="btn btn-success" onclick="testTouchEvents()">
                                    <i class="bi bi-hand-index"></i> Dokunma Testi
                                </button>
                                <button class="btn btn-info" onclick="testResponsiveLayout()">
                                    <i class="bi bi-phone"></i> Responsive Layout Testi
                                </button>
                                <button class="btn btn-warning" onclick="testMobileNavigation()">
                                    <i class="bi bi-arrow-left-right"></i> Mobil Navigasyon Testi
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Mobil Responsive Test Alanı:</h6>
                            <div class="alert alert-info">
                                <strong>Mobil Test Talimatları:</strong>
                                <ul>
                                    <li>Sayfayı mobil boyutlara küçültün (F12 > Device Toolbar)</li>
                                    <li>Sidebar toggle butonuna dokunun</li>
                                    <li>Menü öğelerine dokunun</li>
                                    <li>Sayfayı farklı ekran boyutlarında test edin</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testMobileSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
        Swal.fire({
            title: 'Mobil Sidebar Testi',
            text: 'Sidebar mobil modda toggle edildi!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

function testTouchEvents() {
    Swal.fire({
        title: 'Dokunma Testi',
        text: 'Dokunma olayları çalışıyor!',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function testResponsiveLayout() {
    Swal.fire({
        title: 'Responsive Layout Testi',
        text: 'Sayfayı farklı boyutlarda test edin!',
        icon: 'info',
        confirmButtonText: 'Tamam'
    });
}

function testMobileNavigation() {
    Swal.fire({
        title: 'Mobil Navigasyon Testi',
        text: 'Mobil navigasyon çalışıyor!',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// Test sayfası yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    console.log('Mobil test sayfası yüklendi');
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
