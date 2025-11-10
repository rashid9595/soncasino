<?php
/**
 * TikTok Diamond Buy Permission API Script
 * This script makes a GET request to TikTok's diamond buy API
 */

// Base URL for the request
$baseUrl = "https://webcast.tiktok.com/webcast/wallet_api/fs/diamond_buy/permission_v2";

// Parameters from the original request
$params = [
    "WebIdLastTime" => "1742314051",
    "aid" => "1988",
    "app_language" => "tr-TR",
    "app_name" => "tiktok_web",
    "browser_language" => "tr",
    "browser_name" => "Mozilla",
    "browser_online" => "true",
    "browser_platform" => "Win32",
    "browser_version" => "5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36",
    "channel" => "tiktok_web",
    "cookie_enabled" => "true",
    "data_collection_enabled" => "true",
    "device_id" => "7483181846269675063",
    "device_platform" => "web_pc",
    "focus_state" => "true",
    "from_page" => "",
    "history_len" => "6",
    "is_fullscreen" => "false",
    "is_page_visible" => "true",
    "live_id" => "12",
    "local_country" => "TR",
    "odinId" => "6911598793852191750",
    "os" => "windows",
    "priority_region" => "TR",
    "referer" => "https://www.google.com/",
    "region" => "TR",
    "root_referer" => "https://www.tiktok.com/coin?lang=tr-TR",
    "screen_height" => "1080",
    "screen_width" => "1920",
    "source" => "www.tiktok.com/coin",
    "tz_name" => "Europe/Istanbul",
    "user_id" => "6911598793852191750",
    "user_is_login" => "true",
    "webcast_language" => "tr-TR",
    "msToken" => "Wcl3SdCeCwUSXYBg9KojNjkv5QFS0fhNkcq8XhNCO_MGkhWklP4QlCxJ5l1xtvWZLhqX3-32rmCUEPf5NLKJ6Jrb9T3UFM-q9lHQMr35wGzu_KWQ6-vFfJFEDRPBzIrUBsb8pec3X50H1HSkBVzteaGN",
    "X-Bogus" => "DFSzswVOEWvANrWEtgKRsYvVLFt4",
    "_signature" => "_02B4Z6wo00001OxpRmwAAIDBi.ivYzpyQEjsaULAAFzy0e"
];

// JSON data to send
$jsonData = [
    "block_coin_page" => false,
    "coins" => 22,
    "exchange" => [
        "ab_retention_popup" => false,
        "behavior_data" => [
            "scenario_to_display" => [
                "2" => false,
                "4" => true,
                "8" => true,
                "16" => true
            ]
        ],
        "coins" => 0,
        "currency" => "USD",
        "enable" => true,
        "exchange_input_option" => 1,
        "has_short_video_gift" => false,
        "is_ug_region_user" => false,
        "price_dot" => 4,
        "region" => "TR",
        "revenue" => 1,
        "show_exchange_amount_adjusted_text" => true,
        "show_exchange_tooltip" => true,
        "unit_price" => 128
    ],
    "frozen_coins" => 0,
    "has_google_recharge" => false,
    "is_allow" => true,
    "is_email_confirmed" => false,
    "is_first_web_recharge" => false,
    "is_periodic_payout" => false,
    "is_show" => true,
    "last_web_order" => [
        "device_platform" => "web_pc",
        "finish_time" => 1744222904,
        "ref_hash" => ""
    ],
    "package" => [
        "local_currency" => "",
        "local_price" => 0,
        "local_price_real_dot" => 0,
        "platform_type" => "",
        "price" => 128,
        "real_dot" => 4
    ],
    "quick_payment_available" => true,
    "redeem_info" => [
        "coins_balance" => 22,
        "frozen_coins_balance" => 0,
        "is_enabled" => true,
        "is_first_recharge" => false,
        "is_first_web_recharge" => false,
        "is_region_enabled" => false
    ],
    "show_input_tooltip" => false,
    "show_recharge_amount_adjusted_text" => false,
    "verified_email" => "",
    "web_recharge_input_option" => 1
];

