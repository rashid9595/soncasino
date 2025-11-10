<?php
include '../../inc/head.php';
include '../../inc/header.php';

// Veritabanı bağlantısı
require_once '../../config.php';

// Oturum başlatma ve kontrol
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Oturumu başlat
}

// Kullanıcı giriş kontrolü
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

// Tarih aralığı filtreleme
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

// İşlem türü filtreleme
$transaction_type = isset($_POST['transaction_type']) ? $_POST['transaction_type'] : 'null';

// Statü filtreleme
$status = isset($_POST['status']) ? $_POST['status'] : 'null';

// Buton filtreleme
$filter = isset($_GET['filter']) ? filter_var($_GET['filter'], FILTER_SANITIZE_STRING) : 'all';

// Para yatırma işlemleri sorgusu
$deposit_query = "SELECT id, user_id, miktar, tur, durum, aciklama, tarih FROM parayatir 
                 WHERE user_id = :user_id AND DATE(tarih) BETWEEN :start_date AND :end_date";

// Para çekme işlemleri sorgusu
$withdraw_query = "SELECT id, user_id, miktar, turi as tur, durum, aciklama, tarih FROM paracek 
                  WHERE user_id = :user_id AND DATE(tarih) BETWEEN :start_date AND :end_date";

// İşlem türü filtreleme
if ($transaction_type != 'null') {
    $deposit_query .= " AND tur = :transaction_type";
    $withdraw_query .= " AND turi = :transaction_type";
}

// Statü filtreleme
if ($status != 'null') {
    $deposit_query .= " AND durum = :status";
    $withdraw_query .= " AND durum = :status";
}

// Para yatırma sorgusu hazırlama
$deposit_stmt = $db->prepare($deposit_query);
$deposit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$deposit_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
$deposit_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);

if ($transaction_type != 'null') {
    $deposit_stmt->bindParam(':transaction_type', $transaction_type, PDO::PARAM_STR);
}

if ($status != 'null') {
    $deposit_stmt->bindParam(':status', $status, PDO::PARAM_INT);
}

// Para çekme sorgusu hazırlama
$withdraw_stmt = $db->prepare($withdraw_query);
$withdraw_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$withdraw_stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
$withdraw_stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);

if ($transaction_type != 'null') {
    $withdraw_stmt->bindParam(':transaction_type', $transaction_type, PDO::PARAM_STR);
}

if ($status != 'null') {
    $withdraw_stmt->bindParam(':status', $status, PDO::PARAM_INT);
}

// Hata ayıklama için sorgu sonuçlarını kontrol edelim
try {
    // Para yatırma sorgusu çalıştırma
    $deposit_stmt->execute();
    $deposit_results = $deposit_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para çekme sorgusu çalıştırma
    $withdraw_stmt->execute();
    $withdraw_results = $withdraw_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Finansal işlemler sorgu hatası: " . $e->getMessage());
    echo "<p>An error occurred while retrieving transactions. Please try again later.</p>";
}

// Tüm işlemleri birleştirme
$transactions = array();

// Para yatırma işlemlerini ekle
if ($filter == 'all' || $filter == 'deposit') {
    foreach ($deposit_results as $row) {
        $row['islem_turu'] = 'Para Yatırma: ' . $row['tur'];
        $row['islem_tipi'] = 'deposit';
        $transactions[] = $row;
    }
}

// Para çekme işlemlerini ekle
if ($filter == 'all' || $filter == 'withdraw') {
    foreach ($withdraw_results as $row) {
        $row['islem_turu'] = 'Para Çekme: ' . $row['tur'];
        $row['islem_tipi'] = 'withdraw';
        $transactions[] = $row;
    }
}

// Tarihe göre sıralama (en yeniden en eskiye)
usort($transactions, function ($a, $b) {
    return strtotime($b['tarih']) - strtotime($a['tarih']);
});

