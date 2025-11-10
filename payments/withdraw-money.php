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

// Veritabanı bağlantısı
$host = 'localhost';
$dbname = 'u260321069_ana1';
$user = 'u260321069_ana1';
$pass = 'sifrexnaEFVanavt88';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Connection failed: ' . $e->getMessage());
    exit();
}

// Kullanıcı detaylarını al
$stmt = $pdo->prepare("SELECT firstName, surname, ana_bakiye FROM kullanicilar WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$adsoyad = $user['firstName'] . ' ' . $user['surname'];
$guncelBakiye = $user['ana_bakiye'];

$siteShort = "https://" . $_SERVER["HTTP_HOST"] . "/";

$cekimYontemleri = [
    "havale" => "Havale/EFT",
    "papara" => "Papara",
    "paratim" => "Paratim",
    "parolapara" => "parolapara",
    "popy" => "Popy",
    "crypto" => "Kripto"
];

if(isset($_POST["cekim"])) {
    $method = $_POST["method"];
    $miktar = $_POST["miktar"];
    $iban = $_POST["iban"];
    $bank_name = $_POST["bank_name"];
    
    if($guncelBakiye < $miktar) {
        echo '<div class="col-12 text-center mt-4"> 
                <div class="alert alert-danger"> 
                    '.$miktar.' tutarında bir çekim talebi oluşturamazsınız. Talebiniz '.$guncelBakiye.' nin üstünde. 
                </div> 
              </div>';
    } else {
        // Yönlendirme yap
        switch($method) {
            case 'havale':
                header("Location: " . $siteShort . "payment/havale.php");
                break;
            case 'papara':
                header("Location: " . $siteShort . "payment/papara.php");
                break;
            case 'paratim':
                header("Location: " . $siteShort . "payment/paratim.php");
                break;
                case 'parolapara':
                header("Location: " . $siteShort . "payment/parolapara.php");
                break;
            case 'crypto':
                header("Location: " . $siteShort . "payment/kripto.php");
                break;
                   case 'popy':
                header("Location: " . $siteShort . "payment/popy.php");
                break;
            default:
                echo '<div class="alert alert-danger">Geçersiz ödeme yöntemi.</div>';
        }
        exit();
    }
}
?>


<?php include '../../inc/head.php' ?>
<?php include '../../inc/header.php' ?>

<link rel="stylesheet" href="../../yatirimngs.css">

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
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/payments/withdraw-money" class="active"><i class="fa fa-money"></i> Para Çekme</a><!----><!----></li>

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
<li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/change-password"><i class="fa fa-unlock-alt"></i> Şifremi Değiştir</a><!----><!----></li>

                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/user-info/user-security"><i class="fa fa-check-square-o"></i> İki adımlı doğrulama</a><!----><!----></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active" href="/dashboard/user-info/friend-referral"><i class="fa fa-users"></i> Arkadaş referansı</a><!----><!----></li><!---->
                                    </ul>
                                </div><!----><!---->
                            </li><!---->
                        </ul>
                    </div>
                </div>
                
             <link rel="stylesheet" href="../../yatirim/yatirimngs.css">

 <div class="col s9 rght-cntnt"><router-outlet></router-outlet><app-withdraw-money><deposit-withdraw-money>
                            <div class="pymnt-cntnt">
                                <div class="dshbrd-mdl dshbrd-hide-block">
                                    <div class="mdl-hdr">
                                        <div class="inf-hdr"><i aria-hidden="true" class="fa fa-credit-card-alt"></i><span class="inf-title">Para Çekme</span></div>
                                    </div>
                                    <div class="mdl-cntnt">
                                        <p>Para çekme seçenekleri aşağıda listelenmiştir. Lütfen para çekme türünü seçip hesabınızdan kolayca para çekmek için talimatları takip ediniz.</p><app-static-inner-content contentcode="withdraw-link-top"></app-static-inner-content>
                                    </div><app-notifications>
                                        <div></div>
                                    </app-notifications>
                                </div>
                                
                                


<div class="dshbrd-mdl">
    <div class="mdl-hdr">
        <div class="inf-hdr"><i aria-hidden="true" class="fa fa-credit-card-alt"></i><span class="inf-title">Para Çekme Seçenekleri</span></div>
    </div>
    <div class="mdl-cntnt clear">
        <?php foreach($cekimYontemleri as $key => $value): ?>
        <div class="col s12">
            <div class="card-panel bank-card" data-payment="<?php echo $key; ?>">
                <div class="flex-container">
                    <payment-icon>
                        <img src="/TrendXPAY/xpay<?php echo $key; ?>.png" width="100" height="33" class="">
                    </payment-icon>
                    <div class="flex-item">
                        <div class="bnk-inf-fld bnk-fisrts">
                            <h5><?php echo $value; ?></h5>
                            <small><?php echo $value; ?> ile Para Çekme</small>
                        </div>
                    </div>
                    <div class="flex-item">
                        <div class="bnk-inf-fld trans-fee">
                            <h5>İşlem ücreti &amp; İşlem zamanı</h5>
                            <small>Bedava<span>/ 60 <span class="processing-min">Dk</span></span></small>
                        </div>
                    </div>
                    <br>
                    <div class="bnk-inf-fld">
                        <h5>İşlem limiti</h5>
                        <small>Min: ₺200.00 / Max: ₺100,000.00</small>
                    </div>
                    <a href="javascript:;" class="btn right deposit-btn" onclick="window.open('/payment/<?php echo $key; ?>.php', '_blank');">Para Çekme</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
       
        </div>

        
 <!--       <div class="col s12">
         <!--    <div class="card-panel bank-card" data-payment="upaycoins-withdraw">
          <!--       <div class="flex-container"><payment-icon><img src="https://via.placeholder.com/100x33.png?text=YouPayCoin Çekim" class="hide" hidden=""><img src="https://i.ibb.co/jJfstmV/6.png" width="100" height="33" class="upaycoins-withdraw"></payment-icon>
          <!--           <div class="flex-item">
            <!--             <div>
            <!--                 <div class="bnk-inf-fld bnk-fisrts">
                                <h5 title="YouPayCoin Çekim">YouPayCoin Çekim</h5><small>YouPayCoin Çekim ile işlem yap</small>
                            </div>
                        </div>
                    </div>
                    <div class="flex-item">
                        <div class="bnk-inf-fld trans-fee">
                            <h5>İşlem ücreti <span>&amp;</span> İşlem zamanı</h5><small>Bedava</small>
                        </div>
                    </div><br>
                    <div class="bnk-inf-fld">
                        <h5>İşlem limiti</h5><small>Min: ₺200.00 / Max: ₺300,000.00</small>
                    </div>
            <a href="javascript:;" class="btn right deposit-btn" onclick="window.open('/payment/kripto.php', '_blank');">Para Çekme</a>
                </div>
            </div>
        </div> -->
        


</div>
</div>
    </app-dashboard></main>
    
    <?php include '../../inc/footer.php' ?>