// Cookie from the original request
$cookie = '_ttp=2uUjhqaiHeTowlEqFLuaOBL9Pkz; passport_csrf_token=432b38ce1bf708606783d866bc6c5a3a; passport_csrf_token_default=432b38ce1bf708606783d866bc6c5a3a; multi_sids=6911598793852191750%3A79d3f75b8f454f7296d3bcbd0c2499d4; cmpl_token=AgQQAPOGF-RO0o_UANxRpt0__bgDDncRf7UNYNiPHA; passport_auth_status=8020b0200d7eea6f264eaface734fd18%2C; passport_auth_status_ss=8020b0200d7eea6f264eaface734fd18%2C; uid_tt=45d8bb86d10d5dcd7bf0f8dc652b445096f32feb89e7610c589ab94a08f5016e; uid_tt_ss=45d8bb86d10d5dcd7bf0f8dc652b445096f32feb89e7610c589ab94a08f5016e; sid_tt=79d3f75b8f454f7296d3bcbd0c2499d4; sessionid=79d3f75b8f454f7296d3bcbd0c2499d4; sessionid_ss=79d3f75b8f454f7296d3bcbd0c2499d4; store-idc=alisg; store-country-code=tr; store-country-code-src=uid; tt-target-idc=alisg; tt-target-idc-sign=TnFbDMeVrpdfHeARJgwiRbLt7eB3Avu81JsxR8mn81ZEj37tHfEJNwmujEwJXukyyja_1uvraRe5CY2N7O-W2UArXCHzs9ZYg0HFtpHr7oEiBpSrhPEIm6otv89JeuXI5_25sGYWZox3NiREX5xdRXtiDCeB3hqOzrfRoqQDljDwg-dwD21IkjLZS-wBDyYQojW7pa9ckoQiKAdL2KPHmcay7v0nNY73I_1Hf9Jnt9Em7v3e3ppmYU-vb1cAm0x6obe3jx34aPbzATCb_46I9QjvR5DOhnRyqakx6kSmrUH0aLTTFNKBOrkFy9tWPlAmxTsy5bcQvYVPvjd9H7jH1rgDlhxLaCgzFxuXS-TtNAoU-zIMtjwCZyKrt5EYi6DoHlKU3n1K0IIwPmg8CQnaPybcv-R30LNdt6oVF8dXkayiVuh6nc9_jdDnWfOOvjd5eSj6e9t3Rk8D3iGmysfN_bWaBF3wMOWvoZaGcNTW7ZSm5hNf0wSz6lb2HfXj3Wsf; cookie-consent={%22optional%22:true%2C%22ga%22:true%2C%22af%22:true%2C%22fbp%22:true%2C%22lip%22:true%2C%22bing%22:true%2C%22ttads%22:true%2C%22reddit%22:true%2C%22hubspot%22:true%2C%22version%22:%22v10%22}; tt_chain_token=pr0BSiK36GOHPNfLZiRbmg==; sid_guard=79d3f75b8f454f7296d3bcbd0c2499d4%7C1742960694%7C14905386%7CSun%2C+14-Sep-2025+16%3A08%3A00+GMT; sid_ucp_v1=1.0.0-KDY5ZWY5YTNiNjg3YWUyNWE4YTk2YThiZDc2NTc0MmExZmY5MjQ1MTQKGQiGiNDMvc-79V8QtvCNvwYYsws4CEASSAQQAxoCbXkiIDc5ZDNmNzViOGY0NTRmNzI5NmQzYmNiZDBjMjQ5OWQ0; ssid_ucp_v1=1.0.0-KDY5ZWY5YTNiNjg3YWUyNWE4YTk2YThiZDc2NTc0MmExZmY5MjQ1MTQKGQiGiNDMvc-79V8QtvCNvwYYsws4CEASSAQQAxoCbXkiIDc5ZDNmNzViOGY0NTRmNzI5NmQzYmNiZDBjMjQ5OWQ0; csrf_session_id=e16d4d771c383863d1941cdf11e2acaa; tt_csrf_token=aWviTIsb-6FCwi8WhgNy0WzE49plglHrREUI; store-country-sign=MEIEDPai7aWYDjapux7LogQg9snIpfYwn9JozqNOgvWoR5k7uCC7NFdb3o1e1uOKJOwEEGRaW5rEVTa_NxkHdYj9w2I; odin_tt=cd8de4317cc00b7ef04d710e807582f6582acb9353975b4e0d646015e862e25a9264b49a7f3f98bcd804abb8cc40a253c0558989200f55ad6d89b34adbc84852b9381e15a19e5d921779158e4ff91f99; ttwid=1%7CveKoj9UZBv8urg4X4cjiESo6VPCvC4jwdurmN2lPVoc%7C1744230779%7C7e2c0340e48134c3f469c3d2c5d3c213b7c43b2887019bf47a0b15b54dab9f35; msToken=3NaOTHdeaqAJgLE1VFHWoWl6pftrNXxp5qP3udtLHtPjqeIYk_bsplNOIerDMkh2RM7eT7rrR9bTNOnmIZFTf_fZOfNlOnG2U-BSYs2s1_lwMI5y_a7xQaDXSEvE';

