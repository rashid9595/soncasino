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
define('DB_HOST', 'localhost');
define('DB_NAME', 'u260321069_ana1');
define('DB_USER', 'u260321069_ana1');
define('DB_PASS', 'sifrexnaEFVanavt88');

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Sayfalama için parametreler
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 3; // Her sayfada 3 kayıt göster
    $offset = ($page - 1) * $perPage;

    // Remove date filtering
    $status = isset($_GET['status']) ? $_GET['status'] : null;

    // Debug bilgisi
    $debug = false; // Debug modunu kapat

    // SQL sorgusu oluştur
    $sql = "SELECT b.*, bd.start_ts 
            FROM bahisler b 
            LEFT JOIN bahis_detaylari bd ON b.coupon_id = bd.coupon_id 
            WHERE b.user_id = :user_id";
    $params = [':user_id' => $user_id];

    // Durum filtresini ekle
    if ($status && $status !== '') {
        // Map the status from the frontend to the database values
        $statusMap = [
            'O' => 'active',  // Tamamlanmamış
            'W' => 'won',     // Kazandı
            'L' => 'lost',    // Kaybetti
            'V' => 'canceled' // İptal Edilmiş
        ];
        // Check if the status exists in the map
        if (isset($statusMap[$status])) {
            $dbStatus = $statusMap[$status];
            $sql .= " AND b.status = :status";
            $params[':status'] = $dbStatus;
        }
    }

    // Sıralama ve limit ekle
    $sql .= " ORDER BY b.created_at DESC LIMIT " . (int)$offset . ", " . (int)$perPage;
    unset($params[':offset']);
    unset($params[':perPage']);

    // Toplam bahis sayısını al
    $countSql = "SELECT COUNT(*) as total FROM bahisler b WHERE b.user_id = :user_id";
    $countParams = [':user_id' => $user_id];
    if ($status && $status !== '') {
        $statusMap = [
            'O' => 'active',
            'W' => 'won',
            'L' => 'lost',
            'V' => 'canceled'
        ];
        if (isset($statusMap[$status])) {
            $countSql .= " AND b.status = :status";
            $countParams[':status'] = $statusMap[$status];
        }
    }
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $totalBets = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalBets / $perPage);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $bets = [];
    $totalPages = 0;
    $page = 1;
}

// Durum metinlerini ayarla
$status_texts = [
    'active' => 'Tamamlanmamış',
    'won' => 'Kazandı',
    'lost' => 'Kaybetti',
    'canceled' => 'İptal Edilmiş'
];

// Durum ikonlarını ayarla
$status_icons = [
    'active' => '<i class="stts-O fa fa-clock-o"></i>',
    'won' => '<i class="stts-W fa fa-check"></i>',
    'lost' => '<i class="stts-L fa fa-times"></i>',
    'canceled' => '<i class="stts-V fa fa-ban"></i>'
];

// Durum renklerini ayarla
$status_colors = [
    'active' => '#ff9800', // Orange
    'won' => '#4CAF50',    // Green
    'lost' => '#F44336',   // Red
    'canceled' => '#9E9E9E' // Gray
];
?>


<?php include '../../inc/head.php' ?>
<?php include '../../inc/header.php' ?>

