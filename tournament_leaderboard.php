<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Turnuva Sıralaması";

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

// Check if manually_added column exists in tournament_leaderboard, if not add it
try {
    $checkColumnStmt = $db->prepare("SHOW COLUMNS FROM tournament_leaderboard LIKE 'manually_added'");
    $checkColumnStmt->execute();
    if ($checkColumnStmt->rowCount() == 0) {
        $addColumnStmt = $db->prepare("ALTER TABLE tournament_leaderboard ADD COLUMN manually_added TINYINT(1) NOT NULL DEFAULT 0");
        $addColumnStmt->execute();
    }
} catch (Exception $e) {
    // Log error or handle silently
}

// Get tournament leaderboard
$stmt = $db->prepare("SELECT * FROM tournament_leaderboard WHERE tournament_id = ? ORDER BY rank, points DESC");
$stmt->execute([$tournament_id]);
$leaderboard = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_entry'])) {
        // Validate and get input
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $points = isset($_POST['points']) ? floatval($_POST['points']) : 0;
        $rank = isset($_POST['rank']) ? (int)$_POST['rank'] : 0;
        $prize = isset($_POST['prize']) ? floatval($_POST['prize']) : 0;
        
        if (empty($username)) {
            $error = "Kullanıcı adı gereklidir";
        } else {
            // Check if user already exists in leaderboard
            $stmt = $db->prepare("SELECT COUNT(*) FROM tournament_leaderboard WHERE tournament_id = ? AND username = ?");
            $stmt->execute([$tournament_id, $username]);
            $userExists = ($stmt->fetchColumn() > 0);
            
            if ($userExists) {
                $error = "Bu kullanıcı zaten sıralamada mevcut";
            } else {
                // Insert new entry and mark as manually added
                $stmt = $db->prepare("
                    INSERT INTO tournament_leaderboard (tournament_id, username, points, rank, prize, manually_added) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                if ($stmt->execute([$tournament_id, $username, $points, $rank, $prize])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'create', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Turnuva sıralamasına kullanıcı eklendi: $username (Turnuva ID: $tournament_id)"]);
                    
                    $success = "Sıralama kaydı başarıyla eklendi";
                } else {
                    $error = "Sıralama kaydı eklenirken bir hata oluştu";
                }
            }
        }
    } elseif (isset($_POST['update_entry'])) {
        // Validate and get input
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $points = isset($_POST['points']) ? floatval($_POST['points']) : 0;
        $rank = isset($_POST['rank']) ? (int)$_POST['rank'] : 0;
        $prize = isset($_POST['prize']) ? floatval($_POST['prize']) : 0;
        
        if (empty($id) || empty($username)) {
            $error = "Geçersiz kayıt veya kullanıcı adı";
        } else {
            // Check if entry is manually added
            $stmt = $db->prepare("SELECT manually_added FROM tournament_leaderboard WHERE id = ? AND tournament_id = ?");
            $stmt->execute([$id, $tournament_id]);
            $isManuallyAdded = (int)$stmt->fetchColumn();
            
            if (!$isManuallyAdded) {
                $error = "Bu kayıt manuel olarak eklenmediği için düzenlenemez";
            } else {
                // Update entry
                $stmt = $db->prepare("
                    UPDATE tournament_leaderboard 
                    SET username = ?, points = ?, rank = ?, prize = ? 
                    WHERE id = ? AND tournament_id = ? AND manually_added = 1
                ");
                if ($stmt->execute([$username, $points, $rank, $prize, $id, $tournament_id])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'update', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Turnuva sıralaması güncellendi: $username (Turnuva ID: $tournament_id)"]);
                    
                    $success = "Sıralama kaydı başarıyla güncellendi";
                } else {
                    $error = "Sıralama kaydı güncellenirken bir hata oluştu";
                }
            }
        }
    } elseif (isset($_POST['delete_entry'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (empty($id)) {
            $error = "Geçersiz sıralama kaydı";
        } else {
            // Check if entry is manually added
            $stmt = $db->prepare("SELECT manually_added, username FROM tournament_leaderboard WHERE id = ? AND tournament_id = ?");
            $stmt->execute([$id, $tournament_id]);
            $entry = $stmt->fetch();
            
            if (!$entry || !$entry['manually_added']) {
                $error = "Bu kayıt manuel olarak eklenmediği için silinemez";
            } else {
                // Delete entry
                $stmt = $db->prepare("DELETE FROM tournament_leaderboard WHERE id = ? AND tournament_id = ? AND manually_added = 1");
                if ($stmt->execute([$id, $tournament_id])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'delete', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Turnuva sıralamasından kullanıcı silindi: {$entry['username']} (Turnuva ID: $tournament_id)"]);
                    
                    $success = "Sıralama kaydı başarıyla silindi";
                } else {
                    $error = "Sıralama kaydı silinirken bir hata oluştu";
                }
            }
        }
    } elseif (isset($_POST['update_ranks'])) {
        // Update ranks based on points
        $stmt = $db->prepare("
            UPDATE tournament_leaderboard tl1
            JOIN (
                SELECT id, @rank := @rank + 1 as new_rank
                FROM tournament_leaderboard, (SELECT @rank := 0) r
                WHERE tournament_id = ?
                ORDER BY points DESC
            ) tl2 ON tl1.id = tl2.id
            SET tl1.rank = tl2.new_rank
            WHERE tl1.tournament_id = ?
        ");
        
        if ($stmt->execute([$tournament_id, $tournament_id])) {
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Turnuva sıralaması otomatik olarak güncellendi (Turnuva ID: $tournament_id)"]);
            
            $success = "Sıralama otomatik olarak güncellendi";
        } else {
            $error = "Sıralama güncellenirken bir hata oluştu";
        }
    }
    
    // Refresh leaderboard after update
    $stmt = $db->prepare("SELECT * FROM tournament_leaderboard WHERE tournament_id = ? ORDER BY rank, points DESC");
    $stmt->execute([$tournament_id]);
    $leaderboard = $stmt->fetchAll();
}

// Start output buffering
ob_start();
?>

<!-- Page Content -->
<div class="page-header bg-primary text-white">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Turnuva Sıralaması</h3>
            <ul class="breadcrumb bg-transparent mb-0">
                <li class="breadcrumb-item"><a href="index.php" class="text-white">Gösterge Paneli</a></li>
                <li class="breadcrumb-item"><a href="tournaments.php" class="text-white">Turnuvalar</a></li>
                <li class="breadcrumb-item active text-white">Turnuva Sıralaması</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="tournaments.php" class="btn btn-light me-2">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
            <a href="#" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#update_ranks_modal">
                <i class="fas fa-sort-numeric-down"></i> Otomatik Sırala
            </a>
            <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#add_entry_modal">
                <i class="fas fa-plus"></i> Kullanıcı Ekle
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
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Bilgi:</strong> Sadece manuel olarak eklenen kayıtlar düzenlenebilir veya silinebilir.
                </div>
                
                <?php if (count($leaderboard) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>Sıra</th>
                                <th>Kullanıcı Adı</th>
                                <th>Puanlar</th>
                                <th>Ödül (₺)</th>
                                <th class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $entry): ?>
                            <tr <?php echo isset($entry['manually_added']) && $entry['manually_added'] ? 'class="table-info"' : ''; ?>>
                                <td>
                                    <?php if ($entry['rank'] <= 3): ?>
                                    <div class="rank-badge rank-<?php echo $entry['rank']; ?>">
                                        <?php echo $entry['rank']; ?>
                                    </div>
                                    <?php else: ?>
                                    <?php echo $entry['rank']; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold">
                                    <?php echo htmlspecialchars($entry['username']); ?>
                                    <?php if (isset($entry['manually_added']) && $entry['manually_added']): ?>
                                    <span class="badge bg-secondary ms-1" title="Manuel olarak eklenmiş">M</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-primary fw-bold"><?php echo number_format($entry['points'], 2); ?></td>
                                <td class="fw-bold">₺<?php echo number_format($entry['prize'], 2); ?></td>
                                <td>
                                    <div class="action-buttons d-flex justify-content-center gap-1">
                                        <?php if (isset($entry['manually_added']) && $entry['manually_added']): ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#edit_entry_<?php echo $entry['id']; ?>" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#delete_entry_<?php echo $entry['id']; ?>" title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled title="Manuel eklenmemiş kayıt düzenlenemez">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <?php if (isset($entry['manually_added']) && $entry['manually_added']): ?>
                            <!-- Edit Entry Modal -->
                            <div class="modal custom-modal fade" id="edit_entry_<?php echo $entry['id']; ?>" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">Sıralama Kaydını Düzenle</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="tournament_leaderboard.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                                                <input type="hidden" name="update_entry" value="1">
                                                <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Kullanıcı Adı</label>
                                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($entry['username']); ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Puanlar</label>
                                                    <input type="number" step="0.01" class="form-control" name="points" value="<?php echo $entry['points']; ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Sıra</label>
                                                    <input type="number" class="form-control" name="rank" value="<?php echo $entry['rank']; ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Ödül (₺)</label>
                                                    <input type="number" step="0.01" class="form-control" name="prize" value="<?php echo $entry['prize']; ?>">
                                                </div>
                                                
                                                <div class="submit-section">
                                                    <button type="submit" class="btn btn-primary submit-btn w-100">Güncelle</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /Edit Entry Modal -->
                            
                            <!-- Delete Entry Modal -->
                            <div class="modal custom-modal fade" id="delete_entry_<?php echo $entry['id']; ?>" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title">Sıralama Kaydını Sil</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-center">Bu kullanıcıyı sıralamadan kaldırmak istediğinizden emin misiniz?</p>
                                            <form action="tournament_leaderboard.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                                                <input type="hidden" name="delete_entry" value="1">
                                                <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                                
                                                <div class="submit-section">
                                                    <button type="submit" class="btn btn-danger submit-btn w-100">Evet, Sil</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /Delete Entry Modal -->
                            <?php endif; ?>
                            
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="avatar">
                        <div class="avatar-title bg-light text-primary rounded-circle">
                            <i class="fas fa-trophy fa-3x"></i>
                        </div>
                    </div>
                    <h4 class="mt-4">Henüz sıralama kaydı bulunmamaktadır</h4>
                    <p class="text-muted">Sıralama eklemek için "Kullanıcı Ekle" butonuna tıklayın.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Entry Modal -->
<div class="modal custom-modal fade" id="add_entry_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Sıralamaya Kullanıcı Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-info">
                    <i class="fas fa-info-circle me-2"></i> Manuel olarak eklenen kayıtlar daha sonra düzenlenebilir veya silinebilir.
                </div>
                
                <form action="tournament_leaderboard.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                    <input type="hidden" name="add_entry" value="1">
                    
                    <div class="card border-success mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 text-success">Kullanıcı Bilgileri</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Kullanıcı Adı <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Puanlar <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-star"></i></span>
                                    <input type="number" step="0.01" class="form-control" name="points" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Sıra <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-sort-numeric-down"></i></span>
                                    <input type="number" class="form-control" name="rank" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Ödül (₺)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-money-bill-wave"></i></span>
                                    <input type="number" step="0.01" class="form-control" name="prize" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="submit-section">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> İptal
                        </button>
                        <button type="submit" class="btn btn-success float-end">
                            <i class="fas fa-plus-circle"></i> Kullanıcı Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Add Entry Modal -->

<!-- Update Ranks Modal -->
<div class="modal custom-modal fade" id="update_ranks_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-sort-numeric-down me-2"></i>Sıralamayı Güncelle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info border-info mb-4">
                    <i class="fas fa-info-circle me-2"></i> Bu işlem, kullanıcıları puanlarına göre otomatik olarak sıralayacaktır.
                </div>
                
                <p class="text-center">Devam etmek istiyor musunuz?</p>
                <form action="tournament_leaderboard.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                    <input type="hidden" name="update_ranks" value="1">
                    
                    <div class="submit-section">
                        <button type="submit" class="btn btn-info submit-btn w-100">
                            <i class="fas fa-sort-numeric-down me-2"></i> Evet, Otomatik Sırala
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Update Ranks Modal -->

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

.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: white;
    font-weight: bold;
}

.rank-1 {
    background: linear-gradient(45deg, #FFD700, #FFA500);
    box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);
}

.rank-2 {
    background: linear-gradient(45deg, #C0C0C0, #A9A9A9);
    box-shadow: 0 3px 10px rgba(192, 192, 192, 0.3);
}

.rank-3 {
    background: linear-gradient(45deg, #CD7F32, #8B4513);
    box-shadow: 0 3px 10px rgba(205, 127, 50, 0.3);
}

.action-buttons .btn {
    width: 38px;
    height: 38px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.action-buttons .btn i {
    font-size: 16px;
}

.modal-content {
    border-radius: 0.5rem;
    overflow: hidden;
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

.table>:not(caption)>*>* {
    padding: 0.75rem 0.5rem;
    vertical-align: middle;
}

.custom-modal .modal-content {
    border-radius: 0.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.alert {
    border-left-width: 4px !important;
}

.alert-info {
    border-left-color: #0dcaf0 !important;
}

.alert-warning {
    border-left-color: #ffc107 !important;
}

.alert-danger {
    border-left-color: #dc3545 !important;
}

.alert-success {
    border-left-color: #198754 !important;
}

.table-info {
    background-color: rgba(13, 202, 240, 0.1);
}
</style>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 