// Headers for the request
$headers = [
    'accept: */*',
    'accept-language: tr',
    'dnt: 1',
    'origin: https://www.tiktok.com',
    'referer: https://www.tiktok.com/',
    'sec-ch-ua: "Google Chrome";v="137", "Chromium";v="137", "Not/A)Brand";v="24"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-site',
    'sec-gpc: 1',
    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36',
    'x-secsdk-csrf-token: 0001000000019d6d946443d81e24806cd72349d9e93443dd69938d75fd2a23a9bbe24b24ddd71834c14e332c12ad,e16d4d771c383863d1941cdf11e2acaa',
    'cookie: ' . $cookie
];

// Function to make the API request
function makeApiRequest($baseUrl, $params, $jsonData, $headers) {
    // Build query string from parameters
    $queryString = http_build_query($params);
    
    // Add JSON data as a URL-encoded parameter
    $jsonParam = 'data=' . urlencode(json_encode($jsonData));
    $url = $baseUrl . '?' . $queryString . '&' . $jsonParam;
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Build request options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    
    // Don't send the data in the request body since it's in the URL
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
    
    // For testing/development only
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    // Execute the request
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    // Close cURL session
    curl_close($ch);
    
    return [
        'response' => $response,
        'error' => $error,
        'info' => $info,
        'url' => $url
    ];
}

// Make the API request
$result = makeApiRequest($baseUrl, $params, $jsonData, $headers);

// Output the results
echo "<h1>TikTok Diamond API Request</h1>";

// Display request information
echo "<h2>Request Details</h2>";
echo "<p>URL: " . $result['url'] . "</p>";
echo "<h3>Request Headers</h3>";
echo "<pre>" . implode("\n", array_map(function($header) {
    // Hide full cookie for security
    return strpos($header, 'cookie:') === 0 ? 'cookie: [hidden for security]' : $header;
}, $headers)) . "</pre>";

echo "<h3>JSON Data Sent</h3>";
echo "<pre>" . json_encode($jsonData, JSON_PRETTY_PRINT) . "</pre>";

// Display response information
echo "<h2>Response Details</h2>";
if (!empty($result['error'])) {
    echo "<h3>cURL Error</h3>";
    echo "<p>{$result['error']}</p>";
} else {
    echo "<h3>HTTP Status Code</h3>";
    echo "<p>{$result['info']['http_code']}</p>";
    
    echo "<h3>Response Headers</h3>";
    echo "<pre>";
    print_r($result['info']);
    echo "</pre>";
    
    echo "<h3>Response Body</h3>";
    echo "<pre>";
    $responseData = json_decode($result['response'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        print_r($responseData);
    } else {
        echo htmlspecialchars($result['response']);
    }
    echo "</pre>";
}

// Add a simple interface to modify and re-test
echo <<<HTML
<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1, h2, h3 { color: #333; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input[type="number"], input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
    button { background: #4CAF50; color: white; padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; }
    button:hover { background: #45a049; }
</style>

<h2>Test with Different Parameters</h2>
<form method="post" action="">
    <div class="form-group">
        <label for="coins">Coins:</label>
        <input type="number" id="coins" name="coins" value="22">
    </div>
    <div class="form-group">
        <label for="msToken">msToken:</label>
        <input type="text" id="msToken" name="msToken" value="{$params['msToken']}" style="width:100%">
    </div>
    <div class="form-group">
        <label for="user_id">User ID:</label>
        <input type="text" id="user_id" name="user_id" value="{$params['user_id']}">
    </div>
    <button type="submit">Test Request</button>
</form>
HTML;
?>
