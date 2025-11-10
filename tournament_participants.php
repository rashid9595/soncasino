<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Turnuva Katılımcıları";

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

// Get tournament participants
$stmt = $db->prepare("SELECT * FROM tournament_participants WHERE tournament_id = ? ORDER BY rank, points DESC");
$stmt->execute([$tournament_id]);
$participants = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_participant'])) {
        // Validate and get input
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $points = isset($_POST['points']) ? floatval($_POST['points']) : 0;
        $rank = isset($_POST['rank']) ? (int)$_POST['rank'] : 0;
        
        if (empty($user_id) || empty($username)) {
            $error = "Kullanıcı ID ve kullanıcı adı gereklidir";
        } else {
            // Check if user already exists in participants
            $stmt = $db->prepare("SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
            $stmt->execute([$tournament_id, $user_id]);
            $userExists = ($stmt->fetchColumn() > 0);
            
            if ($userExists) {
                $error = "Bu kullanıcı zaten turnuvaya katılmış";
            } else {
                // Insert new participant
                $stmt = $db->prepare("
                    INSERT INTO tournament_participants (tournament_id, user_id, username, joined_at, points, rank) 
                    VALUES (?, ?, ?, NOW(), ?, ?)
                ");
                if ($stmt->execute([$tournament_id, $user_id, $username, $points, $rank])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'create', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Turnuvaya katılımcı eklendi: $username (Turnuva ID: $tournament_id)"]);
                    
                    $success = "Katılımcı başarıyla eklendi";
                } else {
                    $error = "Katılımcı eklenirken bir hata oluştu";
                }
            }
        }
    } elseif (isset($_POST['update_participant'])) {
        // Validate and get input
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $points = isset($_POST['points']) ? floatval($_POST['points']) : 0;
        $rank = isset($_POST['rank']) ? (int)$_POST['rank'] : 0;
        
        if (empty($id) || empty($user_id) || empty($username)) {
            $error = "Geçersiz kayıt, kullanıcı ID veya kullanıcı adı";
        } else {
            // Update participant
            $stmt = $db->prepare("
                UPDATE tournament_participants 
                SET user_id = ?, username = ?, points = ?, rank = ? 
                WHERE id = ? AND tournament_id = ?
            ");
            if ($stmt->execute([$user_id, $username, $points, $rank, $id, $tournament_id])) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva katılımcısı güncellendi: $username (Turnuva ID: $tournament_id)"]);
                
                $success = "Katılımcı başarıyla güncellendi";
            } else {
                $error = "Katılımcı güncellenirken bir hata oluştu";
            }
        }
    } elseif (isset($_POST['delete_participant'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (empty($id)) {
            $error = "Geçersiz katılımcı kaydı";
        } else {
            // Get username for logging
            $stmt = $db->prepare("SELECT username FROM tournament_participants WHERE id = ? AND tournament_id = ?");
            $stmt->execute([$id, $tournament_id]);
            $username = $stmt->fetchColumn();
            
            // Delete participant
            $stmt = $db->prepare("DELETE FROM tournament_participants WHERE id = ? AND tournament_id = ?");
            if ($stmt->execute([$id, $tournament_id])) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'delete', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva katılımcısı silindi: $username (Turnuva ID: $tournament_id)"]);
                
                $success = "Katılımcı başarıyla silindi";
            } else {
                $error = "Katılımcı silinirken bir hata oluştu";
            }
        }
    } elseif (isset($_POST['sync_leaderboard'])) {
        // Sync participants with leaderboard
        try {
            $db->beginTransaction();
            
            // First, delete existing leaderboard entries for this tournament
            $stmt = $db->prepare("DELETE FROM tournament_leaderboard WHERE tournament_id = ?");
            $stmt->execute([$tournament_id]);
            
            // Then, insert participants into leaderboard
            $stmt = $db->prepare("
                INSERT INTO tournament_leaderboard (tournament_id, username, points, rank, prize)
                SELECT tournament_id, username, points, rank, 0
                FROM tournament_participants
                WHERE tournament_id = ?
            ");
            $stmt->execute([$tournament_id]);
            
            $db->commit();
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Turnuva katılımcıları lider tablosu ile senkronize edildi (Turnuva ID: $tournament_id)"]);
            
            $success = "Katılımcılar lider tablosu ile başarıyla senkronize edildi";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Senkronizasyon sırasında bir hata oluştu: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_ranks'])) {
        // Update ranks based on points
        $stmt = $db->prepare("
            UPDATE tournament_participants tp1
            JOIN (
                SELECT id, @rank := @rank + 1 as new_rank
                FROM tournament_participants, (SELECT @rank := 0) r
                WHERE tournament_id = ?
                ORDER BY points DESC
            ) tp2 ON tp1.id = tp2.id
            SET tp1.rank = tp2.new_rank
            WHERE tp1.tournament_id = ?
        ");
        
        if ($stmt->execute([$tournament_id, $tournament_id])) {
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'update', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Turnuva katılımcıları sıralaması otomatik olarak güncellendi (Turnuva ID: $tournament_id)"]);
            
            $success = "Katılımcı sıralaması otomatik olarak güncellendi";
        } else {
            $error = "Sıralama güncellenirken bir hata oluştu";
        }
    }
    
    // Refresh participants list
    $stmt = $db->prepare("SELECT * FROM tournament_participants WHERE tournament_id = ? ORDER BY rank, points DESC");
    $stmt->execute([$tournament_id]);
    $participants = $stmt->fetchAll();
}

// Start output buffering
ob_start();
?>

<!-- Page Content -->
<div class="page-header bg-primary text-white">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Turnuva Katılımcıları</h3>
            <ul class="breadcrumb bg-transparent">
                <li class="breadcrumb-item"><a href="index.php" class="text-white">Gösterge Paneli</a></li>
                <li class="breadcrumb-item"><a href="tournaments.php" class="text-white">Turnuvalar</a></li>
                <li class="breadcrumb-item active text-white">Turnuva Katılımcıları</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="tournaments.php" class="btn btn-light me-2">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
            <div class="btn-group">
                <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog"></i> İşlemler
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#update_ranks_modal">
                        <i class="fas fa-sort-numeric-down"></i> Otomatik Sırala
                    </a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sync_leaderboard_modal">
                        <i class="fas fa-sync"></i> Lider Tablosu ile Senkronize Et
                    </a></li>
                </ul>
            </div>
            <a href="#" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#add_participant_modal">
                <i class="fas fa-plus"></i> Katılımcı Ekle
            </a>
        </div>
    </div>
</div>

<div class="alerts-container">
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
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
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="card-title mb-0">
                            <?php echo htmlspecialchars($tournament['title']); ?>
                            <span class="badge <?php 
                                echo $tournament['status'] === 'ongoing' ? 'bg-success' : 
                                    ($tournament['status'] === 'completed' ? 'bg-info' : 'bg-warning'); 
                            ?>">
                                <?php 
                                    echo $tournament['status'] === 'ongoing' ? 'Devam Ediyor' : 
                                        ($tournament['status'] === 'completed' ? 'Tamamlandı' : 'Yakında'); 
                                ?>
                            </span>
                        </h5>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-light text-primary">
                            <i class="fas fa-users"></i> <?php echo count($participants); ?> Katılımcı
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($participants) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Kullanıcı ID</th>
                                <th width="25%">Kullanıcı Adı</th>
                                <th width="20%">Katılım Tarihi</th>
                                <th width="10%">Puanlar</th>
                                <th width="10%">Sıra</th>
                                <th width="20%" class="text-center">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $participant): ?>
                            <tr>
                                <td><span class="badge bg-dark"><?php echo $participant['id']; ?></span></td>
                                <td><span class="badge bg-secondary"><?php echo $participant['user_id']; ?></span></td>
                                <td class="fw-bold text-primary"><?php echo htmlspecialchars($participant['username']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($participant['joined_at'])); ?></td>
                                <td>
                                    <span class="points-badge">
                                        <?php echo number_format($participant['points'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($participant['rank'] <= 3 && $participant['rank'] > 0): ?>
                                    <div class="rank-badge rank-<?php echo $participant['rank']; ?>">
                                        <?php echo $participant['rank']; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark"><?php echo $participant['rank']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons d-flex justify-content-center gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#edit_participant_<?php echo $participant['id']; ?>" title="Düzenle">
                                            <i class="fas fa-edit"></i> Düzenle
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#delete_participant_<?php echo $participant['id']; ?>" title="Sil">
                                            <i class="fas fa-trash"></i> Sil
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Edit Participant Modal -->
                            <div class="modal custom-modal fade" id="edit_participant_<?php echo $participant['id']; ?>" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Katılımcı Düzenle</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="tournament_participants.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                                                <input type="hidden" name="update_participant" value="1">
                                                <input type="hidden" name="id" value="<?php echo $participant['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Kullanıcı ID</label>
                                                    <input type="number" class="form-control" name="user_id" value="<?php echo $participant['user_id']; ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Kullanıcı Adı</label>
                                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($participant['username']); ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Puanlar</label>
                                                    <input type="number" step="0.01" class="form-control" name="points" value="<?php echo $participant['points']; ?>">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Sıra</label>
                                                    <input type="number" class="form-control" name="rank" value="<?php echo $participant['rank']; ?>">
                                                </div>
                                                
                                                <div class="submit-section">
                                                    <button type="submit" class="btn btn-primary submit-btn w-100">
                                                        <i class="fas fa-save me-2"></i>Güncelle
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /Edit Participant Modal -->
                            
                            <!-- Delete Participant Modal -->
                            <div class="modal custom-modal fade" id="delete_participant_<?php echo $participant['id']; ?>" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Katılımcı Sil</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="text-center mb-4">
                                                <div class="avatar avatar-xxl">
                                                    <div class="avatar-title bg-light-danger text-danger rounded-circle">
                                                        <i class="fas fa-user-times fa-2x"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-center">Bu kullanıcıyı turnuvadan kaldırmak istediğinizden emin misiniz?</p>
                                            <p class="text-center text-danger">
                                                <strong><?php echo htmlspecialchars($participant['username']); ?></strong>
                                            </p>
                                            <form action="tournament_participants.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                                                <input type="hidden" name="delete_participant" value="1">
                                                <input type="hidden" name="id" value="<?php echo $participant['id']; ?>">
                                                
                                                <div class="submit-section">
                                                    <button type="submit" class="btn btn-danger submit-btn w-100">
                                                        <i class="fas fa-trash me-2"></i>Evet, Sil
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /Delete Participant Modal -->
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <div class="avatar">
                        <div class="avatar-title bg-light text-primary rounded-circle">
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                    </div>
                    <h4 class="mt-4">Henüz katılımcı bulunmamaktadır</h4>
                    <p class="text-muted">Katılımcı eklemek için "Katılımcı Ekle" butonuna tıklayın.</p>
                    <a href="#" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#add_participant_modal">
                        <i class="fas fa-plus me-2"></i>Katılımcı Ekle
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Participant Modal -->
<div class="modal custom-modal fade" id="add_participant_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Katılımcı Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="tournament_participants.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                    <input type="hidden" name="add_participant" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı ID <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="user_id" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Puanlar</label>
                        <input type="number" step="0.01" class="form-control" name="points" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sıra</label>
                        <input type="number" class="form-control" name="rank" value="0">
                    </div>
                    
                    <div class="submit-section">
                        <button type="submit" class="btn btn-success submit-btn w-100">
                            <i class="fas fa-user-plus me-2"></i>Katılımcı Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Add Participant Modal -->

<!-- Update Ranks Modal -->
<div class="modal custom-modal fade" id="update_ranks_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-sort-numeric-down me-2"></i>Sıralamayı Güncelle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="avatar avatar-xxl">
                        <div class="avatar-title bg-light-info text-info rounded-circle">
                            <i class="fas fa-trophy fa-2x"></i>
                        </div>
                    </div>
                </div>
                <p class="text-center">Bu işlem, katılımcıları puanlarına göre otomatik olarak sıralayacaktır. Devam etmek istiyor musunuz?</p>
                <form action="tournament_participants.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                    <input type="hidden" name="update_ranks" value="1">
                    
                    <div class="submit-section">
                        <button type="submit" class="btn btn-info submit-btn w-100">
                            <i class="fas fa-check me-2"></i>Evet, Otomatik Sırala
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Update Ranks Modal -->

<!-- Sync Leaderboard Modal -->
<div class="modal custom-modal fade" id="sync_leaderboard_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-sync me-2"></i>Lider Tablosu ile Senkronize Et</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> Uyarı: Bu işlem mevcut lider tablosunu temizleyecek ve katılımcılarla dolduracaktır.
                </div>
                <div class="text-center mb-4">
                    <div class="avatar avatar-xxl">
                        <div class="avatar-title bg-light-warning text-warning rounded-circle">
                            <i class="fas fa-sync-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
                <p class="text-center">Devam etmek istiyor musunuz?</p>
                <form action="tournament_participants.php?tournament_id=<?php echo $tournament_id; ?>" method="POST">
                    <input type="hidden" name="sync_leaderboard" value="1">
                    
                    <div class="submit-section">
                        <button type="submit" class="btn btn-warning submit-btn w-100">
                            <i class="fas fa-sync me-2"></i>Evet, Senkronize Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Sync Leaderboard Modal -->

<style>
    .alerts-container {
        margin-bottom: 1.5rem;
    }
    
    .rank-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        color: white;
        font-weight: bold;
        font-size: 16px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    .rank-1 {
        background: linear-gradient(45deg, #FFD700, #FFA500);
        box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
    }
    
    .rank-2 {
        background: linear-gradient(45deg, #C0C0C0, #A9A9A9);
        box-shadow: 0 4px 12px rgba(192, 192, 192, 0.4);
    }
    
    .rank-3 {
        background: linear-gradient(45deg, #CD7F32, #8B4513);
        box-shadow: 0 4px 12px rgba(205, 127, 50, 0.4);
    }
    
    .points-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(45deg, #3a7bd5, #00d2ff);
        color: white;
        font-weight: bold;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 14px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }
    
    .action-buttons .btn {
        min-width: 90px;
        padding: 5px 10px;
        border-radius: 4px;
        transition: all 0.2s;
        font-size: 12px;
        font-weight: 500;
    }
    
    .action-buttons .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .action-buttons .btn i {
        font-size: 14px;
        margin-right: 5px;
    }
    
    .page-header {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 0.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .breadcrumb-item a {
        text-decoration: none;
    }
    
    .breadcrumb-item a:hover {
        text-decoration: underline;
    }
    
    .card {
        border: none;
        border-radius: 0.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    
    .card-header {
        padding: 1rem;
    }
    
    .table>:not(caption)>*>* {
        padding: 0.75rem 0.5rem;
        vertical-align: middle;
    }
    
    .custom-modal .modal-content {
        border-radius: 0.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: none;
    }
    
    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .avatar-title {
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .avatar-xxl .avatar-title {
        width: 100px;
        height: 100px;
    }
    
    .bg-light-danger {
        background-color: rgba(220, 53, 69, 0.1) !important;
    }
    
    .bg-light-info {
        background-color: rgba(23, 162, 184, 0.1) !important;
    }
    
    .bg-light-warning {
        background-color: rgba(255, 193, 7, 0.1) !important;
    }
    
    .alert {
        border-radius: 0.4rem;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }
</style>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 