<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hata ayıklama için çıktı tamponlamasını başlat
ob_start();

header('Content-Type: application/json');
error_reporting(E_ALL); 
ini_set('display_errors', 0); // Hataları ekrana yazdırmayı kapat

require_once '../../config.php';

// JSON yanıtı gönderen fonksiyon
function jsonResponse($success, $message, $data = null) {
    // Önceki çıktıları temizle
    ob_end_clean();
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Hata yakalama fonksiyonu
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    
    // Önceki çıktıları temizle
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'message' => "Sunucu hatası: $errstr"
    ]);
    exit;
}

// Hata yakalama fonksiyonunu ayarla
set_error_handler('handleError');

function bonusTalepKontrol($userId, $bonusId, $ip) {
    try {
        global $db;
        
        // Transaction başlat
        $db->beginTransaction();

        // Önce bu bonus için daha önce kullanım var mı kontrol et
        $oncekiKullanim = $db->prepare("SELECT id FROM bonus_kullanim WHERE user_id = ? AND bonus_id = ? AND durum = 1");
        $oncekiKullanim->execute([$userId, $bonusId]);
        if($oncekiKullanim->rowCount() > 0) {
            $db->commit();
            return ["success" => false, "message" => "Bu bonusu daha önce kullandınız!"];
        }

        // Bonus bilgilerini al
        $bonusSorgu = $db->prepare("SELECT * FROM bonuslar WHERE id = ? AND aktif = 1");
        $bonusSorgu->execute([$bonusId]);
        $bonus = $bonusSorgu->fetch(PDO::FETCH_ASSOC);

        if(!$bonus) {
            $db->commit();
            return ["success" => false, "message" => "Bonus bulunamadı veya aktif değil!"];
        }

        // Kullanıcı bilgilerini al
        $userSorgu = $db->prepare("SELECT ana_bakiye FROM kullanicilar WHERE id = ?");
        $userSorgu->execute([$userId]);
        $user = $userSorgu->fetch(PDO::FETCH_ASSOC);

        if(!$user) {
            $db->commit();
            return ["success" => false, "message" => "Kullanıcı bilgileri alınamadı!"];
        }

        $bonusMiktar = 0;
        $yatirimMiktari = 0;
        $yatirimId = null;
        $hataMesaji = "";
        $bonusHakki = true;

        // Bonus türüne göre işlemler
        if($bonus['bonus_turu'] == 'deneme') {
            // Deneme bonusu kontrolleri
            $yatirimSorgu = $db->prepare("SELECT id FROM parayatir WHERE user_id = ? AND durum = 2");
            $yatirimSorgu->execute([$userId]);
            if($yatirimSorgu->rowCount() > 0) {
                $bonusHakki = false;
                $hataMesaji = "Daha önce yatırım yaptığınız için deneme bonusu kullanamazsınız!";
            }

            // IP kontrolü
            if ($bonusHakki) {
                $ipKontrol = $db->prepare("SELECT id FROM bonus_kullanim WHERE ip_adresi = ? AND bonus_id = ?");
                $ipKontrol->execute([$ip, $bonusId]);
                if($ipKontrol->rowCount() > 0) {
                    $bonusHakki = false;
                    $hataMesaji = "Bu IP adresi ile daha önce deneme bonusu alınmış!";
                }
            }

            // Kullanıcı ID kontrolü
            if ($bonusHakki) {
                $kullaniciKontrol = $db->prepare("SELECT id FROM bonus_kullanim WHERE user_id = ? AND bonus_id = ?");
                $kullaniciKontrol->execute([$userId, $bonusId]);
                if($kullaniciKontrol->rowCount() > 0) {
                    $bonusHakki = false;
                    $hataMesaji = "Bu hesap ile daha önce deneme bonusu alınmış!";
                }
            }

            if ($bonusHakki) {
                $bonusMiktar = $bonus['max_miktar'];
            }
        }
        else if($bonus['bonus_turu'] == 'yatirim' || $bonus['bonus_turu'] == 'ilk_yatirim') {
            // Yatırım bonusu kontrolleri
            
            // Tekrar alınabilirlik kontrolü
            if(!$bonus['tekrar_alinabilir']) {
                $oncekiBonus = $db->prepare("SELECT id FROM bonus_kullanim WHERE user_id = ? AND bonus_id = ? AND durum = 1");
                $oncekiBonus->execute([$userId, $bonusId]);
                if($oncekiBonus->rowCount() > 0) {
                    $bonusHakki = false;
                    $hataMesaji = "Bu bonusu sadece bir kez kullanabilirsiniz!";
                }
            }

            // Son yatırım bilgisini al
            if ($bonusHakki) {
                $sonYatirimSorgu = $db->prepare("SELECT id, miktar, tarih FROM parayatir 
                                               WHERE user_id = ? AND durum = 2 
                                               ORDER BY tarih DESC LIMIT 1");
                $sonYatirimSorgu->execute([$userId]);
                $sonYatirim = $sonYatirimSorgu->fetch(PDO::FETCH_ASSOC);

                if(!$sonYatirim) {
                    $bonusHakki = false;
                    $hataMesaji = "Yatırım bonusu için önce para yatırmanız gerekmektedir!";
                } else {
                    // İlk yatırım bonusu için ek kontrol
                    if($bonus['bonus_turu'] == 'ilk_yatirim') {
                        $yatirimSayisi = $db->prepare("SELECT COUNT(*) as sayi FROM parayatir WHERE user_id = ? AND durum = 2");
                        $yatirimSayisi->execute([$userId]);
                        if($yatirimSayisi->fetch(PDO::FETCH_ASSOC)['sayi'] > 1) {
                            $bonusHakki = false;
                            $hataMesaji = "İlk yatırım bonusu sadece ilk yatırımınız için geçerlidir!";
                        }
                    }

                    // Bu yatırım için daha önce bonus kullanılmış mı kontrol et
                    if ($bonusHakki) {
                        $yatirimKontrol = $db->prepare("SELECT id FROM bonus_kullanim 
                                                      WHERE user_id = ? AND yatirim_id = ? AND durum = 1");
                        $yatirimKontrol->execute([$userId, $sonYatirim['id']]);
                        if($yatirimKontrol->rowCount() > 0) {
                            $bonusHakki = false;
                            $hataMesaji = "Bu yatırım için daha önce bonus kullanılmış!";
                        }
                    }

                    // Minimum yatırım şartı kontrolü
                    if ($bonusHakki) {
                        if($sonYatirim['miktar'] < $bonus['min_yatirim_sarti']) {
                            $bonusHakki = false;
                            $hataMesaji = "Son yatırımınız (" . number_format($sonYatirim['miktar'], 2) . " TL) bonus için yetersiz. Minimum " . number_format($bonus['min_yatirim_sarti'], 2) . " TL yatırım yapmalısınız!";
                        }
                    }

                    // Bonus miktarını hesapla
                    if ($bonusHakki) {
                        $bonusMiktar = ($sonYatirim['miktar'] * $bonus['yuzde']) / 100;
                        if($bonusMiktar > $bonus['max_miktar']) {
                            $bonusMiktar = $bonus['max_miktar'];
                        }
                        
                        $yatirimMiktari = $sonYatirim['miktar'];
                        $yatirimId = $sonYatirim['id'];
                    }
                }
            }
        }
        else if($bonus['bonus_turu'] == 'kayip') {
            // Kayıp bonusu kontrolleri
            
            // Son yatırım bilgisini al
            $sonYatirimSorgu = $db->prepare("SELECT * FROM parayatir WHERE user_id = ? AND durum = 2 ORDER BY tarih DESC LIMIT 1");
            $sonYatirimSorgu->execute([$userId]);
            $sonYatirim = $sonYatirimSorgu->fetch(PDO::FETCH_ASSOC);

            if(!$sonYatirim) {
                $bonusHakki = false;
                $hataMesaji = "Kayıp bonusu alabilmek için önce yatırım yapmanız gerekmektedir!";
            } else {
                // Bakiye kontrolü
                if($user['ana_bakiye'] > 5) {
                    $bonusHakki = false;
                    $hataMesaji = "Kayıp bonusu alabilmek için bakiyeniz 5 TL'den az olmalıdır!";
                }

                // Minimum yatırım şartı kontrolü
                if ($bonusHakki) {
                    if($sonYatirim['miktar'] < $bonus['min_yatirim_sarti']) {
                        $bonusHakki = false;
                        $hataMesaji = "Son yatırımınız (" . number_format($sonYatirim['miktar'], 2) . " TL) kayıp bonusu için yetersiz. Minimum " . number_format($bonus['min_yatirim_sarti'], 2) . " TL yatırım yapmalısınız!";
                    }
                }

                // Aynı bonus için son 24 saat kontrolü
                if ($bonusHakki) {
                    $sonBonusKullanim = $db->prepare("SELECT id FROM bonus_kullanim 
                                                    WHERE user_id = ? AND bonus_id = ? AND durum = 1
                                                    AND tarih > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $sonBonusKullanim->execute([$userId, $bonusId]);
                    if($sonBonusKullanim->rowCount() > 0) {
                        $bonusHakki = false;
                        $hataMesaji = "Bu kayıp bonusunu 24 saat içinde tekrar alamazsınız!";
                    }
                }

                // Son yatırıma göre bonus miktarını hesapla
                if ($bonusHakki) {
                    $bonusMiktar = ($sonYatirim['miktar'] * $bonus['yuzde']) / 100;
                    if($bonusMiktar > $bonus['max_miktar']) {
                        $bonusMiktar = $bonus['max_miktar'];
                    }
                    
                    $yatirimMiktari = $sonYatirim['miktar'];
                    $yatirimId = $sonYatirim['id'];
                }
            }
        }
        else {
            $bonusHakki = false;
            $hataMesaji = "Geçersiz bonus türü!";
        }

        // Bonus hakkı yoksa, red tablosuna kaydet ve hata döndür
        if (!$bonusHakki) {
            $redKayit = $db->prepare("INSERT INTO bonus_talep_red (user_id, bonus_id, tarih, ip_adresi, aciklama) 
                                     VALUES (?, ?, NOW(), ?, ?)");
            $redKayit->execute([
                $userId, 
                $bonusId, 
                $ip,
                $hataMesaji
            ]);
            
            $db->commit();
            return ["success" => false, "message" => $hataMesaji];
        }

        // Bonus kullanımını kaydet
        $bonusEkle = $db->prepare("INSERT INTO bonus_kullanim (user_id, bonus_id, miktar, yatirim_miktari, yatirim_id, tarih, ip_adresi, durum, aciklama) 
                                  VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
        
        $durum = 1; // Başarılı
        $aciklama = "Bonus başarıyla tanımlandı!";

        if(!$bonusEkle->execute([
            $userId, 
            $bonusId, 
            $bonusMiktar,
            $yatirimMiktari,
            $yatirimId,
            $ip,
            $durum,
            $aciklama
        ])) {
            $db->rollBack();
            throw new Exception("Bonus kaydedilirken bir hata oluştu!");
        }

        // Bakiye güncelle
        $bakiyeGuncelle = $db->prepare("UPDATE kullanicilar SET ana_bakiye = ana_bakiye + ? WHERE id = ?");
        if(!$bakiyeGuncelle->execute([$bonusMiktar, $userId])) {
            $db->rollBack();
            throw new Exception("Bakiye güncellenirken bir hata oluştu!");
        }

        // İşlemi onayla
        $db->commit();
        
        return [
            "success" => true, 
            "message" => "Bonus başarıyla tanımlandı!", 
            "miktar" => $bonusMiktar
        ];

    } catch (Exception $e) {
        if($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Bonus Hata: " . $e->getMessage());
        
        // Hata durumunda da red tablosuna kaydet
        try {
            $redKayit = $db->prepare("INSERT INTO bonus_talep_red (user_id, bonus_id, tarih, ip_adresi, aciklama) 
                                     VALUES (?, ?, NOW(), ?, ?)");
            $redKayit->execute([
                $userId, 
                $bonusId, 
                $ip,
                "İşlem sırasında bir hata oluştu: " . $e->getMessage()
            ]);
        } catch (Exception $insertEx) {
            error_log("Red kaydı sırasında hata: " . $insertEx->getMessage());
        }
        
        return ["success" => false, "message" => "İşlem sırasında bir hata oluştu: " . $e->getMessage()];
    }
}

// AJAX isteği kontrolü
try {
    if(!isset($_POST['bonusId'])) {
        throw new Exception("Bonus ID bulunamadı!");
    }

    if(!isset($_SESSION['username'])) {
        throw new Exception("Oturum bulunamadı!");
    }

    // 10 saniyelik bekleme süresi kontrolü
    if(isset($_SESSION['bonus_talep_' . $_POST['bonusId']])) {
        $sonTalepZamani = $_SESSION['bonus_talep_' . $_POST['bonusId']];
        $gecenSure = time() - $sonTalepZamani;
        
        if($gecenSure < 10) { // 10 saniye
            $kalanSure = 10 - $gecenSure;
            throw new Exception("Lütfen " . $kalanSure . " saniye bekleyin!");
        }
    }

    // Username'den user ID'yi al
    $usernameSorgu = $db->prepare("SELECT id, username FROM kullanicilar WHERE username = ?");
    $usernameSorgu->execute([$_SESSION['username']]);
    $user = $usernameSorgu->fetch(PDO::FETCH_ASSOC);
    
    if(!$user) {
        throw new Exception("Kullanıcı bulunamadı!");
    }

    $userId = $user['id'];
    $bonusId = $_POST['bonusId'];
    $ip = $_SERVER['REMOTE_ADDR'];

    // Bonus kontrolünü yap
    $sonuc = bonusTalepKontrol($userId, $bonusId, $ip);
    
    // Her talep sonrası session'a zamanı kaydet
    $_SESSION['bonus_talep_' . $bonusId] = time();
    
    // Önceki çıktıları temizle
    ob_end_clean();
    
    echo json_encode($sonuc);
    exit;

} catch (Exception $e) {
    error_log("Bonus İşlem Hatası: " . $e->getMessage());
    
    // Önceki çıktıları temizle
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?> 