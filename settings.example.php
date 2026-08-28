<?php
/**
 * File: settings.php  (Edit settings.example.php and save as settings.php)
 * Project: ForkBB Network (ForkBB.net)
 * Purpose: Initialization before all pages
 */

// SAMPLE VALUES - EDIT
define('BASE_URL', 'https://example.com/');
define('BASE_PATH', '/home/example/public_html');
define('INSTALL_SECRET', 'somerandomlettersandnumbers');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require BASE_PATH . '/vendor/PHPMailer/src/Exception.php';
require BASE_PATH . '/vendor/PHPMailer/src/PHPMailer.php';
require BASE_PATH . '/vendor/PHPMailer/src/SMTP.php';

// SAMPLE VALUES - EDIT
define('SMTP_HOST', 'mail.example.com'); // Your SMTP server
define('SMTP_PORT', 587 ); // SMTP port number
define('SMTP_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);
define('SMTP_USER', 'no-reply@example.com'); // SMTP login
define('SMTP_PASS', 'password'); // SMTP password
define('SMTP_FROM', 'no-reply@example.com'); // Envelope From
define('SMTP_FROM_NAME', 'Your Name Here');
define('ALLOWED_ORIGIN', 'https://example.com/'); // CORS / referer check

// ============================================================
// Environment
// ============================================================

date_default_timezone_set('UTC');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', 'error.log');

// ============================================================
// Request
// ============================================================

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$host = preg_replace('/:\d+$/', '', $host);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// ============================================================
// Page information
// ============================================================

$p = $_GET['p'] ?? '';

$uri = parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH) ?? '';
$pathInfo = pathinfo($uri);

define('DIRNAME',   $pathInfo['dirname']   ?? '/');
define('BASENAME',  $pathInfo['basename']  ?? '');
define('FILENAME',  $pathInfo['filename']  ?? '');
define('EXTENSION', $pathInfo['extension'] ?? '');

// ============================================================
// Rate limiting
// ============================================================

define('RATE_LIMIT_FILE', sys_get_temp_dir() . '/forum_install_rl.json');
define('RATE_LIMIT_MAX', 3);
define('RATE_LIMIT_WINDOW', 3600);

// ============================================================
// Canonical host redirects
// ============================================================

$redirects = [
    'forkbb.net'     => 'www.forkbb.net',
    'woodcentral.com' => 'www.woodcentral.com',
];

if (isset($redirects[$host])) {
    header(
        'Location: https://' . $redirects[$host] . $requestUri,
        true,
        301
    );
    exit;
}

// ============================================================
// Response caching
// ============================================================

$page_type = 'rare';

if ($page_type === 'frequent') {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
} else {
    header('Cache-Control: public, max-age=604800');
}
