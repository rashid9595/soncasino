    <?php
    session_start();
    require_once "../config/database.php";

    // Security check
    if (!isset($_SESSION["admin_id"]) || !isset($_SESSION["2fa_verified"])) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }

    header("Content-Type: application/json");

    try {
        // Get withdrawal requests with user information
        $withdrawalStmt = $db->prepare("
            SELECT p.*, u.username 
            FROM paracek p
            LEFT JOIN kullanicilar u ON p.user_id = u.id
            ORDER BY p.tarih DESC
            LIMIT 100
        ");
        
        $withdrawalStmt->execute();
        $withdrawals = $withdrawalStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get firm names for lookup
        $firmStmt = $db->query("SELECT * FROM firma ORDER BY firma_adi ASC");
        $firms = $firmStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $firmKeyToName = [];
        foreach ($firms as $firm) {
            $firmKeyToName[$firm["firma_key"]] = $firm["firma_adi"];
        }
        
        // Process each withdrawal with additional info
        $now = new DateTime();
        foreach ($withdrawals as &$withdrawal) {
            // Format method name
            $methodName = ucfirst($withdrawal["banka"]);
            if (!empty($withdrawal["firma_key"]) && isset($firmKeyToName[$withdrawal["firma_key"]])) {
                $methodName = $firmKeyToName[$withdrawal["firma_key"]];
            }
            $withdrawal["methodName"] = $methodName;
            
            // Check if withdrawal can be reverted
            $withdrawal["canRevert"] = false;
            $withdrawal["timeRemaining"] = "";
            
            if ($withdrawal["durum"] == 2 && !empty($withdrawal["geri_alma_suresi"])) {
                $deadline = new DateTime($withdrawal["geri_alma_suresi"]);
                
                if ($now < $deadline) {
                    $withdrawal["canRevert"] = true;
                    $interval = $now->diff($deadline);
                    $minutesRemaining = $interval->format("%i");
                    $secondsRemaining = $interval->format("%s");
                    $withdrawal["timeRemaining"] = $minutesRemaining . ":" . $secondsRemaining;
                }
            }
        }
        
        echo json_encode(["withdrawals" => $withdrawals]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    } 