<!-- Add Flatpickr CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<link rel="stylesheet" href="/css/bet-history.css">

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/tr.js"></script>
<script src="/staticjs/bethistory.js"></script>



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
                                                href="/dashboard/account-activity/bet-history" class="active"><i
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
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/casino-pro-history"><i class="fa fa-history"></i> Casinopro Geçmişi</a></li>
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


                <div class="col s9 rght-cntnt" bis_skin_checked="1"><router-outlet></router-outlet><app-bet-history-new>
                        <div class="bet-hstry-cntnt pymnt-cntnt bet-history-new" bis_skin_checked="1">
                            <div class="dshbrd-mdl" bis_skin_checked="1">
                                <div class="mdl-hdr" bis_skin_checked="1">
                                    <div class="inf-hdr" bis_skin_checked="1"><i class="fa fa-history"></i><span class="inf-title">Bahis Geçmişim</span></div>
                                </div>
                                <div class="mdl-cntnt" bis_skin_checked="1">
                                    <form id="betHistoryForm" method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" novalidate="" class="ng-untouched ng-valid ng-dirty">
                                        <input type="hidden" name="status" id="statusInput" value="">
                                    </form>
                                    <div class="type-btn-grp" bis_skin_checked="1">
                                        <button class="btn bg stts-O fltr-btn active"><i class="stts-O">Tamamlanmamış</i><i class="fa fa-clock-o right stts-O"></i></button>
                                        <button class="btn bg stts-W fltr-btn"><i class="stts-W">Kazandı</i><i class="fa fa-check right stts-W"></i></button>
                                        <button class="btn bg stts-L fltr-btn"><i class="stts-L">Kaybetti</i><i class="fa fa-times right stts-L"></i></button>
                                        <button class="btn bg stts-V fltr-btn"><i class="stts-V">İptal Edilmiş</i><i class="fa fa-ban right stts-V"></i></button>
                                        <button class="btn bg stts- fltr-btn"><i class="stts-">Hepsi</i><i class="fa fa-bars right stts-"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="dshbrd-mdl" bis_skin_checked="1">
                                <div class="mdl-hdr" bis_skin_checked="1">
                                    <div class="inf-hdr" bis_skin_checked="1">
                                        <div class="inf-left" bis_skin_checked="1"><i class="fa fa-bars"></i><span class="inf-title">Bahis Geçmişim</span></div>
                                        <div class="inf-right" bis_skin_checked="1"><span class="inf-title">Gösteriliyor: <span class="badge" style="background-color: #2196F3; color: white; padding: 3px 8px; border-radius: 10px;"><?php echo count($bets); ?> adet</span></span></div>
                                    </div>
                                </div>
                                <div class="bet-history-container">
                                    <?php if(empty($bets)): ?>
                                    <div class="no-bets-found">
                                        <i class="fa fa-search"></i>
                                        <p>Gösterilecek bahis bulunamadı.</p>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach($bets as $bet): 
                                            $bet_data = json_decode($bet['bet_data'], true);
                                            $bet_date = date('d-m-Y H:i', strtotime($bet['created_at']));
                                            $status = isset($status_texts[$bet['status']]) ? $status_texts[$bet['status']] : 'Bilinmiyor';
                                            $status_icon = isset($status_icons[$bet['status']]) ? $status_icons[$bet['status']] : '<i class="fa fa-question"></i>';
                                            $status_color = isset($status_colors[$bet['status']]) ? $status_colors[$bet['status']] : '#9E9E9E';
                                            $bet_type = isset($bet_data['type']) && $bet_data['type'] == 1 ? 'TEKLİ' : 'KOMBİNE';
                                            
                                            // Bahis detaylarını hazırla
                                            $bets_details = isset($bet_data['bets']) ? $bet_data['bets'] : [];
                                        ?>
                                    <div class="bet-card">
                                        <div class="bet-card-header">
                                            <div class="header">
                                                <div class="bet-status header-item" style="color: <?php echo $status_color; ?>;">
                                                    <?php echo $status_icon; ?><span class="mar-left-5" style="color: #000 !important;"><?php echo $status; ?></span>
                                                </div>
                                                <div class="bet-date header-item"><i class="fa fa-calendar-o" style="margin-right: 5px;"></i> <?php echo date('d-m-Y H:i', $bet['start_ts']); ?></div>
                                            </div>
                                            <div class="header">
                                                <div class="bet-no header-item" style="color: #000 !important;"><strong style="color: #000;">Bahis No:</strong> <span style="color: #000;"><?php echo htmlspecialchars($bet['coupon_id']); ?></span></div>
                                                <div class="bet-type header-item" style="background-color: #f5f5f5 !important; color: #000 !important; padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd;"><?php echo $bet_type; ?></div>
                                            </div>
                                        </div>
                                        <div class="bet-info" data-start-ts="<?php echo htmlspecialchars($bet['start_ts']); ?>">
                                            <?php if (!empty($bets_details)): ?>
                                            <ul>
                                                <?php foreach($bets_details as $bet_detail): ?>
                                                <li class="match-infos">
                                                    <div class="finished-match-infos">
                                                        <div class="team-names">
                                                            <i class="fa fa-trophy" style="margin-right: 5px; color: #FFC107;"></i>
                                                            <span style="color: #000 !important; font-weight: bold;"><?php echo htmlspecialchars($bet_detail['match_title']); ?></span>
                                                        </div>
                                                        <div class="final-result"></div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <span style="color: #000 !important;"><i class="fa fa-tag" style="margin-right: 5px; color: #999;"></i><?php echo htmlspecialchars($bet_detail['market_name']); ?></span>
                                                </li>
                                                <li class="bet-selection">
                                                    <span><i class="fa fa-check-circle" style="margin-right: 5px; color: #4CAF50;"></i><?php echo htmlspecialchars($bet_detail['pick']); ?></span>
                                                    <span class="right"><?php echo number_format($bet_detail['price'], 2); ?></span>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php endif; ?>
                                            <ul class="payment-info">
                                                <li>
                                                    <span>Toplam Bahis:</span>
                                                    <span><?php echo number_format($bet['amount'], 2); ?><span class="currency-symbol"> ₺</span></span>
                                                </li>
                                                <li>
                                                    <span>Olası Kazanç:</span>
                                                    <span style="color: #4CAF50; font-weight: bold;"><?php echo number_format($bet['potential_winnings'], 2); ?><span class="currency-symbol"> ₺</span></span>
                                                </li>
                                                <li>
                                                    <span>Toplam Oran:</span>
                                                    <span style="color: #2196F3;"><?php echo number_format($bet['total_odds'], 2); ?></span>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="btn-more-wrapper">
                                            <a class="bet-info waves-effect waves-light show-bet-detail" data-bet-id="<?php echo $bet['id']; ?>">
                                                <i class="fa fa-chevron-down" style="margin-right: 5px;"></i> Daha Fazla Göster
                                            </a>
                                        </div>
                                    </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <div class="pagination-container">
                                        <?php if ($totalPages > 1): ?>
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                            <li><a href="/dashboard/account-activity/bet-history?page=1">&laquo;</a></li>
                                            <li><a href="/dashboard/account-activity/bet-history?page=<?php echo $page-1; ?>">&lsaquo;</a></li>
                                            <?php endif; ?>
                                            
                                            <?php
                                            $startPage = max($page - 2, 1);
                                            $endPage = min($startPage + 4, $totalPages);
                                            
                                            if ($startPage > 1) {
                                                echo '<li><span>...</span></li>';
                                            }
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++) {
                                                if ($i == $page) {
                                                    echo '<li class="active"><span>' . $i . '</span></li>';
                                                } else {
                                                    echo '<li><a href="/dashboard/account-activity/bet-history?page=' . $i . '">' . $i . '</a></li>';
                                                }
                                            }
                                            
                                            if ($endPage < $totalPages) {
                                                echo '<li><span>...</span></li>';
                                            }
                                            ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                            <li><a href="/dashboard/account-activity/bet-history?page=<?php echo $page+1; ?>">&rsaquo;</a></li>
                                            <li><a href="/dashboard/account-activity/bet-history?page=<?php echo $totalPages; ?>">&raquo;</a></li>
                                            <?php endif; ?>
                                        </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div id="betDetailModal" materialize="betDetailPopup" class="bet-detail-modal bet-modal-md bet-tckt-modal bet-modal-fix" style="z-index: 1019; display: none; opacity: 0; transform: scaleX(0.7); top: 5%;" bis_skin_checked="1">
                                <div id="bet-detail-container" class="bet-history-detail-container">
                                    <div class="modal-header">
                                        <div class="title">
                                            <div class="flex-container">
                                                <div class="flex-item bet-detail-modal-id"></div>
                                                <div class="flex-item">
                                                    <div class="modal-close-button">
                                                        <a><i class="fa fa-times close-bet-modal"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex-container">
                                                <div class="flex-item bet-detail-modal-status"></div>
                                                <div class="flex-item">
                                                    <div class="title-date bet-detail-modal-date"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-content">
                                        <div class="main-history">
                                            <div class="history-modal-head bet-detail-modal-summary">
                                                <!-- Summary content will be inserted here -->
                                            </div>
                                        </div>
                                        <ul materialize="collapsible" data-collapsible="expandable" class="tckt-lst collapsible">
                                            <li class="tckt-itm active">
                                                <div class="collapsible-header active">Kupon Detayları</div>
                                                <div class="collapsible-body" style="display: block;">
                                                    <div class="main-history">
                                                        <div class="history-modal-head bet-detail-modal-details">
                                                            <!-- Details content will be inserted here -->
                                                        </div>
                                                    </div>
                                                    <div class="history-modal-content bet-detail-modal-content">
                                                        <!-- Event content will be inserted here -->
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </app-bet-history-new></div>

            </div>
        </div>
    </app-dashboard></main>



<?php include '../../inc/footer.php' ?>