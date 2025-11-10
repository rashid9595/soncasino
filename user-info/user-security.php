<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Oturumu başlat
}

// Kontrol et: Kullanıcı giriş yapmış mı?
if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    // Giriş yapmamışsa, JavaScript ile yönlendir
    echo '<script type="text/javascript">
            window.location.href = "/";
          </script>';
    exit(); // Redireksiyon sonrası script çalışmasın
}

// Kullanıcı bilgilerini al
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Misafir';
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '0000000';
?>

<?php include '../../inc/head.php' ?>
<?php include '../../inc/header.php' ?>


<main><router-outlet></router-outlet><app-dashboard>
        <div class="container dshbrd-cntr">
            <div class="row">
                <div class="col s3 lft-cntnt">
                    <div class="u-info">
                        <h5 class="u-name"><?php echo htmlspecialchars($username); ?><!----></h5>
                        <h5 class="u-number">Kullanıcı Numarası : <?php echo htmlspecialchars($user_id); ?></h5>
                    </div>
                    <div class="dshbrd-sdbr">
                        <ul materialize="collapsible" data-collapsible="expandable" class="collapsible">
                            <li class="active">
                                <div class="collapsible-header active">Hesabım</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/payments/deposit-money" class=""><i class="fa fa-money"></i> Para Yatırma</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/payments/withdraw-money"><i class="fa fa-money"></i> Para Çekme</a><!----><!----></li>

                                        <li class="collection-item" hidden=""><!----><a routerlinkactive="active" href="//ngsbahis799.com/contents/promotions"><i class="fa fa-calendar-plus-o"></i> Şikayet ve Öneriler</a><!----></li><!---->
                                    </ul>
                                </div><!----><!---->
                            </li>
                            <li class="active">
                                <div class="collapsible-header active">İşlemler</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/bet-history"><i class="fa fa-history"></i> Bahis Geçmişi</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/financial-transactions" class=""><i class="fa fa-pie-chart"></i> Finans Geçmişim</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/bonus-activities"><i class="fa fa-gift"></i> Bonus Hareketlerim</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/casino-pro-history"><i class="fa fa-history"></i> Casinopro Geçmişi</a><!----><!----></li>

                                    </ul>
                                </div><!----><!---->
                            </li>
                            <li class="active">
                                <div class="collapsible-header active">Kullanıcı Hareketleri</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/personal-and-account-detail" class=""><i class="fa fa-user"></i> Kişisel ve Hesap Bilgilerim</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/user-details"><i class="fa fa-user-plus"></i> Kişisel Bilgiler</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/account-details"><i class="fa fa-user-plus"></i> Hesap Bilgileri</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/change-password" class=""><i class="fa fa-unlock-alt"></i> Şifremi Değiştir</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/user-security" class="active"><i class="fa fa-check-square-o"></i> İki adımlı doğrulama</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/friend-referral"><i class="fa fa-users"></i> Arkadaş referansı</a><!----><!----></li><!---->
                                    </ul>
                                </div><!----><!---->
                            </li><!---->
                        </ul>
                    </div>
                </div>
                
                
                
                
                
                
                
                
                
          <div class="col s9 rght-cntnt"><router-outlet></router-outlet><app-user-security>
        <div class="user-security-cntnt">
            <div class="dshbrd-mdl">
                <div class="mdl-hdr">
                    <div class="inf-hdr"><i class="fa fa-qrcode fa-fw"></i><span class="inf-title">İki adımlı doğrulama</span></div>
                </div>
                
                <div class="mdl-cntnt"><app-static-inner-content contentcode="otp_info"><!----></app-static-inner-content>
                    <!-- -->
                    
                  <?php
include '../../config.php'; // Veritabanı bağlantısını ekle

// Oturum kontrolü
if (!isset($_SESSION['id'])) {
    echo "<p>Kullanıcı oturumu bulunamadı.</p>";
    exit();
}

