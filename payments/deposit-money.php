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

// Ödeme yöntemlerini JSON dosyasından oku
$jsonFile = __DIR__ . '/../../data/payment_methods.json';
$paymentMethods = [];

if (file_exists($jsonFile)) {
    $jsonContent = file_get_contents($jsonFile);
    $paymentMethods = json_decode($jsonContent, true);
}
?>

<?php include '../../inc/head.php' ?>
<?php include '../../inc/header.php' ?>
</div>
 <link rel="stylesheet" href="../../yatirim/yatirimngs.css">


<main><router-outlet></router-outlet><app-dashboard>
        <div class="container dshbrd-cntr">
            <div class="row">
                <div class="col s3 lft-cntnt">
                    <div class="u-info">
                        <h5 class="u-name"><?php echo htmlspecialchars($username); ?></h5>
                        <h5 class="u-number">Kullanıcı Numarası : <?php echo htmlspecialchars($user_id); ?></h5>
                    </div>
                    <div class="dshbrd-sdbr">
                        <ul materialize="collapsible" data-collapsible="expandable" class="collapsible">
                            <li class="active">
                                <div class="collapsible-header active">Hesabım</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/payments/deposit-money" class="active"><i
                                                    class="fa fa-money"></i> Para Yatırma</a></li>
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/payments/withdraw-money"><i class="fa fa-money"></i>
                                                Para Çekme</a></li>

                                        <li class="collection-item" hidden=""><a routerlinkactive="active"
                                                href="//ngsbahis799.com/contents/promotions"><i
                                                    class="fa fa-calendar-plus-o"></i> Şikayet ve Öneriler</a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                            <li class="active">
                                <div class="collapsible-header active">İşlemler</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/account-activity/bet-history"><i
                                                    class="fa fa-history"></i> Bahis Geçmişi</a></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active"
                                                href="/dashboard/account-activity/jackpot-history"><i
                                                    class="fa fa-history"></i> Jackpot Geçmişi</a></li>
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/account-activity/financial-transactions" class=""><i
                                                    class="fa fa-pie-chart"></i> Finans Geçmişim</a></li>
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/account-activity/bonus-activities"><i
                                                    class="fa fa-gift"></i> Bonus Hareketlerim</a></li>
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/account-activity/casino-pro-history" class=""><i
                                                    class="fa fa-history"></i> Casinopro Geçmişi</a></li>


                                    </ul>
                                </div>
                            </li>
                            <li class="active">
                                <div class="collapsible-header active">Kullanıcı Hareketleri</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/user-info/personal-and-account-detail"><i
                                                    class="fa fa-user"></i> Kişisel ve Hesap
                                                Bilgilerim</a></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active"
                                                href="/dashboard/user-info/user-details"><i class="fa fa-user-plus"></i>
                                                Kişisel Bilgiler</a></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active"
                                                href="/dashboard/user-info/account-details"><i
                                                    class="fa fa-user-plus"></i> Hesap Bilgileri</a></li>
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/user-info/change-password"><i
                                                    class="fa fa-unlock-alt"></i> Şifremi Değiştir</a>
                                        </li>

                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/user-info/user-security"><i
                                                    class="fa fa-check-square-o"></i> İki adımlı
                                                doğrulama</a></li>
                                        <li class="collection-item" hidden=""><a routerlinkactive="active"
                                                href="/dashboard/user-info/friend-referral"><i class="fa fa-users"></i>
                                                Arkadaş referansı</a></li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>


                <div class="col s9 rght-cntnt"><router-outlet></router-outlet><app-deposit-money><deposit-withdraw-money>
                            <div class="pymnt-cntnt">
                                <div class="dshbrd-mdl dshbrd-hide-block">
                                    <div class="mdl-hdr">
                                        <div class="inf-hdr"><i aria-hidden="true" class="fa fa-credit-card-alt"></i><span class="inf-title">Para Yatırma</span></div>
                                    </div>
                                    <div class="mdl-cntnt">
                                        <p>Para yatırma seçenekleri aşağıda listelenmiştir. Lütfen para yatırma türünü seçip hesabınıza kolayca yatırmak için talimatları takip ediniz.</p>
                                        <app-static-inner-content contentcode="deposit-link-top">
                                            
                                        </app-static-inner-content>
                                    </div>
                                    <app-notifications>
                                        <div></div>
                                    </app-notifications>
                                </div>
                                <div class="dshbrd-mdl">
                                    <div class="mdl-hdr">
                                        <div class="inf-hdr"><i aria-hidden="true" class="fa fa-credit-card-alt"></i><span class="inf-title">Para Yatırma Seçenekleri</span></div>
                                    </div>
                                    <div class="mdl-cntnt clear"><app-static-inner-content contentcode="deposit-link">
                                            <div class="pay-container pay-deposit2">
                                                <?php foreach ($paymentMethods as $method): ?>
                                                <div class="pay-item" onclick="openPaymentMethod('<?php echo $method['route']; ?>')" style="cursor: pointer;">
                                                    <div class="pay-l">
                                                        <div class="pay-img">
                                                            <img src="<?php echo $method['image_url']; ?>" alt="<?php echo $method['title']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="pay-r">
                                                        <div class="pay-title">
                                                            <p>
                                                                <span><?php echo $method['title']; ?></span>
                                                                <span><?php echo $method['subtitle']; ?></span>
                                                            </p>
                                                        </div>
                                                        <div class="pay-desc">
                                                            <p class="trancate"><?php echo $method['description']; ?></p>
                                                        </div>
                                                        <div class="pay-link">
                                                            <span class="pay-btn myBtn havale">
                                                                <img src="<?php echo $method['button_icon']; ?>">
                                                                <span></span> <?php echo $method['button_text']; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <script>
                                            function openPaymentMethod(url) {
                                                const width = 1700;
                                                const height = 687;
                                                const left = (screen.width / 2) - (width / 2);
                                                const top = (screen.height / 2) - (height / 2);
                                                
                                                window.open(url, '_blank', `toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=${width}, height=${height}, top=${top}, left=${left}`);
                                            }
                                            </script>
                                        </div></div></div></div>



                                </div><app-static-inner-content contentcode="deposit-bottom"></app-static-inner-content>
                            </div>
                            <div id="PaymentFormModal" materialize="modal" class="modal open dshbrd-modal pymnt-mdl" style="z-index: 1075;"></div>
                            <div id="confirmPromptModal" materialize="modal" class="modal modal-sm dshbrd-modal" style="z-index: 1077;"><app-confirm-promt></app-confirm-promt></div>
                        </deposit-withdraw-money></app-deposit-money></div>
            </div>
        </div>
    </app-dashboard></main>

        <?php include '../../inc/footer.php' ?>