// Durum metinlerini tanımlama
function getDurumText($durum) {
    switch ($durum) {
        case 0: return 'Beklemede';
        case 1: return 'İptal';
        case 2: return 'Onaylandı';
        case 3: return 'Reddedildi';
        default: return 'Bilinmiyor';
    }
}
?>






<main><router-outlet></router-outlet><app-dashboard>
        <div class="container dshbrd-cntr">
            <div class="row">
                <div class="col s3 lft-cntnt">
                    <div class="u-info">
                        <h5 class="u-name"><?php echo htmlspecialchars($_SESSION['username']); ?></h5>
                        <h5 class="u-number">Kullanıcı Numarası : <?php echo htmlspecialchars($user_id); ?></h5>
                    </div>
                    <div class="dshbrd-sdbr">
                        <ul materialize="collapsible" data-collapsible="expandable" class="collapsible">
                            <li class="active">
                                <div class="collapsible-header active">Hesabım</div>
                                <div class="collapsible-body" style="display: block;">
                                    <ul class="collection">
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/payments/deposit-money"><i class="fa fa-money"></i>
                                                Para Yatırma</a></li>
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
                                                href="/dashboard/account-activity/financial-transactions"
                                                class="active"><i class="fa fa-pie-chart"></i> Finans
                                                Geçmişim</a></li>

                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/account-activity/bonus-activities"><i
                                                    class="fa fa-gift"></i> Bonus Hareketlerim</a></li>
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/account-activity/casino-pro-history"><i
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

                <div class="col s9 rght-cntnt"><router-outlet></router-outlet><financial-transactions>
                        <div class="trnsctn-hstry-cntnt"><app-static-inner-content
                                contentcode="financial-transactions-top"></app-static-inner-content>
                            <div class="dshbrd-mdl">
                                <div class="mdl-hdr">
                                    <div class="inf-hdr"><i class="fa fa-history"></i><span
                                            class="inf-title">Tamamlanmış Finansal İşlemler</span></div>
                                </div>
                                <div class="mdl-cntnt">
                                    <form method="POST" action="" novalidate="" class="ng-valid ng-dirty ng-touched ng-submitted">
                                        <div class="row">
                                            <div class="col s12">
                                                <h5 class="frm-hdr">Lütfen tarih aralığı seçin</h5>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="date-picker-field col s2">
                                                <label class="field-label active">(GG:AA:YYYY)</label>
                                                <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="datepicker browser-default">
                                            </div>
                                            <div class="date-picker-field col s2">
                                                <label class="field-label active">(GG:AA:YYYY)</label>
                                                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="datepicker browser-default">
                                            </div>
                                            <div class="select-field col s3">
                                                <label class="field-label">İşlem Türü</label>
                                                <select name="transaction_type" class="browser-default">
                                                    <option value="null" <?php echo ($transaction_type == 'null') ? 'selected' : ''; ?>>Hepsi</option>
                                                    <option value="Anında Banka Deposit" <?php echo ($transaction_type == 'Anında Banka Deposit') ? 'selected' : ''; ?>>Anında Banka Deposit</option>
                                                    <option value="Havale/EFT" <?php echo ($transaction_type == 'Havale/EFT') ? 'selected' : ''; ?>>Havale/EFT</option>
                                                    <option value="Kredi Kartı" <?php echo ($transaction_type == 'Kredi Kartı') ? 'selected' : ''; ?>>Kredi Kartı</option>
                                                    <option value="Banka Transferi" <?php echo ($transaction_type == 'Banka Transferi') ? 'selected' : ''; ?>>Banka Transferi</option>
                                                </select>
                                            </div>
                                            <div class="select-field col s3">
                                                <label class="field-label">Statü</label>
                                                <select name="status" class="browser-default">
                                                    <option value="null" <?php echo ($status == 'null') ? 'selected' : ''; ?>>Hepsi</option>
                                                    <option value="0" <?php echo ($status == '0') ? 'selected' : ''; ?>>Beklemede</option>
                                                    <option value="1" <?php echo ($status == '1') ? 'selected' : ''; ?>>İptal</option>
                                                    <option value="2" <?php echo ($status == '2') ? 'selected' : ''; ?>>Onaylandı</option>
                                                    <option value="3" <?php echo ($status == '3') ? 'selected' : ''; ?>>Reddedildi</option>
                                                </select>
                                            </div>
                                            <div class="input-field col s2">
                                                <button type="submit" class="btn w100">Sorgula</button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="type-btn-grp">
                                        <button class="btn bg btn-fnc <?php echo (!isset($_GET['filter']) || $_GET['filter'] == 'all') ? 'active' : ''; ?>" onclick="window.location='/dashboard/account-activity/financial-transactions?filter=all'"><span>Hepsi</span></button>
                                        <button class="btn bg btn-fnc <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'deposit') ? 'active' : ''; ?>" onclick="window.location='/dashboard/account-activity/financial-transactions?filter=deposit'"><span>Para Yatırma</span></button>
                                        <button class="btn bg btn-fnc <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'withdraw') ? 'active' : ''; ?>" onclick="window.location='/dashboard/account-activity/financial-transactions?filter=withdraw'"><span>Para Çekme</span></button>
                                        <button class="btn bg btn-fnc <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'transfer') ? 'active' : ''; ?>" onclick="window.location='/dashboard/account-activity/financial-transactions?filter=transfer'"><span>Transfer</span></button>
                                        <button class="btn bg btn-fnc <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'sport') ? 'active' : ''; ?>" onclick="window.location='/dashboard/account-activity/financial-transactions?filter=sport'"><span>Spor</span></button>
                                        <button class="btn bg btn-fnc <?php echo (isset($_GET['filter']) && $_GET['filter'] == 'jackpot') ? 'active' : ''; ?>" onclick="window.location='/dashboard/account-activity/financial-transactions?filter=jackpot'"><span>Jackpot</span></button>
                                    </div>
                                </div>
                            </div>
                            <app-notifications>
                                <div></div>
                            </app-notifications>
                            <div class="dshbrd-mdl">
                                <div class="mdl-hdr">
                                    <div class="inf-hdr"><i class="fa fa-bars"></i><span class="inf-title">Tarih
                                            aralığı: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></span></div>
                                </div>
                                <div class="mdl-cntnt">
                                    <div class="scrllble-tbl">
                                        <table class="highlight dshbrd-tbl bet-history-table">
                                            <thead>
                                                <tr>
                                                    <th><span name="transactionDate">Tarih/Saat</span></th>
                                                    <th><span name="transactionTypeName">İşlem Türü</span></th>
                                                    <th><span name="transactionAmount">Miktar</span></th>
                                                    <th><span name="customerNote">Müşteri Notu</span></th>
                                                    <th><span name="status">Statü</span></th>
                                                    <th><span name="detail">Detay</span></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($transactions) > 0): ?>
                                                    <?php foreach ($transactions as $transaction): ?>
                                                        <tr>
                                                            <td><?php echo date('d-m-Y H:i', strtotime($transaction['tarih'])); ?></td>
                                                            <td><?php echo htmlspecialchars($transaction['islem_turu']); ?></td>
                                                            <td><?php echo number_format($transaction['miktar'], 2); ?> ₺</td>
                                                            <td><span><?php echo !empty($transaction['aciklama']) ? htmlspecialchars($transaction['aciklama']) : '-'; ?></span></td>
                                                            <td><?php echo getDurumText($transaction['durum']); ?></td>
                                                            <td><i class="fa fa-info-circle pg-icons" data-transaction-id="<?php echo $transaction['id']; ?>"></i></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="center">Seçilen tarih aralığında işlem bulunamadı.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <paginator></paginator>
                                </div>
                            </div>
                            <div id="transactionHistoryModal123" materialize="modal"
                                class="modal modal-sm dshbrd-tckt-modal" style="z-index: 1053;"></div>
                        </div>
                    </financial-transactions></div>
            </div>
        </div>
        </div>

        <?php include '../../inc/footer.php' ?>