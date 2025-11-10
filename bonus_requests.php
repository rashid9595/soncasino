<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Bonus Talepleri";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check permissions
if (!isset($_SESSION['role_id'])) {
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

$isAdmin = ($_SESSION['role_id'] == 1);

if (!$isAdmin) {
    $stmt = $db->prepare("
        SELECT ap.* 
        FROM admin_permissions ap 
        WHERE ap.role_id = ? AND ap.menu_item = 'bonus_requests' AND ap.can_view = 1
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
        case 'process_request':
            try {
                $stmt = $db->prepare("
                    UPDATE bonus_requests 
                    SET status = ?, 
                        process_date = NOW(), 
                        processed_by = ?,
                        notes = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['status'],
                    $_SESSION['admin_id'],
                    $_POST['notes'],
                    $_POST['request_id']
                ]);
                
                // Log activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (admin_id, action, description, created_at) 
                    VALUES (?, 'process_bonus_request', ?, NOW())
                ");
                $stmt->execute([
                    $_SESSION['admin_id'], 
                    "Bonus talebi işlendi: ID {$_POST['request_id']} - Durum: {$_POST['status']}"
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Bonus talebi başarıyla işlendi']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get all bonus requests with user and bonus details
$stmt = $db->query("
    SELECT 
        br.*,
        u.username,
        b.bonus_adi,
        a.username as admin_username
    FROM bonus_requests br
    LEFT JOIN users u ON br.user_id = u.id
    LEFT JOIN bonuslar b ON br.bonus_id = b.id
    LEFT JOIN administrators a ON br.processed_by = a.id
    ORDER BY br.request_date DESC
");
$requests = $stmt->fetchAll();

ob_start();
?>

<style>
.card {
    background: var(--dark-card);
    border: 1px solid var(--dark-accent);
}

.form-section {
    background: var(--dark-card);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.status-badge {
    text-transform: uppercase;
    font-weight: bold;
}

.status-pending {
    background-color: var(--warning);
}

.status-approved {
    background-color: var(--success);
}

.status-rejected {
    background-color: var(--danger);
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Bonus Talepleri</h1>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="table-responsive">
                <table class="table table-bordered" id="requestsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Bonus</th>
                            <th>Talep Tarihi</th>
                            <th>Durum</th>
                            <th>İşlem Tarihi</th>
                            <th>İşleyen Admin</th>
                            <th>Notlar</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo $request['id']; ?></td>
                            <td><?php echo htmlspecialchars($request['username']); ?></td>
                            <td><?php echo htmlspecialchars($request['bonus_adi']); ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($request['request_date'])); ?></td>
                            <td>
                                <span class="badge status-badge status-<?php echo $request['status']; ?>">
                                    <?php 
                                    switch($request['status']) {
                                        case 'pending': echo 'Bekliyor'; break;
                                        case 'approved': echo 'Onaylandı'; break;
                                        case 'rejected': echo 'Reddedildi'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $request['process_date'] ? date('d.m.Y H:i', strtotime($request['process_date'])) : '-'; ?></td>
                            <td><?php echo $request['admin_username'] ? htmlspecialchars($request['admin_username']) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($request['notes'] ?? ''); ?></td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-success" onclick="processRequest(<?php echo $request['id']; ?>, 'approved')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="processRequest(<?php echo $request['id']; ?>, 'rejected')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted">İşlem yapıldı</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function processRequest(requestId, status) {
    Swal.fire({
        title: status === 'approved' ? 'Bonus Talebini Onayla' : 'Bonus Talebini Reddet',
        text: 'İşlem notlarını giriniz:',
        input: 'textarea',
        inputPlaceholder: 'İşlem notları...',
        showCancelButton: true,
        confirmButtonText: status === 'approved' ? 'Onayla' : 'Reddet',
        cancelButtonText: 'İptal',
        showLoaderOnConfirm: true,
        preConfirm: (notes) => {
            return $.ajax({
                url: 'bonus_requests.php',
                type: 'POST',
                data: {
                    action: 'process_request',
                    request_id: requestId,
                    status: status,
                    notes: notes
                }
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Başarılı',
                    text: result.value.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: result.value.message
                });
            }
        }
    });
}

// DataTable initialization
$(document).ready(function() {
    $('#requestsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[3, "desc"]],
        "pageLength": 25,
        "responsive": true
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 