<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "Turnuva Oyunları";

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

// Get tournament games
$stmt = $db->prepare("SELECT * FROM tournament_games WHERE tournament_id = ? ORDER BY id");
$stmt->execute([$tournament_id]);
$games = $stmt->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_game'])) {
        // Validate and get input
        $game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
        $game_name = isset($_POST['game_name']) ? trim($_POST['game_name']) : '';
        $game_provider = isset($_POST['game_provider']) ? trim($_POST['game_provider']) : '';
        $game_image = isset($_POST['game_image']) ? trim($_POST['game_image']) : '';
        
        if (empty($game_id)) {
            $error = "Oyun ID'si gereklidir";
        } else {
            // Check if game already exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM tournament_games WHERE tournament_id = ? AND game_id = ?");
            $stmt->execute([$tournament_id, $game_id]);
            $gameExists = ($stmt->fetchColumn() > 0);
            
            if ($gameExists) {
                $error = "Bu oyun zaten turnuvaya eklenmiş";
            } else {
                // Insert new game
                $stmt = $db->prepare("
                    INSERT INTO tournament_games (tournament_id, game_id, game_name, game_provider, game_image) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$tournament_id, $game_id, $game_name, $game_provider, $game_image])) {
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'create', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Turnuva oyunu eklendi: $game_name (Turnuva ID: $tournament_id)"]);
                    
                    $success = "Oyun başarıyla eklendi";
                } else {
                    $error = "Oyun eklenirken bir hata oluştu";
                }
            }
        }
    } elseif (isset($_POST['update_game'])) {
        // Validate and get input
        $game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : 0;
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $game_name = isset($_POST['game_name']) ? trim($_POST['game_name']) : '';
        $game_provider = isset($_POST['game_provider']) ? trim($_POST['game_provider']) : '';
        $game_image = isset($_POST['game_image']) ? trim($_POST['game_image']) : '';
        
        if (empty($id)) {
            $error = "Geçersiz oyun kaydı";
        } else {
            // Update game
            $stmt = $db->prepare("
                UPDATE tournament_games 
                SET game_id = ?, game_name = ?, game_provider = ?, game_image = ? 
                WHERE id = ? AND tournament_id = ?
            ");
            if ($stmt->execute([$game_id, $game_name, $game_provider, $game_image, $id, $tournament_id])) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva oyunu güncellendi: $game_name (Turnuva ID: $tournament_id)"]);
                
                $success = "Oyun başarıyla güncellendi";
            } else {
                $error = "Oyun güncellenirken bir hata oluştu";
            }
        }
    } elseif (isset($_POST['delete_game'])) {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if (empty($id)) {
            $error = "Geçersiz oyun kaydı";
        } else {
            // Get game name for logging
            $stmt = $db->prepare("SELECT game_name FROM tournament_games WHERE id = ? AND tournament_id = ?");
            $stmt->execute([$id, $tournament_id]);
            $game_name = $stmt->fetchColumn();
            
            // Delete game
            $stmt = $db->prepare("DELETE FROM tournament_games WHERE id = ? AND tournament_id = ?");
            if ($stmt->execute([$id, $tournament_id])) {
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'delete', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Turnuva oyunu silindi: $game_name (Turnuva ID: $tournament_id)"]);
                
                $success = "Oyun başarıyla silindi";
            } else {
                $error = "Oyun silinirken bir hata oluştu";
            }
        }
    }
    
    // Refresh games list
    $stmt = $db->prepare("SELECT * FROM tournament_games WHERE tournament_id = ? ORDER BY id");
    $stmt->execute([$tournament_id]);
    $games = $stmt->fetchAll();
}

// Start output buffering
ob_start();
?>

<!-- Page Header -->
<div class="page-header" style="background-color: #2196F3; margin-bottom: 30px; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title" style="color: white; font-weight: 600; margin: 0;">Turnuva Oyunları</h3>
            <ul class="breadcrumb" style="background: transparent; padding: 0; margin: 8px 0 0;">
                <li class="breadcrumb-item"><a href="index.php" style="color: rgba(255,255,255,0.8);">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="tournaments.php" style="color: rgba(255,255,255,0.8);">Turnuvalar</a></li>
                <li class="breadcrumb-item active" style="color: white;">Turnuva Oyunları</li>
            </ul>
        </div>
        <div class="col-auto float-right ml-auto">
            <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addGameModal" style="border-radius: 50px; padding: 8px 20px;"><i class="bi bi-plus-lg"></i> Oyun Ekle</a>
        </div>
    </div>
