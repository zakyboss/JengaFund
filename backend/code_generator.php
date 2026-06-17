<?php
/**
 * Utility to generate secure verification codes
 *
 * Requires PHPMailer. Download from https://github.com/PHPMailer/PHPMailer/releases
 * and place the contents of the PHPMailer folder into JengaFund/vendor/phpmailer/phpmailer/
 * (so PHPMailer.php is at JengaFund/vendor/phpmailer/phpmailer/src/PHPMailer.php)
 */

function generateVerificationCode($length = 6) {
    try {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    } catch (Exception $e) {
        // Fallback if random_int fails
        return substr(str_shuffle("0123456789"), 0, $length);
    }
}

function sendVerificationEmail($to, $code) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true); // true enables exceptions

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME');
        $mail->Password   = getenv('SMTP_PASSWORD');
        $mail->SMTPSecure = getenv('SMTP_ENCRYPTION'); // Use 'ssl' or 'tls'
        $mail->Port       = getenv('SMTP_PORT');
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom('no-reply@jengafund.com', 'JengaFund');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Verify your JengaFund Account";
        $template = require __DIR__ . '/../Mail/verification_template.php';
        $mail->Body    = str_replace('{{CODE}}', $code, $template);
        $mail->AltBody = "Your JengaFund verification code is: " . $code . ". This code will expire in 1 hour."; // For non-HTML mail clients

        $mail->send();
        error_log("Verification email sent to: " . $to);
        return true;
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log("Verification email failed to send to " . $to . ". Mailer Error: {$mail->ErrorInfo}");
        return false;
    } catch (Exception $e) {
        error_log("General error in sendVerificationEmail: " . $e->getMessage());
        return false;
    }
}