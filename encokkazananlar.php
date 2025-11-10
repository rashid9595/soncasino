<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// İzin kontrolü - Sadece super admin (role_id = 1)
if ($_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

// İstatistikleri hesapla
try {
    $stmt = $db->query("SELECT COUNT(*) as totalWinners FROM encokkazananlar");
    $totalWinners = $stmt->fetch()['totalWinners'];

    $stmt = $db->query("SELECT COUNT(*) as dailyWinners FROM encokkazananlar WHERE time_period = 'daily'");
    $dailyWinners = $stmt->fetch()['dailyWinners'];

    $stmt = $db->query("SELECT COUNT(*) as weeklyWinners FROM encokkazananlar WHERE time_period = 'weekly'");
    $weeklyWinners = $stmt->fetch()['weeklyWinners'];

    $stmt = $db->query("SELECT COUNT(*) as monthlyWinners FROM encokkazananlar WHERE time_period = 'monthly'");
    $monthlyWinners = $stmt->fetch()['monthlyWinners'];

    $stmt = $db->query("SELECT SUM(winning_amount) as totalWinnings FROM encokkazananlar");
    $totalWinnings = $stmt->fetch()['totalWinnings'] ?? 0;

    $stmt = $db->query("SELECT AVG(winning_amount) as avgWinnings FROM encokkazananlar");
    $avgWinnings = round($stmt->fetch()['avgWinnings'] ?? 0, 2);

} catch (Exception $e) {
    $totalWinners = 0;
    $dailyWinners = 0;
    $weeklyWinners = 0;
    $monthlyWinners = 0;
    $totalWinnings = 0;
    $avgWinnings = 0;
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit':
                try {
                    $id = $_POST['id'];
                    $game_name = $_POST['game_name'];
                    $player_username = $_POST['player_username'];
                    $winning_amount = $_POST['winning_amount'];
                    $game_image_url = $_POST['game_image_url'];
                    $time_period = $_POST['time_period'];
                    $game_id = $_POST['game_id'];
                    $redirect_url = $_POST['redirect_url'];
                    
                    $stmt = $db->prepare("UPDATE encokkazananlar SET 
                        game_name = ?, 
                        player_username = ?, 
                        winning_amount = ?, 
                        game_image_url = ?, 
                        time_period = ?, 
                        game_id = ?, 
                        redirect_url = ?,
                        updated_at = NOW()
                    WHERE id = ?");
                    $stmt->execute([$game_name, $player_username, $winning_amount, $game_image_url, $time_period, $game_id, $redirect_url, $id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'edit_winner', "En çok kazanan güncellendi: $player_username - $game_name", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Kazanan bilgileri başarıyla güncellendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Güncelleme sırasında hata oluştu: " . $e->getMessage();
                }
                break;

            case 'add':
                try {
                    $game_name = $_POST['game_name'];
                    $player_username = $_POST['player_username'];
                    $winning_amount = $_POST['winning_amount'];
                    $game_image_url = $_POST['game_image_url'];
                    $time_period = $_POST['time_period'];
                    $game_id = $_POST['game_id'];
                    $redirect_url = $_POST['redirect_url'];
                    
                    $stmt = $db->prepare("INSERT INTO encokkazananlar (game_name, player_username, winning_amount, game_image_url, time_period, game_id, redirect_url, created_at) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$game_name, $player_username, $winning_amount, $game_image_url, $time_period, $game_id, $redirect_url]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'add_winner', "Yeni en çok kazanan eklendi: $player_username - $game_name", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Yeni kazanan başarıyla eklendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Ekleme sırasında hata oluştu: " . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $db->prepare("DELETE FROM encokkazananlar WHERE id = ?");
                    $stmt->execute([$id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'delete_winner', "En çok kazanan silindi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Kazanan başarıyla silindi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Silme sırasında hata oluştu: " . $e->getMessage();
                }
                break;
        }
    }
}

// Kazananları getir
try {
    $periods = ['daily', 'weekly', 'monthly'];
    $winners = [];

    foreach ($periods as $period) {
        $stmt = $db->prepare("SELECT * FROM encokkazananlar WHERE time_period = ? ORDER BY winning_amount DESC, player_username ASC");
        $stmt->execute([$period]);
        $winners[$period] = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $winners = ['daily' => [], 'weekly' => [], 'monthly' => []];
}

ob_start();
?>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-0">
                    <i class="bi bi-trophy me-2"></i>
                    En Çok Kazananlar Yönetimi
                </h1>
                <p class="mb-0 mt-2 opacity-75">Günlük, haftalık ve aylık en çok kazananları yönetin</p>
            </div>
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addWinnerModal">
                <i class="bi bi-plus-circle me-2"></i>
                Yeni Kazanan Ekle
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- İstatistikler -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-trophy"></i>
            </div>
            <div class="stat-number"><?php echo $totalWinners; ?></div>
            <div class="stat-label">Toplam Kazanan</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-number"><?php echo $dailyWinners; ?></div>
            <div class="stat-label">Günlük Kazanan</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-calendar-week"></i>
            </div>
            <div class="stat-number"><?php echo $weeklyWinners; ?></div>
            <div class="stat-label">Haftalık Kazanan</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-calendar-month"></i>
            </div>
            <div class="stat-number"><?php echo $monthlyWinners; ?></div>
            <div class="stat-label">Aylık Kazanan</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-number"><?php echo number_format($totalWinnings, 0, ',', '.'); ?></div>
            <div class="stat-label">Toplam Kazanç</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-number"><?php echo number_format($avgWinnings, 0, ',', '.'); ?></div>
            <div class="stat-label">Ortalama Kazanç</div>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo $_SESSION['success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo $_SESSION['error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-pills mb-4" id="winnersTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily-content" type="button" role="tab" aria-controls="daily-content" aria-selected="true">
                <i class="bi bi-calendar-day me-2"></i> Günlük Kazananlar
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="weekly-tab" data-bs-toggle="tab" data-bs-target="#weekly-content" type="button" role="tab" aria-controls="weekly-content" aria-selected="false">
                <i class="bi bi-calendar-week me-2"></i> Haftalık Kazananlar
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly-content" type="button" role="tab" aria-controls="monthly-content" aria-selected="false">
                <i class="bi bi-calendar-month me-2"></i> Aylık Kazananlar
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="winnersTabContent">
        <!-- Daily Winners Tab -->
        <div class="tab-pane fade show active" id="daily-content" role="tabpanel" aria-labelledby="daily-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-day me-2"></i> Günlük Kazananlar
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="dailyWinnersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Oyun Adı</th>
                                    <th>Oyuncu</th>
                                    <th>Kazanç</th>
                                    <th>Oyun Resmi</th>
                                    <th>Dönem</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($winners['daily'] as $winner): ?>
                                <tr>
                                    <td><?php echo $winner['id']; ?></td>
                                    <td><?php echo htmlspecialchars($winner['game_name']); ?></td>
                                    <td><?php echo htmlspecialchars($winner['player_username']); ?></td>
                                    <td><?php echo number_format($winner['winning_amount'], 0, ',', '.'); ?> ₺</td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($winner['game_image_url']); ?>" alt="Oyun" style="max-width: 60px; height: auto;">
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">Günlük</span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-winner" 
                                                data-id="<?php echo $winner['id']; ?>"
                                                data-game-name="<?php echo htmlspecialchars($winner['game_name']); ?>"
                                                data-player-username="<?php echo htmlspecialchars($winner['player_username']); ?>"
                                                data-winning-amount="<?php echo $winner['winning_amount']; ?>"
                                                data-game-image-url="<?php echo htmlspecialchars($winner['game_image_url']); ?>"
                                                data-time-period="<?php echo $winner['time_period']; ?>"
                                                data-game-id="<?php echo htmlspecialchars($winner['game_id']); ?>"
                                                data-redirect-url="<?php echo htmlspecialchars($winner['redirect_url']); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu kazananı silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $winner['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Winners Tab -->
        <div class="tab-pane fade" id="weekly-content" role="tabpanel" aria-labelledby="weekly-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-week me-2"></i> Haftalık Kazananlar
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="weeklyWinnersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Oyun Adı</th>
                                    <th>Oyuncu</th>
                                    <th>Kazanç</th>
                                    <th>Oyun Resmi</th>
                                    <th>Dönem</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($winners['weekly'] as $winner): ?>
                                <tr>
                                    <td><?php echo $winner['id']; ?></td>
                                    <td><?php echo htmlspecialchars($winner['game_name']); ?></td>
                                    <td><?php echo htmlspecialchars($winner['player_username']); ?></td>
                                    <td><?php echo number_format($winner['winning_amount'], 0, ',', '.'); ?> ₺</td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($winner['game_image_url']); ?>" alt="Oyun" style="max-width: 60px; height: auto;">
                                    </td>
                                    <td>
                                        <span class="badge bg-success">Haftalık</span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-winner" 
                                                data-id="<?php echo $winner['id']; ?>"
                                                data-game-name="<?php echo htmlspecialchars($winner['game_name']); ?>"
                                                data-player-username="<?php echo htmlspecialchars($winner['player_username']); ?>"
                                                data-winning-amount="<?php echo $winner['winning_amount']; ?>"
                                                data-game-image-url="<?php echo htmlspecialchars($winner['game_image_url']); ?>"
                                                data-time-period="<?php echo $winner['time_period']; ?>"
                                                data-game-id="<?php echo htmlspecialchars($winner['game_id']); ?>"
                                                data-redirect-url="<?php echo htmlspecialchars($winner['redirect_url']); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu kazananı silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $winner['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Winners Tab -->
        <div class="tab-pane fade" id="monthly-content" role="tabpanel" aria-labelledby="monthly-tab">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-month me-2"></i> Aylık Kazananlar
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="monthlyWinnersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Oyun Adı</th>
                                    <th>Oyuncu</th>
                                    <th>Kazanç</th>
                                    <th>Oyun Resmi</th>
                                    <th>Dönem</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($winners['monthly'] as $winner): ?>
                                <tr>
                                    <td><?php echo $winner['id']; ?></td>
                                    <td><?php echo htmlspecialchars($winner['game_name']); ?></td>
                                    <td><?php echo htmlspecialchars($winner['player_username']); ?></td>
                                    <td><?php echo number_format($winner['winning_amount'], 0, ',', '.'); ?> ₺</td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($winner['game_image_url']); ?>" alt="Oyun" style="max-width: 60px; height: auto;">
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">Aylık</span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-winner" 
                                                data-id="<?php echo $winner['id']; ?>"
                                                data-game-name="<?php echo htmlspecialchars($winner['game_name']); ?>"
                                                data-player-username="<?php echo htmlspecialchars($winner['player_username']); ?>"
                                                data-winning-amount="<?php echo $winner['winning_amount']; ?>"
                                                data-game-image-url="<?php echo htmlspecialchars($winner['game_image_url']); ?>"
                                                data-time-period="<?php echo $winner['time_period']; ?>"
                                                data-game-id="<?php echo htmlspecialchars($winner['game_id']); ?>"
                                                data-redirect-url="<?php echo htmlspecialchars($winner['redirect_url']); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu kazananı silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $winner['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Winner Modal -->
<div class="modal fade" id="addWinnerModal" tabindex="-1" aria-labelledby="addWinnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWinnerModalLabel">
                    <i class="bi bi-plus-circle me-2"></i> Yeni Kazanan Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="game_name" class="form-label">
                                <i class="bi bi-controller me-1"></i> Oyun Adı
                            </label>
                            <input type="text" class="form-control" id="game_name" name="game_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="player_username" class="form-label">
                                <i class="bi bi-person me-1"></i> Oyuncu Kullanıcı Adı
                            </label>
                            <input type="text" class="form-control" id="player_username" name="player_username" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="winning_amount" class="form-label">
                                <i class="bi bi-currency-dollar me-1"></i> Kazanç Miktarı
                            </label>
                            <input type="number" class="form-control" id="winning_amount" name="winning_amount" required>
                        </div>
                        <div class="col-md-6">
                            <label for="time_period" class="form-label">
                                <i class="bi bi-calendar me-1"></i> Dönem
                            </label>
                            <select class="form-select" id="time_period" name="time_period" required>
                                <option value="daily">Günlük</option>
                                <option value="weekly">Haftalık</option>
                                <option value="monthly">Aylık</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="game_id" class="form-label">
                                <i class="bi bi-hash me-1"></i> Oyun ID
                            </label>
                            <input type="text" class="form-control" id="game_id" name="game_id" required>
                        </div>
                        <div class="col-md-6">
                            <label for="redirect_url" class="form-label">
                                <i class="bi bi-link-45deg me-1"></i> Yönlendirme URL
                            </label>
                            <input type="text" class="form-control" id="redirect_url" name="redirect_url" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="game_image_url" class="form-label">
                            <i class="bi bi-image me-1"></i> Oyun Resmi URL
                        </label>
                        <input type="text" class="form-control" id="game_image_url" name="game_image_url" required>
                        <div class="mt-2">
                            <img id="game_image_preview" src="" alt="Oyun Resmi Önizleme" class="img-thumbnail d-none" style="max-height: 100px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-2"></i> Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Winner Modal -->
<div class="modal fade" id="editWinnerModal" tabindex="-1" aria-labelledby="editWinnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editWinnerModalLabel">
                    <i class="bi bi-pencil-square me-2"></i> Kazanan Düzenle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editWinnerForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_winner_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_game_name" class="form-label">
                                <i class="bi bi-controller me-1"></i> Oyun Adı
                            </label>
                            <input type="text" class="form-control" id="edit_game_name" name="game_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_player_username" class="form-label">
                                <i class="bi bi-person me-1"></i> Oyuncu Kullanıcı Adı
                            </label>
                            <input type="text" class="form-control" id="edit_player_username" name="player_username" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_winning_amount" class="form-label">
                                <i class="bi bi-currency-dollar me-1"></i> Kazanç Miktarı
                            </label>
                            <input type="number" class="form-control" id="edit_winning_amount" name="winning_amount" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_time_period" class="form-label">
                                <i class="bi bi-calendar me-1"></i> Dönem
                            </label>
                            <select class="form-select" id="edit_time_period" name="time_period" required>
                                <option value="daily">Günlük</option>
                                <option value="weekly">Haftalık</option>
                                <option value="monthly">Aylık</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_game_id" class="form-label">
                                <i class="bi bi-hash me-1"></i> Oyun ID
                            </label>
                            <input type="text" class="form-control" id="edit_game_id" name="game_id" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_redirect_url" class="form-label">
                                <i class="bi bi-link-45deg me-1"></i> Yönlendirme URL
                            </label>
                            <input type="text" class="form-control" id="edit_redirect_url" name="redirect_url" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_game_image_url" class="form-label">
                            <i class="bi bi-image me-1"></i> Oyun Resmi URL
                        </label>
                        <input type="text" class="form-control" id="edit_game_image_url" name="game_image_url" required>
                        <div class="mt-2">
                            <img id="edit_game_image_preview" src="" alt="Oyun Resmi Önizleme" class="img-thumbnail" style="max-height: 100px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i> Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #0d6efd;
    --secondary-color: #6c757d;
    --success-color: #198754;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #0dcaf0;
    --light-color: #f8f9fa;
    --dark-color: #212529;
    --accent-color: #0d6efd;
    --dark-bg: #1a1a1a;
    --dark-secondary: #2d2d2d;
    --dark-accent: #404040;
    --text-light: #ffffff;
    --text-muted: #6c757d;
    --border-color: #404040;
}

/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, var(--primary-color), #0056b3);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dashboard-header h1 {
    color: white;
    font-weight: 600;
}

.dashboard-header p {
    color: rgba(255, 255, 255, 0.8);
}

/* Stat Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--dark-secondary), var(--dark-accent));
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    color: white;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid var(--border-color);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--accent-color);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Navigation Pills */
.nav-pills .nav-link {
    color: var(--text-light);
    background-color: var(--dark-secondary);
    border: 1px solid var(--border-color);
    margin-right: 0.5rem;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    transition: all 0.2s ease;
}

.nav-pills .nav-link:hover {
    background-color: var(--dark-accent);
    border-color: var(--accent-color);
}

.nav-pills .nav-link.active {
    background-color: var(--accent-color);
    border-color: var(--accent-color);
    color: white;
}

/* Cards */
.card {
    background-color: var(--dark-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: var(--dark-accent);
    border-bottom: 1px solid var(--border-color);
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.5rem;
}

.card-header h5 {
    color: white;
    margin: 0;
    font-weight: 600;
}

.card-body {
    padding: 1.5rem;
}

/* Tables */
.table {
    color: var(--text-light);
    margin-bottom: 0;
}

.table thead th {
    background-color: var(--dark-accent);
    border-color: var(--border-color);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table tbody td {
    border-color: var(--border-color);
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: var(--dark-accent);
}

/* Buttons */
.btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
    border: none;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #146c43;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #b02a37;
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-secondary:hover {
    background-color: #5c636a;
    transform: translateY(-1px);
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Alerts */
.alert {
    border-radius: 8px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.alert-success {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
    border-left: 4px solid #198754;
}

.alert-danger {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border-left: 4px solid #dc3545;
}

/* Modals */
.modal-content {
    background-color: var(--dark-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
}

.modal-header {
    border-bottom: 1px solid var(--border-color);
    background-color: var(--dark-accent);
    border-radius: 12px 12px 0 0;
}

.modal-title {
    color: white;
    font-weight: 600;
}

.modal-body {
    color: var(--text-light);
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    background-color: var(--dark-accent);
    border-radius: 0 0 12px 12px;
}

/* Form Controls */
.form-control, .form-select {
    background-color: var(--dark-bg);
    border: 1px solid var(--border-color);
    color: var(--text-light);
    border-radius: 8px;
    padding: 0.75rem 1rem;
}

.form-control:focus, .form-select:focus {
    background-color: var(--dark-bg);
    border-color: var(--accent-color);
    color: var(--text-light);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.form-label {
    color: var(--text-light);
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-text {
    color: var(--text-muted);
    font-size: 0.875rem;
}

/* DataTable özel stilleri */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    color: white !important;
    margin-bottom: 10px;
}

.dataTables_wrapper .dataTables_length select {
    color: white !important;
    background-color: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    padding: 5px 10px;
    border-radius: 5px;
}

.dataTables_wrapper .dataTables_filter input {
    color: white !important;
    background-color: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    padding: 5px 10px;
    border-radius: 5px;
    margin-left: 5px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    color: white !important;
    background: var(--dark-secondary) !important;
    border: 1px solid var(--dark-accent) !important;
    border-radius: 5px;
    margin: 0 2px;
    padding: 5px 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--accent-color) !important;
    color: white !important;
    border: 1px solid var(--accent-color) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--dark-accent) !important;
    color: white !important;
}

/* Badges */
.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
}

.bg-primary {
    background-color: var(--primary-color) !important;
}

.bg-success {
    background-color: var(--success-color) !important;
}

.bg-warning {
    background-color: var(--warning-color) !important;
    color: var(--dark-color) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables with enhanced configuration
    const dataTableConfig = {
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        dom: '<"top"lf>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]],
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [
            {
                targets: -1, // Last column (actions)
                orderable: false,
                searchable: false
            }
        ]
    };

    // Initialize DataTables
    $('#dailyWinnersTable, #weeklyWinnersTable, #monthlyWinnersTable').DataTable(dataTableConfig);

    // Handle edit winner button clicks
    document.querySelectorAll('.edit-winner').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const gameName = this.getAttribute('data-game-name');
            const playerUsername = this.getAttribute('data-player-username');
            const winningAmount = this.getAttribute('data-winning-amount');
            const gameImageUrl = this.getAttribute('data-game-image-url');
            const timePeriod = this.getAttribute('data-time-period');
            const gameId = this.getAttribute('data-game-id');
            const redirectUrl = this.getAttribute('data-redirect-url');

            // Populate edit modal
            document.getElementById('edit_winner_id').value = id;
            document.getElementById('edit_game_name').value = gameName;
            document.getElementById('edit_player_username').value = playerUsername;
            document.getElementById('edit_winning_amount').value = winningAmount;
            document.getElementById('edit_game_image_url').value = gameImageUrl;
            document.getElementById('edit_time_period').value = timePeriod;
            document.getElementById('edit_game_id').value = gameId;
            document.getElementById('edit_redirect_url').value = redirectUrl;

            // Update image preview
            const preview = document.getElementById('edit_game_image_preview');
            if (gameImageUrl) {
                preview.src = gameImageUrl;
                preview.classList.remove('d-none');
            } else {
                preview.classList.add('d-none');
            }

            // Show modal
            const editModal = new bootstrap.Modal(document.getElementById('editWinnerModal'));
            editModal.show();
        });
    });

    // Handle image URL changes for preview
    document.getElementById('game_image_url').addEventListener('input', function() {
        const preview = document.getElementById('game_image_preview');
        if (this.value) {
            preview.src = this.value;
            preview.classList.remove('d-none');
        } else {
            preview.classList.add('d-none');
        }
    });

    document.getElementById('edit_game_image_url').addEventListener('input', function() {
        const preview = document.getElementById('edit_game_image_preview');
        if (this.value) {
            preview.src = this.value;
            preview.classList.remove('d-none');
        } else {
            preview.classList.add('d-none');
        }
    });
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 