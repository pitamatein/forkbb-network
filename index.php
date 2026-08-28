<?php
/**
 * File: index.php
 * Project: ForkBB.net
 * Purpose: Main entry point
 */

// MUST BE FIRST
require_once 'settings.php';

// =====================================================
// SECURITY HEADERS
// =====================================================

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// =====================================================
// ROUTING
// =====================================================

$requested = trim($_GET['p'] ?? 'Home', '/ ');

$parts = explode('/', $requested, 2);

if (count($parts) === 2) {
    $subdir = basename($parts[0]);
    $page   = basename($parts[1]);

    $filepath = './templates/' . $subdir . '/' . $page . '.php';
} else {
    $subdir = '';
    $page   = basename($requested);

    $filepath = './templates/' . $page . '.php';
}

// =====================================================
// FILE VALIDATION
// =====================================================

$realBase = realpath('./templates');
$realFile = realpath($filepath);

if (
    !$realBase ||
    !$realFile ||
    !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR) ||
    !is_file($realFile)
) {
    http_response_code(404);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>404 Not Found</title>
    </head>
    <body>
        <h1>404 Not Found</h1>
    </body>
    </html>
    <?php

    exit;
}

// =====================================================
// DEFAULT META
// =====================================================

$title = '';

$meta_keywords =
    'discourse alternative, phpbb alternative, simple forum hosting, affordable forum hosting, small community forum';

$meta_description =
    'Launch a fast, modern discussion forum in minutes. ForkBB-powered hosting — no server setup, no maintenance, just your community.';

// =====================================================
// LOAD SIDECAR META FILE
// =====================================================

$metaFile = preg_replace('/\.php$/', '.meta.php', $filepath);

if ($metaFile && is_file($metaFile)) {
    include $metaFile;
}

// =====================================================
// TITLE GENERATION
// =====================================================

if (empty($title)) {
    $titlePage = $subdir
        ? ucwords(str_replace('_', ' ', $subdir))
            . ' - '
            . ucwords(str_replace('_', ' ', $page))
        : ucwords(str_replace('_', ' ', $page));

    $titleParts = [$titlePage];

    foreach (['q', 'tag', 'search'] as $param) {
        if (!empty($_GET[$param])) {
            $titleParts[] = trim((string) $_GET[$param]);
        }
    }

    $title = implode(' | ', $titleParts);
}

// =====================================================
// ESCAPE META OUTPUT
// =====================================================