</div>
<!-- /Page Header -->

<!-- Alerts Container -->
<div id="alerts-container">
    <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <strong>Başarılı:</strong> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle-fill me-2"></i> <strong>Hata:</strong> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
</div>
<!-- /Alerts Container -->

<div class="row">
    <div class="col-md-12">
        <div class="card" style="border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="card-header" style="background-color: #2196F3; color: white; border-radius: 10px 10px 0 0;">
                <h4 class="card-title mb-0"><?php echo htmlspecialchars($tournament['title']); ?> - Oyunlar</h4>
            </div>
            <div class="card-body">                
                <div class="game-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php if (count($games) > 0): ?>
                        <?php foreach ($games as $game): ?>
                            <div class="game-card" style="background-color: #1e293b; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; overflow: hidden;">
                                <div class="game-image" style="height: 160px; overflow: hidden; position: relative; border-radius: 10px 10px 0 0;">
                                    <img src="<?php echo htmlspecialchars($game['game_image']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div class="game-info" style="padding: 15px;">
                                    <h4 style="font-size: 18px; margin-bottom: 8px; color: #f8fafc; font-weight: 600;"><?php echo htmlspecialchars($game['game_name']); ?></h4>
                                    <p style="color: #94a3b8; margin-bottom: 15px; font-size: 14px;">Provider: <?php echo htmlspecialchars($game['game_provider']); ?></p>
                                    <div class="game-actions" style="display: flex; gap: 10px;">
                                        <a href="#" class="btn btn-primary btn-sm edit-game-btn" data-bs-toggle="modal" data-bs-target="#editGameModal" 
                                          data-game-id="<?php echo $game['id']; ?>"
                                          style="flex: 1; text-align: center; border-radius: 4px; padding: 6px 10px;">
                                          <i class="bi bi-pencil-fill"></i> Düzenle
                                        </a>
                                        <a href="#" class="btn btn-danger btn-sm delete-game" data-bs-toggle="modal" data-bs-target="#deleteGameModal" 
                                          data-id="<?php echo $game['id']; ?>"
                                          data-name="<?php echo htmlspecialchars($game['game_name']); ?>"
                                          style="flex: 1; text-align: center; border-radius: 4px; padding: 6px 10px;">
                                          <i class="bi bi-trash-fill"></i> Sil
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-md-12">
                            <div class="alert alert-info" style="border-radius: 5px;">
                                <i class="bi bi-info-circle me-2"></i> Henüz eklenmiş oyun bulunmamaktadır.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Game Modal -->
<div id="addGameModal" class="modal custom-modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; background: var(--dark-card);">
            <div class="modal-header" style="background-color: #2196F3; color: white; border-bottom: none; padding: 20px;">
                <h5 class="modal-title">Oyun Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <form id="addGameForm" action="" method="post">
                    <input type="hidden" name="add_game" value="1">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Oyun ID <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="game_id" id="add_game_id" required>
                                    <button type="button" class="btn btn-primary" id="search_game_btn">
                                        <i class="bi bi-search"></i> Oyun Ara
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Oyun Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="game_name" id="add_game_name" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Sağlayıcı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="game_provider" id="add_game_provider" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Görsel URL <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="game_image" id="add_image_url" required>
                                    <button type="button" class="btn btn-info preview-add-game-image">
                                        <i class="bi bi-eye-fill"></i> Görsel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="game_search_results" class="mb-3" style="display: none;">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> Oyun bilgileri bulundu ve form dolduruldu.
                        </div>
                    </div>
                    
                    <div class="submit-section text-center" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" id="saveGameBtn" style="padding: 8px 30px; border-radius: 4px;">Oyun Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Add Game Modal -->

