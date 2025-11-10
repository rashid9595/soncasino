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
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/personal-and-account-detail" class="active"><i class="fa fa-user"></i> Kişisel ve Hesap Bilgilerim</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/user-details"><i class="fa fa-user-plus"></i> Kişisel Bilgiler</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/account-details"><i class="fa fa-user-plus"></i> Hesap Bilgileri</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/change-password" class=""><i class="fa fa-unlock-alt"></i> Şifremi Değiştir</a><!----><!----></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/user-security"><i class="fa fa-check-square-o"></i> İki adımlı doğrulama</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/friend-referral"><i class="fa fa-users"></i> Arkadaş referansı</a><!----><!----></li><!---->
                                    </ul>
                                </div><!----><!---->
                            </li><!---->
                        </ul>
                    </div>
                </div>
                
                
                <div class="col s9 rght-cntnt"><router-outlet></router-outlet><app-personal-and-account-detail>
                        <div class="prsnl-accnt-inf-cntnt">
                            <div class="dshbrd-mdl">
                                <div class="mdl-hdr">
                                    <div class="inf-hdr"><i class="material-icons">supervisor_account</i><label class="inf-title">Kişisel ve Hesap Bilgilerim</label></div>
                                </div>
                                
<?php
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Misafir';
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '0000000';

include '../../config.php';

function maskData($data, $visibleStart = 2, $maskChar = '*') {
    $length = strlen($data);
    if ($length <= $visibleStart) {
        return str_repeat($maskChar, $length);
    }
    $maskedPortion = str_repeat($maskChar, $length - $visibleStart);
    return substr($data, 0, $visibleStart) . $maskedPortion;
}

function maskEmail($email) {
    $parts = explode('@', $email);
    $localPart = substr($parts[0], 0, 3) . str_repeat('*', max(0, strlen($parts[0]) - 3));
    return $localPart . '@*****.***';
}

try {
    // Kullanıcı bilgilerini id ile getir
    $stmt = $db->prepare("SELECT * FROM kullanicilar WHERE id = :id");
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo '<div class="mdl-cntnt">';
        echo '<table class="striped">';
        echo '<tbody>';
        echo '<tr><td>Kullanıcı Adı</td><td>' . htmlspecialchars($user['username']) . '</td></tr>';
        echo '<tr><td>E-posta</td><td>' . htmlspecialchars(maskEmail($user['email'])) . '</td></tr>';
        echo '<tr><td>Para Birimi</td><td>TRY</td></tr>';
        echo '<tr><td>Kullanıcı Numarası</td><td>' . htmlspecialchars($user['id']) . '</td></tr>';
        echo '<tr><td>Ad</td><td>' . htmlspecialchars($user['firstName']) . '</td></tr>';
        echo '<tr><td>Soyad</td><td>' . htmlspecialchars($user['surname']) . '</td></tr>';
        echo '<tr><td>Doğum Tarihi</td><td>' . maskData($user['birthDay'], 1) . '/' . maskData($user['birthMonth'], 1) . '/' . maskData($user['birthYear'], 2) . '</td></tr>';
        echo '<tr><td>Kimlik</td><td>' . maskData($user['identity'], 2) . '</td></tr>';
        echo '<tr><td>Güvenli Kelime</td><td>' . maskData($user['safeWord'], 3) . '</td></tr>';
        echo '<tr><td>Telefon</td><td>' . maskData($user['phone'], 3) . '</td></tr>';

        // Cinsiyet bilgisi için kontrol
        $genderText = 'Belirtilmemiş';
        if ($user['gender'] == 1) {
            $genderText = 'Erkek';
        } elseif ($user['gender'] == 2) {
            $genderText = 'Kadın';
        }
        echo '<tr><td>Cinsiyet</td><td>' . $genderText . '</td></tr>';

        // Ülke bilgisi kontrolü
        $countryText = ($user['countryId'] == 1) ? 'Türkiye' : 'Bilinmiyor';
        echo '<tr><td>Ülke</td><td>' . $countryText . '</td></tr>';

        echo '<tr><td>Şehir</td><td>' . htmlspecialchars($user['cityName']) . '</td></tr>';
        echo '<tr><td>Adres</td><td>' . maskData($user['address'], 5) . '</td></tr>';

        echo '</tbody></table></div>';
    } else {
        echo 'Kullanıcı bulunamadı.';
    }

} catch (PDOException $e) {
    echo 'Bir hata oluştu: ' . $e->getMessage();
}
?>


                                </div>
                            </div>
                        </div><phone-verify><!----></phone-verify>
                    </app-personal-and-account-detail><!----></div>
            </div>
        </div>
    </app-dashboard><!----></main>

<?php include '../../inc/footer.php' ?>