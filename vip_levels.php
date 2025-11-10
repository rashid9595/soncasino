<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "VIP Seviye Yönetimi";

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
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

// If user is admin (role_id = 1), grant full access
$isAdmin = ($_SESSION['role_id'] == 1);

if (!$isAdmin) {
    $stmt = $db->prepare("
        SELECT ap.* 
        FROM admin_permissions ap 
        WHERE ap.role_id = ? AND ap.menu_item = 'vip_levels' AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_level':
            $username = trim($_POST['username']);
            $level = trim($_POST['level']);
            
            if (empty($username) || empty($level)) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
                exit();
            }
            
            try {
                $db->beginTransaction();
                
                // Update VIP level
                $stmt = $db->prepare("
                    UPDATE kullanici_vip 
                    SET vip_level = ? 
                    WHERE username = ?
                ");
                $stmt->execute([$level, $username]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'update', ?, NOW())
                ");
                $stmt->execute([$_SESSION['admin_id'], "VIP seviyesi güncellendi: $username"]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'VIP seviyesi başarıyla güncellendi']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Bir hata oluştu']);
            }
            exit();
            
        case 'get_user_level':
            $username = trim($_POST['username']);
            
            if (empty($username)) {
                echo json_encode(['success' => false, 'message' => 'Geçersiz kullanıcı adı']);
                exit();
            }
            
            $stmt = $db->prepare("
                SELECT vip_level, total_turnover 
                FROM kullanici_vip 
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            $level = $stmt->fetch();
            
            if ($level) {
                echo json_encode(['success' => true, 'data' => $level]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
            }
            exit();
    }
}

// Get all users with VIP levels
$stmt = $db->prepare("
    SELECT 
        kv.*,
        kvp.toplam_puan,
        kvp.kullanilan_puan,
        kr.rakeback_balance,
        kr.total_rakeback_earned
    FROM kullanici_vip kv
    LEFT JOIN kullanici_vip_puan kvp ON kv.username = kvp.username
    LEFT JOIN kullanicilar_rakeback kr ON kv.username = kr.username
    ORDER BY kv.total_turnover DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">VIP Seviye Yönetimi</h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="vipLevelsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Kullanıcı Adı</th>
                            <th>VIP Seviyesi</th>
                            <th>Toplam Turnover</th>
                            <th>Toplam Puan</th>
                            <th>Kullanılan Puan</th>
                            <th>Rakeback Bakiyesi</th>
                            <th>Son Güncelleme</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <span class="badge badge-vip badge-<?php echo strtolower($user['vip_level']); ?>">
                                    <?php echo htmlspecialchars($user['vip_level']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($user['total_turnover'], 2); ?> ₺</td>
                            <td><?php echo number_format($user['toplam_puan'], 2); ?></td>
                            <td><?php echo number_format($user['kullanilan_puan'], 2); ?></td>
                            <td><?php echo number_format($user['rakeback_balance'], 2); ?> ₺</td>
                            <td><?php echo date('d.m.Y H:i', strtotime($user['last_update'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Level Modal -->
<div class="modal fade" id="editLevelModal" tabindex="-1" aria-labelledby="editLevelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLevelModalLabel">VIP Seviyesini Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editLevelForm">
                    <input type="hidden" id="edit_username" name="username">
                    <div class="mb-3">
                        <label for="edit_vip_level" class="form-label text-light">VIP Seviyesi</label>
                        <select class="form-control bg-dark text-light border-dark" id="edit_vip_level" name="level" required>
                            <option value="Standart">Standart</option>
                            <option value="Bronz">Bronz</option>
                            <option value="Gümüş">Gümüş</option>
                            <option value="Altın">Altın</option>
                            <option value="Platin">Platin</option>
                            <option value="Elmas">Elmas</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_turnover" class="form-label text-light">Toplam Turnover</label>
                        <input type="number" class="form-control bg-dark text-light border-dark" id="edit_turnover" name="turnover" min="0" step="0.01" readonly>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" onclick="updateLevel()">Güncelle</button>
            </div>
        </div>
    </div>
</div>

<style>
.modal-content {
    background: var(--dark-card);
    border: 1px solid var(--dark-accent);
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
}

.modal-header {
    border-bottom: 1px solid var(--dark-accent);
    background: var(--dark-secondary);
}

.modal-footer {
    border-top: 1px solid var(--dark-accent);
    background: var(--dark-secondary);
}

.modal-title {
    color: var(--dark-text);
}

.btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

.badge-vip {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-standart { background: var(--blue-600); color: white; }
.badge-bronz { background: #cd7f32; color: white; }
.badge-gumus { background: #c0c0c0; color: black; }
.badge-altin { background: #ffd700; color: black; }
.badge-platin { background: #e5e4e2; color: black; }
.badge-elmas { background: #b9f2ff; color: black; }
</style>

<script>
$(document).ready(function() {
    $('#vipLevelsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[2, "desc"]], // Sort by total turnover column descending
        "pageLength": 25,
        "responsive": true
    });
});

function showEditLevelModal(username) {
    // Show loading state
    Swal.fire({
        title: 'Yükleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'vip_levels.php',
        type: 'POST',
        data: {
            action: 'get_user_level',
            username: username
        },
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                $('#edit_username').val(username);
                $('#edit_vip_level').val(response.data.vip_level);
                $('#edit_turnover').val(response.data.total_turnover);
                
                var editModal = new bootstrap.Modal(document.getElementById('editLevelModal'));
                editModal.show();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: response.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Sunucu ile iletişim kurulurken bir hata oluştu'
            });
        }
    });
}

function updateLevel() {
    const username = $('#edit_username').val();
    const level = $('#edit_vip_level').val();

    if (!username || !level) {
        Swal.fire({
            icon: 'error',
            title: 'Hata',
            text: 'Lütfen tüm alanları doldurun'
        });
        return;
    }

    // Show loading state
    Swal.fire({
        title: 'İşleniyor...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'vip_levels.php',
        type: 'POST',
        data: {
            action: 'update_level',
            username: username,
            level: level
        },
        success: function(response) {
            if (response.success) {
                var modal = bootstrap.Modal.getInstance(document.getElementById('editLevelModal'));
                modal.hide();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı',
                    text: response.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: response.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Sunucu ile iletişim kurulurken bir hata oluştu'
            });
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 