try {
    // Kullanıcının 2FA durumunu kontrol et
    $query = "SELECT twofactor FROM kullanicilar WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
    $stmt->execute();
    $twoFactorStatus = $stmt->fetchColumn();

    // Duruma göre içerik belirleme
    if ($twoFactorStatus === 'aktif') {
        echo "<p>Google Authenticator aktif durumda. 2FA'yı kaldırmak için canlı desteğe bağlanınız.</p>";
    } else {
        // Google Authenticator kurulum adımları
        echo <<<HTML
        <div class="radio-field">
            <h5 class="radio-hdr">Doğrulama tipi</h5>
            <div class="radio-cntnt">
                <div class="radio-grp">
                    <input name="twoStepAuthentication" type="radio" id="GA" value="gaValidation">
                    <label for="GA">Google Authenticator</label>
                </div>
            </div>
        </div>
        <div class="ga-information-ct">
            <p>Google Authenticator'ı aktifleştirmek için aşağıdaki adımları takip ediniz:</p>
            <ol>
                <li>Apple Store ya da Android Market'ten Google Authenticator uygulamasını indirin.</li>
                <li>Doğrulama yöntemi olarak GA yapılması için talebinizi oluşturun.</li>
                <li>Bu talep sonrasında mail adresinize Google tarafından bir link gönderilecektir.</li>
                <li>Gönderilen linki açtığınızda karşınıza bir QR code çıkacaktır.</li>
                <li>GA uygulamasını açarak gelen ekranda QR kodunu okutunuz.</li>
                <li>Sisteme giriş esnasında GA kodunu girerek girişinizi tamamlayabilirsiniz.</li>
            </ol>
            <div class="checkbox-field">
                <input type="checkbox" required name="agreeGA" id="agreeGA">
                <label for="agreeGA">
                    <span class="required-icon"></span> Google Authenticator aktif etme adımlarını okudum, onaylıyorum
                </label>
            </div>
            <div class="input-field clear">
                <button id="sendEmailButton" class="btn right clear" disabled>Kabul ediyorum</button>
            </div>
        </div>
HTML;
    }

} catch (PDOException $e) {
    echo "<p>Bir hata oluştu: " . $e->getMessage() . "</p>";
}
?>

                           
                            
                            
                            
                            <script>
document.getElementById('sendEmailButton').addEventListener('click', function() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../../dashboard/user-info/send_email.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        // Alert kaldırıldı, sessizce devam edecek
    };
    xhr.send();
});
</script>

                    </div><!----><!----><!----><!----><!---->
                </div>
            </div>
        </div>
        
        
        <div id="smsModal" materialize="modal" class="modal modal-sm open dshbrd-modal" style="z-index: 1045;">
            <div class="modal-content">
                <div class="modul-accordion">
                    <div class="modal-close-button"><a><i class="fa fa-times right"></i></a></div>
                </div>
                <div class="modul-content"><b>Lütfen sistemde kayıtlı telefon numaranıza gelen SMS deki doğrulama kodunu giriniz.</b>
                    <form novalidate="" class="ng-untouched ng-pristine ng-invalid">
                        <div class="input-field"><input id="SmsCode" name="smsCode" type="password" required="" maxlength="6" class="browser-default ng-untouched ng-pristine ng-invalid" placeholder="SMS şifresi" wfd-id="id5"></div>
                        <div class="remaning-time-cntnt"><span class="remaning-time"> Kalan Süre: sn. </span><a href="javascript:;" class="right disabled"> SMS şifremi tekrar gönder <i class="fa fa-refresh fa-fw fa-spin"></i></a></div><button type="submit" class="btn" disabled="">Gönder</button>
                    </form>
                </div>
            </div>
        </div>
        
        


        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
<div id="gaModal" class="modal modal-sm dshbrd-modal" style="display: none;">
    <div class="modal-content">
        <div class="modul-accordion">
            <div class="modal-close-button">
                <a href="javascript:void(0)" id="closeModal"><i class="fa fa-times right"></i></a>
            </div>
        </div>
        <div class="modul-content">
            <b>Lütfen Google Authenticator şifrenizi giriniz</b>
            <form novalidate="" method="POST" action="">
                <div class="input-field">
                    <input id="OtpPassword" name="otpPassword" type="text" required placeholder="Google Authenticator Şifresi">
                </div>
                <button type="submit" class="btn">Gönder</button>
            </form>
        </div>
    </div></div></div></div></div></div>
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>

<script>
    // Eğer başarılı bir doğrulama yapıldıysa, modal kapanacak ve alert gösterilecek
    document.getElementById('closeModal').addEventListener('click', function() {
        document.getElementById('gaModal').style.display = 'none';
    });

    // Modal'ı gösterme fonksiyonu
    function showModal() {
        document.getElementById('gaModal').style.display = 'block';
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script>
    // Formu gönderme işlemi
    document.getElementById('verifyForm').addEventListener('submit', function (e) {
        e.preventDefault();
        
        // Kullanıcı tarafından girilen doğrulama kodunu al
        const otpPassword = document.getElementById('OtpPassword').value;

        // Form verilerini POST olarak gönder
        fetch('', {
            method: 'POST',
            body: new URLSearchParams({
                otpPassword: otpPassword
            }),
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  // Eğer doğrulama başarılıysa SweetAlert ile başarı mesajı göster
                  Swal.fire({
                      icon: 'success',
                      title: 'Başarılı',
                      text: 'Hesabınız başarıyla doğrulandı!'
                  }).then(() => {
                      // Modalı kapat
                      document.getElementById('gaModal').style.display = 'none';
                  });
              } else {
                  // Eğer doğrulama başarısızsa SweetAlert ile hata mesajı göster
                  Swal.fire({
                      icon: 'error',
                      title: 'Hata',
                      text: data.message
                  });
              }
          });
    });
