<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Set page title
$pageTitle = "Yeni Turnuva Ekle";

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

// Simplified permission check for super admin
if ($_SESSION['role_id'] != 1) {
    $_SESSION['error'] = 'Bu işlemi gerçekleştirme izniniz yok.';
    header("Location: tournaments.php");
    exit();
}

// Initialize variables
$success = '';
$error = '';

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
    if (isset($_POST['add_tournament'])) {
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
                $db->beginTransaction();
                
                // Insert new tournament
                $stmt = $db->prepare("
                    INSERT INTO tournaments (title, type, total_prize, start_date, end_date, status, prize_pool, banner_image, mobile_banner_image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $type, $total_prize, $start_date, $end_date, $status, $prize_pool, $banner_image, $mobile_banner_image]);
                
                // Get the new tournament ID
                $tournament_id = $db->lastInsertId();
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'create', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Yeni turnuva oluşturuldu: $title (ID: $tournament_id)"]);
                
                $db->commit();
                
                $_SESSION['success'] = "Turnuva başarıyla eklendi";
                header("Location: tournament_details.php?tournament_id=" . $tournament_id);
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Turnuva eklenirken bir hata oluştu: " . $e->getMessage();
            }
        }
    }
}

// Calculate statistics
try {
    // Total tournaments
    $stmt = $db->prepare("SELECT COUNT(*) FROM tournaments");
    $stmt->execute();
    $totalTournaments = $stmt->fetchColumn();
    
    // Active tournaments
    $stmt = $db->prepare("SELECT COUNT(*) FROM tournaments WHERE status = 'ongoing'");
    $stmt->execute();
    $activeTournaments = $stmt->fetchColumn();
    
    // Total prize pool
    $stmt = $db->prepare("SELECT SUM(prize_pool) FROM tournaments");
    $stmt->execute();
    $totalPrizePool = $stmt->fetchColumn() ?: 0;
    
    // Today's tournaments
    $stmt = $db->prepare("SELECT COUNT(*) FROM tournaments WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayTournaments = $stmt->fetchColumn();
    
    // Average prize pool
    $stmt = $db->prepare("SELECT AVG(prize_pool) FROM tournaments");
    $stmt->execute();
    $avgPrizePool = $stmt->fetchColumn() ?: 0;
    
    // Completed tournaments
    $stmt = $db->prepare("SELECT COUNT(*) FROM tournaments WHERE status = 'completed'");
    $stmt->execute();
    $completedTournaments = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $totalTournaments = $activeTournaments = $totalPrizePool = $todayTournaments = $avgPrizePool = $completedTournaments = 0;
}

// Start output buffering
ob_start();
?>

<style>
:root {
    --primary-color: #0ea5e9;
    --primary-dark: #0284c7;
    --secondary-color: #64748b;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #06b6d4;
    --light-bg: #f8fafc;
    --dark-bg: #0f172a;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --border-radius: 0.5rem;
    --card-radius: 0.75rem;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.dashboard-header {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.8));
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: var(--shadow-lg);
}

.greeting {
    color: #ffffff;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.dashboard-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
    font-weight: 400;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: var(--card-radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--info-color));
}

