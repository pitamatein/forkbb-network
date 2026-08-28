<?php

// Send_Access_Code.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* -------------------------------------------------
   Logging
------------------------------------------------- */
function logMsg(string $message): void
{
    @file_put_contents(
        './storage/logs/access_code.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function logEmail(string $status, string $recipient, string $subject, string $body): void
{
    @file_put_contents(
        './storage/logs/email.log',
        sprintf(
            "[%s] %s | To: %s | Subject: %s | Body: %s\n",
            date('Y-m-d H:i:s'),
            $status,
            $recipient,
            str_replace(["\r", "\n"], ' ', $subject),
            str_replace(["\r", "\n"], ' ', $body)
        ),
        FILE_APPEND | LOCK_EX
    );
}

$msg = '';

if (isset($_POST['email'])) {

    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Invalid email');
    }

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';

    logMsg("Install request | IP={$ip} | Email={$email}");

    /* -------------------------------------------------
       Build signed payload (5 min expiry)
    ------------------------------------------------- */
    $data = [
        'email'   => $email,
        'expires' => time() + 300
    ];

    $payload = base64_encode(json_encode($data));

    $signature = hash_hmac(
        'sha256',
        $payload,
        INSTALL_SECRET
    );

    $link = BASE_URL . "/?p=Verify_Access_Code"
          . "&d=" . urlencode($payload)
          . "&s=" . urlencode($signature);

    $subject = 'ForkBB Node Verification (5 minutes)';

    $body =
        "Verify your email to create a ForkBB node:\n\n" .
        $link . "\n\n" .
        "This link expires in 5 minutes.\n";

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom('no-reply@forkbb.net', 'ForkBB.net');
        $mail->addAddress($email);

        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();

        logMsg("Verification sent | IP={$ip} | Email={$email}");
        logEmail('SENT', $email, $subject, $body);

        $msg = 'Check your email for a verification link.';

    } catch (Exception $e) {

        logMsg("EMAIL FAIL | {$mail->ErrorInfo}");
        logEmail('FAILED', $email, $subject ?? '', $mail->ErrorInfo);

        $msg = 'Email error: ' . htmlspecialchars($mail->ErrorInfo);
    }
}
?>

<div class="w3-light-grey w3-padding w3-auto w3-card w3-round-large w3-margin-top">
    <p class="w3-bold w3-large">VERIFY EMAIL</p>
    <p><?= $msg ?></p>
</div>
