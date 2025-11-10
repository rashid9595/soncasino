<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor']);
    exit();
}

// Check if 2FA is verified
if (!isset($_SESSION['2fa_verified'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '2FA doğrulaması gerekiyor']);
    exit();
}

// Check if the user has permission to view tournaments
$has_permission = false;

if ($_SESSION['role_id'] == 1) {
    // Admin has all permissions
    $has_permission = true;
} else {
    $stmt = $db->prepare("
        SELECT ap.* 
        FROM admin_permissions ap 
        WHERE ap.role_id = ? AND ap.menu_item = 'tournaments' AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if ($stmt->fetch()) {
        $has_permission = true;
    }
}

if (!$has_permission) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bu işlemi gerçekleştirme izniniz yok']);
    exit();
}

// Check if we're fetching a tournament game by its ID
if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $tournamentGameId = (int)$_GET['id'];
    
    try {
        // Get tournament game by ID
        $stmt = $db->prepare("SELECT * FROM tournament_games WHERE id = ?");
        $stmt->execute([$tournamentGameId]);
        $tournamentGame = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tournamentGame) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Turnuva oyunu bulundu',
                'data' => $tournamentGame
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Turnuva oyunu bulunamadı'
            ]);
        }
        exit();
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Veritabanı hatası: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Get the game ID from the request (for searching in games database)
$game_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;

if ($game_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Geçersiz oyun ID\'si']);
    exit();
}

// Connect to the games database (u260321069_game1)
try {
    // Games database connection configuration
    // Note: These credentials should be stored securely in a production environment
    $games_db_config = [
        'host' => 'localhost',
        'dbname' => 'u260321069_game11',
        'username' => 'u260321069_game11', // Specific user for games database
        'password' => 'sifrexnaEFVanavt88'  // Specific password for games database
    ];
    
    // Connect to the games database
    $games_db = new PDO(
        "mysql:host={$games_db_config['host']};dbname={$games_db_config['dbname']}",
        $games_db_config['username'],
        $games_db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Define all slot game tables
    $slot_tables = [
        'games_100hp', 'games_1x2gaming', 'games_3oaks', 'games_3oaksp', 'games_7777',
        'games_7_mojos_slots', 'games_adlunam', 'games_airdice', 'games_altente',
        'games_amigogaming', 'games_amusnet', 'games_apparat', 'games_armadillostudios',
        'games_aviator', 'games_aviatrix', 'games_backseat', 'games_barbara_bang',
        'games_betsoft_a', 'games_bfgames', 'games_bgaming', 'games_bgamingp',
        'games_bigpot', 'games_bigtimegaming_a', 'games_booming', 'games_bullshark',
        'games_caletagaming', 'games_ctinteractive', 'games_egtdigital', 'games_elagames_a',
        'games_endorphina', 'games_espressogames_a', 'games_evoplay', 'games_fancyshoes',
        'games_fazi', 'games_fils', 'games_galaxsys', 'games_gamzix', 'games_goldenrace',
        'games_habanero', 'games_hacksaw', 'games_iconix', 'games_irondog', 'games_irondogp',
        'games_irondogpp', 'games_jetx', 'games_jvl', 'games_kagaming', 'games_macaw',
        'games_mancala', 'games_micro_gaming', 'games_netentwc', 'games_netgame',
        'games_nolimitcity_a', 'games_novo_matic', 'games_onegame', 'games_onetouch',
        'games_only_play', 'games_pateplay', 'games_pgsoft', 'games_platingaming',
        'games_platipus', 'games_play_son', 'games_play_sonp', 'games_popok',
        'games_pragmatic', 'games_pragmatic_virtual', 'games_pragmatics',
        'games_prospectgaming', 'games_redrake', 'games_redstone', 'games_redtigerwc',
        'games_retrogames', 'games_rubyplay', 'games_skywind', 'games_slotopia',
        'games_smartsoft', 'games_spearhead', 'games_spearheadp', 'games_spinomenal',
        'games_spribe', 'games_tada', 'games_tiptop', 'games_turbo_games',
        'games_wazdan_a', 'games_yggdrasil_a', 'games_yoloplay_a'
    ];
    
    // Define all live casino tables
    $live_tables = [
        'games_7_mojos', 'games_absolute', 'games_bitville', 'games_creedz',
        'games_creedz_bj', 'games_creedz_vgs', 'games_evolutionwc', 'games_evolutionwchs',
        'games_evolutionwcls', 'games_evolutionwcx', 'games_evolutionwcy', 'games_ezugi',
        'games_ezugix', 'games_ezugiz', 'games_iconic21', 'games_livegames', 'games_liw',
        'games_micro_gaming_live', 'games_onetouch_live', 'games_popok_live',
        'games_pragmatic_bj', 'games_pragmatic_live', 'games_religa', 'games_sagaming',
        'games_skywind_bj', 'games_skywind_live', 'games_tvbet', 'games_vivo'
    ];
    
    // Combine all tables
    $all_tables = array_merge($slot_tables, $live_tables);
    
    // Game found flag
    $game = null;
    $table_found = '';
    
    // Check if table exists before querying
    function tableExists($db, $table) {
        try {
            $result = $db->query("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Search in all tables
    foreach ($all_tables as $table) {
        if (tableExists($games_db, $table)) {
            try {
                $stmt = $games_db->prepare("SELECT * FROM `$table` WHERE game_id = ? LIMIT 1");
                $stmt->execute([$game_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $game = $result;
                    $table_found = $table;
                    break;
                }
            } catch (Exception $e) {
                // Skip this table if query fails
                continue;
            }
        }
    }
    
    if ($game) {
        // Add game type based on the table it was found in
        $game_type = in_array($table_found, $live_tables) ? 'live' : 'slot';
        $game['game_type_category'] = $game_type;
        $game['provider_table'] = $table_found;
        
        // Game found, return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Oyun bulundu: ' . $table_found,
            'game' => $game
        ]);
    } else {
        // Game not found
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Oyun bulunamadı'
        ]);
    }
} catch (PDOException $e) {
    // Database connection or query error
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
} 