<!-- Edit Game Modal -->
<div id="editGameModal" class="modal custom-modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; background: var(--dark-card);">
            <div class="modal-header" style="background-color: #2196F3; color: white; border-bottom: none; padding: 20px;">
                <h5 class="modal-title">Oyun Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <form id="editGameForm" action="" method="post">
                    <input type="hidden" name="update_game" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Oyun ID <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="game_id" id="edit_game_id" required>
                                    <button type="button" class="btn btn-primary" id="edit_search_game_btn">
                                        <i class="bi bi-search"></i> Oyun Ara
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Oyun Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="game_name" id="edit_game_name" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Sağlayıcı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="game_provider" id="edit_game_provider" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Görsel URL <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="game_image" id="edit_image_url" required>
                                    <button type="button" class="btn btn-info preview-game-image">
                                        <i class="bi bi-eye-fill"></i> Görsel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="edit_game_search_results" class="mb-3" style="display: none;">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> Oyun bilgileri bulundu ve form dolduruldu.
                        </div>
                    </div>
                    
                    <div class="submit-section text-center" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" id="updateGameBtn" style="padding: 8px 30px; border-radius: 4px;">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Edit Game Modal -->

<!-- Delete Game Modal -->
<div class="modal custom-modal fade" id="deleteGameModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden; background: var(--dark-card);">
            <div class="modal-header" style="background-color: #f44336; color: white; border-bottom: none; padding: 20px;">
                <h5 class="modal-title">Oyun Sil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <form action="" method="post">
                    <input type="hidden" name="delete_game" value="1">
                    <input type="hidden" name="id" id="delete_game_id">
                    <div class="form-header">
                        <p>Bu oyunu silmek istediğinizden emin misiniz?</p>
                        <p><strong id="delete_game_name"></strong></p>
                    </div>
                    <div class="submit-section text-center" style="margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="padding: 8px 20px; border-radius: 4px; margin-right: 10px;">İptal</button>
                        <button type="submit" class="btn btn-danger" style="padding: 8px 20px; border-radius: 4px;">Sil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- /Delete Game Modal -->

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: var(--dark-card);">
            <div class="modal-header" style="background-color: #2196F3; color: white;">
                <h5 class="modal-title" id="previewTitle">Görsel Önizleme</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="" alt="Preview" style="max-width: 100%; max-height: 400px;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
<!-- /Image Preview Modal -->

