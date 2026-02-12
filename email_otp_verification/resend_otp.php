<?php
// Load configuration and session safely
require_once __DIR__ . '/../conn.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Required PHPMailer files
require '../vendor/autoload.php';

// Set header to return a JSON response
header('Content-Type: application/json');

/**
 * Sends a new OTP using the PHPMailer configuration from index.php.
 *
 * @param string $email The recipient's email address.
 * @param string $otp The new 6-digit OTP.
 * @return bool True on success, false on failure.
 */
function sendNewOtpEmail($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        // --- Server settings using config constants ---
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        // --- Recipients ---
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // --- Content ---
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Your New Verification Code'; // A clear subject for the resent code
        $mail->Body    = "<h3>Your new verification code is: <b>$otp</b></h3><p>This code is valid for 60 seconds.</p>";

        $mail->send();
        return true; // Email sent successfully

    } catch (Exception $e) {
        // Log the detailed error on the server without showing it to the user
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false; // Email failed to send
    }
}

// Check if the user's email is in the session
if (!isset($_SESSION['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Your session has expired. Please start the registration process again.']);
    exit();
}

$email = $_SESSION['email'];

// Generate a new 6-digit OTP
$new_otp = rand(100000, 999999);

// Update session with the new OTP and reset the 1-minute expiry time
$_SESSION['otp'] = $new_otp;
$_SESSION['otp_expiry'] = time() + 60;

// Attempt to send the email with the new OTP
if (sendNewOtpEmail($email, $new_otp)) {
    echo json_encode(['status' => 'success', 'message' => 'A new OTP has been sent to your email.']);
} else {
    // If sending fails, expire the timer immediately so the user can try again
    $_SESSION['otp_expiry'] = time();
    echo json_encode(['status' => 'error', 'message' => 'We could not send the OTP. Please try again in a moment.']);
}

exit();
?>