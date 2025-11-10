<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/database.php';

// Set page title
$pageTitle = "Slider Alt Bölüm Düzen";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Simplified permission check for super admin
if ($_SESSION['role_id'] != 1) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// Handle slider item add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $imageUrl = trim($_POST['resim_url']);
    $linkUrl = trim($_POST['link_url']);
    
    if (empty($imageUrl) || empty($linkUrl)) {
        $error = "Resim URL ve Link URL alanları boş olamaz.";
    } else {
        try {
            // Check if the image URL is valid and accessible
            $headers = @get_headers($imageUrl);
            if (!$headers || strpos($headers[0], '200') === false) {
                $error = "Geçersiz URL veya erişilemeyen resim.";
            } else {
                // Set default values for other fields
                $stmt = $db->prepare("INSERT INTO slideraltbolumduzen (resim_url, link_url, alt_text, tip, sira, durum) VALUES (?, ?, ?, 'normal', (SELECT COALESCE(MAX(sira), 0) + 1 FROM slideraltbolumduzen t), 1)");
                $stmt->execute([$imageUrl, $linkUrl, 'Slider Image']);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'create', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Yeni slider öğesi eklendi: $imageUrl"]);
                
                $message = "Slider öğesi başarıyla eklendi.";
            }
        } catch (PDOException $e) {
            $error = "Slider öğesi eklenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Handle slider item update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $itemId = (int)$_POST['item_id'];
    $imageUrl = trim($_POST['edit_resim_url']);
    $linkUrl = trim($_POST['edit_link_url']);
    
    if (empty($imageUrl) || empty($linkUrl)) {
        $error = "Resim URL ve Link URL alanları boş olamaz.";
    } else {
        try {
            // Check if the image URL is valid and accessible
            $headers = @get_headers($imageUrl);
            if (!$headers || strpos($headers[0], '200') === false) {
                $error = "Geçersiz URL veya erişilemeyen resim.";
            } else {
                $stmt = $db->prepare("UPDATE slideraltbolumduzen SET resim_url = ?, link_url = ? WHERE id = ?");
                $stmt->execute([$imageUrl, $linkUrl, $itemId]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "Slider öğesi güncellendi: $imageUrl"]);
                
                $message = "Slider öğesi başarıyla güncellendi.";
            }
        } catch (PDOException $e) {
            $error = "Slider öğesi güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Handle slider item delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $itemId = (int)$_GET['delete'];
    
    try {
        // Get item info for logging
        $stmt = $db->prepare("SELECT resim_url FROM slideraltbolumduzen WHERE id = ?");
        $stmt->execute([$itemId]);
        $itemInfo = $stmt->fetch();
        
        $stmt = $db->prepare("DELETE FROM slideraltbolumduzen WHERE id = ?");
        $stmt->execute([$itemId]);
        
        if ($stmt->rowCount() > 0) {
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (admin_id, action, description, created_at) 
                VALUES (?, 'delete', ?, NOW())
            ");
            $stmt->execute([$_SESSION['admin_id'], "Slider öğesi silindi: " . ($itemInfo['resim_url'] ?? 'Unknown')]);
            
            $message = "Slider öğesi başarıyla silindi.";
        } else {
            $error = "Slider öğesi bulunamadı.";
        }
    } catch (PDOException $e) {
        $error = "Slider öğesi silinirken bir hata oluştu: " . $e->getMessage();
    }
}

// Get all slider items
try {
    $stmt = $db->query("SELECT * FROM slideraltbolumduzen ORDER BY sira, id");
    $sliderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Slider öğeleri yüklenirken bir hata oluştu: " . $e->getMessage();
    $sliderItems = [];
}

// Calculate statistics
try {
    // Total slider items
    $stmt = $db->prepare("SELECT COUNT(*) FROM slideraltbolumduzen");
    $stmt->execute();
    $totalSliderItems = $stmt->fetchColumn();
    
    // Active slider items
    $stmt = $db->prepare("SELECT COUNT(*) FROM slideraltbolumduzen WHERE durum = 1");
    $stmt->execute();
    $activeSliderItems = $stmt->fetchColumn();
    
    // Today's slider items
    $stmt = $db->prepare("SELECT COUNT(*) FROM slideraltbolumduzen WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todaySliderItems = $stmt->fetchColumn();
    
    // Normal type items
    $stmt = $db->prepare("SELECT COUNT(*) FROM slideraltbolumduzen WHERE tip = 'normal'");
    $stmt->execute();
    $normalTypeItems = $stmt->fetchColumn();
    
    // Special type items
    $stmt = $db->prepare("SELECT COUNT(*) FROM slideraltbolumduzen WHERE tip != 'normal'");
    $stmt->execute();
    $specialTypeItems = $stmt->fetchColumn();
    
    // Average order
    $stmt = $db->prepare("SELECT AVG(sira) FROM slideraltbolumduzen");
    $stmt->execute();
    $avgOrder = $stmt->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    $totalSliderItems = $activeSliderItems = $todaySliderItems = $normalTypeItems = $specialTypeItems = $avgOrder = 0;
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

.slider-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--card-radius);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}

.slider-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.slider-title {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.slider-body {
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

.slider-img {
    max-height: 80px;
    max-width: 100%;
    object-fit: contain;
    border-radius: 0.25rem;
    box-shadow: var(--shadow-sm);
}

.slider-preview {
    width: 120px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    overflow: hidden;
    border: 1px solid var(--border-color);
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

.btn-info {
    background: linear-gradient(135deg, var(--info-color), #0891b2);
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

.form-control {
    border-radius: 0.375rem;
    border: 1px solid var(--border-color);
    padding: 0.75rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    outline: none;
}

.form-label {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: block;
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
    
    .slider-body {
        padding: 1rem;
    }
}
</style>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="greeting">Slider Alt Bölüm Düzen</h1>
            <p class="dashboard-subtitle">Slider alt bölüm öğelerini yönetin ve düzenleyin</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="fas fa-plus"></i> Yeni Öğe Ekle
            </button>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <i class="fas fa-images"></i>
        <h3><?php echo number_format($totalSliderItems); ?></h3>
        <p>Toplam Slider Öğesi</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-check-circle"></i>
        <h3><?php echo number_format($activeSliderItems); ?></h3>
        <p>Aktif Öğe</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-tag"></i>
        <h3><?php echo number_format($normalTypeItems); ?></h3>
        <p>Normal Tip</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-star"></i>
        <h3><?php echo number_format($specialTypeItems); ?></h3>
        <p>Özel Tip</p>
    </div>
    <div class="stat-card">
        <i class="fas fa-sort-numeric-up"></i>
        <h3><?php echo number_format($avgOrder, 1); ?></h3>
        <p>Ortalama Sıra</p>
    </div>
</div>

<?php if (!empty($message)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Slider Card -->
<div class="slider-card">
    <div class="slider-header">
        <h2 class="slider-title">
            <i class="fas fa-sliders-h"></i>
            Slider Alt Bölüm Öğeleri
        </h2>
    </div>
    <div class="slider-body">
        <div class="table-responsive">
            <table class="table table-hover" id="sliderItemsTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag me-2"></i>ID</th>
                        <th><i class="fas fa-image me-2"></i>Önizleme</th>
                        <th><i class="fas fa-tag me-2"></i>Tip</th>
                        <th><i class="fas fa-link me-2"></i>Resim URL</th>
                        <th><i class="fas fa-external-link-alt me-2"></i>Link URL</th>
                        <th class="text-center"><i class="fas fa-cogs me-2"></i>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sliderItems)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-images fa-3x mb-3"></i>
                            <p>Henüz slider öğesi eklenmemiş.</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($sliderItems as $item): ?>
                        <tr>
                            <td><strong><?php echo $item['id']; ?></strong></td>
                            <td>
                                <div class="slider-preview">
                                    <img src="<?php echo htmlspecialchars($item['resim_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['alt_text'] ?? 'Slider Image'); ?>" class="slider-img"
                                         onerror="this.src='assets/img/image-error.png'">
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $item['tip'] === 'normal' ? 'bg-primary' : 'bg-warning'; ?>">
                                    <?php echo htmlspecialchars($item['tip']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($item['resim_url']); ?>">
                                    <a href="<?php echo htmlspecialchars($item['resim_url']); ?>" target="_blank" class="text-decoration-none">
                                        <?php echo htmlspecialchars($item['resim_url']); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($item['link_url']); ?>">
                                    <a href="<?php echo htmlspecialchars($item['link_url']); ?>" target="_blank" class="text-decoration-none">
                                        <?php echo htmlspecialchars($item['link_url']); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-info edit-item" 
                                            data-id="<?php echo $item['id']; ?>"
                                            data-resim-url="<?php echo htmlspecialchars($item['resim_url']); ?>"
                                            data-link-url="<?php echo htmlspecialchars($item['link_url']); ?>"
                                            title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-item"
                                            data-id="<?php echo $item['id']; ?>"
                                            title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> Yeni Slider Öğesi Ekle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="resim_url" class="form-label">Resim URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="resim_url" name="resim_url" 
                                   required placeholder="https://example.com/image.jpg">
                            <div id="imagePreview" class="mt-2 d-none">
                                <img src="" alt="Önizleme" class="img-fluid" style="max-height: 150px; border-radius: 0.25rem;">
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="link_url" class="form-label">Link URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="link_url" name="link_url" 
                                   required placeholder="/pages/vip">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> İptal
                    </button>
                    <button type="submit" name="add_item" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel">
                    <i class="fas fa-edit me-2"></i> Slider Öğesini Düzenle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_item_id" name="item_id">
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="edit_resim_url" class="form-label">Resim URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_resim_url" name="edit_resim_url" required>
                            <div id="editImagePreview" class="mt-2">
                                <img src="" alt="Önizleme" class="img-fluid" style="max-height: 150px; border-radius: 0.25rem;">
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <label for="edit_link_url" class="form-label">Link URL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_link_url" name="edit_link_url" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> İptal
                    </button>
                    <button type="submit" name="update_item" class="btn btn-info">
                        <i class="fas fa-save me-1"></i> Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#sliderItemsTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
        },
        order: [[0, 'asc']],
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
    
    // Preview image when URL is entered (Add Modal)
    document.getElementById('resim_url').addEventListener('input', function() {
        const url = this.value.trim();
        const preview = document.getElementById('imagePreview');
        
        if (url) {
            preview.classList.remove('d-none');
            preview.querySelector('img').src = url;
        } else {
            preview.classList.add('d-none');
        }
    });
    
    // Edit item button functionality
    document.querySelectorAll('.edit-item').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const resimUrl = this.getAttribute('data-resim-url');
            const linkUrl = this.getAttribute('data-link-url');
            
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_resim_url').value = resimUrl;
            document.getElementById('edit_link_url').value = linkUrl;
            document.getElementById('editImagePreview').querySelector('img').src = resimUrl;
            
            const editModal = new bootstrap.Modal(document.getElementById('editItemModal'));
            editModal.show();
        });
    });
    
    // Preview image when URL is entered (Edit Modal)
    document.getElementById('edit_resim_url').addEventListener('input', function() {
        const url = this.value.trim();
        const preview = document.getElementById('editImagePreview').querySelector('img');
        
        if (url) {
            preview.src = url;
        }
    });
    
    // Delete item functionality
    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            
            Swal.fire({
                title: 'Slider Öğesi Sil',
                text: "Bu slider öğesi kalıcı olarak silinecektir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?delete=${itemId}`;
                }
            });
        });
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 