<style>
    .game-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .game-actions .btn {
        transition: all 0.3s ease;
    }
    
    .game-actions .btn:hover {
        transform: translateY(-2px);
    }
    
    .custom-modal .modal-content {
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    }

    @media (max-width: 767px) {
        .game-grid {
            grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit game modal
    document.querySelectorAll('.edit-game-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            var gameId = this.getAttribute('data-game-id');
            
            // Get the record ID directly from the button data attribute
            document.getElementById('edit_id').value = gameId;
            
            // Show loading indicator
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Yükleniyor...';
            const editButton = this; // Store reference to the button
            
            // Fetch existing game data from the database
            fetch('ajax/search_game.php?id=' + gameId)
            .then(response => response.json())
            .then(data => {
                // Reset button text
                editButton.innerHTML = '<i class="bi bi-pencil-fill"></i> Düzenle';
                
                if (data.success) {
                    const game = data.data;
                    if (game) {
                        document.getElementById('edit_game_id').value = game.game_id || '';
                        document.getElementById('edit_game_name').value = game.game_name || '';
                        document.getElementById('edit_game_provider').value = game.game_provider || '';
                        document.getElementById('edit_image_url').value = game.game_image || '';
                        
                        // Show success message
                        document.getElementById('edit_game_search_results').style.display = 'block';
                        document.getElementById('edit_game_search_results').innerHTML = `
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i> <strong>Oyun bilgileri yüklendi:</strong> ${game.game_name}
                            </div>
                        `;
                    } else {
                        showAlert('error', 'Hata', 'Oyun bilgileri bulunamadı');
                        document.getElementById('edit_game_search_results').style.display = 'none';
                    }
                } else {
                    showAlert('error', 'Hata', data.message || 'Oyun bilgileri alınamadı');
                    document.getElementById('edit_game_search_results').style.display = 'none';
                }
            })
            .catch(error => {
                // Reset button text
                editButton.innerHTML = '<i class="bi bi-pencil-fill"></i> Düzenle';
                
                showAlert('error', 'Hata', 'Oyun bilgileri alınırken bir hata oluştu: ' + error);
                document.getElementById('edit_game_search_results').style.display = 'none';
            });
        });
    });
    
    // Game search button in Add Game modal
    document.getElementById('search_game_btn').addEventListener('click', function() {
        const gameId = document.getElementById('add_game_id').value.trim();
        
        if (!gameId) {
            showAlert('warning', 'Uyarı', 'Lütfen arama yapmak için bir Oyun ID girin.');
            return;
        }
        
        // Show loading indicator
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aranıyor...';
        this.disabled = true;
        
        // Fetch game data from search_game.php
        fetch('ajax/search_game.php?game_id=' + gameId)
        .then(response => response.json())
        .then(data => {
            // Reset button
            this.innerHTML = '<i class="bi bi-search"></i> Oyun Ara';
            this.disabled = false;
            
            if (data.success) {
                // Get proper field names based on the database response
                // Map the fields from the database structure (game_name, provider_game, cover/banner)
                document.getElementById('add_game_name').value = data.game.game_name || data.game.name || '';
                document.getElementById('add_game_provider').value = data.game.provider_game || data.game.provider || '';
                
                // Use cover or banner for the image URL
                const imageUrl = data.game.cover || data.game.banner || data.game.image_url || data.game.image || '';
                
                // If the image URL doesn't start with http, add the domain
                document.getElementById('add_image_url').value = imageUrl.startsWith('http') ? imageUrl : 
                    (imageUrl.startsWith('drakon/') ? 'https://gator.drakonapi.tech/storage/' + imageUrl : imageUrl);
                
                // Show success message
                document.getElementById('game_search_results').style.display = 'block';
                document.getElementById('game_search_results').innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i> <strong>Oyun bulundu:</strong> ${data.game.game_name || data.game.name}
                        <p class="mb-0 mt-1">Sağlayıcı: <strong>${data.game.provider_game || data.game.provider || 'Belirtilmemiş'}</strong></p>
                    </div>
                `;
                
                // Preview image if available (in a separate action, not automatic)
                if (imageUrl) {
                    const fullImageUrl = imageUrl.startsWith('http') ? imageUrl : 
                        (imageUrl.startsWith('drakon/') ? 'https://gator.drakonapi.tech/storage/' + imageUrl : imageUrl);
                    
                    // Update preview image source but don't show modal automatically
                    document.getElementById('previewImage').src = fullImageUrl;
                }
            } else {
                // Show error message
                showAlert('error', 'Hata', data.message || 'Oyun bulunamadı.');
                document.getElementById('game_search_results').style.display = 'none';
            }
        })
        .catch(error => {
            // Reset button
            this.innerHTML = '<i class="bi bi-search"></i> Oyun Ara';
            this.disabled = false;
            
            showAlert('error', 'Hata', 'Oyun aranırken bir hata oluştu.');
            document.getElementById('game_search_results').style.display = 'none';
        });
    });
    
    // Game search button in Edit Game modal
    document.getElementById('edit_search_game_btn').addEventListener('click', function() {
        const gameId = document.getElementById('edit_game_id').value.trim();
        
        if (!gameId) {
            showAlert('warning', 'Uyarı', 'Lütfen arama yapmak için bir Oyun ID girin.');
            return;
        }
        
        // Show loading indicator
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aranıyor...';
        this.disabled = true;
        
        // Fetch game data from search_game.php
        fetch('ajax/search_game.php?game_id=' + gameId)
        .then(response => response.json())
        .then(data => {
            // Reset button
            this.innerHTML = '<i class="bi bi-search"></i> Oyun Ara';
            this.disabled = false;
            
            if (data.success) {
                // Get proper field names based on the database response
                // Map the fields from the database structure (game_name, provider_game, cover/banner)
                document.getElementById('edit_game_name').value = data.game.game_name || data.game.name || '';
                document.getElementById('edit_game_provider').value = data.game.provider_game || data.game.provider || '';
                
                // Use cover or banner for the image URL
                const imageUrl = data.game.cover || data.game.banner || data.game.image_url || data.game.image || '';
                
                // If the image URL doesn't start with http, add the domain
                document.getElementById('edit_image_url').value = imageUrl.startsWith('http') ? imageUrl : 
                    (imageUrl.startsWith('drakon/') ? 'https://gator.drakonapi.tech/storage/' + imageUrl : imageUrl);
                
                // Show success message
                document.getElementById('edit_game_search_results').style.display = 'block';
                document.getElementById('edit_game_search_results').innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i> <strong>Oyun bulundu:</strong> ${data.game.game_name || data.game.name}
                        <p class="mb-0 mt-1">Sağlayıcı: <strong>${data.game.provider_game || data.game.provider || 'Belirtilmemiş'}</strong></p>
                    </div>
                `;
                
                // Preview image if available (in a separate action, not automatic)
                if (imageUrl) {
                    const fullImageUrl = imageUrl.startsWith('http') ? imageUrl : 
                        (imageUrl.startsWith('drakon/') ? 'https://gator.drakonapi.tech/storage/' + imageUrl : imageUrl);
                    
                    // Update preview image source but don't show modal automatically
                    document.getElementById('previewImage').src = fullImageUrl;
                }
            } else {
                // Show error message
                showAlert('error', 'Hata', data.message || 'Oyun bulunamadı.');
                document.getElementById('edit_game_search_results').style.display = 'none';
            }
        })
        .catch(error => {
            // Reset button
            this.innerHTML = '<i class="bi bi-search"></i> Oyun Ara';
            this.disabled = false;
            
            showAlert('error', 'Hata', 'Oyun aranırken bir hata oluştu.');
            document.getElementById('edit_game_search_results').style.display = 'none';
        });
    });
    
    // Delete game modal
    document.querySelectorAll('.delete-game').forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            document.getElementById('delete_game_id').value = id;
            document.getElementById('delete_game_name').textContent = name;
        });
    });
    
    // Image preview for edit form
    document.querySelector('.preview-game-image').addEventListener('click', function() {
        var imageUrl = document.getElementById('edit_image_url').value;
        if (imageUrl) {
            document.getElementById('previewImage').src = imageUrl;
            document.getElementById('previewTitle').textContent = 'Görsel Önizleme';
            var previewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
            previewModal.show();
        } else {
            showAlert('warning', 'Uyarı', 'Lütfen önce bir görsel URL\'si girin.');
        }
    });
    
    // Image preview for add form
    document.querySelector('.preview-add-game-image').addEventListener('click', function() {
        var imageUrl = document.getElementById('add_image_url').value;
        if (imageUrl) {
            document.getElementById('previewImage').src = imageUrl;
            document.getElementById('previewTitle').textContent = 'Görsel Önizleme';
            var previewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
            previewModal.show();
        } else {
            showAlert('warning', 'Uyarı', 'Lütfen önce bir görsel URL\'si girin.');
        }
    });
    
    // Function to show alerts
    function showAlert(type, title, message) {
        var alertClass = 'alert-info';
        var icon = 'bi-info-circle-fill';
        
        if (type === 'success') {
            alertClass = 'alert-success';
            icon = 'bi-check-circle-fill';
        } else if (type === 'error') {
            alertClass = 'alert-danger';
            icon = 'bi-exclamation-circle-fill';
        } else if (type === 'warning') {
            alertClass = 'alert-warning';
            icon = 'bi-exclamation-triangle-fill';
        }
        
        var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
            '<i class="bi ' + icon + ' me-2"></i> <strong>' + title + ':</strong> ' + message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
            
        // Add alert to container
        document.getElementById('alerts-container').innerHTML = alertHtml;
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            const alertElement = document.querySelector('#alerts-container .alert');
            if (alertElement) {
                const bsAlert = new bootstrap.Alert(alertElement);
                bsAlert.close();
            }
        }, 5000);
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 