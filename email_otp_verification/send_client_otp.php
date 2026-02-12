<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; // safer path resolution

function sendClientOTP($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Client Registration';
        $mail->Body = "
            <h3>Welcome!</h3>
            <p>Use the following OTP to verify your client registration:</p>
            <h2 style='color: blue;'>$otp</h2>
            <p>This OTP will expire soon. Please complete your registration promptly.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Client email error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
