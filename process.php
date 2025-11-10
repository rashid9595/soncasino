// Eğer yoksa aşağıdaki ban işlemleriyle ilgili kodları ekleyin
// Ban işlemleri için AJAX endpoint'leri
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'İşlem başarısız oldu.'];
    
    // Oturum ve yetki kontrolü
    if (!isset($_SESSION['admin_id'])) {
        $response['message'] = 'Oturum süreniz dolmuş. Lütfen tekrar giriş yapın.';
        echo json_encode($response);
        exit;
    }
    
    // Ban ekleme işlemi
    if ($action === 'add_ban') {
        if (isset($_POST['username']) && isset($_POST['reason']) && isset($_POST['duration'])) {
            $username = $_POST['username'];
            $reason = $_POST['reason'];
            $duration = (int)$_POST['duration'];
            
            // Kullanıcı var mı kontrol et
            $stmt = $mysqli->prepare("SELECT id FROM site_users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Kullanıcı bulunamadı.';
                echo json_encode($response);
                exit;
            }
            
            $row = $result->fetch_assoc();
            $userId = $row['id'];
            
            // Ban kaydı ekle
            $stmt = $mysqli->prepare("INSERT INTO chat_bans (user_id, reason, created_by, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))");
            $stmt->bind_param("isis", $userId, $reason, $_SESSION['admin_id'], $duration);
            
            if ($stmt->execute()) {
                // Aktivite logu ekle
                $log_action = "create";
                $log_item = "chat_ban";
                $log_item_id = $stmt->insert_id;
                $log_description = "$username kullanıcısı için $duration gün süreli yeni ban eklendi. Sebep: $reason";
                $admin_id = $_SESSION['admin_id'];
                
                $log_stmt = $mysqli->prepare("INSERT INTO activity_logs (admin_id, action, item, item_id, description) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->bind_param("issss", $admin_id, $log_action, $log_item, $log_item_id, $log_description);
                $log_stmt->execute();
                
                $response['success'] = true;
                $response['message'] = 'Ban başarıyla eklendi.';
            } else {
                $response['message'] = 'Ban eklenirken bir hata oluştu: ' . $mysqli->error;
            }
        } else {
            $response['message'] = 'Eksik parametreler.';
        }
    }
    
    // Ban silme işlemi
    else if ($action === 'delete_ban') {
        if (isset($_POST['id'])) {
            $banId = (int)$_POST['id'];
            
            // Ban kaydını bul
            $stmt = $mysqli->prepare("SELECT cb.id, cb.user_id, cb.reason, su.username 
                                      FROM chat_bans cb 
                                      JOIN site_users su ON cb.user_id = su.id 
                                      WHERE cb.id = ?");
            $stmt->bind_param("i", $banId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Ban kaydı bulunamadı.';
                echo json_encode($response);
                exit;
            }
            
            $ban = $result->fetch_assoc();
            
            // Ban kaydını sil
            $stmt = $mysqli->prepare("DELETE FROM chat_bans WHERE id = ?");
            $stmt->bind_param("i", $banId);
            
            if ($stmt->execute()) {
                // Aktivite logu ekle
                $log_action = "delete";
                $log_item = "chat_ban";
                $log_item_id = $banId;
                $log_description = "{$ban['username']} kullanıcısının ban kaydı silindi. Sebep: {$ban['reason']}";
                $admin_id = $_SESSION['admin_id'];
                
                $log_stmt = $mysqli->prepare("INSERT INTO activity_logs (admin_id, action, item, item_id, description) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->bind_param("issss", $admin_id, $log_action, $log_item, $log_item_id, $log_description);
                $log_stmt->execute();
                
                $response['success'] = true;
                $response['message'] = 'Ban kaydı başarıyla silindi.';
            } else {
                $response['message'] = 'Ban silinirken bir hata oluştu: ' . $mysqli->error;
            }
        } else {
            $response['message'] = 'Eksik parametreler.';
        }
    }
    
    // Ban güncelleme işlemi
    else if ($action === 'update_ban') {
        if (isset($_POST['id']) && isset($_POST['reason']) && isset($_POST['duration'])) {
            $banId = (int)$_POST['id'];
            $reason = $_POST['reason'];
            $duration = (int)$_POST['duration'];
            
            // Ban kaydını bul
            $stmt = $mysqli->prepare("SELECT cb.id, cb.user_id, cb.reason, su.username 
                                      FROM chat_bans cb 
                                      JOIN site_users su ON cb.user_id = su.id 
                                      WHERE cb.id = ?");
            $stmt->bind_param("i", $banId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Ban kaydı bulunamadı.';
                echo json_encode($response);
                exit;
            }
            
            $ban = $result->fetch_assoc();
            
            // Ban kaydını güncelle
            $stmt = $mysqli->prepare("UPDATE chat_bans SET reason = ?, expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
            $stmt->bind_param("sii", $reason, $duration, $banId);
            
            if ($stmt->execute()) {
                // Aktivite logu ekle
                $log_action = "update";
                $log_item = "chat_ban";
                $log_item_id = $banId;
                $log_description = "{$ban['username']} kullanıcısının ban kaydı güncellendi. Yeni sebep: $reason, Yeni süre: $duration gün";
                $admin_id = $_SESSION['admin_id'];
                
                $log_stmt = $mysqli->prepare("INSERT INTO activity_logs (admin_id, action, item, item_id, description) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->bind_param("issss", $admin_id, $log_action, $log_item, $log_item_id, $log_description);
                $log_stmt->execute();
                
                $response['success'] = true;
                $response['message'] = 'Ban kaydı başarıyla güncellendi.';
            } else {
                $response['message'] = 'Ban güncellenirken bir hata oluştu: ' . $mysqli->error;
            }
        } else {
            $response['message'] = 'Eksik parametreler.';
        }
    }
    
    // Response'u JSON olarak döndür
    echo json_encode($response);
    exit;
} 