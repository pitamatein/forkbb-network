<?php

// Send_Email.php

// ─── Helpers ──────────────────────────────────────────────────────────────────

function json_response(bool $ok, string $message, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

function sanitize(string $s): string {
    return trim(strip_tags($s));
}

// ─── Request Validation ───────────────────────────────────────────────────────

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method not allowed.', 405);
}

// Optional: restrict to same-origin requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!str_starts_with($origin, ALLOWED_ORIGIN)) {
    // Uncomment to enforce:
    // json_response(false, 'Forbidden.', 403);
}

// Accept both application/json and multipart/form-data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw    = file_get_contents('php://input');
    $input  = json_decode($raw, true) ?? [];
} else {
    $input = $_POST;
}

// Required fields
$to      = sanitize($input['to']      ?? '');
$subject = sanitize($input['subject'] ?? '');
$body    = sanitize($input['body']    ?? '');

// Optional
$replyTo = sanitize($input['reply_to'] ?? '');
$cc      = sanitize($input['cc']       ?? '');
$bcc     = sanitize($input['bcc']      ?? '');
$isHtml  = !empty($input['is_html']);   // pass is_html=1 to send HTML body

if (!$to || !$subject || !$body) {
    json_response(false, 'to, subject, and body are required.', 400);
}

foreach (array_filter([$to, $cc, $bcc, $replyTo]) as $addr) {
    if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
        json_response(false, "Invalid email address: {$addr}", 400);
    }
}

// Basic rate-limit: one send per IP per 60 s (file-lock approach, no DB needed)
$lockDir  = sys_get_temp_dir() . '/mailer_locks';
@mkdir($lockDir, 0700, true);
$lockFile = $lockDir . '/' . md5($_SERVER['REMOTE_ADDR'] ?? 'cli');
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 60) {
    json_response(false, 'Please wait before sending another message.', 429);
}
touch($lockFile);

// ─── Send ─────────────────────────────────────────────────────────────────────

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->SMTPDebug  = SMTP::DEBUG_OFF;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($to);
    if ($replyTo) $mail->addReplyTo($replyTo);
    if ($cc)      $mail->addCC($cc);
    if ($bcc)     $mail->addBCC($bcc);

    $mail->Subject = $subject;

    if ($isHtml) {
        $mail->isHTML(true);
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
    } else {
        $mail->isHTML(false);
        $mail->Body = $body;
    }

    // File attachment (optional — uncomment and adapt)
    // if (!empty($_FILES['attachment']['tmp_name'])) {
    //     $mail->addAttachment($_FILES['attachment']['tmp_name'],
    //                          $_FILES['attachment']['name']);
    // }

    $mail->send();
    json_response(true, "Message sent to {$to}.");

} catch (Exception $e) {
    error_log('PHPMailer: ' . $mail->ErrorInfo);
    json_response(false, 'Send failed. Check server error log.', 500);
}
