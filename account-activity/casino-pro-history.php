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
                                        <li class="collection-item"><a routerlinkactive="active"
                                                href="/dashboard/account-activity/bonus-activities"><i
                                                    class="fa fa-gift"></i> Bonus Hareketlerim</a></li>
                                        <li class="collection-item"><a routerlinkactive="active" href="/dashboard/account-activity/casino-pro-history" class="active"><i class="fa fa-history"></i> Casinopro Geçmişi</a></li>
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

                <div class="col s9 rght-cntnt"><router-outlet></router-outlet><casino-pro-history>
                        <div class="csno-pro-hstry-cntnt">
                            <div class="dshbrd-mdl">
                                <div class="mdl-hdr">
                                    <div class="inf-hdr"><i class="fa fa-history"></i><span class="inf-title">Casinopro
                                            Geçmişi</span></div>
                                </div>
                                <div class="mdl-cntnt">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col s5">
                                                <label for="startDate">Başlangıç Tarihi</label>
                                                <input type="date" id="startDate" name="startDate" value="<?php echo date('Y-m-d', strtotime('-3 days')); ?>" required>
                                            </div>
                                            <div class="col s5">
                                                <label for="endDate">Bitiş Tarihi</label>
                                                <input type="date" id="endDate" name="endDate" value="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="col s2">
                                                <button type="submit" class="btn w100">Sorgula</button>
                                            </div>
                                        </div>
                                    </form>

                                </div>
                            </div><app-notifications>
                                <div></div>
                            </app-notifications>
                         
                                                
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Oturumu başlat
}
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Misafir';

// Ana veritabanı bağlantısı
$main_host = 'localhost';
$main_dbname = 'u260321069_ana1';
$main_dbuser = 'u260321069_ana1';
$main_dbpass = 'sifrexnaEFVanavt88';

// Ana veritabanı bağlantısını oluştur
$main_conn = new mysqli($main_host, $main_dbuser, $main_dbpass, $main_dbname);

// Bağlantıyı kontrol et
if ($main_conn->connect_error) {
    die("Ana veritabanı bağlantı hatası: " . $main_conn->connect_error);
}

// Kullanıcı ID'sini almak için sorgu
$user_query = "SELECT id FROM kullanicilar WHERE username = ?";
$stmt = $main_conn->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close();

// Eğer kullanıcı bulunamazsa, varsayılan bir ID kullan
if (!$user_id) {
    $user_id = '0000000'; // Varsayılan kullanıcı ID'si
}

// Tarih aralığı seçimi
$startDate = $_POST['startDate'] ?? date('Y-m-d', strtotime('-3 days')); // Varsayılan başlangıç tarihi
$endDate = $_POST['endDate'] ?? date('Y-m-d'); // Varsayılan bitiş tarihi

// SQL sorgusu
$sql = "
SELECT 
    t.round_id,
    GROUP_CONCAT(DISTINCT t.round_id) AS bahis_numarasi,
    MAX(t.created_at) AS oynanilan_tarih,
    SUM(CASE WHEN t.type = 'bet' THEN t.amount ELSE 0 END) AS ana_kasa_tutar,
    SUM(CASE WHEN t.type = 'win' THEN t.type_money ELSE 0 END) AS kazanilan_miktar,  -- Kazanılan miktar
    CASE 
        WHEN SUM(CASE WHEN t.type = 'win' THEN 1 ELSE 0 END) > 0 THEN 'win' 
        ELSE 'bet' 
    END AS type,  -- Type kısmında win veya bet
    g.game_name AS oyun,  -- Oyun adı
    g.provider_game AS oyun_saglayici,  -- Oyun sağlayıcısı
    g.game_type AS kategori,  -- Kategori
    t.game AS game_id  -- Game ID
FROM 
    transactions t
LEFT JOIN 
    games g ON t.game = g.game_code  -- Left join with games table
WHERE 
    t.user_id = ? AND
    t.created_at BETWEEN ? AND ?  -- Tarih aralığı filtrelemesi
GROUP BY 
    t.round_id
ORDER BY 
    MAX(t.created_at) DESC
";

// Prepare the statement
$stmt = $main_conn->prepare($sql);

// Check if the statement was prepared successfully
if ($stmt === false) {
    die("SQL sorgusu hazırlama hatası: " . $main_conn->error);
}

$stmt->bind_param("iss", $user_id, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// HTML yapısı
echo '<div class="dshbrd-mdl">
        <div class="mdl-hdr">
            <div class="inf-hdr"><i class="fa fa-bars"></i><span class="inf-title">Tarih aralığı: ' . htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate) . '</span></div>
        </div>
        <div class="mdl-cntnt">
            <div class="scrllble-tbl">
                <table class="dshbrd-tbl">
                    <thead>
                        <tr>
                            <th>Bahis Numarası</th>
                            <th>Oynanılan Tarih</th>
                            <th>Kazanılan Tarih</th>
                            <th>Ana Kasa tutarından oynandı</th>
                            <th>Bonustan oynandı</th>
                            <th>Kazanılan Miktar - Ana Kasa</th>
                            <th>Kazanılan Miktar - Bonus</th>
                            <th>Oyun Sağlayıcı</th>
                            <th>Oyun</th>
                            <th>Kategori</th>
                            <th>Statü</th>
                        </tr>
                    </thead>
                    <tbody>';

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['bahis_numarasi']) . "</td>"; // Bahis Numarası
        echo "<td>" . htmlspecialchars($row['oynanilan_tarih']) . "</td>";
        echo "<td>" . htmlspecialchars($row['oynanilan_tarih']) . "</td>"; // Kazanılan Tarih
        echo "<td>" . htmlspecialchars($row['ana_kasa_tutar']) . " ₺</td>"; // Ana Kasa tutarından oynandı
        echo "<td>0.00 ₺</td>"; // Bonus'tan oynandı
        
        // Kazanılan Miktar - Ana Kasa
        if (htmlspecialchars($row['type']) == "win") {
            echo "<td>" . htmlspecialchars($row['kazanilan_miktar']) . " ₺</td>"; // Kazanılan Miktar - Ana Kasa for win
        } else {
            echo "<td>0.00 ₺</td>"; // Kazanılan Miktar - Ana Kasa for bet
        }

        echo "<td>0.00 ₺</td>"; // Kazanılan Miktar - Bonus
        echo "<td>" . htmlspecialchars($row['oyun_saglayici']) . "</td>"; // Oyun Sağlayıcı
        echo "<td>" . htmlspecialchars($row['oyun']) . "</td>"; // Oyun
        echo "<td>" . htmlspecialchars($row['kategori']) . "</td>"; // Kategori
        
        // Statü kısmında kazanan miktar kontrolü
        if (htmlspecialchars($row['type']) == "win") {
            echo "<td><i aria-hidden='true' class='fa fa-check stts-W'></i></td>"; // Statü tik
        } else {
            echo "<td><i aria-hidden='true' class='fa fa-times stts-L'></i></td>"; // Statü x
        }
        
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='11'>Oyun geçmişi bulunamadı.</td></tr>";
}

echo '          </tbody>
                </table>
    </div>';


// Bağlantıları kapat
$stmt->close();
$main_conn->close();
?>

                                  
                                </div>
                            </div>
                        </div>
                    </casino-pro-history></div>



            </div>
        </div>
        </div>
    </app-dashboard></main>

<?php include '../../inc/footer.php' ?>