$title = htmlspecialchars(
    $title,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$meta_keywords = htmlspecialchars(
    $meta_keywords,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$meta_description = htmlspecialchars(
    $meta_description,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

// =====================================================
// TEMPLATE RENDERER
// =====================================================

function render_file(string $file): string
{
    if (!is_file($file)) {
        return '';
    }

    ob_start();

    try {
        include $file;

        return ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
}

// =====================================================
// LOAD PAGE COMPONENTS
// =====================================================

$head = render_file('./templates/head.php');
$header = render_file('./templates/header.php');
$content = render_file($filepath);
$footer = render_file('./templates/footer.php');

// =====================================================
// OUTPUT PAGE
// =====================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title><?= $title ?></title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="keywords" content="<?= $meta_keywords ?>">

    <meta name="description" content="<?= $meta_description ?>">
    
	<meta property="og:title" content="<?= $title ?>">
	<meta property="og:description" content="<?= $meta_description ?>">
	<meta property="og:image" content="/images/forkbb-hero.avif">
	<meta property="og:url" content="https://www.forkbb.net">
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="ForkBB Network">

    <?= $head ?>
    

<style>
/* ============ Tokens ============ */
:root{
  --paper:      #FAFAF7;
  --paper-dim:  #F1EFE8;
  --ink:        #15171B;
  --ink-soft:   #565C64;
  --ink-faint:  #8B9099;
  --line:       #E2DFD6;
  --line-dark:  #2A2D33;
  --accent:     #2A4FD6;
  --accent-ink: #172C86;
  --accent-tint:#EBEFFC;
  --online:     #1E8A5B;

  --display: 'Space Grotesk', system-ui, sans-serif;
  --body:    'IBM Plex Sans', system-ui, sans-serif;
  --mono:    'IBM Plex Mono', ui-monospace, Menlo, monospace;

  --max: 1120px;
}

*{ box-sizing:border-box; }
html{ scroll-behavior:smooth; }
body{
  margin:0;
  background:var(--paper);
  color:var(--ink);
  font-family:var(--body);
  font-size:16px;
  line-height:1.6;
  -webkit-font-smoothing:antialiased;
}
img{ max-width:100%; display:block; }
a{ color:inherit; }
.wrap{ max-width:var(--max); margin:0 auto; padding:0 28px; }

h1,h2,h3{ font-family:var(--display); font-weight:600; letter-spacing:-0.01em; margin:0; }
.eyebrow{
  font-family:var(--mono);
  font-size:0.78rem;
  letter-spacing:0.04em;
  color:var(--accent);
  display:flex; align-items:center; gap:8px;
  margin-bottom:14px;
}
.eyebrow::before{ content:''; width:14px; height:1px; background:var(--accent); }

/* ============ Header ============ */
header{
  position:sticky; top:0; z-index:20;
  background:rgba(250,250,247,0.92);
  backdrop-filter:blur(8px);
  border-bottom:1px solid var(--line);
}
.nav{
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 0;
}
.logo{
  font-family:var(--display); font-weight:700; font-size:1.15rem;
  display:flex; align-items:baseline; gap:2px;
}
.logo .dot{ color:var(--accent); }
.nav-links{ display:flex; gap:28px; font-size:0.92rem; color:var(--ink-soft); }
.nav-links a{ text-decoration:none; }
.nav-links a:hover{ color:var(--ink); }
.nav-cta{
  font-size:0.88rem; font-weight:600;
  padding:9px 18px;
  border-radius:6px;
  background:var(--ink);
  color:var(--paper);
  text-decoration:none;
}
.nav-cta:hover{ background:var(--accent-ink); }
@media (max-width:780px){ .nav-links{ display:none; } }

/* ============ Hero ============ */
.hero{ padding:76px 0 56px; }
.hero-grid{
  display:grid; grid-template-columns:1.05fr 1fr; gap:64px; align-items:center;
}
@media (max-width:900px){ .hero-grid{ grid-template-columns:1fr; gap:44px; } }

.hero h1{
  font-size:clamp(2.1rem, 4vw, 3rem);
  line-height:1.08;
  margin-bottom:20px;
}
.hero h1 em{ font-style:normal; color:var(--accent); }
.hero p.lede{
  font-size:1.08rem; color:var(--ink-soft); max-width:46ch;
  margin-bottom:28px;
}

/* email capture */
.capture{
  display:flex; gap:10px; max-width:460px; margin-bottom:14px;
}
.capture input{
  flex:1; min-width:0;
  padding:13px 16px;
  border:1px solid var(--line);
  border-radius:7px;
  font-family:var(--body); font-size:0.95rem;
  background:#fff;
}
.capture input:focus{ outline:2px solid var(--accent); outline-offset:1px; border-color:var(--accent); }
.capture button{
  font-family:var(--body); font-weight:600; font-size:0.95rem;
  padding:13px 20px;
  border:none; border-radius:7px;
  background:var(--accent); color:#fff;
  cursor:pointer; white-space:nowrap;
}
.capture button:hover{ background:var(--accent-ink); }
.hero-foot{ font-size:0.84rem; color:var(--ink-faint); display:flex; align-items:center; gap:16px; }
.hero-foot a{ text-decoration:underline; text-decoration-color:var(--line); text-underline-offset:3px; }

/* ---- thread mockup: the signature element ---- */
.thread-card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:12px;
  overflow:hidden;
  box-shadow:0 1px 2px rgba(20,20,20,0.04);
}
.thread-head{
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 18px;
  border-bottom:1px solid var(--line);
  font-family:var(--mono); font-size:0.76rem; color:var(--ink-soft);
}
.thread-head .status{ display:flex; align-items:center; gap:7px; }
.pulse{
  width:7px; height:7px; border-radius:50%;
  background:var(--online);
  box-shadow:0 0 0 rgba(30,138,91,0.5);
  animation:pulse 2.2s infinite;
}
@keyframes pulse{
  0%{ box-shadow:0 0 0 0 rgba(30,138,91,0.35); }
  70%{ box-shadow:0 0 0 6px rgba(30,138,91,0); }
  100%{ box-shadow:0 0 0 0 rgba(30,138,91,0); }
}
.row{
  display:flex; align-items:center; justify-content:space-between;
  gap:12px;
  padding:13px 18px;
  border-bottom:1px solid var(--line);
}
.row:last-child{ border-bottom:none; }
.row .name{ font-size:0.93rem; font-weight:600; }
.row .sub{ font-size:0.8rem; color:var(--ink-faint); margin-top:2px; }
.row .stats{ font-family:var(--mono); font-size:0.76rem; color:var(--ink-soft); white-space:nowrap; text-align:right; }
.row.live{ background:var(--accent-tint); }
.typing{ display:inline-flex; align-items:center; gap:5px; color:var(--accent); font-family:var(--mono); font-size:0.78rem; }
.cursor{
  display:inline-block; width:6px; height:13px; background:var(--accent);
  animation:blink 1s steps(1) infinite;
}
@keyframes blink{ 50%{ opacity:0; } }
@media (prefers-reduced-motion:reduce){
  .pulse, .cursor{ animation:none; }
}

/* ============ Sections generic ============ */
section{ padding:64px 0; }
.section-head{ max-width:640px; margin-bottom:44px; }
.section-head h2{ font-size:clamp(1.6rem,2.6vw,2.1rem); margin-bottom:14px; }
.section-head p{ color:var(--ink-soft); font-size:1.02rem; }

.divider{ border:none; border-top:1px solid var(--line); margin:0; }

/* ============ Steps (real sequence — numbering earned) ============ */
.steps{ display:grid; grid-template-columns:repeat(3,1fr); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:12px; overflow:hidden; }
@media (max-width:780px){ .steps{ grid-template-columns:1fr; } }
.step{ background:var(--paper); padding:30px 28px; }
.step .num{ font-family:var(--mono); color:var(--accent); font-size:0.85rem; margin-bottom:14px; }
.step h3{ font-size:1.08rem; margin-bottom:8px; }
.step p{ color:var(--ink-soft); font-size:0.93rem; margin:0; }

/* ============ Feature list (no card soup) ============ */
.feature-list{ border-top:1px solid var(--line); }
.feature-row{
  display:grid; grid-template-columns:280px 1fr; gap:32px;
  padding:26px 0; border-bottom:1px solid var(--line);
}
@media (max-width:780px){ .feature-row{ grid-template-columns:1fr; gap:8px; } }
.feature-row .label{ font-family:var(--mono); font-size:0.85rem; color:var(--ink-faint); }
.feature-row h3{ font-size:1.1rem; margin-bottom:6px; }
.feature-row p{ color:var(--ink-soft); margin:0; max-width:56ch; }

/* ============ Benefit grid — flat, thin border, no shadow ============ */
.benefits{
  display:grid; grid-template-columns:repeat(4,1fr); gap:1px;
  background:var(--line); border:1px solid var(--line); border-radius:12px; overflow:hidden;
}
@media (max-width:900px){ .benefits{ grid-template-columns:repeat(2,1fr); } }
@media (max-width:520px){ .benefits{ grid-template-columns:1fr; } }
.benefit{ background:var(--paper); padding:26px 24px; }
.benefit .mark{ font-family:var(--mono); font-size:0.78rem; color:var(--accent); margin-bottom:10px; }
.benefit h3{ font-size:0.98rem; margin-bottom:8px; }
.benefit p{ font-size:0.86rem; color:var(--ink-soft); margin:0; }

/* ============ Use cases ============ */
.uses{ display:grid; grid-template-columns:repeat(5,1fr); gap:1px; background:var(--line); border:1px solid var(--line); border-radius:12px; overflow:hidden; }
@media (max-width:900px){ .uses{ grid-template-columns:repeat(2,1fr); } }
@media (max-width:560px){ .uses{ grid-template-columns:1fr; } }
.use{ background:var(--paper); padding:24px 20px; }
.use h3{ font-size:0.96rem; margin-bottom:8px; }
.use p{ font-size:0.83rem; color:var(--ink-soft); margin:0; }

/* ============ CTA band ============ */
.cta-band{
  background:var(--ink);
  color:var(--paper);
  border-radius:16px;
  padding:56px 48px;
  display:flex; align-items:center; justify-content:space-between; gap:32px;
  flex-wrap:wrap;
}
.cta-band h2{ font-size:1.7rem; color:#fff; margin-bottom:8px; }
.cta-band p{ color:#B8BEC9; margin:0; font-size:0.98rem; }
.cta-band .btn{
  font-family:var(--body); font-weight:600; font-size:0.95rem;
  padding:14px 26px; border-radius:7px;
  background:var(--accent); color:#fff; text-decoration:none;
  white-space:nowrap;
}
.cta-band .btn:hover{ background:#4B6BE0; }

</style>

</head>
<body>

<header class="w3-container">
  <div class="wrap nav">
    <a href="/" class="logo"><img style="height:80px; width:auto" src="/images/forkbb-network-logo.avif" alt="ForkBB Network"></a>
    <nav class="nav-links">
      <a href="/#steps">How it works</a>
      <a href="/#features">Features</a>
      <a href="/#uses">Use cases</a>
    </nav>
    <a class="nav-cta" href="/#get-forum">Start a forum</a>
  </div>
</header>

<?= $header ?>
<?= $content ?>
<?= $footer ?>

</body>
</html>