</script>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Oturumu başlat
}

require '../../vendor/autoload.php'; // Composer autoload
use OTPHP\TOTP; // OTPHP kütüphanesini kullanıyoruz

// Bu sınıf, 2FA işlemlerini yönetiyor
class GoogleAuthenticator {
    private $db;
    private $userId;
    private $email;
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->email = $this->getUserEmail();
    }
    
    private function getUserEmail() {
        $query = "SELECT email FROM kullanicilar WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $this->userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    // Doğrulama kodunu kontrol eden fonksiyon
    public function verifyCode($secretKey, $inputCode) {
        $totp = TOTP::create($secretKey); // Secret key ile TOTP oluşturuyoruz
        
        // Google Authenticator'ın oluşturduğu kodu doğruluyoruz
        if ($totp->verify($inputCode)) {
            return true;  // Kod doğru
        } else {
            return false; // Kod yanlış
        }
    }
}

// Oturum kontrolü ve doğrulama işlemi
try {
    // Oturum kontrolü: Kullanıcı oturum açmış mı?
    if (!isset($_SESSION['id'])) {
        throw new Exception("No active session found");
    }

    // Veritabanı bağlantısı (örnek)
    $db = new PDO('mysql:host=localhost;dbname=AnaVeritabani', 'u260321069_ana1', 'sifrexnaEFVanavt88');  // Veritabanı bağlantını yap
    
    // GoogleAuthenticator sınıfını başlat
    $auth = new GoogleAuthenticator($db, $_SESSION['id']);
    
    // Kullanıcının secret key'ini al
    $query = "SELECT secret_key FROM kullanicilar WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
    $stmt->execute();
    $secretKey = $stmt->fetchColumn(); // Kullanıcının secret key'ini alıyoruz
    
    // Kullanıcıdan gelen doğrulama kodunu al
    if (isset($_POST['otpPassword'])) {
        $inputCode = $_POST['otpPassword']; // Kullanıcının girdiği doğrulama kodu
        
        // Kodu doğrula
        if ($auth->verifyCode($secretKey, $inputCode)) {
            // Başarılı, veritabanında "twofactor" değerini "aktif" yap
            $updateQuery = "UPDATE kullanicilar SET twofactor = 'aktif' WHERE id = :id";
            $stmt = $db->prepare($updateQuery);
            $stmt->bindParam(':id', $_SESSION['id'], PDO::PARAM_INT);
            $stmt->execute();

            // Başarıyla doğrulandı, modal göster
            echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: 'Hesabınız başarıyla doğrulandı ve 2FA aktif oldu!'
                    }).then(function() {
                        // İlgili sayfaya yönlendirme veya modal'ı kapatma
                        document.getElementById('gaModal').style.display = 'none';
                    });
                  </script>";
        } else {
            // Hatalı kod, hata mesajı ile birlikte modal'ı aç
            echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Hata',
                        text: 'Kod yanlış! Lütfen tekrar deneyin.'
                    }).then(function() {
                        document.getElementById('gaModal').style.display = 'block';
                    });
                  </script>";
        }
    } else {
        echo "";
    }

} catch (Exception $e) {
    error_log("2FA setup failed: " . $e->getMessage());
    echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Hata',
                text: 'Bir hata oluştu! Lütfen tekrar deneyin.'
            });
          </script>";
}
?>



       <script>
    // Checkbox kontrolü ve buton aktifleştirme
    document.getElementById('agreeGA').addEventListener('change', function () {
        document.querySelector('.btn.right.clear').disabled = !this.checked;
    });

    // Modal açma
    document.querySelector('.btn.right.clear').addEventListener('click', function (event) {
        event.preventDefault();
        if (!this.disabled) {
            const modal = document.getElementById('gaModal');
            modal.style.display = 'block';
        }
    });

    // Modal kapatma
    document.getElementById('closeModal').addEventListener('click', function () {
        const modal = document.getElementById('gaModal');
        modal.style.display = 'none';
    });
</script>

        
        <div id="emailModal" materialize="modal" class="modal modal-md open dshbrd-modal" style="z-index: 1049;">
            <div class="modal-content"><a href="javascript:;" class="modal-action modal-close"><i class="fa fa-times"></i></a>
                <div class="modul-content"><b>Lütfen sistemde kayıtlı e-postanıza gönderilen şifreyi giriniz.</b>
                    <form novalidate="" class="ng-untouched ng-pristine ng-invalid">
                        <div class="input-field"><input id="emailCode" name="emailCode" type="text" required="" maxlength="6" class="browser-default ng-untouched ng-pristine ng-invalid" placeholder="E-mail kodu" wfd-id="id7"></div><button type="submit" class="btn" disabled="">Gönder</button>
                    </form>
                </div>
            </div>
        </div>
    </app-user-security><!---->
    

<?php include '../../inc/footer.php' ?>