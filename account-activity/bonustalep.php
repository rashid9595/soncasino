<?php
// session_start()'ı yalnızca bir kez çağırmalısınız
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Oturumu başlat
}
?>
<script src="chrome-extension://eppiocemhmnlbhjplcgkofciiegomcon/content/location/location.js"
    id="eppiocemhmnlbhjplcgkofciiegomcon"></script>
<script src="chrome-extension://eppiocemhmnlbhjplcgkofciiegomcon/libs/extend-native-history-api.js"></script>
<script src="chrome-extension://eppiocemhmnlbhjplcgkofciiegomcon/libs/requests.js"></script>

<head>
    <script src="chrome-extension://eppiocemhmnlbhjplcgkofciiegomcon/content/location/location.js"
        id="eppiocemhmnlbhjplcgkofciiegomcon"></script>
    <script src="chrome-extension://eppiocemhmnlbhjplcgkofciiegomcon/libs/extend-native-history-api.js"></script>
    <script src="chrome-extension://eppiocemhmnlbhjplcgkofciiegomcon/libs/requests.js"></script>


    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"
        integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1"
        crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"
        integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM"
        crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"
        type="text/javascript"></script>
    <link rel="stylesheet" href="/dashboard/account-activity/Content/assets-desktop/css/style.css">
        <link rel="stylesheet" href="/css/bonustalep.css">

    <script type="text/javascript" src="/Content/assets-desktop/js/main.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10?_=1636711324144"></script>
   
    <title>Bonus Talep Et</title>


</head>

<body __processed_725b3b9c-11c0-4d3d-af23-b978e38cfa15__="true"
    __processed_b5e859e7-c9aa-483e-84e4-37e94d693ccc__="true">

<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../config.php';

// Kullanıcı giriş kontrolü
if(!isset($_SESSION['username'])) {
    header("Location: /login.php");
    exit;
}

// Aktif bonusları SQL'den çek
$bonusSorgu = $db->prepare("SELECT * FROM bonuslar WHERE aktif = 1 ORDER BY id DESC");
$bonusSorgu->execute();
$bonuslar = $bonusSorgu->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcı bilgilerini al
$usernameSorgu = $db->prepare("SELECT id, username FROM kullanicilar WHERE username = ?");
$usernameSorgu->execute([$_SESSION['username']]);
$user = $usernameSorgu->fetch(PDO::FETCH_ASSOC);

$username = htmlspecialchars($_SESSION['username']);
$user_id = htmlspecialchars($user['id']);

// Kullanıcının bonus taleplerini getir
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
$offset = ($page - 1) * $limit;

