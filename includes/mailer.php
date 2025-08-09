<?php
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
require_once __DIR__ . '/../config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

// Enable debug mode only in development
if (Environment::isDebug()) {
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
}

$mail->isSMTP();
$mail->SMTPAuth = true;

// Use environment variables for email configuration
$mail->Host = Environment::get('MAIL_HOST', 'smtp.gmail.com');
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = Environment::get('MAIL_PORT', 587);
$mail->Username = Environment::get('MAIL_USERNAME', '');
$mail->Password = Environment::get('MAIL_PASSWORD', '');

$mail->isHTML(true);

return $mail;