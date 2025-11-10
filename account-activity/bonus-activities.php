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
href="/dashboard/payments/deposit-money"><i
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

<li class="collection-item"><a routerlinkactive="active" href="/tr/dashboard/account-activity/bonus-activities" class="active"><i class="fa fa-gift"></i> Bonus Hareketlerim</a><!----><!----></li>
<li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/casino-pro-history"><i class="fa fa-history"></i> Casinopro Geçmişi</a><!----><!----></li> </ul>
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

<div class="col s9 rght-cntnt"><router-outlet></router-outlet><app-bonus-activities><div class="bns-actvts-cntnt"><app-notifications><div><!----></div></app-notifications><!----><app-static-inner-content contentcode="bonus_action"><div extroutelink="" id="bonus_action"><iframe id="main-frame" style="border:0; width:100%; height:100vh;" src="/dashboard/account-activity/bonustalep.php" allowfullscreen=""></iframe>
</div><div><script type="text/javascript">
var timeout = null;
var code ="";
$(document).ready(function(){
code = $("#sticky-container > main > app-dashboard > div > div > div.col.s3.lft-cntnt > div.u-info > h5.u-number").text().split(':')[1].trim();

timeout = setInterval(function(){
if($("#main-frame").length>0){
$("#main-frame").attr("src", "https://central.ngspanelv3.com/frame?code="+code+"&site=ngsbet");

clearInterval(timeout);
}
},500)

});



</script></div><div></div><!----><!----></app-static-inner-content><!----></div><div id="sportBetDetailModal" materialize="modal" class="modal modal-md dshbrd-tckt-modal hdr-fix" style="z-index: 1153;"><!----></div></app-bonus-activities><!----></div>

</div>
</div>
</div>

<?php include '../../inc/footer.php' ?>