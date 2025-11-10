<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Turnuva Detayları";

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
    // Get user's role_id if not set in session
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

$stmt = $db->prepare("
    SELECT ap.* 
    FROM admin_permissions ap 
    WHERE ap.role_id = ? AND ap.menu_item = 'tournaments' AND ap.can_view = 1
");
$stmt->execute([$_SESSION['role_id']]);
if (!$stmt->fetch() && $_SESSION['role_id'] != 1) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

// Initialize variables
$tournament_id = isset($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : 0;
$success = '';
$error = '';

// Check for tournament_id
if ($tournament_id <= 0) {
    $_SESSION['error'] = 'Geçersiz turnuva ID\'si.';
    header("Location: tournaments.php");
    exit();
}

// Check for session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get tournament information
$stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    $_SESSION['error'] = 'Turnuva bulunamadı.';
    header("Location: tournaments.php");
    exit();
}

// Get tournament details
$stmt = $db->prepare("SELECT * FROM tournament_details WHERE tournament_id = ?");
$stmt->execute([$tournament_id]);
$details = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_details'])) {
        // Validate and get input
        $rules = isset($_POST['rules']) ? trim($_POST['rules']) : '';
        $start_date_text = isset($_POST['start_date_text']) ? trim($_POST['start_date_text']) : '';
        $end_date_text = isset($_POST['end_date_text']) ? trim($_POST['end_date_text']) : '';
        $duration_days = isset($_POST['duration_days']) ? (int)$_POST['duration_days'] : 0;
        $min_bet_amount = isset($_POST['min_bet_amount']) ? floatval($_POST['min_bet_amount']) : 0;
        $point_calculation = isset($_POST['point_calculation']) ? trim($_POST['point_calculation']) : '';
        $prize_distribution = isset($_POST['prize_distribution']) ? trim($_POST['prize_distribution']) : '';
        $prize_rules = isset($_POST['prize_rules']) ? trim($_POST['prize_rules']) : '';
        $participating_sites = isset($_POST['participating_sites']) ? trim($_POST['participating_sites']) : '';
        $provider_list = isset($_POST['provider_list']) ? trim($_POST['provider_list']) : '';
        $prize_positions = isset($_POST['prize_positions']) ? (int)$_POST['prize_positions'] : 0;
        
        if ($details) {
            // Update existing details
            $stmt = $db->prepare("
                UPDATE tournament_details 
                SET rules = ?, start_date_text = ?, end_date_text = ?, duration_days = ?, 
                    min_bet_amount = ?, point_calculation = ?, prize_distribution = ?, 
                    prize_rules = ?, participating_sites = ?, provider_list = ?, prize_positions = ?
                WHERE tournament_id = ?
            ");
            if ($stmt->execute([
                $rules, $start_date_text, $end_date_text, $duration_days, 
                $min_bet_amount, $point_calculation, $prize_distribution, 
                $prize_rules, $participating_sites, $provider_list, $prize_positions, 
                $tournament_id
            ])) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva detayları güncellendi: " . $tournament['title']]);
                
                $success = "Turnuva detayları başarıyla güncellendi";
            } else {
                $error = "Turnuva detayları güncellenirken bir hata oluştu";
            }
        } else {
            // Insert new details
            $stmt = $db->prepare("
                INSERT INTO tournament_details (tournament_id, rules, start_date_text, end_date_text, 
                    duration_days, min_bet_amount, point_calculation, prize_distribution, 
                    prize_rules, participating_sites, provider_list, prize_positions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([
                $tournament_id, $rules, $start_date_text, $end_date_text, 
                $duration_days, $min_bet_amount, $point_calculation, $prize_distribution, 
                $prize_rules, $participating_sites, $provider_list, $prize_positions
            ])) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'create', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva detayları oluşturuldu: " . $tournament['title']]);
                
                $success = "Turnuva detayları başarıyla oluşturuldu";
            } else {
                $error = "Turnuva detayları oluşturulurken bir hata oluştu";
            }
        }
        
        // Refresh details after update
        $stmt = $db->prepare("SELECT * FROM tournament_details WHERE tournament_id = ?");
        $stmt->execute([$tournament_id]);
        $details = $stmt->fetch();
    }
}

