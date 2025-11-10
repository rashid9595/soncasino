<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u260321069_ana1');
define('DB_USER', 'u260321069_ana1');
define('DB_PASS', 'sifrexnaEFVanavt88'); // Replace with your actual database password

// Site configuration
define('SITE_URL', 'https://lacasino.com');
define('ADMIN_EMAIL', 'admin@lacasino.com');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Time zone
date_default_timezone_set('Europe/Istanbul');

// PDO ile veritabanına bağlanma
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usernames, $passwords);  // 'usernames' ve 'passwords' kullanılıyor
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Veritabanı bağlantısı başarısız: " . $e->getMessage();
    exit();
}
?>
