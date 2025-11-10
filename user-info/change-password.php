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
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/account-activity/jackpot-history"><i class="fa fa-history"></i> Jackpot Geçmişi</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/financial-transactions" class=""><i class="fa fa-pie-chart"></i> Finans Geçmişim</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/bonus-activities"><i class="fa fa-gift"></i> Bonus Hareketlerim</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/casino-pro-history" class=""><i class="fa fa-history"></i> Casinopro Geçmişi</a><!----><!----></li>


                                    </ul>
                                </div><!----><!---->
                            </li>
                            <li class="active">
                                <div class="collapsible-header active">Kullanıcı Hareketleri</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
<li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/personal-and-account-detail"><i class="fa fa-user"></i> Kişisel ve Hesap Bilgilerim</a><!----><!----></li>
<li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/user-details"><i class="fa fa-user-plus"></i> Kişisel Bilgiler</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/account-details"><i class="fa fa-user-plus"></i> Hesap Bilgileri</a><!----><!----></li>
<li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/change-password" class="active"><i class="fa fa-unlock-alt"></i> Şifremi Değiştir</a><!----><!----></li>

                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/user-security"><i class="fa fa-check-square-o"></i> İki adımlı doğrulama</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/friend-referral"><i class="fa fa-users"></i> Arkadaş referansı</a><!----><!----></li><!---->
                                    </ul>
                                </div><!----><!---->
                            </li><!---->
                        </ul>
                    </div>
                </div>
                
                
                
                
                
                <?php
include '../../config.php';

// Kullanıcı oturum id'sini al
if (!isset($_SESSION['id'])) {
    die("Kullanıcı oturumu geçerli değil.");
}
$userId = $_SESSION['id'];

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';

    // Şifre kriterlerini kontrol et
    if (!preg_match('/[A-Z]/', $newPassword)) {
        $error = "Şifreniz en az 1 büyük harf içermeli.";
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = "Şifreniz en az 1 küçük harf içermeli.";
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = "Şifreniz en az 1 nümerik değer içermeli.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Şifreniz en az 8 karakter içermeli.";
    } else {
        try {
            // Kullanıcının mevcut şifresini al
            $stmt = $db->prepare("SELECT password FROM kullanicilar WHERE id = :id");
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Kullanıcı bulunamadı.";
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $error = "Mevcut şifreniz yanlış.";
            } else {
                // Yeni şifreyi hashle ve güncelle
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE kullanicilar SET password = :password WHERE id = :id");
                $updateStmt->bindParam(':password', $newPasswordHash);
                $updateStmt->bindParam(':id', $userId, PDO::PARAM_INT);

                if ($updateStmt->execute()) {
                    $success = "Şifreniz başarıyla değiştirildi.";
                } else {
                    $error = "Şifre değiştirilirken bir hata oluştu.";
                }
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}
?>
                
                
                
                
    <div class="col s9 rght-cntnt"><router-outlet></router-outlet><app-change-password>
               
               
                <div class="chng-psswrd-cntnt">
                        <div class="dshbrd-mdl">
                            <div class="mdl-hdr">
                                <div class="inf-hdr"><i class="fa fa-unlock-alt fa-fw"></i><span class="inf-title">Şifremi Değiştir</span></div>
                            </div>
                            <div class="mdl-cntnt">
                                <?php if (!empty($error)): ?>
                                    <div class="card-panel message-box error">
                                        <div><?php echo htmlspecialchars($error); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($success)): ?>
                                    <div class="card-panel message-box success">
                                        <div><?php echo htmlspecialchars($success); ?></div>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate name="changePasswordForm" class="ng-untouched ng-pristine ng-invalid">
                                    <div class="input-field bubble">
                                        <input type="password" name="currentPassword" id="current-password" required class="browser-default ng-untouched ng-pristine ng-invalid" placeholder="Eski Şifre">
                                        <password-eye el="current-password">
                                            <a class="btn password-eye toogle-btn">
                                                <i class="fa fa-eye show"></i>
                                                <i class="fa fa-eye-slash dont-show"></i>
                                            </a>
                                        </password-eye>
                                    </div>
                                    <div class="input-field bubble">
                                        <input type="password" name="newPassword" id="new-password" required class="browser-default ng-untouched ng-pristine ng-invalid" placeholder="Yeni Şifre">
                                        <bubble-validator>
                                            <div class="bubble-vldtr">
                                                <ul class="vldtr-lst">
                                                    <li><i class="fa fa-check-circle-o"></i><span>Şifreniz en az 1 büyük harf içermeli. </span></li>
                                                    <li><i class="fa fa-check-circle-o"></i><span>Şifreniz en az 1 küçük harf içermeli. </span></li>
                                                    <li><i class="fa fa-check-circle-o"></i><span>Şifreniz en az 1 nümerik değer içermeli. </span></li>
                                                    <li><i class="fa fa-check-circle-o"></i><span>Şifreniz en az 8 karakter içermeli. </span></li>
                                                </ul>
                                            </div>
                                        </bubble-validator>
                                        <password-eye el="new-password">
                                            <a class="btn password-eye toogle-btn">
                                                <i class="fa fa-eye show"></i>
                                                <i class="fa fa-eye-slash dont-show"></i>
                                            </a>
                                        </password-eye>
                                    </div>
                                    <div class="input-field das-change-pass">
                                        <button type="submit" class="btn" <?php echo $buttonDisabled; ?>>Değiştir</button>

                                    </div>
                                </form>
                                
                                
                                </div>
                            </div>
                        </div>
                    </app-change-password></div>
            </div>
        </div>
    </app-dashboard></main></div>
<script>
    
    document.querySelectorAll('.password-eye').forEach(function(toggleBtn) {
    toggleBtn.addEventListener('click', function(event) {
        event.preventDefault();

        const inputId = toggleBtn.parentElement.getAttribute('el');
        const passwordInput = document.getElementById(inputId);

        if (passwordInput) {
            const showIcon = toggleBtn.querySelector('.show');
            const hideIcon = toggleBtn.querySelector('.dont-show');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                showIcon.style.display = 'none';
                hideIcon.style.display = 'inline';
            } else {
                passwordInput.type = 'password';
                showIcon.style.display = 'inline';
                hideIcon.style.display = 'none';
            }
        }
    });
});

// Sayfa yüklendiğinde hata durumuna göre "disabled" durumunu kaldır
window.addEventListener('load', function () {
    const errorBox = document.querySelector('.message-box.error');
    const submitButton = document.querySelector('button[type="submit"]');
    
    if (errorBox) {
        submitButton.removeAttribute('disabled');
    }
});

</script>
<?php include '../../inc/footer.php' ?>