// Start output buffering
ob_start();
?>

<!-- Page Content -->
<div class="page-header bg-primary text-white">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Turnuva Detayları</h3>
            <ul class="breadcrumb bg-transparent mb-0">
                <li class="breadcrumb-item"><a href="index.php" class="text-white">Gösterge Paneli</a></li>
                <li class="breadcrumb-item"><a href="tournaments.php" class="text-white">Turnuvalar</a></li>
                <li class="breadcrumb-item active text-white">Turnuva Detayları</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="tournaments.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <?php echo htmlspecialchars($tournament['title']); ?>
                    <span class="badge bg-light text-primary ms-2">
                        <?php 
                            echo $tournament['status'] === 'ongoing' ? 'Devam Ediyor' : 
                                ($tournament['status'] === 'completed' ? 'Tamamlandı' : 'Yakında'); 
                        ?>
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <form action="tournament_details.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                    <input type="hidden" name="update_details" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4 border">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Temel Bilgiler</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Başlangıç Tarihi Metni</label>
                                            <input type="text" class="form-control" name="start_date_text" value="<?php echo htmlspecialchars($details['start_date_text'] ?? ''); ?>">
                                            <small class="text-muted">Format: 26.03.2025</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Bitiş Tarihi Metni</label>
                                            <input type="text" class="form-control" name="end_date_text" value="<?php echo htmlspecialchars($details['end_date_text'] ?? ''); ?>">
                                            <small class="text-muted">Format: 11.04.2025</small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Süre (Gün)</label>
                                            <input type="number" class="form-control" name="duration_days" value="<?php echo (int)($details['duration_days'] ?? 0); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Minimum Bahis Tutarı (₺)</label>
                                            <input type="number" step="0.01" class="form-control" name="min_bet_amount" value="<?php echo number_format(($details['min_bet_amount'] ?? 0), 2, '.', ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Ödül Pozisyonları</label>
                                        <input type="number" class="form-control" name="prize_positions" value="<?php echo (int)($details['prize_positions'] ?? 0); ?>">
                                        <small class="text-muted">Kaç kişi ödül alacak</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4 border">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Katılım Bilgileri</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Katılımcı Siteler</label>
                                        <textarea class="form-control" name="participating_sites" rows="2"><?php echo htmlspecialchars($details['participating_sites'] ?? ''); ?></textarea>
                                        <small class="text-muted">Virgülle ayırarak girin</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Sağlayıcı Listesi</label>
                                        <textarea class="form-control" name="provider_list" rows="2"><?php echo htmlspecialchars($details['provider_list'] ?? ''); ?></textarea>
                                        <small class="text-muted">Virgülle ayırarak girin</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Puan Hesaplama</label>
                                        <textarea class="form-control" name="point_calculation" rows="3"><?php echo htmlspecialchars($details['point_calculation'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4 border">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Ödül ve Kural Bilgileri</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Turnuva Kuralları</label>
                                        <textarea class="form-control" name="rules" rows="4"><?php echo htmlspecialchars($details['rules'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Ödül Dağıtımı</label>
                                        <textarea class="form-control" name="prize_distribution" rows="3"><?php echo htmlspecialchars($details['prize_distribution'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Ödül Kuralları</label>
                                        <textarea class="form-control" name="prize_rules" rows="3"><?php echo htmlspecialchars($details['prize_rules'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Detayları Kaydet
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.page-header {
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    position: relative;
    z-index: 1;
}

.breadcrumb-item a {
    text-decoration: none;
}

.breadcrumb-item a:hover {
    text-decoration: underline;
}

.card {
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
    overflow: hidden;
}

.card-header {
    padding: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.card.border {
    border: 1px solid rgba(0, 0, 0, 0.125) !important;
}

.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.form-control:focus, .form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease-in-out;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}
</style>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 