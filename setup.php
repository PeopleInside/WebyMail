<?php
declare(strict_types=1);
ini_set('expose_php', '0');
header_remove('X-Powered-By');

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

$cspNonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; object-src 'none'; script-src 'self' 'nonce-{$cspNonce}'; style-src 'self'; style-src-elem 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 0');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/**
 * WebyMail – First-run setup wizard
 * Access this file directly (setup.php) before the application is configured.
 * Once setup is complete, this file can be deleted or renamed.
 */

define('WEBYMAIL_ROOT', __DIR__);

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/src/Config.php';

function scriptNonce(): string
{
    global $cspNonce;
    return $cspNonce;
}

// Allow post-install review / re-run when explicitly requested.
$forceSetup = !empty($_GET['force']) || !empty($_POST['force']);
$setupCompleted = (bool) Config::get('setup_complete');

if ($setupCompleted && !$forceSetup) {
    $step = 'done';
}

$step  = 'welcome';
$error = null;
$requirements = [];
$securityChecks = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? 'welcome';

    if ($step === 'requirements') {
        $needed = ['imap', 'pdo_sqlite', 'openssl', 'mbstring', 'iconv'];
        $allOk = true;
        foreach ($needed as $ext) {
            $ok = extension_loaded($ext);
            $requirements[$ext] = $ok;
            if (!$ok) $allOk = false;
        }
        if (!$allOk && !isset($_POST['ignore_requirements'])) {
            $step = 'requirements';
        } else {
            $step = 'server';
        }
    } elseif ($step === 'server') {
        // Just show server settings form
    } elseif ($step === 'save') {
        // Validate and save
        $appName   = trim($_POST['app_name']   ?? 'WebyMail');
        $imapHost  = trim($_POST['imap_host']  ?? '');
        $imapPort  = (int) ($_POST['imap_port'] ?? 993);
        $imapSsl   = !empty($_POST['imap_ssl']);
        $smtpHost  = trim($_POST['smtp_host']  ?? '');
        $smtpPort  = (int) ($_POST['smtp_port'] ?? 587);
        $smtpSsl   = !empty($_POST['smtp_ssl']);
        $smtpTls   = !empty($_POST['smtp_starttls']);
        $captchaOn  = !empty($_POST['captcha_enabled']);
        $timezone  = trim($_POST['timezone'] ?? 'Europe/Rome');
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Europe/Rome';
        }
        $hideServer = !empty($_POST['hide_server_on_login']);
        $removeFavicon = !empty($_POST['remove_favicon']);

        Config::set('app_name',       $appName);
        Config::set('imap_host',      $imapHost);
        Config::set('imap_port',      $imapPort);
        Config::set('imap_ssl',       $imapSsl);
        Config::set('smtp_host',      $smtpHost);
        Config::set('smtp_port',      $smtpPort);
        Config::set('smtp_ssl',       $smtpSsl);
        Config::set('smtp_starttls',  $smtpTls);
        Config::set('captcha_enabled', $captchaOn);
        Config::set('2fa_enabled',     true); // Always enabled by default, can be disabled in config.php manually if needed
        Config::set('timezone',       $timezone);
        Config::set('hide_server_on_login', $hideServer);
        Config::set('setup_complete', true);
        Config::set('version', null); // Ensure version is removed from config.php
        Config::set('update_url', Config::UPDATE_URL);

        // Cleanup obsolete keys
        Config::set('altcha_hmac_key', null);
        Config::set('altcha_enabled', null);

        if ($removeFavicon) {
            Config::set('favicon_path', null);
        }

        // Handle Favicon upload
        if (!empty($_FILES['favicon']['name']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['ico', 'png'], true) && ($_FILES['favicon']['size'] ?? 0) <= (512 * 1024)) {
                $imgDir = __DIR__ . '/assets/img';
                if (!is_dir($imgDir)) {
                    mkdir($imgDir, 0755, true);
                }
                $faviconPath = 'assets/img/favicon.' . $ext;
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], __DIR__ . '/' . $faviconPath)) {
                    Config::set('favicon_path', $faviconPath);
                }
            }
        }

        // Ensure data directory is writable
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true)) {
            $error = 'Cannot create data/ directory. Please create it manually and make it writable.';
            $step  = 'server';
        } else {
            Config::save();

            // Initialise the database (creates the SQLite file + schema)
            try {
                Database::getInstance();
            } catch (Exception $e) {
                $error = 'Database initialisation failed: ' . $e->getMessage();
                $step  = 'server';
            }

            if ($error === null) {
                $step = 'security';
                $sys = Config::checkSystem();
                $securityChecks = $sys['security'];
            }
        }
    } elseif ($step === 'finish') {
        $step = 'done';
    } elseif ($step === 'fix_permissions') {
        Config::fixPermissions();
        $step = 'security';
        $sys = Config::checkSystem();
        $securityChecks = $sys['security'];
    }
}

// Render
ob_start();
include __DIR__ . '/templates/setup.php';
$content = ob_get_clean();

$pageTitle   = Config::get('app_name', 'WebyMail') . ' Setup';
$shellLayout = false;

include __DIR__ . '/templates/layout.php';
