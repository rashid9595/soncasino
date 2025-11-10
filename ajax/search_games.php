<?php
// Initialize session if needed
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Connect to games database
try {
    $games_db = new PDO("mysql:host=localhost;dbname=u260321069_game1", "u260321069_game1", "sifrexnaEFVanavt88");
    $games_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $games_db->exec("SET NAMES utf8");
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit();
}

// Get search parameters - support both GET and POST for backwards compatibility
$search = '';
$form_type = 'add'; // Default form type

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = isset($_POST['search_term']) ? $_POST['search_term'] : '';
    $form_type = isset($_POST['form_type']) ? $_POST['form_type'] : 'add';
} else {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
}

// Validate input
if (empty($search)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Missing search parameter']);
    exit();
}

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
    'games_7_mojos', 'games_absolute', 'games_bitville', 
    'games_creedroomz', 'games_creedroomz_b', 'games_creedz', 'games_creedz_bj', 'games_creedz_vgs',
    'games_evolutionwc', 'games_evolutionwchs', 'games_evolutionwcls', 'games_evolutionwcx', 'games_evolutionwcy',
    'games_iconic21', 'games_imagine_live', 'games_livegames', 'games_liw',
    'games_micro_gaming_live', 'games_onetouch_live', 'games_popok_live',
    'games_pragmatic_bj', 'games_pragmatic_live', 'games_pragmatic_virtual',
    'games_religa', 'games_sagaming',
    'games_skywind_bj', 'games_skywind_live',
    'games_tvbet', 'games_vivo'
];

// Filter tables based on form type if specified
if ($form_type === 'live') {
    $all_tables = $live_tables;
} elseif ($form_type === 'add') {
    // For 'add' form type, use all tables by default
    $all_tables = array_merge($slot_tables, $live_tables);
} else {
    // For any other form type, use all tables as fallback
    $all_tables = array_merge($slot_tables, $live_tables);
}

// Function to check if a table exists
function tableExists($db, $table) {
    try {
        $result = $db->query("SELECT 1 FROM `$table` LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

$results = [];

// Search across all tables for the game
foreach ($all_tables as $table) {
    if (tableExists($games_db, $table)) {
        try {
            $stmt = $games_db->prepare("SELECT game_id as id, game_name, banner, provider_game, game_code, provider_id, rtp, technology, cover FROM `$table` 
                                       WHERE game_name LIKE :search 
                                       ORDER BY game_name ASC 
                                       LIMIT 5");
            $stmt->execute(['search' => "%{$search}%"]);
            $table_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add the table name and game type to each result
            foreach ($table_results as $result) {
                $result['provider'] = str_replace('games_', '', $table);
                $result['game_type'] = in_array($table, $live_tables) ? 'live' : 'slot';
                $results[] = $result;
            }
            
            // Limit to prevent too many results
            if (count($results) >= 20) {
                break;
            }
        } catch (Exception $e) {
            // Skip this table if there's an error
            continue;
        }
    }
}

// Sort results by game name
usort($results, function($a, $b) {
    return strcmp($a['game_name'], $b['game_name']);
});

// Limit to top 20 results
$results = array_slice($results, 0, 20);

// Return results as JSON with success flag
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'results' => $results,
    'count' => count($results)
]);
?> 