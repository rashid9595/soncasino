<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Set page title
$pageTitle = "Turnuva Yönetimi";

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
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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

// Redirect to edit page if action is edit
if ($action === 'edit' && $id > 0) {
    header("Location: tournament_edit.php?id=$id");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_tournament'])) {
        // Validate input
        $title = trim($_POST['title']);
        $type = trim($_POST['type']);
        $total_prize = floatval($_POST['total_prize']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = $_POST['status'];
        $prize_pool = floatval($_POST['prize_pool']);
        $banner_image = trim($_POST['banner_image']);
        $mobile_banner_image = trim($_POST['mobile_banner_image']);
        
        if (empty($title) || empty($type) || empty($start_date) || empty($end_date)) {
            $error = "Lütfen gerekli tüm alanları doldurun";
        } else {
            try {
                // Insert new tournament
                $stmt = $db->prepare("
                    INSERT INTO tournaments (title, type, total_prize, start_date, end_date, status, prize_pool, banner_image, mobile_banner_image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$title, $type, $total_prize, $start_date, $end_date, $status, $prize_pool, $banner_image, $mobile_banner_image])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'create', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Yeni turnuva oluşturuldu: $title"]);
                    
                    $success = "Turnuva başarıyla eklendi";
                } else {
                    $error = "Turnuva eklenirken bir hata oluştu";
                }
            } catch (Exception $e) {
                $error = "Turnuva eklenirken bir hata oluştu: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['edit_tournament'])) {
        // Validate input
        $title = trim($_POST['title']);
        $type = trim($_POST['type']);
        $total_prize = floatval($_POST['total_prize']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = $_POST['status'];
        $prize_pool = floatval($_POST['prize_pool']);
        $banner_image = trim($_POST['banner_image']);
        $mobile_banner_image = trim($_POST['mobile_banner_image']);
        
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
                if ($stmt->execute([$title, $type, $total_prize, $start_date, $end_date, $status, $prize_pool, $banner_image, $mobile_banner_image, $id])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'update', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Turnuva güncellendi: $title"]);
                    
                    $success = "Turnuva başarıyla güncellendi";
                } else {
                    $error = "Turnuva güncellenirken bir hata oluştu";
                }
            } catch (Exception $e) {
                $error = "Turnuva güncellenirken bir hata oluştu: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_tournament'])) {
        try {
            // Get title for logging
            $stmt = $db->prepare("SELECT title FROM tournaments WHERE id = ?");
            $stmt->execute([$id]);
            $title = $stmt->fetchColumn();
            
            // Delete tournament
            $stmt = $db->prepare("DELETE FROM tournaments WHERE id = ?");
            if ($stmt->execute([$id])) {
                // Also delete related records
                $stmt = $db->prepare("DELETE FROM tournament_details WHERE tournament_id = ?");
                $stmt->execute([$id]);
                $stmt = $db->prepare("DELETE FROM tournament_games WHERE tournament_id = ?");
                $stmt->execute([$id]);
                $stmt = $db->prepare("DELETE FROM tournament_leaderboard WHERE tournament_id = ?");
                $stmt->execute([$id]);
                $stmt = $db->prepare("DELETE FROM tournament_participants WHERE tournament_id = ?");
                $stmt->execute([$id]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'delete', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva silindi: $title"]);
                
                $success = "Turnuva ve ilgili tüm veriler başarıyla silindi";
            } else {
                $error = "Turnuva silinirken bir hata oluştu";
            }
        } catch (Exception $e) {
            $error = "Turnuva silinirken bir hata oluştu: " . $e->getMessage();
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

// Get all tournaments
$stmt = $db->prepare("SELECT * FROM tournaments ORDER BY id DESC");
$stmt->execute();
$tournaments = $stmt->fetchAll();

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

.tournaments-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.tournaments-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.tournaments-title {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.tournaments-body {
    padding: 1.5rem;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 2px solid var(--border-color);
    color: var(--text-primary);
    font-weight: 600;
    padding: 1rem 0.75rem;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.table tbody tr:hover {
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(6, 182, 212, 0.05));
}

.badge {
    padding: 0.5em 0.75em;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.375rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.badge.bg-success {
    background: linear-gradient(135deg, var(--success-color), #059669) !important;
}

.badge.bg-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)) !important;
}

.badge.bg-warning {
    background: linear-gradient(135deg, var(--warning-color), #d97706) !important;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
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

.btn-info {
    background: linear-gradient(135deg, var(--info-color), #0891b2);
    color: #ffffff;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning-color), #d97706);
    color: #ffffff;
}

.btn-secondary {
    background: linear-gradient(135deg, var(--secondary-color), #475569);
    color: #ffffff;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
    color: #ffffff;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
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

.modal-content {
    border-radius: var(--card-radius);
    border: none;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: #ffffff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: var(--card-radius) var(--card-radius) 0 0;
}

.modal-title {
    font-weight: 600;
}

.btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%);
}

.form-control, .form-select {
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    padding: 0.75rem;
    transition: all 0.2s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.form-label {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
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
}
</style>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="greeting">Turnuva Yönetimi</h1>
            <p class="dashboard-subtitle">Tüm turnuvaları yönetin ve takip edin</p>
        </div>
        <div>
            <a href="tournaments_add.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Yeni Turnuva Ekle
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

<!-- Tournaments Card -->
<div class="tournaments-card">
    <div class="tournaments-header">
        <h2 class="tournaments-title">
            <i class="fas fa-trophy"></i>
            Tüm Turnuvalar
        </h2>
    </div>
    <div class="tournaments-body">
        <?php if (count($tournaments) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover" id="tournamentsTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag me-2"></i>ID</th>
                        <th><i class="fas fa-trophy me-2"></i>Başlık</th>
                        <th><i class="fas fa-tag me-2"></i>Tür</th>
                        <th><i class="fas fa-coins me-2"></i>Ödül Havuzu</th>
                        <th><i class="fas fa-calendar me-2"></i>Başlangıç</th>
                        <th><i class="fas fa-calendar-check me-2"></i>Bitiş</th>
                        <th><i class="fas fa-info-circle me-2"></i>Durum</th>
                        <th class="text-center"><i class="fas fa-cogs me-2"></i>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments as $tournament): ?>
                    <tr>
                        <td><strong><?php echo $tournament['id']; ?></strong></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($tournament['title']); ?></td>
                        <td><?php echo htmlspecialchars($tournament['type']); ?></td>
                        <td class="text-success fw-bold">₺<?php echo number_format($tournament['prize_pool'], 2); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($tournament['start_date'])); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($tournament['end_date'])); ?></td>
                        <td>
                            <span class="badge <?php 
                                echo $tournament['status'] === 'ongoing' ? 'bg-success' : 
                                    ($tournament['status'] === 'completed' ? 'bg-primary' : 'bg-warning'); 
                            ?>">
                                <?php 
                                    echo $tournament['status'] === 'ongoing' ? 'Devam Ediyor' : 
                                        ($tournament['status'] === 'completed' ? 'Tamamlandı' : 'Yakında'); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="tournament_edit.php?id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="tournament_details.php?tournament_id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-info" title="Detaylar">
                                    <i class="fas fa-info-circle"></i>
                                </a>
                                <a href="tournament_games.php?tournament_id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-success" title="Oyunlar">
                                    <i class="fas fa-gamepad"></i>
                                </a>
                                <a href="tournament_leaderboard.php?tournament_id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-warning" title="Sıralama">
                                    <i class="fas fa-trophy"></i>
                                </a>
                                <a href="tournament_participants.php?tournament_id=<?php echo $tournament['id']; ?>" class="btn btn-sm btn-secondary" title="Katılımcılar">
                                    <i class="fas fa-users"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" title="Sil" onclick="confirmDeleteTournament(<?php echo $tournament['id']; ?>, '<?php echo htmlspecialchars($tournament['title']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-trophy fa-4x text-muted"></i>
            </div>
            <h4 class="text-muted">Henüz turnuva bulunmamaktadır</h4>
            <p class="text-muted">Yeni bir turnuva oluşturmak için "Yeni Turnuva Ekle" butonuna tıklayın.</p>
            <a href="tournaments_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> İlk Turnuvayı Oluştur
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#tournamentsTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columnDefs: [
            {
                targets: -1,
                orderable: false,
                searchable: false
            }
        ]
    });
});

function confirmDeleteTournament(id, title) {
    Swal.fire({
        title: 'Turnuva Sil',
        html: `
            <p>Bu turnuvayı silmek istediğinizden emin misiniz?</p>
            <p class="text-danger"><strong>${title}</strong></p>
            <p class="text-danger"><small>Bu işlem geri alınamaz ve tüm turnuva verileri silinecektir.</small></p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Evet, Sil',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'tournaments.php';
            
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_tournament';
            deleteInput.value = '1';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            
            form.appendChild(deleteInput);
            form.appendChild(idInput);
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