.stat-card:nth-child(1)::before { background: linear-gradient(90deg, #3b82f6, #1d4ed8); }
.stat-card:nth-child(2)::before { background: linear-gradient(90deg, #10b981, #059669); }
.stat-card:nth-child(3)::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
.stat-card:nth-child(4)::before { background: linear-gradient(90deg, #ef4444, #dc2626); }
.stat-card:nth-child(5)::before { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
.stat-card:nth-child(6)::before { background: linear-gradient(90deg, #06b6d4, #0891b2); }

.stat-card h3 {
    color: #ffffff;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-card p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    font-weight: 500;
    margin: 0;
}

.stat-card i {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 2rem;
    opacity: 0.3;
    color: #ffffff;
}

.tournament-form-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.tournament-form-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.tournament-form-title {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.tournament-form-body {
    padding: 2rem;
}

.form-section {
    background: #ffffff;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.form-section-title {
    color: var(--text-primary);
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary-color);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-label {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: block;
}

.form-control, .form-select {
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    padding: 0.75rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    outline: none;
}

.form-text {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-size: 0.875rem;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: #ffffff;
}

.btn-success {
    background: linear-gradient(135deg, var(--success-color), #059669);
    color: #ffffff;
}

.btn-secondary {
    background: linear-gradient(135deg, var(--secondary-color), #475569);
    color: #ffffff;
}

.alert {
    border-radius: var(--border-radius);
    border: none;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
    color: #dc2626;
    border-left: 4px solid var(--danger-color);
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
    color: #059669;
    border-left: 4px solid var(--success-color);
}

.preview-section {
    background: #f8fafc;
    border-radius: var(--border-radius);
    padding: 1rem;
    margin-top: 1rem;
    border: 1px solid var(--border-color);
}

.preview-image {
    max-width: 100%;
    height: auto;
    border-radius: 0.25rem;
    box-shadow: var(--shadow-sm);
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

@media (max-width: 768px) {
    .stat-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card h3 {
        font-size: 1.5rem;
    }
    
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .greeting {
        font-size: 1.5rem;
    }
    
    .tournament-form-body {
        padding: 1rem;
    }
    
    .form-section {
        padding: 1rem;
    }
}
</style>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="greeting">Yeni Turnuva Ekle</h1>
            <p class="dashboard-subtitle">Yeni bir turnuva oluşturun ve yönetin</p>
        </div>
        <div>
            <a href="tournaments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <i class="fas fa-trophy"></i>
        <h3><?php echo number_format($totalTournaments); ?></h3>
        <p>Toplam Turnuva</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-play-circle"></i>
        <h3><?php echo number_format($activeTournaments); ?></h3>
        <p>Aktif Turnuva</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-coins"></i>
        <h3>₺<?php echo number_format($totalPrizePool, 2); ?></h3>
        <p>Toplam Ödül Havuzu</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-calendar-day"></i>
        <h3><?php echo number_format($todayTournaments); ?></h3>
        <p>Bugünkü Turnuva</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-check-circle"></i>
        <h3><?php echo number_format($completedTournaments); ?></h3>
        <p>Tamamlanan Turnuva</p>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Tournament Form Card -->
<div class="tournament-form-card">
    <div class="tournament-form-header">
        <h2 class="tournament-form-title">
            <i class="fas fa-plus-circle"></i>
            Turnuva Bilgileri
        </h2>
    </div>
    <div class="tournament-form-body">
        <form action="tournaments_add.php" method="POST" id="tournamentForm">
            <input type="hidden" name="add_tournament" value="1">
            
            <!-- Basic Information Section -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-info-circle"></i>
                    Temel Bilgiler
                </h3>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Turnuva Başlığı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required>
                        <div class="form-text">Örn: HAFTALIK ₺1.000.000 SLOT CASINO TURNUVASI</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Turnuva Türü <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="type" required>
                        <div class="form-text">Örn: Özel Network Turnuvası</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Toplam Ödül (₺) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="total_prize" required>
                    </div>
                </div>
            </div>
            
            <!-- Prize and Status Section -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-coins"></i>
                    Ödül ve Durum
                </h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ödül Havuzu (₺) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="prize_pool" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Durum <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" required>
                            <option value="upcoming">Yakında</option>
                            <option value="ongoing">Devam Ediyor</option>
                            <option value="completed">Tamamlandı</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Date and Time Section -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-calendar-alt"></i>
                    Zaman ve Tarih
                </h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="start_date" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Bitiş Tarihi <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="end_date" required>
                    </div>
                </div>
            </div>
            
            <!-- Visual Content Section -->
            <div class="form-section">
                <h3 class="form-section-title">
                    <i class="fas fa-images"></i>
                    Görsel İçerikler
                </h3>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Banner Resmi URL</label>
                        <input type="text" class="form-control" name="banner_image" id="bannerImage">
                        <div class="form-text">Masaüstü banner görsel URL'i</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mobil Banner Resmi URL</label>
                        <input type="text" class="form-control" name="mobile_banner_image" id="mobileBannerImage">
                        <div class="form-text">Mobil banner görsel URL'i</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="previewImages">
                        <label class="form-check-label" for="previewImages">
                            Görselleri Önizle
                        </label>
                    </div>
                </div>
                
                <div class="row mt-3" id="imagePreviews" style="display: none;">
                    <div class="col-md-6 mb-3">
                        <div class="preview-section">
                            <h6 class="mb-2">Masaüstü Banner Önizleme</h6>
                            <div class="banner-preview" style="height: 200px; display: flex; align-items: center; justify-content: center; background-color: #e9ecef; border-radius: 4px;">
                                <span class="text-muted" id="bannerPlaceholder">Görsel URL'si girin</span>
                                <img src="" alt="Banner Önizleme" class="preview-image" id="bannerPreview" style="max-height: 200px; display: none;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="preview-section">
                            <h6 class="mb-2">Mobil Banner Önizleme</h6>
                            <div class="mobile-preview" style="height: 200px; display: flex; align-items: center; justify-content: center; background-color: #e9ecef; border-radius: 4px;">
                                <span class="text-muted" id="mobilePlaceholder">Görsel URL'si girin</span>
                                <img src="" alt="Mobil Banner Önizleme" class="preview-image" id="mobilePreview" style="max-height: 200px; display: none;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='tournaments.php'">
                    <i class="fas fa-times"></i> İptal
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Turnuva Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview functionality
    const previewCheck = document.getElementById('previewImages');
    const imagePreviews = document.getElementById('imagePreviews');
    const bannerInput = document.getElementById('bannerImage');
    const mobileInput = document.getElementById('mobileBannerImage');
    const bannerPreview = document.getElementById('bannerPreview');
    const mobilePreview = document.getElementById('mobilePreview');
    const bannerPlaceholder = document.getElementById('bannerPlaceholder');
    const mobilePlaceholder = document.getElementById('mobilePlaceholder');
    
    previewCheck.addEventListener('change', function() {
        imagePreviews.style.display = this.checked ? 'block' : 'none';
        updatePreviews();
    });
    
    bannerInput.addEventListener('input', updatePreviews);
    mobileInput.addEventListener('input', updatePreviews);
    
    function updatePreviews() {
        if (bannerInput.value) {
            bannerPreview.src = bannerInput.value;
            bannerPreview.style.display = 'block';
            bannerPlaceholder.style.display = 'none';
            
            // Handle image load error
            bannerPreview.onerror = function() {
                bannerPreview.style.display = 'none';
                bannerPlaceholder.style.display = 'block';
                bannerPlaceholder.textContent = 'Geçersiz görsel URL\'si';
            };
        } else {
            bannerPreview.style.display = 'none';
            bannerPlaceholder.style.display = 'block';
            bannerPlaceholder.textContent = 'Görsel URL\'si girin';
        }
        
        if (mobileInput.value) {
            mobilePreview.src = mobileInput.value;
            mobilePreview.style.display = 'block';
            mobilePlaceholder.style.display = 'none';
            
            // Handle image load error
            mobilePreview.onerror = function() {
                mobilePreview.style.display = 'none';
                mobilePlaceholder.style.display = 'block';
                mobilePlaceholder.textContent = 'Geçersiz görsel URL\'si';
            };
        } else {
            mobilePreview.style.display = 'none';
            mobilePlaceholder.style.display = 'block';
            mobilePlaceholder.textContent = 'Görsel URL\'si girin';
        }
    }
    
    // Form validation
    const form = document.getElementById('tournamentForm');
    form.addEventListener('submit', function(e) {
        const title = document.querySelector('input[name="title"]').value.trim();
        const type = document.querySelector('input[name="type"]').value.trim();
        const startDate = document.querySelector('input[name="start_date"]').value;
        const endDate = document.querySelector('input[name="end_date"]').value;
        
        if (!title || !type || !startDate || !endDate) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Lütfen gerekli tüm alanları doldurun.',
                confirmButtonColor: '#ef4444'
            });
            return false;
        }
        
        if (new Date(startDate) >= new Date(endDate)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Bitiş tarihi başlangıç tarihinden sonra olmalıdır.',
                confirmButtonColor: '#ef4444'
            });
            return false;
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 