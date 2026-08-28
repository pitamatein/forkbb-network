<?php

// Install_Node.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ============================================================
   LOGGING
   ============================================================ */

/**
 * Write installer log entry (file + browser output)
 */
function logInstaller(string $message): void
{
    file_put_contents(
        './logs/installer.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Log email activity separately for audit/debugging
 */
function logEmail(
    string $status,
    string $recipient,
    string $subject,
    string $body
): void {
    file_put_contents(
        './logs/email.log',
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

/**
 * Echo + log message
 */
function logMsg(string $msg): void
{
    echo '[INSTALL] ' . htmlspecialchars($msg) . '<br>';
    logInstaller($msg);
}

/* ============================================================
   UTILITY
   ============================================================ */

/**
 * Generate a human-readable admin passphrase.
 * - Space-separated
 * - Easy to type
 * - Strong enough for temporary admin credentials
 */
function generatePassphrase(): string
{
    $words = [
        'Maple','Granite','Silver','Autumn','Cedar',
        'Willow','River','Harbor','Forest','Prairie',
        'Thunder','Falcon','Sunrise','Summit','Meadow',
        'Canyon','Ocean','Eagle','Brook','Horizon'
    ];

    return
        $words[array_rand($words)] . ' ' .
        $words[array_rand($words)] . ' ' .
        random_int(10, 99) . ' ' .
        $words[array_rand($words)];
}

/**
 * Convert forum title into safe directory slug
 */
function makeDirName(string $title, string $basePath = ''): string
{
    // Transliterate accented/unicode characters to ASCII where possible
    $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($title));
    if ($translit === false || $translit === '') {
        $translit = $title; // fall back to original if iconv fails
    }

    // Remove anything that isn't a letter, digit, or space
    $clean = preg_replace('/[^A-Za-z0-9 ]+/', '', $translit);

    // Collapse runs of whitespace into a single underscore
    $slug = preg_replace('/\s+/', '_', trim($clean));
    $slug = strtolower(trim($slug, '_'));

    // Guard against empty result (e.g. title was all punctuation/emoji)
    if ($slug === '') {
        $slug = 'node';
    }

    // Truncate to a sane length for filesystem/URL friendliness
    $slug = substr($slug, 0, 64);
    $slug = rtrim($slug, '_');

    // Ensure uniqueness against existing directories, if a base path is given
    if ($basePath !== '') {
        $candidate = $slug;
        $i = 2;
        while (is_dir(rtrim($basePath, '/') . '/' . $candidate)) {
            $candidate = $slug . '_' . $i;
            $i++;
        }
        $slug = $candidate;
    }

    return $slug;
}

/**
 * Recursive directory copy (pristine ForkBB source expected)
 */
function copyDirectory(string $source, string $destination): void
{
    if (!is_dir($source)) {
        throw new RuntimeException("Source not found: $source");
    }

    if (!is_dir($destination)) {
        if (!mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new RuntimeException("Failed to create: $destination");
        }
    }

    foreach (scandir($source) as $item) {
        if ($item === '.' || $item === '..') continue;

        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $destination . DIRECTORY_SEPARATOR . $item;

        if (is_dir($src)) {
            copyDirectory($src, $dst);
        } else {
            if (!copy($src, $dst)) {
                throw new RuntimeException("Failed copying: $src");
            }
        }
    }
}

/* ============================================================
   FORKBB INSTALLER (CLI WRAPPER)
   ============================================================ */

/**
 * Run ForkBB CLI installer using SQLite backend
 */
function installForkBBSQLite(
    string $destination,
    string $dir,
    string $title,
    string $email,
    string $adminPass
): void {

    $cli = $destination . '/app/cli.php';

    if (!file_exists($cli)) {
        throw new RuntimeException("CLI not found: $cli");
    }

    $baseUrl = BASE_URL . '/-/' . $dir;

    /*
     * IMPORTANT:
     * ForkBB CLI expects a full argument set even for SQLite mode.
     * Removing "unused" DB args breaks install completion.
     */
    $cmd =
        '/usr/bin/php ' .
        escapeshellarg($cli) .
        ' install ' .
        '--installlang=en ' .
        '--dbtype=sqlite ' .
        '--dbhost=localhost ' .
        '--dbname=forum.sqlite ' .
        '--dbuser="" ' .
        '--dbpass="" ' .
        '--dbprefix="forum_" ' .
        '--username=admin ' .
        '--password=' . escapeshellarg($adminPass) . ' ' .
        '--email=' . escapeshellarg($email) . ' ' .
        '--title=' . escapeshellarg($title) . ' ' .
        '--descr="" ' .
        '--baseurl=' . escapeshellarg($baseUrl) . ' ' .
        '--defaultlang=en ' .
        '--defaultstyle=ForkBB ' .
        '--cookie_domain="" ' .
        '--cookie_path=' . escapeshellarg('/-/' . $dir . '/') . ' ' .
        '--cookie_secure=1 ';
        
// Pass SMTP configuration to the ForkBB installer process.
putenv('FORKBB_WEBMASTER_EMAIL=' . SMTP_USER);
putenv('FORKBB_SMTP_HOST=' . SMTP_HOST.':'.SMTP_PORT);
putenv('FORKBB_SMTP_USER=' . SMTP_USER);
putenv('FORKBB_SMTP_PASS=' . SMTP_PASS);

            
    logMsg('Running ForkBB installer');

    exec($cmd . ' 2>&1', $output, $rc);

    foreach ($output as $line) {
        logInstaller('[CLI] ' . $line);
    }

    logInstaller("CLI return code: " . $rc);

    if ($rc !== 0) {
        throw new RuntimeException(
            "ForkBB install failed:\n" . implode("\n", $output)
        );
    }

    logMsg('ForkBB installer completed');
}

/* ============================================================
   EMAIL
   ============================================================ */

/**
 * Send admin credentials + reset link
 */
function sendWelcomeEmail(
    string $title,
    string $dir,
    string $email,
    string $passphrase
): void {

    $subject = 'Your ForkBB forum is ready';

    $body =
        "Your forum has been created.\n\n" .
        "Forum: $title\n" .
        "URL: " . BASE_URL . "/-/$dir/\n\n" .
        "Reset passphrase:\n" .
        BASE_URL . "/-/$dir/login/forget";

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom('no-reply@forkbb.net', 'ForkBB.net');
        $mail->addAddress($email);

        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();

        logEmail('SENT', $email, $subject, $body);
        logMsg('Email sent');

    } catch (Throwable $e) {
        logEmail('FAILED', $email, $subject, $e->getMessage());
        logMsg('Email failed: ' . $e->getMessage());
    }
}

/* ============================================================
   INSTALLATION ORCHESTRATION
   ============================================================ */

function installForum(array $cfg): void
{
    $title = $cfg['title'];
    $dir   = $cfg['dir'];
    $passphrase = $cfg['passphrase'];

    $destination =
        BASE_PATH . '/-/' . $dir;

    logMsg("Installing node: $dir");

    /*
     * Step 1:
     * Copy pristine ForkBB source tree
     */
    copyDirectory($cfg['source'] . '/forkbb-master', $destination);
    logMsg('Files copied');


    /*
     * Step 2:
     * Run ForkBB SQLite installer
     */
    installForkBBSQLite(
        $destination,
        $dir,
        $title,
        $cfg['email'],
        $passphrase
    );

    /*
     * Step 4:
     * Email credentials + reset link
     */
    sendWelcomeEmail(
        $title,
        $dir,
        $cfg['email'],
        $passphrase
    );
}

/* ============================================================
   MAIN ENTRY
   ============================================================ */

echo '<div class="w3-light-grey w3-padding w3-auto w3-card w3-round-large w3-margin-top">';

echo '<h1 class="w3-bold w3-large">CREATING FORUM</h1>';

/*
 * Cloudflare-aware IP capture (future rate limiting use)
 */
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];

$dirRaw = $_POST['forum_dir'] ?? '';
$email  = $_POST['email'] ?? '';
$code   = $_POST['code'] ?? '';
$aup    = $_POST['aup'] ?? '';

if ($aup !== 'Yes') {
    die('AUP required');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email');
}

$title = trim($dirRaw);
$dir   = makeDirName($dirRaw);

if (!$dir) {
    die('Invalid forum name');
}

if (is_dir("-/$dir")) {
    die('Forum exists. Go back and try a different name.');
}

$passphrase = generatePassphrase();

installForum([
    'dir'    => $dir,
    'title'  => $title,
    'email'  => $email,
    'source' => BASE_PATH . '/vendor',
    'passphrase' => $passphrase
]);


echo '<p>Done.</p>';
echo "<code>
Username: admin<br>
Passphrase: $passphrase
</code>";
echo '<p><a href="/-/' . htmlspecialchars($dir) . '/">View forum</a></p>';

echo '</div>';
