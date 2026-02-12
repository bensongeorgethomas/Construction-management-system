<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendDecisionEmail($email, $decision) {
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
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = 'Registration Status';
        $mail->AltBody = $decision === 'approved'
            ? 'Your worker registration has been approved.'
            : 'Your worker registration has been declined.';

        if ($decision === 'approved') {
            $mail->Body = "<h3>എടാ മോനേ, നീ വിഷയം തന്നെ കേട്ടോ. എനിക്ക് നിന്നെ അങ്ങോട്ട് ഇഷ്ടപ്പെട്ടു. നീ ചുമ്മാ തീയാ. ഇവിടെ വന്നാൽ പിന്നെ നിൻ്റെ ജീവിതം തന്നെ ഒരു പൊളി പൊളിക്കും</h3><p>Your worker registration has been <b>approved</b>.</p>";
        } else {
            $mail->Body = "<h3>നിനക്ക് കഴിവില്ലന്ന് നിനക്കും അറിയാം, നമുക്കും അറിയാം. അത് കൊണ്ട് നിന്റെ നന്മയ്ക്ക് വേണ്ടി വീട്ടിൽ ഇരിക്ക്</h3><p>Your worker registration has been <b>declined</b>.</p>";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
