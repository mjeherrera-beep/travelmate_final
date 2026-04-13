<?php
session_start();
include 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

if (!isset($_SESSION['verification_email'])) {
    header("Location: register.php");
    exit();
}

$email = $_SESSION['verification_email'];
$new_code = rand(100000, 999999);
$token_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Update user with new code
$update = "UPDATE users SET verification_token = '$new_code', token_expires = '$token_expires' WHERE email = '$email' AND email_verified = 0";
mysqli_query($conn, $update);

// Send new verification email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'travelmate323@gmail.com';
    $mail->Password   = 'onlpyhrkreziannz';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail->setFrom('travelmate323@gmail.com', 'TravelMate');
    $mail->addAddress($email);
    
    $mail->isHTML(true);
    $mail->Subject = 'Your New Verification Code - TravelMate';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; background: #ffffff; border-radius: 24px; border: 1px solid #e8e8e8;'>
            <div style='text-align: center;'>
                <h2 style='color: #1a1a1a;'>New Verification Code</h2>
                <p style='color: #6b6b6b;'>Here is your new verification code:</p>
                <div style='background: #f5f5f0; padding: 20px; border-radius: 16px; margin: 24px 0; text-align: center;'>
                    <span style='font-size: 36px; font-weight: 700; color: #f39c12; letter-spacing: 8px;'>$new_code</span>
                </div>
                <p style='color: #8a8a8a; font-size: 12px;'>This code will expire in 10 minutes.</p>
            </div>
        </div>
    ";
    
    $mail->send();
    $_SESSION['verification_code'] = $new_code;
    header("Location: verify_code.php?resent=1");
    exit();
} catch (Exception $e) {
    header("Location: verify_code.php?error=1");
    exit();
}
?>