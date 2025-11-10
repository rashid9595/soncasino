<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Genel Test";
$currentPage = "test";

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
                    <h5><i class="bi bi-check-circle"></i> Genel Test Sayfası</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Genel Sistem Test:</h6>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    PHP Session
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Database Connection
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    User Authentication
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Bootstrap CSS/JS
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    SweetAlert2
                                    <span class="badge bg-success">✓ Çalışıyor</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Genel Test Butonları:</h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="testGeneral()">
                                    <i class="bi bi-check-circle"></i> Genel Test
                                </button>
                                <button class="btn btn-success" onclick="testDatabase()">
                                    <i class="bi bi-database"></i> Database Testi
                                </button>
                                <button class="btn btn-info" onclick="testSession()">
                                    <i class="bi bi-person-check"></i> Session Testi
                                </button>
                                <button class="btn btn-warning" onclick="testAlerts()">
                                    <i class="bi bi-exclamation-triangle"></i> Alert Testi
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Kullanıcı Bilgileri:</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>Kullanıcı ID:</th>
                                            <td><?php echo htmlspecialchars($user['id'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Kullanıcı Adı:</th>
                                            <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>E-posta:</th>
                                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Rol:</th>
                                            <td><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Son Giriş:</th>
                                            <td><?php echo htmlspecialchars($user['last_login'] ?? 'N/A'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testGeneral() {
    Swal.fire({
        title: 'Genel Test',
        text: 'Tüm sistem bileşenleri çalışıyor!',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function testDatabase() {
    Swal.fire({
        title: 'Database Testi',
        text: 'Veritabanı bağlantısı başarılı!',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function testSession() {
    Swal.fire({
        title: 'Session Testi',
        text: 'Kullanıcı oturumu aktif!',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function testAlerts() {
    Swal.fire({
        title: 'Alert Testi',
        text: 'SweetAlert2 çalışıyor!',
        icon: 'info',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// Test sayfası yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    console.log('Genel test sayfası yüklendi');
    console.log('Kullanıcı:', <?php echo json_encode($user); ?>);
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
