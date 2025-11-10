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

// Connect to games database
try {
    $games_db = new PDO("mysql:host=localhost;dbname=u260321069_game1", "u260321069_game1", "sifrexnaEFVanavt88");
    $games_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $games_db->exec("SET NAMES utf8");
    
    // Add game_type column to customgames table if it doesn't exist
    $games_db->exec("
        ALTER TABLE customgames 
        ADD COLUMN IF NOT EXISTS game_type VARCHAR(255) DEFAULT NULL
    ");
} catch (PDOException $e) {
    die("Oyunlar veritabanına bağlantı hatası: " . $e->getMessage());
}

// İstatistikleri hesapla
try {
    $stmt = $games_db->query("SELECT COUNT(*) as totalCategories FROM categories");
    $totalCategories = $stmt->fetch()['totalCategories'];

    $stmt = $games_db->query("SELECT COUNT(*) as totalLiveCategories FROM livecategories");
    $totalLiveCategories = $stmt->fetch()['totalLiveCategories'];

    $stmt = $games_db->query("SELECT COUNT(*) as totalGames FROM customgames");
    $totalGames = $stmt->fetch()['totalGames'];

    $stmt = $games_db->query("SELECT COUNT(*) as popularGames FROM customgames WHERE is_popular = 1");
    $popularGames = $stmt->fetch()['popularGames'];

    $stmt = $games_db->query("SELECT COUNT(*) as todayCategories FROM categories WHERE DATE(created_at) = CURDATE()");
    $todayCategories = $stmt->fetch()['todayCategories'];

    $stmt = $games_db->query("SELECT COUNT(*) as todayGames FROM customgames WHERE DATE(created_at) = CURDATE()");
    $todayGames = $stmt->fetch()['todayGames'];

} catch (Exception $e) {
    $totalCategories = 0;
    $totalLiveCategories = 0;
    $totalGames = 0;
    $popularGames = 0;
    $todayCategories = 0;
    $todayGames = 0;
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                try {
                    $category_name = $_POST['category_name'];
                    $provider_id = $_POST['provider_id'];
                    $game_id = $_POST['game_id'];
                    $game_name = $_POST['game_name'];
                    $game_code = $_POST['game_code'];
                    $game_type = $_POST['game_type'];
                    $cover = $_POST['cover'];
                    $technology = $_POST['technology'];
                    $rtp = $_POST['rtp'];
                    $provider_game = $_POST['provider_game'];
                    $banner = $_POST['banner'];
                    
                    // Check if category already exists
                    $stmt = $games_db->prepare("SELECT COUNT(*) as count FROM categories WHERE category_name = ? AND game_id = ?");
                    $stmt->execute([$category_name, $game_id]);
                    $category_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                    
                    if ($category_exists) {
                        $_SESSION['error'] = "Bu kategori ve oyun kombinasyonu zaten mevcut.";
                    } else {
                        $stmt = $games_db->prepare("INSERT INTO categories (category_name, provider_id, game_id, game_name, game_code, game_type, cover, technology, rtp, provider_game, banner, created_at) 
                                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$category_name, $provider_id, $game_id, $game_name, $game_code, $game_type, $cover, $technology, $rtp, $provider_game, $banner]);

                        // Aktivite logu
                        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$_SESSION['admin_id'], 'add_game_category', "Yeni casino kategorisi eklendi: $category_name - $game_name", $_SERVER['REMOTE_ADDR']]);

                        $_SESSION['success'] = "Kategori başarıyla eklendi.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Kategori eklenirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'edit_category':
                try {
                    $id = $_POST['id'];
                    $category_name = $_POST['category_name'];
                    $provider_id = $_POST['provider_id'];
                    $game_id = $_POST['game_id'];
                    $game_name = $_POST['game_name'];
                    $game_code = $_POST['game_code'];
                    $game_type = $_POST['game_type'];
                    $cover = $_POST['cover'];
                    $technology = $_POST['technology'];
                    $rtp = $_POST['rtp'];
                    $provider_game = $_POST['provider_game'];
                    $banner = $_POST['banner'];
                    
                    $stmt = $games_db->prepare("UPDATE categories SET 
                        category_name = ?, 
                        provider_id = ?, 
                        game_id = ?, 
                        game_name = ?, 
                        game_code = ?, 
                        game_type = ?, 
                        cover = ?, 
                        technology = ?, 
                        rtp = ?, 
                        provider_game = ?, 
                        banner = ?,
                        updated_at = NOW()
                    WHERE id = ?");
                    $stmt->execute([$category_name, $provider_id, $game_id, $game_name, $game_code, $game_type, $cover, $technology, $rtp, $provider_game, $banner, $id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'edit_game_category', "Casino kategorisi güncellendi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Kategori başarıyla güncellendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Kategori güncellenirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'delete_category':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $games_db->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'delete_game_category', "Casino kategorisi silindi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Kategori başarıyla silindi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Kategori silinirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'add_live_category':
                try {
                    $category_name = $_POST['category_name'];
                    $provider_id = $_POST['provider_id'];
                    $game_id = $_POST['game_id'];
                    $game_name = $_POST['game_name'];
                    $game_code = $_POST['game_code'];
                    $game_type = $_POST['game_type'];
                    $cover = $_POST['cover'];
                    $technology = $_POST['technology'];
                    $rtp = $_POST['rtp'];
                    $provider_game = $_POST['provider_game'];
                    $banner = $_POST['banner'];
                    
                    // Check if category already exists
                    $stmt = $games_db->prepare("SELECT COUNT(*) as count FROM livecategories WHERE category_name = ? AND game_id = ?");
                    $stmt->execute([$category_name, $game_id]);
                    $category_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                    
                    if ($category_exists) {
                        $_SESSION['error'] = "Bu canlı kategori ve oyun kombinasyonu zaten mevcut.";
                    } else {
                        $stmt = $games_db->prepare("INSERT INTO livecategories (category_name, provider_id, game_id, game_name, game_code, game_type, cover, technology, rtp, provider_game, banner, created_at) 
                                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$category_name, $provider_id, $game_id, $game_name, $game_code, $game_type, $cover, $technology, $rtp, $provider_game, $banner]);

                        // Aktivite logu
                        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$_SESSION['admin_id'], 'add_live_category', "Yeni canlı casino kategorisi eklendi: $category_name - $game_name", $_SERVER['REMOTE_ADDR']]);

                        $_SESSION['success'] = "Canlı kategori başarıyla eklendi.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = "Canlı kategori eklenirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'edit_live_category':
                try {
                    $id = $_POST['id'];
                    $category_name = $_POST['category_name'];
                    $provider_id = $_POST['provider_id'];
                    $game_id = $_POST['game_id'];
                    $game_name = $_POST['game_name'];
                    $game_code = $_POST['game_code'];
                    $game_type = $_POST['game_type'];
                    $cover = $_POST['cover'];
                    $technology = $_POST['technology'];
                    $rtp = $_POST['rtp'];
                    $provider_game = $_POST['provider_game'];
                    $banner = $_POST['banner'];
                    
                    $stmt = $games_db->prepare("UPDATE livecategories SET 
                        category_name = ?, 
                        provider_id = ?, 
                        game_id = ?, 
                        game_name = ?, 
                        game_code = ?, 
                        game_type = ?, 
                        cover = ?, 
                        technology = ?, 
                        rtp = ?, 
                        provider_game = ?, 
                        banner = ?,
                        updated_at = NOW()
                    WHERE id = ?");
                    $stmt->execute([$category_name, $provider_id, $game_id, $game_name, $game_code, $game_type, $cover, $technology, $rtp, $provider_game, $banner, $id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'edit_live_category', "Canlı casino kategorisi güncellendi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Canlı kategori başarıyla güncellendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Canlı kategori güncellenirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'delete_live_category':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $games_db->prepare("DELETE FROM livecategories WHERE id = ?");
                    $stmt->execute([$id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'delete_live_category', "Canlı casino kategorisi silindi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Canlı kategori başarıyla silindi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Canlı kategori silinirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'add_game':
                try {
                    $game_id = $_POST['game_id'];
                    $game_name = $_POST['game_name'];
                    $provider = $_POST['provider'];
                    $category_name = $_POST['category_name'];
                    $banner = $_POST['banner'];
                    $game_type = $_POST['game_type'] ?? '';
                    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
                    
                    $stmt = $games_db->prepare("INSERT INTO customgames (game_id, game_name, banner, provider, category_name, game_type, is_popular, created_at) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$game_id, $game_name, $banner, $provider, $category_name, $game_type, $is_popular]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'add_popular_game', "Yeni popüler oyun eklendi: $game_name", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Oyun başarıyla eklendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Oyun eklenirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'edit_game':
                try {
                    $id = $_POST['id'];
                    $game_id = $_POST['game_id'];
                    $game_name = $_POST['game_name'];
                    $provider = $_POST['provider'];
                    $category_name = $_POST['category_name'];
                    $banner = $_POST['banner'];
                    $game_type = $_POST['game_type'] ?? '';
                    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
                    
                    $stmt = $games_db->prepare("UPDATE customgames SET game_id = ?, game_name = ?, banner = ?, 
                                               provider = ?, category_name = ?, game_type = ?, is_popular = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$game_id, $game_name, $banner, $provider, $category_name, $game_type, $is_popular, $id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'edit_popular_game', "Popüler oyun güncellendi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Oyun başarıyla güncellendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Oyun güncellenirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'delete_game':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $games_db->prepare("DELETE FROM customgames WHERE id = ?");
                    $stmt->execute([$id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'delete_popular_game', "Popüler oyun silindi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Oyun başarıyla silindi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Oyun silinirken hata oluştu: " . $e->getMessage();
                }
                break;
                
            case 'toggle_popular':
                try {
                    $id = $_POST['id'];
                    
                    $stmt = $games_db->prepare("UPDATE customgames SET is_popular = NOT is_popular, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);

                    // Aktivite logu
                    $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$_SESSION['admin_id'], 'toggle_popular_game', "Oyun popülerlik durumu değiştirildi: ID $id", $_SERVER['REMOTE_ADDR']]);

                    $_SESSION['success'] = "Oyun popülerlik durumu güncellendi.";
                } catch (Exception $e) {
                    $_SESSION['error'] = "Oyun popülerlik durumu güncellenirken hata oluştu: " . $e->getMessage();
                }
                break;
        }
    }
}

// Verileri getir
try {
    $stmt = $games_db->query("SELECT * FROM categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $games_db->query("SELECT * FROM livecategories ORDER BY category_name ASC");
    $live_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $games_db->query("SELECT * FROM customgames ORDER BY game_name ASC");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    $live_categories = [];
    $games = [];
}

ob_start();
?>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-0">
                    <i class="bi bi-controller me-2"></i>
                    Oyunlar Menü Yönetimi
                </h1>
                <p class="mb-0 mt-2 opacity-75">Casino kategorileri, canlı casino kategorileri ve popüler oyunları yönetin</p>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <!-- İstatistikler -->
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
    <ul class="nav nav-pills mb-4" id="gameManagementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-content" type="button" role="tab" aria-controls="categories-content" aria-selected="true">
                <i class="bi bi-grid me-2"></i> Casino Kategorileri
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="live-categories-tab" data-bs-toggle="tab" data-bs-target="#live-categories-content" type="button" role="tab" aria-controls="live-categories-content" aria-selected="false">
                <i class="bi bi-camera-video me-2"></i> Canlı Casino Kategorileri
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="popular-games-tab" data-bs-toggle="tab" data-bs-target="#popular-games-content" type="button" role="tab" aria-controls="popular-games-content" aria-selected="false">
                <i class="bi bi-star me-2"></i> Popüler Casino Oyunları
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="gameManagementTabContent">
        <!-- Categories Tab -->
        <div class="tab-pane fade show active" id="categories-content" role="tabpanel" aria-labelledby="categories-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-grid me-1"></i> Casino Kategorileri
                    </h6>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-circle"></i> Yeni Kategori Ekle
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="categoriesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kategori Adı</th>
                                    <th>Oyun Adı</th>
                                    <th>Resim</th>
                                    <th>Sağlayıcı</th>
                                    <th>Oyun Tipi</th>
                                    <th>RTP</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): 
                                ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['game_name']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($category['banner']); ?>" alt="<?php echo htmlspecialchars($category['game_name']); ?>" style="max-width: 80px;">
                                    </td>
                                    <td><?php echo htmlspecialchars($category['provider_game']); ?></td>
                                    <td><?php echo htmlspecialchars($category['game_type']); ?></td>
                                    <td><?php echo $category['rtp']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-category" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-category-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                data-provider-id="<?php echo htmlspecialchars($category['provider_id']); ?>"
                                                data-game-id="<?php echo htmlspecialchars($category['game_id']); ?>"
                                                data-game-name="<?php echo htmlspecialchars($category['game_name']); ?>"
                                                data-game-code="<?php echo htmlspecialchars($category['game_code']); ?>"
                                                data-game-type="<?php echo htmlspecialchars($category['game_type']); ?>"
                                                data-cover="<?php echo htmlspecialchars($category['cover']); ?>"
                                                data-technology="<?php echo htmlspecialchars($category['technology']); ?>"
                                                data-rtp="<?php echo $category['rtp']; ?>"
                                                data-provider-game="<?php echo htmlspecialchars($category['provider_game']); ?>"
                                                data-banner="<?php echo htmlspecialchars($category['banner']); ?>">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
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
        
        <!-- Live Categories Tab -->
        <div class="tab-pane fade" id="live-categories-content" role="tabpanel" aria-labelledby="live-categories-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-camera-video me-1"></i> Canlı Casino Kategorileri
                    </h6>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLiveCategoryModal">
                        <i class="bi bi-plus-circle"></i> Yeni Canlı Kategori Ekle
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="liveCategoriesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kategori Adı</th>
                                    <th>Oyun Adı</th>
                                    <th>Resim</th>
                                    <th>Sağlayıcı</th>
                                    <th>Oyun Tipi</th>
                                    <th>RTP</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($live_categories as $category): 
                                ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['game_name']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($category['banner']); ?>" alt="<?php echo htmlspecialchars($category['game_name']); ?>" style="max-width: 80px;">
                                    </td>
                                    <td><?php echo htmlspecialchars($category['provider_game']); ?></td>
                                    <td><?php echo htmlspecialchars($category['game_type']); ?></td>
                                    <td><?php echo $category['rtp']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-live-category" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-category-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                data-provider-id="<?php echo htmlspecialchars($category['provider_id']); ?>"
                                                data-game-id="<?php echo htmlspecialchars($category['game_id']); ?>"
                                                data-game-name="<?php echo htmlspecialchars($category['game_name']); ?>"
                                                data-game-code="<?php echo htmlspecialchars($category['game_code']); ?>"
                                                data-game-type="<?php echo htmlspecialchars($category['game_type']); ?>"
                                                data-cover="<?php echo htmlspecialchars($category['cover']); ?>"
                                                data-technology="<?php echo htmlspecialchars($category['technology']); ?>"
                                                data-rtp="<?php echo $category['rtp']; ?>"
                                                data-provider-game="<?php echo htmlspecialchars($category['provider_game']); ?>"
                                                data-banner="<?php echo htmlspecialchars($category['banner']); ?>">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu canlı kategoriyi silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_live_category">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
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
        
        <!-- Popular Games Tab -->
        <div class="tab-pane fade" id="popular-games-content" role="tabpanel" aria-labelledby="popular-games-tab">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-star me-1"></i> Popüler Casino Oyunları
                    </h6>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPopularGameModal">
                        <i class="bi bi-plus-circle"></i> Yeni Popüler Oyun Ekle
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="popularGamesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Oyun Adı</th>
                                    <th>Resim</th>
                                    <th>Sağlayıcı</th>
                                    <th>Kategori</th>
                                    <th>Oyun Tipi</th>
                                    <th>Popüler</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($games as $game): ?>
                                <tr>
                                    <td><?php echo $game['id']; ?></td>
                                    <td><?php echo htmlspecialchars($game['game_name']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($game['banner']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" style="max-width: 80px;">
                                    </td>
                                    <td><?php echo htmlspecialchars($game['provider']); ?></td>
                                    <td><?php echo htmlspecialchars($game['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($game['game_type'] ?: 'Belirtilmemiş'); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_popular">
                                            <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $game['is_popular'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                <i class="bi <?php echo $game['is_popular'] ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                                <?php echo $game['is_popular'] ? 'Evet' : 'Hayır'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-game" 
                                                data-id="<?php echo $game['id']; ?>"
                                                data-game-id="<?php echo htmlspecialchars($game['game_id']); ?>"
                                                data-game-name="<?php echo htmlspecialchars($game['game_name']); ?>"
                                                data-banner="<?php echo htmlspecialchars($game['banner']); ?>"
                                                data-provider="<?php echo htmlspecialchars($game['provider']); ?>"
                                                data-category-name="<?php echo htmlspecialchars($game['category_name']); ?>"
                                                data-game-type="<?php echo htmlspecialchars($game['game_type']); ?>"
                                                data-is-popular="<?php echo $game['is_popular']; ?>">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Bu oyunu silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_game">
                                            <input type="hidden" name="id" value="<?php echo $game['id']; ?>">
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="addCategoryModalLabel"><i class="bi bi-plus-circle"></i> Yeni Casino Kategori Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_category">
                        
                        <div class="mb-3">
                            <label for="category_search" class="form-label">Oyun Ara</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="category_search" placeholder="Oyun adını yazın...">
                            <div class="form-text text-muted">Oyun adını yazın ve listeden seçin</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category_name" class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="category_name" name="category_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="game_id_category" class="form-label">Oyun ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="game_id_category" name="game_id" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="provider_id" class="form-label">Sağlayıcı ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="provider_id" name="provider_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="game_name_category" class="form-label">Oyun Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="game_name_category" name="game_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="game_code" class="form-label">Oyun Kodu</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="game_code" name="game_code" required>
                            </div>
                            <div class="col-md-6">
                                <label for="game_type_category" class="form-label">Oyun Tipi</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="game_type_category" name="game_type" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="provider_game" class="form-label">Sağlayıcı Oyun</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="provider_game" name="provider_game" required>
                            </div>
                            <div class="col-md-6">
                                <label for="rtp" class="form-label">RTP (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light border-secondary" id="rtp" name="rtp" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="technology" class="form-label">Teknoloji</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="technology" name="technology">
                            </div>
                            <div class="col-md-6">
                                <label for="cover" class="form-label">Kapak URL</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="cover" name="cover">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="banner_category" class="form-label">Banner URL</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="banner_category" name="banner" required>
                            <div class="mt-2">
                                <img id="banner_category_preview" src="" alt="Banner Önizleme" class="img-thumbnail bg-dark d-none" style="max-height: 100px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg"></i> Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="editCategoryModalLabel">
                        <i class="bi bi-pencil-square"></i> Casino Kategori Düzenle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editCategoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" name="id" id="edit_category_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_category_name" class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_category_name" name="category_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_game_id_category" class="form-label">Oyun ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_game_id_category" name="game_id" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_provider_id" class="form-label">Sağlayıcı ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_provider_id" name="provider_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_game_name_category" class="form-label">Oyun Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_game_name_category" name="game_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_game_code" class="form-label">Oyun Kodu</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_game_code" name="game_code" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_game_type_category" class="form-label">Oyun Tipi</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_game_type_category" name="game_type" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_provider_game" class="form-label">Sağlayıcı Oyun</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_provider_game" name="provider_game" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_rtp" class="form-label">RTP (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light border-secondary" id="edit_rtp" name="rtp" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_technology" class="form-label">Teknoloji</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_technology" name="technology">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_cover" class="form-label">Kapak URL</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_cover" name="cover">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_banner_category" class="form-label">Banner URL</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_banner_category" name="banner" required>
                            <div class="mt-2">
                                <img id="edit_banner_category_preview" src="" alt="Banner Önizleme" class="img-thumbnail bg-dark" style="max-height: 100px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Live Category Modal -->
    <div class="modal fade" id="addLiveCategoryModal" tabindex="-1" aria-labelledby="addLiveCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="addLiveCategoryModalLabel"><i class="bi bi-plus-circle"></i> Yeni Canlı Casino Kategori Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_live_category">
                        
                        <div class="mb-3">
                            <label for="live_category_search" class="form-label">Oyun Ara</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="live_category_search" placeholder="Oyun adını yazın...">
                            <div class="form-text text-muted">Oyun adını yazın ve listeden seçin</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="live_category_name" class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_category_name" name="category_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="live_game_id_category" class="form-label">Oyun ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_game_id_category" name="game_id" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="live_provider_id" class="form-label">Sağlayıcı ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_provider_id" name="provider_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="live_game_name_category" class="form-label">Oyun Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_game_name_category" name="game_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="live_game_code" class="form-label">Oyun Kodu</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_game_code" name="game_code" required>
                            </div>
                            <div class="col-md-6">
                                <label for="live_game_type_category" class="form-label">Oyun Tipi</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_game_type_category" name="game_type" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="live_provider_game" class="form-label">Sağlayıcı Oyun</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_provider_game" name="provider_game" required>
                            </div>
                            <div class="col-md-6">
                                <label for="live_rtp" class="form-label">RTP (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light border-secondary" id="live_rtp" name="rtp" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="live_technology" class="form-label">Teknoloji</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_technology" name="technology">
                            </div>
                            <div class="col-md-6">
                                <label for="live_cover" class="form-label">Kapak URL</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="live_cover" name="cover">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="live_banner_category" class="form-label">Banner URL</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="live_banner_category" name="banner" required>
                            <div class="mt-2">
                                <img id="live_banner_category_preview" src="" alt="Banner Önizleme" class="img-thumbnail bg-dark d-none" style="max-height: 100px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg"></i> Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Live Category Modal -->
    <div class="modal fade" id="editLiveCategoryModal" tabindex="-1" aria-labelledby="editLiveCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="editLiveCategoryModalLabel">
                        <i class="bi bi-pencil-square"></i> Canlı Casino Kategori Düzenle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editLiveCategoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_live_category">
                        <input type="hidden" name="id" id="edit_live_category_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_live_category_name" class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_category_name" name="category_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_live_game_id_category" class="form-label">Oyun ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_game_id_category" name="game_id" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_live_provider_id" class="form-label">Sağlayıcı ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_provider_id" name="provider_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_live_game_name_category" class="form-label">Oyun Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_game_name_category" name="game_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_live_game_code" class="form-label">Oyun Kodu</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_game_code" name="game_code" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_live_game_type_category" class="form-label">Oyun Tipi</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_game_type_category" name="game_type" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_live_provider_game" class="form-label">Sağlayıcı Oyun</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_provider_game" name="provider_game" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_live_rtp" class="form-label">RTP (%)</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control bg-dark text-light border-secondary" id="edit_live_rtp" name="rtp" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_live_technology" class="form-label">Teknoloji</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_technology" name="technology">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_live_cover" class="form-label">Kapak URL</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_cover" name="cover">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_live_banner_category" class="form-label">Banner URL</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_live_banner_category" name="banner" required>
                            <div class="mt-2">
                                <img id="edit_live_banner_category_preview" src="" alt="Banner Önizleme" class="img-thumbnail bg-dark" style="max-height: 100px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Popular Game Modal -->
    <div class="modal fade" id="addPopularGameModal" tabindex="-1" aria-labelledby="addPopularGameModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="addPopularGameModalLabel"><i class="bi bi-plus-circle"></i> Yeni Popüler Oyun Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_game">
                        <input type="hidden" name="is_popular" value="1">
                        
                        <div class="mb-3">
                            <label for="popular_game_search" class="form-label">Oyun Ara</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary" id="popular_game_search" placeholder="Oyun adını yazın...">
                            <div class="form-text text-muted">Oyun adını yazın ve listeden seçin</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="popular_game_id" class="form-label">Oyun ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="popular_game_id" name="game_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="popular_game_name" class="form-label">Oyun Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="popular_game_name" name="game_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="popular_provider" class="form-label">Sağlayıcı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="popular_provider" name="provider" required>
                            </div>
                            <div class="col-md-6">
                                <label for="popular_category_name" class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="popular_category_name" name="category_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="popular_game_type" class="form-label">Oyun Tipi</label>
                                <select class="form-select bg-dark text-light border-secondary" id="popular_game_type" name="game_type">
                                    <option value="slot">Slot</option>
                                    <option value="live">Canlı Casino</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="popular_banner" class="form-label">Banner URL</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="popular_banner" name="banner" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="mt-2">
                                <img id="popular_banner_preview" src="" alt="Banner Önizleme" class="img-thumbnail bg-dark d-none" style="max-height: 100px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg"></i> Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Popular Game Modal -->
    <div class="modal fade" id="editGameModal" tabindex="-1" aria-labelledby="editGameModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="editGameModalLabel">
                        <i class="bi bi-pencil-square"></i> Oyun Düzenle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editGameForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_game">
                        <input type="hidden" name="id" id="edit_game_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_game_id_field" class="form-label">Oyun ID</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_game_id_field" name="game_id" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_game_name_field" class="form-label">Oyun Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_game_name_field" name="game_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_provider" class="form-label">Sağlayıcı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_provider" name="provider" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_category_name_field" class="form-label">Kategori Adı</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_category_name_field" name="category_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_game_type_field" class="form-label">Oyun Tipi</label>
                                <select class="form-select bg-dark text-light border-secondary" id="edit_game_type_field" name="game_type">
                                    <option value="slot">Slot</option>
                                    <option value="live">Canlı Casino</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_banner" class="form-label">Banner URL</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="edit_banner" name="banner" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_is_popular" name="is_popular">
                                <label class="form-check-label" for="edit_is_popular">Popüler Oyun</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="mt-2">
                                <img id="edit_banner_preview" src="" alt="Banner Önizleme" class="img-thumbnail bg-dark" style="max-height: 100px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Güncelle
                        </button>
                    </div>
                </form>
            </div>
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

/* Search results styling */
.search-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
    z-index: 99998;
    display: none;
    animation: fadeIn 0.2s ease-out;
}

.search-overlay.active {
    display: block;
}

.game-search-results {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    max-width: 95%;
    max-height: 80vh;
    background-color: #222;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    z-index: 99999;
    overflow-y: auto;
    display: none;
    animation: zoomIn 0.2s ease-out;
    color: #fff;
}

.game-search-results.active {
    display: block;
}

.game-search-results .close-button {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 24px;
    color: #ccc;
    cursor: pointer;
    padding: 5px;
    line-height: 1;
}

.game-search-results .close-button:hover {
    color: #fff;
}

.search-results-content {
    padding: 20px;
}

.search-results-content .loading,
.search-results-content .no-results,
.search-results-content .error {
    text-align: center;
    padding: 20px;
    font-size: 16px;
}

.search-results-content .error {
    color: #dc3545;
}

.game-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.game-item {
    padding: 12px 15px;
    border-bottom: 1px solid #333;
    cursor: pointer;
    transition: background-color 0.2s;
}

.game-item:last-child {
    border-bottom: none;
}

.game-item:hover {
    background-color: #333;
}

.game-info {
    display: flex;
    flex-direction: column;
}

.game-name {
    font-weight: bold;
    margin-bottom: 5px;
    font-size: 1rem;
}

.game-provider {
    font-size: 0.85em;
    color: #aaa;
    margin-bottom: 2px;
}

.game-details {
    font-size: 0.85em;
    color: #999;
    margin-bottom: 2px;
}

.game-type {
    font-size: 0.85em;
    color: #17a2b8;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes zoomIn {
    from { 
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.95);
    }
    to { 
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    $('#categoriesTable, #liveCategoriesTable, #popularGamesTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        dom: '<"top"lf>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tümü"]]
    });
    
    // Setup category search functionality
    setupCategorySearch('#category_search', 'add');
    setupCategorySearch('#live_category_search', 'live');
    setupCategorySearch('#popular_game_search', 'add');
    
    // Handle category edit button
    const editCategoryModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    
    document.querySelectorAll('.edit-category').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const categoryName = this.getAttribute('data-category-name');
            const providerId = this.getAttribute('data-provider-id');
            const gameId = this.getAttribute('data-game-id');
            const gameName = this.getAttribute('data-game-name');
            const gameCode = this.getAttribute('data-game-code');
            const gameType = this.getAttribute('data-game-type');
            const cover = this.getAttribute('data-cover');
            const technology = this.getAttribute('data-technology');
            const rtp = this.getAttribute('data-rtp');
            const providerGame = this.getAttribute('data-provider-game');
            const banner = this.getAttribute('data-banner');
            
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = categoryName;
            document.getElementById('edit_provider_id').value = providerId;
            document.getElementById('edit_game_id_category').value = gameId;
            document.getElementById('edit_game_name_category').value = gameName;
            document.getElementById('edit_game_code').value = gameCode;
            document.getElementById('edit_game_type_category').value = gameType;
            document.getElementById('edit_cover').value = cover;
            document.getElementById('edit_technology').value = technology;
            document.getElementById('edit_rtp').value = rtp;
            document.getElementById('edit_provider_game').value = providerGame;
            document.getElementById('edit_banner_category').value = banner;
            
            // Update banner preview
            const bannerPreview = document.getElementById('edit_banner_category_preview');
            if (banner) {
                bannerPreview.src = banner;
                bannerPreview.classList.remove('d-none');
            } else {
                bannerPreview.classList.add('d-none');
            }
            
            editCategoryModal.show();
        });
    });
    
    // Handle live category edit button
    const editLiveCategoryModal = new bootstrap.Modal(document.getElementById('editLiveCategoryModal'));
    
    document.querySelectorAll('.edit-live-category').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const categoryName = this.getAttribute('data-category-name');
            const providerId = this.getAttribute('data-provider-id');
            const gameId = this.getAttribute('data-game-id');
            const gameName = this.getAttribute('data-game-name');
            const gameCode = this.getAttribute('data-game-code');
            const gameType = this.getAttribute('data-game-type');
            const cover = this.getAttribute('data-cover');
            const technology = this.getAttribute('data-technology');
            const rtp = this.getAttribute('data-rtp');
            const providerGame = this.getAttribute('data-provider-game');
            const banner = this.getAttribute('data-banner');
            
            document.getElementById('edit_live_category_id').value = id;
            document.getElementById('edit_live_category_name').value = categoryName;
            document.getElementById('edit_live_provider_id').value = providerId;
            document.getElementById('edit_live_game_id_category').value = gameId;
            document.getElementById('edit_live_game_name_category').value = gameName;
            document.getElementById('edit_live_game_code').value = gameCode;
            document.getElementById('edit_live_game_type_category').value = gameType;
            document.getElementById('edit_live_cover').value = cover;
            document.getElementById('edit_live_technology').value = technology;
            document.getElementById('edit_live_rtp').value = rtp;
            document.getElementById('edit_live_provider_game').value = providerGame;
            document.getElementById('edit_live_banner_category').value = banner;
            
            // Update banner preview
            const bannerPreview = document.getElementById('edit_live_banner_category_preview');
            if (banner) {
                bannerPreview.src = banner;
                bannerPreview.classList.remove('d-none');
            } else {
                bannerPreview.classList.add('d-none');
            }
            
            editLiveCategoryModal.show();
        });
    });
    
    // Banner URL change handler for category
    document.getElementById('banner_category').addEventListener('input', function() {
        const bannerPreview = document.getElementById('banner_category_preview');
        if (this.value) {
            bannerPreview.src = this.value;
            bannerPreview.classList.remove('d-none');
        } else {
            bannerPreview.classList.add('d-none');
        }
    });
    
    document.getElementById('edit_banner_category').addEventListener('input', function() {
        const bannerPreview = document.getElementById('edit_banner_category_preview');
        if (this.value) {
            bannerPreview.src = this.value;
            bannerPreview.classList.remove('d-none');
        } else {
            bannerPreview.classList.add('d-none');
        }
    });
    
    // Banner URL change handler for live category
    document.getElementById('live_banner_category').addEventListener('input', function() {
        const bannerPreview = document.getElementById('live_banner_category_preview');
        if (this.value) {
            bannerPreview.src = this.value;
            bannerPreview.classList.remove('d-none');
        } else {
            bannerPreview.classList.add('d-none');
        }
    });
    
    document.getElementById('edit_live_banner_category').addEventListener('input', function() {
        const bannerPreview = document.getElementById('edit_live_banner_category_preview');
        if (this.value) {
            bannerPreview.src = this.value;
            bannerPreview.classList.remove('d-none');
        } else {
            bannerPreview.classList.add('d-none');
        }
    });
    
    // Handle game edit button
    const editGameModal = new bootstrap.Modal(document.getElementById('editGameModal'));
    
    document.querySelectorAll('.edit-game').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const gameId = this.getAttribute('data-game-id');
            const gameName = this.getAttribute('data-game-name');
            const banner = this.getAttribute('data-banner');
            const provider = this.getAttribute('data-provider');
            const categoryName = this.getAttribute('data-category-name');
            const gameType = this.getAttribute('data-game-type');
            const isPopular = this.getAttribute('data-is-popular') === "1";
            
            document.getElementById('edit_game_id').value = id;
            document.getElementById('edit_game_id_field').value = gameId;
            document.getElementById('edit_game_name_field').value = gameName;
            document.getElementById('edit_banner').value = banner;
            document.getElementById('edit_provider').value = provider;
            document.getElementById('edit_category_name_field').value = categoryName;
            
            // Set game type select
            const gameTypeSelect = document.getElementById('edit_game_type_field');
            for (let i = 0; i < gameTypeSelect.options.length; i++) {
                if (gameTypeSelect.options[i].value === gameType) {
                    gameTypeSelect.selectedIndex = i;
                    break;
                }
            }
            
            // Set is popular checkbox
            document.getElementById('edit_is_popular').checked = isPopular;
            
            // Update banner preview
            const bannerPreview = document.getElementById('edit_banner_preview');
            if (banner) {
                bannerPreview.src = banner;
                bannerPreview.classList.remove('d-none');
            } else {
                bannerPreview.classList.add('d-none');
            }
            
            editGameModal.show();
        });
    });
    
    // Banner URL change handler for popular game
    document.getElementById('popular_banner').addEventListener('input', function() {
        const bannerPreview = document.getElementById('popular_banner_preview');
        if (this.value) {
            bannerPreview.src = this.value;
            bannerPreview.classList.remove('d-none');
        } else {
            bannerPreview.classList.add('d-none');
        }
    });
    
    document.getElementById('edit_banner').addEventListener('input', function() {
        const bannerPreview = document.getElementById('edit_banner_preview');
        if (this.value) {
            bannerPreview.src = this.value;
            bannerPreview.classList.remove('d-none');
        } else {
            bannerPreview.classList.add('d-none');
        }
    });
    
    // Category search functionality
    function setupCategorySearch(inputSelector, formType) {
        const $searchInput = $(inputSelector);
        const $gameTypeInput = $searchInput.closest('form').find('input[name="game_type"]');
        let $searchResults, $overlay;

        // Create search results container and overlay if they don't exist
        if (!$('#search-results-container').length) {
            $searchResults = $('<div id="search-results-container" class="game-search-results"></div>');
            $overlay = $('<div id="search-overlay" class="search-overlay"></div>');
            $('body').append($overlay).append($searchResults);
            
            // Add close button to search results
            const $closeButton = $('<span class="close-button">&times;</span>');
            $searchResults.append($closeButton);
            
            // Add content container
            $searchResults.append('<div class="search-results-content"></div>');
        } else {
            $searchResults = $('#search-results-container');
            $overlay = $('#search-overlay');
        }

        // Function to close search results
        const closeSearchResults = () => {
            $searchResults.removeClass('active');
            $overlay.removeClass('active');
        };

        // Event to close search results when clicking the close button
        $searchResults.on('click', '.close-button', closeSearchResults);
        
        // Event to close search results when clicking outside
        $overlay.on('click', closeSearchResults);
        
        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $searchResults.hasClass('active')) {
                closeSearchResults();
            }
        });

        // Debounce function to limit API requests
        let searchTimeout;
        
        $searchInput.on('input', function() {
            const searchTerm = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 2) {
                closeSearchResults();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                // Show loading state
                $searchResults.addClass('active');
                $overlay.addClass('active');
                $searchResults.find('.search-results-content').html('<div class="loading">Searching...</div>');
                
                // Fetch search results
                $.ajax({
                    url: 'ajax/search_games.php',
                    method: 'POST',
                    data: {
                        search_term: searchTerm,
                        form_type: formType
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.results.length > 0) {
                                let resultsHtml = '<ul class="game-list">';
                                
                                response.results.forEach(game => {
                                    resultsHtml += `
                                        <li class="game-item" 
                                            data-id="${game.id}" 
                                            data-name="${game.game_name}" 
                                            data-banner="${game.banner || ''}" 
                                            data-provider="${game.provider}"
                                            data-provider-game="${game.provider_game || ''}"
                                            data-game-code="${game.game_code || ''}"
                                            data-provider-id="${game.provider_id || ''}"
                                            data-rtp="${game.rtp || ''}"
                                            data-technology="${game.technology || ''}"
                                            data-cover="${game.cover || ''}"
                                            data-game-type="${game.game_type}">
                                            <div class="game-info">
                                                <span class="game-name">${game.game_name}</span>
                                                <span class="game-provider">Provider: ${game.provider}</span>
                                                <span class="game-details">ID: ${game.id} | Code: ${game.game_code || 'N/A'}</span>
                                                <span class="game-type">${game.game_type === 'live' ? 'Live Casino' : 'Slot'} | RTP: ${game.rtp || 'N/A'}</span>
                                            </div>
                                        </li>
                                    `;
                                });
                                
                                resultsHtml += '</ul>';
                                $searchResults.find('.search-results-content').html(resultsHtml);
                            } else {
                                $searchResults.find('.search-results-content').html('<div class="no-results">No games found</div>');
                            }
                        } else {
                            $searchResults.find('.search-results-content').html(`<div class="error">${response.message || 'Error fetching results'}</div>`);
                        }
                    },
                    error: function() {
                        $searchResults.find('.search-results-content').html('<div class="error">Failed to fetch results</div>');
                    }
                });
            }, 300);
        });

        // Event delegation for game selection
        $searchResults.on('click', '.game-item', function() {
            const $this = $(this);
            const gameId = $this.data('id');
            const gameName = $this.data('name');
            const gameProvider = $this.data('provider');
            const gameType = $this.data('game-type');
            const gameBanner = $this.data('banner');
            const gameCode = $this.data('game-code');
            const providerId = $this.data('provider-id');
            const providerGame = $this.data('provider-game');
            const rtp = $this.data('rtp');
            const technology = $this.data('technology');
            const cover = $this.data('cover');
            
            // Update form fields
            const $form = $searchInput.closest('form');
            
            $form.find('input[name="game_id"]').val(gameId);
            $form.find('input[name="game_name"]').val(gameName);
            $searchInput.val(gameName);
            
            // Set the game_type field
            $gameTypeInput.val(gameType);
            
            // Set additional fields if they exist in the form
            if ($form.find('input[name="game_code"]').length) {
                $form.find('input[name="game_code"]').val(gameCode);
            }
            
            if ($form.find('input[name="provider_id"]').length) {
                $form.find('input[name="provider_id"]').val(providerId);
            }
            
            if ($form.find('input[name="provider_game"]').length) {
                $form.find('input[name="provider_game"]').val(providerGame);
            }
            
            if ($form.find('input[name="rtp"]').length) {
                $form.find('input[name="rtp"]').val(rtp);
            }
            
            if ($form.find('input[name="technology"]').length) {
                $form.find('input[name="technology"]').val(technology);
            }
            
            if ($form.find('input[name="cover"]').length) {
                $form.find('input[name="cover"]').val(cover);
            }
            
            // If there are other fields to update
            if ($form.find('input[name="provider"]').length) {
                $form.find('input[name="provider"]').val(gameProvider);
            }
            
            if ($form.find('input[name="banner"]').length && gameBanner) {
                $form.find('input[name="banner"]').val(gameBanner);
                
                // Update banner preview if it exists
                const bannerId = $form.find('input[name="banner"]').attr('id');
                if (bannerId) {
                    const previewId = bannerId + '_preview';
                    const $preview = $('#' + previewId);
                    if ($preview.length) {
                        $preview.attr('src', gameBanner);
                        $preview.removeClass('d-none');
                    }
                }
            }
            
            // Close the search results
            closeSearchResults();
        });
    }
});
</script>

<?php
$pageContent = ob_get_clean();
require_once 'includes/layout.php';
?> 