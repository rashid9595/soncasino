<?php
include 'includes/db.php';
include 'includes/functions.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

/**
 * Güvenli INSERT fonksiyonu.
 * Tabloda olmayan sütunları otomatik olarak çıkarır.
 */
function safeInsert(PDO $pdo, string $table, array $data) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table`");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $filtered = [];
    foreach ($data as $k => $v) {
        if (in_array($k, $cols, true)) {
            $filtered[$k] = $v;
        }
    }

    if (empty($filtered)) {
        throw new Exception("safeInsert: '$table' tablosunda uygun sütun bulunamadı.");
    }

    $columns = implode("`,`", array_keys($filtered));
    $placeholders = implode(",", array_fill(0, count($filtered), "?"));
    $sql = "INSERT INTO `$table` (`$columns`) VALUES ($placeholders)";
    $p = $pdo->prepare($sql);
    $p->execute(array_values($filtered));

    return $pdo->lastInsertId();
}

if (isset($_POST['add_balance'])) {
    $user_id = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);
    $reason = trim($_POST['reason']);
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'];

    try {
        $pdo->beginTransaction();

        // Kullanıcıyı bul
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new Exception("Kullanıcı bulunamadı!");
        }

        $old_balance = floatval($user['balance']);
        $new_balance = $old_balance + $amount;

        // Kullanıcı bakiyesini güncelle
        $update = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $update->execute([$new_balance, $user_id]);

        // Bakiye geçmişine güvenli kayıt
        $insertData = [
            'user_id' => $user_id,
            'amount' => $amount,
            'balance_type' => 'ana_bakiye',
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ];
        safeInsert($pdo, 'bakiye_gecmisi', $insertData);

        // Admin log kaydı
        $desc = "Kullanıcı bakiyesi eklendi: ID $user_id - Eklenen: $amount TRY - Yeni Bakiye: $new_balance TRY - Sebep: $reason";
        safeInsert($pdo, 'activity_logs', [
            'admin_id' => $admin_id,
            'action' => 'balance',
            'description' => $desc,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $pdo->commit();
        $success = "Bakiye başarıyla eklendi.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "İşlem sırasında bir hata oluştu: " . $e->getMessage();
    }
}

// Kullanıcı detaylarını al
if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("Kullanıcı bulunamadı.");
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Detayları</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; color: #222; margin: 40px; }
        h1, h2 { color: #444; }
        form { margin-top: 20px; background: #fff; padding: 20px; border-radius: 8px; max-width: 400px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="number"], input[type="text"] { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #28a745; color: #fff; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; }
        button:hover { background: #218838; }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .user-info { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Kullanıcı Detayları</h1>

    <?php if (isset($success)): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($user)): ?>
        <div class="user-info">
            <p><strong>ID:</strong> <?= $user['id'] ?></p>
            <p><strong>Kullanıcı Adı:</strong> <?= htmlspecialchars($user['username']) ?></p>
            <p><strong>Mevcut Bakiye:</strong> <?= number_format($user['balance'], 2) ?> TRY</p>
        </div>

        <h2>Bakiye Ekle</h2>
        <form method="post">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <label>Tutar (TRY):</label>
            <input type="number" name="amount" step="0.01" required>

            <label>Sebep:</label>
            <input type="text" name="reason" placeholder="Örn: Bonus, Manuel Yükleme..." required>

            <button type="submit" name="add_balance">Bakiye Ekle</button>
        </form>
    <?php endif; ?>
</body>
</html>
