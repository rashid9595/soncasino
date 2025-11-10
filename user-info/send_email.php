<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require '../../vendor/autoload.php';
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/Exception.php';
require '../../PHPMailer/src/SMTP.php';
require '../../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class GoogleAuthenticator {
    private $db;
    private $userId;
    private $email;
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->email = $this->getUserEmail();
    }
    
    private function getUserEmail() {
        $query = "SELECT email FROM kullanicilar WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $this->userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function generateSecretKey($length = 16) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secretKey = '';
        for ($i = 0; $i < $length; $i++) {
            $secretKey .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $secretKey;
    }
    
    public function generateQRCode($secretKey) {
        $issuer = 'Dissbet';
        $name = $this->email;
        $otpauth_url = "otpauth://totp/" . rawurlencode("$issuer:$name") . 
                      "?secret=" . $secretKey . 
                      "&issuer=" . rawurlencode($issuer);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new ImagickImageBackEnd()
        );

        $writer = new Writer($renderer);
        $qrImage = $writer->writeString($otpauth_url);
        
        return [
            'image' => base64_encode($qrImage),
            'url' => $otpauth_url
        ];
    }
    
    public function saveQRCode($base64Image) {
        $directory = '../../qr_codes/';
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
        
        $fileName = "qr_code_{$this->userId}.png";
        $filePath = $directory . $fileName;
        
        @file_put_contents($filePath, base64_decode($base64Image));
        return $fileName;
    }
    
    public function sendEmail($qrCodePath, $secretKey) {
        $mail = new PHPMailer(true);
        
        // Get email settings from database
        $query = "SELECT * FROM admin_email_settings WHERE id = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $email_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $mail->isSMTP();
        $mail->Host = $email_settings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_settings['smtp_user']; 
        $mail->Password = $email_settings['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $email_settings['smtp_port'];
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($email_settings['smtp_from'], $email_settings['smtp_from_name']);
        $mail->addAddress($this->email);
        
        $mail->isHTML(true);
        $mail->Subject = 'Google Authenticator Doğrulaması';
        
        if (file_exists($qrCodePath)) {
            $mail->addEmbeddedImage($qrCodePath, 'qrcode');
            
            $mail->Body = "
                <p>Google Authenticator'ı aktif etmek için aşağıdaki QR kodunu tarayın:</p>
                <img src='cid:qrcode' alt='QR Code' style='max-width: 200px;'>
                <p>Bu QR kodu, hesabınızda 2 faktörlü doğrulamayı etkinleştirecektir.</p>
                <p>Manual giriş için kod: {$secretKey}</p>
            ";
            
            @$mail->send();
        }
    }
    
    public function updateUserRecord($secretKey) {
        $query = "UPDATE kullanicilar SET secret_key = :secretKey, twofactor = 'pasif' WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':secretKey', $secretKey, PDO::PARAM_STR);
        $stmt->bindParam(':id', $this->userId, PDO::PARAM_INT);
        @$stmt->execute();
    }
}

if (isset($_SESSION['id'])) {
    @header_remove();
    @ini_set('display_errors', 0);
    @error_reporting(0);
    
    $auth = new GoogleAuthenticator($db, $_SESSION['id']);
    $secretKey = $auth->generateSecretKey();
    $qrCode = $auth->generateQRCode($secretKey);
    $qrCodeFileName = $auth->saveQRCode($qrCode['image']);
    $qrCodePath = "../../qr_codes/$qrCodeFileName";
    $auth->sendEmail($qrCodePath, $secretKey);
    $auth->updateUserRecord($secretKey);
    
    exit();
}
?>