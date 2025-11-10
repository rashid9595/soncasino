<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Turnuva Düzenle";

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
    WHERE ap.role_id = ? AND ap.menu_item = 'tournaments' AND ap.can_edit = 1
");
$stmt->execute([$_SESSION['role_id']]);
if (!$stmt->fetch() && $_SESSION['role_id'] != 1) {
    $_SESSION['error'] = 'Bu işlemi gerçekleştirme izniniz yok.';
    header("Location: tournaments.php");
    exit();
}

// Initialize variables
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

// Check for valid tournament ID
if ($id <= 0) {
    $_SESSION['error'] = 'Geçersiz turnuva ID\'si.';
    header("Location: tournaments.php");
    exit();
}

// Get tournament information
$stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
$stmt->execute([$id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    $_SESSION['error'] = 'Turnuva bulunamadı.';
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_tournament'])) {
        // Validate input
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $type = isset($_POST['type']) ? trim($_POST['type']) : '';
        $total_prize = isset($_POST['total_prize']) ? floatval($_POST['total_prize']) : 0;
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        $status = isset($_POST['status']) ? $_POST['status'] : 'upcoming';
        $prize_pool = isset($_POST['prize_pool']) ? floatval($_POST['prize_pool']) : 0;
        $banner_image = isset($_POST['banner_image']) ? trim($_POST['banner_image']) : '';
        $mobile_banner_image = isset($_POST['mobile_banner_image']) ? trim($_POST['mobile_banner_image']) : '';
        
        if (empty($title) || empty($type) || empty($start_date) || empty($end_date)) {
            $error = "Lütfen gerekli tüm alanları doldurun";
        } else {
            try {
                // Update tournament
                $stmt = $db->prepare("
                    UPDATE tournaments 
                    SET title = ?, type = ?, total_prize = ?, start_date = ?, end_date = ?, 
                        status = ?, prize_pool = ?, banner_image = ?, mobile_banner_image = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $type, $total_prize, $start_date, $end_date, 
                    $status, $prize_pool, $banner_image, $mobile_banner_image, $id
                ]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva güncellendi: $title (ID: $id)"]);
                
                $_SESSION['success'] = "Turnuva başarıyla güncellendi";
                header("Location: tournaments.php");
                exit();
            } catch (Exception $e) {
                $error = "Turnuva güncellenirken bir hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Format dates for datetime-local input
$startDate = date('Y-m-d\TH:i', strtotime($tournament['start_date']));
$endDate = date('Y-m-d\TH:i', strtotime($tournament['end_date']));

// Start output buffering
ob_start();
?>

<!-- Page Content -->
<div class="page-header bg-primary text-white">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Turnuva Düzenle</h3>
            <ul class="breadcrumb bg-transparent mb-0">
                <li class="breadcrumb-item"><a href="index.php" class="text-white">Gösterge Paneli</a></li>
                <li class="breadcrumb-item"><a href="tournaments.php" class="text-white">Turnuvalar</a></li>
                <li class="breadcrumb-item active text-white">Turnuva Düzenle</li>
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
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Turnuva Bilgileri</h5>
            </div>
            <div class="card-body">
                <form action="tournament_edit.php?id=<?php echo $id; ?>" method="POST">
                    <input type="hidden" name="edit_tournament" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4 border">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Temel Bilgiler</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Turnuva Başlığı <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($tournament['title']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Turnuva Türü <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="type" value="<?php echo htmlspecialchars($tournament['type']); ?>" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Toplam Ödül (₺) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control" name="total_prize" value="<?php echo $tournament['total_prize']; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Ödül Havuzu (₺) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control" name="prize_pool" value="<?php echo $tournament['prize_pool']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4 border">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Zaman ve Durum</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control" name="start_date" value="<?php echo $startDate; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Bitiş Tarihi <span class="text-danger">*</span></label>
                                            <input type="datetime-local" class="form-control" name="end_date" value="<?php echo $endDate; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Durum <span class="text-danger">*</span></label>
                                        <select class="form-select" name="status" required>
                                            <option value="upcoming" <?php echo $tournament['status'] === 'upcoming' ? 'selected' : ''; ?>>Yakında</option>
                                            <option value="ongoing" <?php echo $tournament['status'] === 'ongoing' ? 'selected' : ''; ?>>Devam Ediyor</option>
                                            <option value="completed" <?php echo $tournament['status'] === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4 border">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0">Görsel İçerikler</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Banner Resmi URL</label>
                                            <input type="text" class="form-control" name="banner_image" value="<?php echo htmlspecialchars($tournament['banner_image']); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Mobil Banner Resmi URL</label>
                                            <input type="text" class="form-control" name="mobile_banner_image" value="<?php echo htmlspecialchars($tournament['mobile_banner_image']); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="preview_images" <?php echo (!empty($tournament['banner_image']) || !empty($tournament['mobile_banner_image'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="preview_images">
                                                Görselleri Önizle
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3 image-previews" style="display: <?php echo (!empty($tournament['banner_image']) || !empty($tournament['mobile_banner_image'])) ? 'flex' : 'none'; ?>;">
                                        <div class="col-md-6 mb-3">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0">Masaüstü Banner Önizleme</h6>
                                                </div>
                                                <div class="card-body text-center">
                                                    <div class="banner-preview" style="height: 200px; display: flex; align-items: center; justify-content: center; background-color: #e9ecef; border-radius: 4px;">
                                                        <span class="text-muted banner-placeholder" style="display: <?php echo empty($tournament['banner_image']) ? 'block' : 'none'; ?>">Görsel URL'si girin</span>
                                                        <img src="<?php echo htmlspecialchars($tournament['banner_image']); ?>" alt="Banner Önizleme" class="img-fluid banner-image" style="max-height: 200px; display: <?php echo !empty($tournament['banner_image']) ? 'block' : 'none'; ?>;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0">Mobil Banner Önizleme</h6>
                                                </div>
                                                <div class="card-body text-center">
                                                    <div class="mobile-preview" style="height: 200px; display: flex; align-items: center; justify-content: center; background-color: #e9ecef; border-radius: 4px;">
                                                        <span class="text-muted mobile-placeholder" style="display: <?php echo empty($tournament['mobile_banner_image']) ? 'block' : 'none'; ?>">Görsel URL'si girin</span>
                                                        <img src="<?php echo htmlspecialchars($tournament['mobile_banner_image']); ?>" alt="Mobil Banner Önizleme" class="img-fluid mobile-image" style="max-height: 200px; display: <?php echo !empty($tournament['mobile_banner_image']) ? 'block' : 'none'; ?>;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 text-end">
                            <a href="tournaments.php" class="btn btn-secondary me-2">İptal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Değişiklikleri Kaydet
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

.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview functionality
    const previewCheck = document.getElementById('preview_images');
    const imagePreviews = document.querySelector('.image-previews');
    const bannerInput = document.querySelector('input[name="banner_image"]');
    const mobileInput = document.querySelector('input[name="mobile_banner_image"]');
    const bannerImage = document.querySelector('.banner-image');
    const mobileImage = document.querySelector('.mobile-image');
    const bannerPlaceholder = document.querySelector('.banner-placeholder');
    const mobilePlaceholder = document.querySelector('.mobile-placeholder');
    
    previewCheck.addEventListener('change', function() {
        imagePreviews.style.display = this.checked ? 'flex' : 'none';
        updatePreviews();
    });
    
    bannerInput.addEventListener('input', updatePreviews);
    mobileInput.addEventListener('input', updatePreviews);
    
    function updatePreviews() {
        if (bannerInput.value) {
            bannerImage.src = bannerInput.value;
            bannerImage.style.display = 'block';
            bannerPlaceholder.style.display = 'none';
            
            // Handle image load error
            bannerImage.onerror = function() {
                bannerImage.style.display = 'none';
                bannerPlaceholder.style.display = 'block';
                bannerPlaceholder.textContent = 'Geçersiz görsel URL\'si';
            };
        } else {
            bannerImage.style.display = 'none';
            bannerPlaceholder.style.display = 'block';
            bannerPlaceholder.textContent = 'Görsel URL\'si girin';
        }
        
        if (mobileInput.value) {
            mobileImage.src = mobileInput.value;
            mobileImage.style.display = 'block';
            mobilePlaceholder.style.display = 'none';
            
            // Handle image load error
            mobileImage.onerror = function() {
                mobileImage.style.display = 'none';
                mobilePlaceholder.style.display = 'block';
                mobilePlaceholder.textContent = 'Geçersiz görsel URL\'si';
            };
        } else {
            mobileImage.style.display = 'none';
            mobilePlaceholder.style.display = 'block';
            mobilePlaceholder.textContent = 'Görsel URL\'si girin';
        }
    }
    
    // Check images on page load for validation
    bannerImage.onerror = function() {
        bannerImage.style.display = 'none';
        bannerPlaceholder.style.display = 'block';
        bannerPlaceholder.textContent = 'Geçersiz görsel URL\'si';
    };
    
    mobileImage.onerror = function() {
        mobileImage.style.display = 'none';
        mobilePlaceholder.style.display = 'block';
        mobilePlaceholder.textContent = 'Geçersiz görsel URL\'si';
    };
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 