<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/database.php';

// Set current page for sidebar highlighting
$currentPage = 'password_reset_logs';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: password_reset_logs.php");
    exit();
}

$id = intval($_GET['id']);

// Get password reset record
try {
    $stmt = $db->prepare("
        SELECT p.*, u.username, u.email 
        FROM password_resets p
        JOIN kullanicilar u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        header("Location: password_reset_logs.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Start output buffering
ob_start();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Şifre Sıfırlama Detayları</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="index.php">Ana Sayfa</a></li>
                    <li class="breadcrumb-item"><a href="password_reset_logs.php">Şifre Değiştirme Logları</a></li>
                    <li class="breadcrumb-item active">Detay #<?php echo $id; ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Şifre Sıfırlama Bilgileri</h3>
                        <div class="card-tools">
                            <a href="password_reset_logs.php" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-left"></i> Geri Dön
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 200px;">ID</th>
                                        <td><?php echo htmlspecialchars($reset['id']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Kullanıcı ID</th>
                                        <td>
                                            <a href="site_users.php?action=view&id=<?php echo $reset['user_id']; ?>">
                                                <?php echo htmlspecialchars($reset['user_id']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Kullanıcı Adı</th>
                                        <td>
                                            <a href="site_users.php?action=view&id=<?php echo $reset['user_id']; ?>">
                                                <?php echo htmlspecialchars($reset['username']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><?php echo htmlspecialchars($reset['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Token</th>
                                        <td>
                                            <div class="text-break">
                                                <?php echo htmlspecialchars($reset['token']); ?>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 200px;">Son Geçerlilik Tarihi</th>
                                        <td><?php echo htmlspecialchars($reset['expires_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Kullanıldı</th>
                                        <td>
                                            <?php if ($reset['used'] == 1): ?>
                                                <span class="badge bg-success">Evet</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Hayır</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Oluşturulma Tarihi</th>
                                        <td><?php echo htmlspecialchars($reset['created_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Durum</th>
                                        <td>
                                            <?php 
                                            $expiryDate = new DateTime($reset['expires_at']);
                                            $now = new DateTime();
                                            
                                            if ($reset['used'] == 1) {
                                                echo '<span class="badge bg-success">Kullanıldı</span>';
                                            } elseif ($expiryDate < $now) {
                                                echo '<span class="badge bg-warning">Süresi Doldu</span>';
                                            } else {
                                                echo '<span class="badge bg-primary">Aktif</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 