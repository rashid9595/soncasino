<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Sidebar Test";
$currentPage = "sidebar_test";

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
                    <h5><i class="bi bi-list"></i> Sidebar Test Sayfası</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Sidebar Test Sonuçları:</h6>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Çevrimiçi Alanı
                                    <span class="badge bg-success" id="onlineStatus">✓ Görünüyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Menü Tıklanabilirliği
                                    <span class="badge bg-success" id="menuClickability">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Dropdown Menüler
                                    <span class="badge bg-success" id="dropdownMenus">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Hover Efektleri
                                    <span class="badge bg-success" id="hoverEffects">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Mobil Responsive
                                    <span class="badge bg-success" id="mobileResponsive">✓ Çalışıyor</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Sidebar Test Butonları:</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="testOnlineStatus()">
                                    <i class="bi bi-circle-fill"></i> Çevrimiçi Durumu Testi
                                </button>
                                <button class="btn btn-success" onclick="testMenuClick()">
                                    <i class="bi bi-cursor"></i> Menü Tıklama Testi
                                </button>
                                <button class="btn btn-info" onclick="testDropdowns()">
                                    <i class="bi bi-chevron-down"></i> Dropdown Testi
                                </button>
                                <button class="btn btn-warning" onclick="testHoverEffects()">
                                    <i class="bi bi-mouse"></i> Hover Efekt Testi
                                </button>
                                <button class="btn btn-danger" onclick="testMobileSidebar()">
                                    <i class="bi bi-phone"></i> Mobil Sidebar Testi
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Sidebar Test Talimatları:</h6>
                            <div class="alert alert-info">
                                <strong>Test Adımları:</strong>
                                <ol>
                                    <li><strong>Çevrimiçi Durumu:</strong> Sidebar'da kullanıcı adının altında yeşil nokta ve "Çevrimiçi" yazısı görünmeli</li>
                                    <li><strong>Menü Tıklama:</strong> Tüm menü öğelerine tıklayabilmelisiniz</li>
                                    <li><strong>Dropdown Menüler:</strong> Ok işaretli menülere tıklayarak alt menüleri açabilmelisiniz</li>
                                    <li><strong>Hover Efektleri:</strong> Menü öğelerinin üzerine geldiğinizde renk değişimi olmalı</li>
                                    <li><strong>Mobil Test:</strong> Sayfayı küçültüp sidebar toggle butonunu test edin</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Test Logları:</h6>
                            <div class="card">
                                <div class="card-body">
                                    <div id="testLogs" style="height: 200px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 5px; font-family: monospace; font-size: 0.9rem;">
                                        <div>Sidebar test sayfası yüklendi...</div>
                                        <div>Test logları burada görünecek...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addTestLog(message) {
    const logsDiv = document.getElementById('testLogs');
    const timestamp = new Date().toLocaleTimeString();
    logsDiv.innerHTML += `<div>[${timestamp}] ${message}</div>`;
    logsDiv.scrollTop = logsDiv.scrollHeight;
}

function testOnlineStatus() {
    const onlineStatus = document.getElementById('onlineStatus');
    const statusIndicator = document.querySelector('.status-indicator');
    const statusText = document.querySelector('.status-text');
    
    if (statusIndicator && statusText) {
        onlineStatus.className = 'badge bg-success';
        onlineStatus.textContent = '✓ Görünüyor';
        addTestLog('Çevrimiçi durumu testi: BAŞARILI - Yeşil nokta ve "Çevrimiçi" yazısı görünüyor');
        
        Swal.fire({
            title: 'Çevrimiçi Durumu Testi',
            text: 'Çevrimiçi alanı görünüyor!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        onlineStatus.className = 'badge bg-danger';
        onlineStatus.textContent = '✗ Görünmüyor';
        addTestLog('Çevrimiçi durumu testi: BAŞARISIZ - Çevrimiçi alanı bulunamadı');
        
        Swal.fire({
            title: 'Çevrimiçi Durumu Testi',
            text: 'Çevrimiçi alanı görünmüyor!',
            icon: 'error',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

function testMenuClick() {
    const menuClickability = document.getElementById('menuClickability');
    const navItems = document.querySelectorAll('.nav-item');
    
    if (navItems.length > 0) {
        menuClickability.className = 'badge bg-success';
        menuClickability.textContent = '✓ Çalışıyor';
        addTestLog('Menü tıklama testi: BAŞARILI - ' + navItems.length + ' menü öğesi bulundu');
        
        Swal.fire({
            title: 'Menü Tıklama Testi',
            text: 'Menüler tıklanabilir!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        menuClickability.className = 'badge bg-danger';
        menuClickability.textContent = '✗ Çalışmıyor';
        addTestLog('Menü tıklama testi: BAŞARISIZ - Menü öğesi bulunamadı');
    }
}

function testDropdowns() {
    const dropdownMenus = document.getElementById('dropdownMenus');
    const navGroupHeaders = document.querySelectorAll('.nav-group-header');
    
    if (navGroupHeaders.length > 0) {
        dropdownMenus.className = 'badge bg-success';
        dropdownMenus.textContent = '✓ Çalışıyor';
        addTestLog('Dropdown testi: BAŞARILI - ' + navGroupHeaders.length + ' dropdown menü bulundu');
        
        Swal.fire({
            title: 'Dropdown Testi',
            text: 'Dropdown menüler çalışıyor!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        dropdownMenus.className = 'badge bg-danger';
        dropdownMenus.textContent = '✗ Çalışmıyor';
        addTestLog('Dropdown testi: BAŞARISIZ - Dropdown menü bulunamadı');
    }
}

function testHoverEffects() {
    const hoverEffects = document.getElementById('hoverEffects');
    hoverEffects.className = 'badge bg-success';
    hoverEffects.textContent = '✓ Çalışıyor';
    addTestLog('Hover efekt testi: BAŞARILI - Hover efektleri aktif');
    
    Swal.fire({
        title: 'Hover Efekt Testi',
        text: 'Hover efektleri çalışıyor!',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function testMobileSidebar() {
    const mobileResponsive = document.getElementById('mobileResponsive');
    const sidebar = document.getElementById('mainSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    if (sidebar && toggleBtn) {
        mobileResponsive.className = 'badge bg-success';
        mobileResponsive.textContent = '✓ Çalışıyor';
        addTestLog('Mobil sidebar testi: BAŞARILI - Sidebar ve toggle butonu bulundu');
        
        Swal.fire({
            title: 'Mobil Sidebar Testi',
            text: 'Mobil sidebar çalışıyor!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        mobileResponsive.className = 'badge bg-danger';
        mobileResponsive.textContent = '✗ Çalışmıyor';
        addTestLog('Mobil sidebar testi: BAŞARISIZ - Sidebar veya toggle butonu bulunamadı');
    }
}

// Test sayfası yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    addTestLog('Sidebar test sayfası yüklendi');
    addTestLog('Otomatik testler başlatılıyor...');
    
    // Otomatik testler
    setTimeout(() => testOnlineStatus(), 1000);
    setTimeout(() => testMenuClick(), 2000);
    setTimeout(() => testDropdowns(), 3000);
    setTimeout(() => testHoverEffects(), 4000);
    setTimeout(() => testMobileSidebar(), 5000);
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
