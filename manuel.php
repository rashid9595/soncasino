<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admincik/login.php');
    exit;
}

// Process payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id']) && isset($_POST['status'])) {
    $payment_id = intval($_POST['payment_id']);
    $status = intval($_POST['status']);
    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);

    try {
        // Start transaction
        $db->beginTransaction();

        // Update payment status
        $stmt = $db->prepare("UPDATE parayatir SET durum = ? WHERE id = ?");
        $stmt->execute([$status, $payment_id]);

        // If payment is approved (status = 2), add miktar to ana_bakiye
        if ($status === 2) {
            $stmt = $db->prepare("UPDATE kullanicilar SET ana_bakiye = ana_bakiye + ? WHERE id = ?");
            $success = $stmt->execute([$amount, $user_id]);
            
            if (!$success) {
                throw new Exception("Bakiye güncellenemedi");
            }
        }

        // Commit transaction
        $db->commit();
        header('Location: manuel.php?success=1');
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        echo "Hata oluştu: " . $e->getMessage();
        exit;
    }
}

// Get all pending manual parayatir
$sql = "
    SELECT p.*, u.username 
    FROM parayatir p 
    JOIN kullanicilar u ON p.user_id = u.id 
    WHERE p.durum = 0 
    ORDER BY p.tarih DESC
";

$stmt = $db->query($sql);
$parayatir = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manuel Ödemeler - Admin Panel</title>
    <style>
        .container {
            width: 95%;
            margin: 20px auto;
            padding: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
            background-color: #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,.05);
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
        }
        .btn-sm {
            font-size: 14px;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Manuel Ödemeler</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">İşlem başarıyla tamamlandı.</div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parayatir as $payment): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo htmlspecialchars($payment['username']); ?></td>
                            <td><?php echo number_format($payment['miktar'], 2); ?> TL</td>
                            <td>
                                <?php
                                switch ($payment['durum']) {
                                    case 0: echo '<span class="badge badge-warning">Beklemede</span>'; break;
                                    case 2: echo '<span class="badge badge-success">Onaylandı</span>'; break;
                                    case 3: echo '<span class="badge badge-danger">Reddedildi</span>'; break;
                                }
                                ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($payment['tarih'])); ?></td>
                            <td>
                                <?php if ($payment['durum'] === 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $payment['user_id']; ?>">
                                        <input type="hidden" name="amount" value="<?php echo $payment['miktar']; ?>">
                                        <input type="hidden" name="status" value="2">
                                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Ödemeyi onaylamak istediğinize emin misiniz?')">Onayla</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $payment['user_id']; ?>">
                                        <input type="hidden" name="amount" value="<?php echo $payment['miktar']; ?>">
                                        <input type="hidden" name="status" value="3">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Ödemeyi reddetmek istediğinize emin misiniz?')">Reddet</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 