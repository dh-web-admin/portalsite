<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';  // Gmail SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';  // Your Gmail address
        $mail->Password   = 'your-app-password';      // Gmail App Password (NOT your regular password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Darkhorse Portal');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Alternative configurations for other email providers:

/*
// For Outlook/Hotmail:
$mail->Host       = 'smtp.office365.com';
$mail->Port       = 587;

// For Yahoo:
$mail->Host       = 'smtp.mail.yahoo.com';
$mail->Port       = 587;

// For custom SMTP server:
$mail->Host       = 'mail.yourdomain.com';
$mail->Port       = 587; // or 465 for SSL
*/
?>