// Başarılı bonus talepleri
$talepSorgu = $db->prepare("SELECT bk.*, b.bonus_adi 
                           FROM bonus_kullanim bk 
                           JOIN bonuslar b ON b.id = bk.bonus_id 
                           WHERE bk.user_id = ? 
                           ORDER BY bk.tarih DESC 
                           LIMIT $limit OFFSET $offset");
$talepSorgu->execute([$user_id]);
$talepler = $talepSorgu->fetchAll(PDO::FETCH_ASSOC);

// Reddedilen bonus talepleri
$redSorgu = $db->prepare("SELECT br.*, b.bonus_adi 
                         FROM bonus_talep_red br 
                         JOIN bonuslar b ON b.id = br.bonus_id 
                         WHERE br.user_id = ? 
                         ORDER BY br.tarih DESC 
                         LIMIT $limit OFFSET $offset");
$redSorgu->execute([$user_id]);
$redTalepler = $redSorgu->fetchAll(PDO::FETCH_ASSOC);

// Tüm talepleri birleştir ve tarihe göre sırala
$tumTalepler = array_merge($talepler, $redTalepler);
usort($tumTalepler, function($a, $b) {
    return strtotime($b['tarih']) - strtotime($a['tarih']);
});

// Sayfalama için toplam talep sayısını al
$toplamSorgu = $db->prepare("SELECT 
                            (SELECT COUNT(*) FROM bonus_kullanim WHERE user_id = ?) + 
                            (SELECT COUNT(*) FROM bonus_talep_red WHERE user_id = ?) as toplam");
$toplamSorgu->execute([$user_id, $user_id]);
$toplamTalep = $toplamSorgu->fetch(PDO::FETCH_ASSOC)['toplam'];
$toplamSayfa = ceil($toplamTalep / $limit);

// Sayfalama için sadece gerekli kayıtları al
$tumTalepler = array_slice($tumTalepler, $offset, $limit);
?>

<main>
    <div>
        <div class="bSec">
            <div class="bSecTitle">Bonusunu Seç</div>
            <div class="bSecCon" id="bonus-all">
                <?php foreach($bonuslar as $bonus): ?>
                    <div class="bSecBox" onclick="Request(<?php echo $bonus['id']; ?>, '<?php echo $bonus['bonus_adi']; ?>')">
                        <a href="javascript:void(0)" data="<?php echo $bonus['id']; ?>" class="allClick">
                            <div class="bSecBoxImg">
                                <?php if (filter_var($bonus['resim_url'], FILTER_VALIDATE_URL)): ?>
                                    <img src="<?php echo htmlspecialchars($bonus['resim_url']); ?>">
                                <?php else: ?>
                                    <img src="/Content/assets-desktop/images/ngsbet/<?php echo htmlspecialchars($bonus['resim_url']); ?>">
                                <?php endif; ?>
                                <p><?php echo $bonus['bonus_adi']; ?></p>
                            </div>
                            <div class="bSecBoxTit"><span>Talep Et</span></div>
                        </a>
                    </div>
                <?php endforeach; ?>
                <div class="bSecClear"></div>
            </div>
        </div>
    </div>

    <div class="bSecTitle">Geçmiş Bonus Talepleri</div>
    <div class="bSecCon">
        <div>
            <table class="nero-table-2">
                <thead>
                    <tr>
                        <th scope="col"># </th>
                        <th scope="col">Bonus</th>
                        <th scope="col">Açıklama</th>
                        <th scope="col">Tarih</th>
                        <th scope="col">Durum</th>
                    </tr>
                </thead>
                <tbody id="bonus-history-table">
                    <?php if(count($tumTalepler) > 0): ?>
                        <?php foreach($tumTalepler as $index => $talep): ?>
                            <tr>
                                <td data-label="#"><?php echo $index + 1 + $offset; ?></td>
                                <td data-label="Bonus: "><?php echo htmlspecialchars($talep['bonus_adi']); ?></td>
                                <td data-label="Açıklama: ">
                                    <span title="<?php echo htmlspecialchars($talep['aciklama'] ?? 'Bilgi yok'); ?>">
                                        <?php echo htmlspecialchars($talep['aciklama'] ?? 'Bilgi yok'); ?>
                                    </span>
                                </td>
                                <td data-label="Tarih: "><?php echo date('d.m.Y H:i:s', strtotime($talep['tarih'])); ?></td>
                                <td data-label="Durum">
                                    <?php if(isset($talep['durum']) && $talep['durum'] == 1): ?>
                                        <button class="btn-greenn">Bonus Eklenmiştir</button>
                                    <?php else: ?>
                                        <button class="btn-redd">Bonus Hakkınız Yoktur</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center"> Kayıtlı Bir Talebiniz Bulunmamakta! </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="dvrecords">
                <select class="records" id="count" onchange="changeLimit(this.value)">
                    <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                </select>

                <div class="pagination">
                    <ul class="pagination-3">
                        <?php if($page > 1): ?>
                            <li class="page-number prev"><a href="javascript:void(0)" onclick="Page(<?php echo $page - 1; ?>)">«</a></li>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($toplamSayfa, $page + 2); $i++): ?>
                            <li class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                                <a href="javascript:void(0)" onclick="Page(<?php echo $i; ?>)"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($page < $toplamSayfa): ?>
                            <li class="page-number next"><a href="javascript:void(0)" onclick="Page(<?php echo $page + 1; ?>)">»</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Son talep zamanını saklamak için localStorage kullanacağız
function Request(bonusId, bonusAdi) {
    // Son talep zamanını kontrol et
    var lastRequestTime = localStorage.getItem('lastBonusRequestTime');
    var now = new Date().getTime();
    
    if (lastRequestTime && (now - parseInt(lastRequestTime)) < 300000) { // 5 dakika = 300000 ms
        // 5 dakika geçmemiş, kalan süreyi hesapla
        var remainingTime = Math.ceil((300000 - (now - parseInt(lastRequestTime))) / 1000);
        var minutes = Math.floor(remainingTime / 60);
        var seconds = remainingTime % 60;
        
        // Sweetalert ile kullanıcıya bilgi ver
        Swal.fire({
            icon: 'warning',
            title: 'Çok Sık Bonus Talebi!',
            text: `Lütfen ${minutes} dakika ${seconds} saniye sonra tekrar deneyin.`,
            confirmButtonText: 'Tamam'
        });
        return;
    }
    
    // Talep zamanını kaydet
    localStorage.setItem('lastBonusRequestTime', now.toString());
    
    // Önce beklemede olan bir talep ekleyelim
    var newRow = `
        <tr id="pending-bonus-${bonusId}">
            <td data-label="#">-</td>
            <td data-label="Bonus: ">${bonusAdi}</td>
            <td data-label="Açıklama: ">
                <span>
                    Hakkınız olması durumunda bonusunuz birazdan otomatik olarak hesabınıza yansıyacaktır.
                </span>
            </td>
            <td data-label="Tarih: ">${getCurrentDateTime()}</td>
            <td data-label="Durum">
                <button class="btn-yelloww">İşlem Sırasında</button>
            </td>
        </tr>
    `;
    
    // Eğer "Kayıtlı Bir Talebiniz Bulunmamakta!" mesajı varsa onu kaldıralım
    if ($("#bonus-history-table tr td.text-center").length > 0) {
        $("#bonus-history-table").empty();
    }
    
    // Yeni satırı tablonun başına ekleyelim
    $("#bonus-history-table").prepend(newRow);
    
    // Bonus talebini hemen gönder
    $.ajax({
        url: '/dashboard/account-activity/bonustaleps.php',
        type: 'POST',
        dataType: 'json',
        data: {
            bonusId: bonusId
        },
        success: function(response) {
            // Bekleyen satırı kaldır
            $(`#pending-bonus-${bonusId}`).remove();
            
            // Sonucu göster
            var resultRow = `
                <tr>
                    <td data-label="#">-</td>
                    <td data-label="Bonus: ">${bonusAdi}</td>
                    <td data-label="Açıklama: ">
                        <span title="${response.message}">
                            ${response.message}
                        </span>
                    </td>
                    <td data-label="Tarih: ">${getCurrentDateTime()}</td>
                    <td data-label="Durum">
                        <button class="${response.success ? 'btn-greenn' : 'btn-redd'}">
                            ${response.success ? 'Bonus Eklenmiştir' : 'Bonus Hakkınız Yoktur'}
                        </button>
                    </td>
                </tr>
            `;
            
            $("#bonus-history-table").prepend(resultRow);

            // Başarılı ise sayfayı yenile
            if (response.success) {
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.log('Response Text:', xhr.responseText);
            
            // Bekleyen satırı kaldır
            $(`#pending-bonus-${bonusId}`).remove();
            
            // Hata mesajını almaya çalış
            var errorMessage = "İşlem sırasında bir hata oluştu!";
            try {
                var response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                console.error('JSON parse error:', e);
            }
            
            // Hata satırı ekle
            var errorRow = `
                <tr>
                    <td data-label="#">-</td>
                    <td data-label="Bonus: ">${bonusAdi}</td>
                    <td data-label="Açıklama: ">
                        <span title="${errorMessage}">
                            ${errorMessage}
                        </span>
                    </td>
                    <td data-label="Tarih: ">${getCurrentDateTime()}</td>
                    <td data-label="Durum">
                        <button class="btn-redd">Hata</button>
                    </td>
                </tr>
            `;
            
            $("#bonus-history-table").prepend(errorRow);
        }
    });
}

function getCurrentDateTime() {
    var now = new Date();
    var day = String(now.getDate()).padStart(2, '0');
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var year = now.getFullYear();
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    var seconds = String(now.getSeconds()).padStart(2, '0');
    
    return day + '.' + month + '.' + year + ' ' + hours + ':' + minutes + ':' + seconds;
}

function Page(page) {
    var limit = $('#count').val();
    window.location.href = '?page=' + page + '&limit=' + limit;
}

function changeLimit(limit) {
    window.location.href = '?page=1&limit=' + limit;
}
</script> 