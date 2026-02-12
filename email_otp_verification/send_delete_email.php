<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust this path to point to your vendor directory
$vendorPath = __DIR__ . '/../vendor/autoload.php'; // Goes up 3 levels from email_otp_verification
if (!file_exists($vendorPath)) {
    die("Vendor autoload file not found. Please check the path: $vendorPath");
}
require $vendorPath;

function sendDeleteNotification($email, $name) {
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
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = 'Account Deletion Notice';
        $mail->Body = "
            <h2>Dear $name,</h2>
            <p>നിൻ്റെ കഴിവില്ലായ്മ അറിയാൻ കുറച്ചു വൈകിപ്പോയി. നിനക്ക് വേറെ എന്തോ വലിയ കഴിവുണ്ടെന്ന് ഞങ്ങൾ നീ അറിയാതെ തന്നെ മനസ്സിലാക്കിക്കഴിഞ്ഞു. അതുകൊണ്ട് നീ കുറച്ചു നാൾ വീട്ടിൽ ഇരിക്ക്. ഇനി ഒരു തിരിച്ചുവരവ് ഉണ്ടാകരുത്.</p>
            <p>Details:</p>
            <ul>
                <li><strong>Date:</strong> ".date('Y-m-d H:i:s')."</li>
            </ul>
        ";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Deletion email failed for $email: " . $mail->ErrorInfo);
        return false;
    }
}
?>