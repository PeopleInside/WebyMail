<?php
declare(strict_types=1);

require_once __DIR__ . '/requirements_check.php';

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/src/Config.php';

// Configure secure session cookie params BEFORE starting the PHP session.
if (session_status() === PHP_SESSION_NONE) {
    $secure = Config::isRequestSecure();
    if ($secure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Security Headers matching index.php
$cspNonce = bin2hex(random_bytes(16));
$cspHeader = "default-src 'self'; " .
             "script-src 'self' 'nonce-{$cspNonce}'; " .
             "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
             "font-src 'self' https://fonts.gstatic.com; " .
             "img-src 'self' data: https:; " .
             "connect-src 'self'; " .
             "frame-ancestors 'self'; " .
             "base-uri 'self'; " .
             "form-action 'self';";

header("Content-Security-Policy: $cspHeader");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

/**
 * WebyMail – First-run setup wizard
 * Access this file directly (setup.php) before the application is configured.
 * Once setup is complete, this file can be deleted or renamed.
 */

define('WEBYMAIL_ROOT', __DIR__);

// Persist ?force=1 into the session so it survives POST redirects
// (form actions use ?action=setup which drops the force query param).
if (($_GET['force'] ?? '') === '1') {
    $_SESSION['setup_force'] = true;
}
$isForced = !empty($_SESSION['setup_force']);

// If already set up, redirect to main app unless force setup is requested
if (Config::get('setup_complete') && !$isForced && ($_GET['action'] ?? '') !== 'setup') {
    header('Location: index.php');
    exit;
}

// Re-running setup on an already-configured installation (force=1) requires
// an active authenticated session to prevent unauthorised reconfiguration.
if ($isForced && Config::get('setup_complete')) {
    $sessionObj = new Session();
    $currentSession = $sessionObj->current();
    if ($currentSession === null || (int)$currentSession['user_id'] !== 1) {
        // No valid session or not the first user: abort and send the user to the normal login page
        unset($_SESSION['setup_force']);
        header('Location: index.php?action=login');
        exit;
    }
}

// Simple CSRF token for setup forms (session-bound, single token for the whole wizard)
if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}
$setupCsrfToken = $_SESSION['setup_csrf'];

// Validate CSRF on every POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['setup_csrf'] ?? '';
    if (!hash_equals($setupCsrfToken, $submittedCsrf)) {
        http_response_code(403);
        die('Security token mismatch. Please go back and try again.');
    }
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
        $hideServer = !empty($_POST['hide_server_on_login']);
        $removeFavicon = !empty($_POST['remove_favicon']);

        // Validate server hosts if specified (SSRF / syntax protection)
        try {
            if ($imapHost !== '') {
                $imapHost = HostValidator::validateHostAndPort($imapHost, $imapPort);
            }
            if ($smtpHost !== '') {
                $smtpHost = HostValidator::validateHostAndPort($smtpHost, $smtpPort);
            }
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $step  = 'server';
        }

        // Validate Favicon upload (allow only raster .ico / .png with verified MIME; no .svg)
        $pendingFavicon = null;
        if ($error === null && !empty($_FILES['favicon']['name']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $tmpFile  = $_FILES['favicon']['tmp_name'] ?? '';
            $fileSize = (int) ($_FILES['favicon']['size'] ?? 0);
            $ext      = strtolower(pathinfo((string) $_FILES['favicon']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['ico', 'png'], true)) {
                $error = 'Invalid favicon format. Only .ico and .png files are allowed.';
                $step  = 'server';
            } elseif ($fileSize <= 0 || $fileSize > 524288) {
                $error = 'Favicon file must be under 512 KB.';
                $step  = 'server';
            } elseif ($tmpFile !== '' && is_uploaded_file($tmpFile)) {
                $mime = '';
                if (function_exists('finfo_open')) {
                    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mime = strtolower((string) @finfo_file($finfo, $tmpFile));
                        @finfo_close($finfo);
                    }
                }
                if ($ext === 'png') {
                    $imgInfo = @getimagesize($tmpFile);
                    if ($imgInfo === false || ($imgInfo[2] ?? 0) !== IMAGETYPE_PNG || ($mime !== '' && $mime !== 'image/png')) {
                        $error = 'Uploaded PNG favicon is invalid.';
                        $step  = 'server';
                    } else {
                        $pendingFavicon = ['tmp' => $tmpFile, 'ext' => 'png'];
                    }
                } else {
                    $allowedIcoMimes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/ico', 'application/octet-stream'];
                    $rawHead = @file_get_contents($tmpFile, false, null, 0, 4);
                    $isIcoMagic = ($rawHead !== false && strlen($rawHead) === 4 && ($rawHead === "\x00\x00\x01\x00" || $rawHead === "\x00\x00\x02\x00"));
                    if (!$isIcoMagic || ($mime !== '' && !in_array($mime, $allowedIcoMimes, true))) {
                        $error = 'Uploaded ICO favicon is invalid.';
                        $step  = 'server';
                    } else {
                        $pendingFavicon = ['tmp' => $tmpFile, 'ext' => 'ico'];
                    }
                }
            }
        }

        if ($error === null) {
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

            if ($pendingFavicon !== null) {
                $imgDir = __DIR__ . '/assets/img';
                if (!is_dir($imgDir)) {
                    mkdir($imgDir, 0755, true);
                }
                foreach (['ico', 'png', 'svg'] as $oldExt) {
                    $oldFile = $imgDir . '/favicon.' . $oldExt;
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $faviconPath = 'assets/img/favicon.' . $pendingFavicon['ext'];
                if (move_uploaded_file($pendingFavicon['tmp'], __DIR__ . '/' . $faviconPath)) {
                    @chmod(__DIR__ . '/' . $faviconPath, 0644);
                    Config::set('favicon_path', $faviconPath);
                }
            }

            // Ensure data directory is writable
            $dataDir = __DIR__ . '/data';
            if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true)) {
                $error = 'Cannot create data/ directory. Please create it manually and make it writable.';
                $step  = 'server';
            } else {
                Config::save();

                // Database backup before schema modifications (if database exists)
                $dbPath = Config::resolveDbPath();
                if (is_file($dbPath)) {
                    // Save backup in the same directory as the database
                    $backupDir = dirname($dbPath);

                    if (is_dir($backupDir) && is_writable($backupDir)) {
                        $backupFile = $backupDir . '/webymail_backup_' . date('Ymd_His') . '.db';
                        @copy($dbPath, $backupFile);
                        // Also copy WAL/SHM if they exist, to ensure a complete backup
                        foreach (['-wal', '-shm'] as $suffix) {
                            if (is_file($dbPath . $suffix)) {
                                @copy($dbPath . $suffix, $backupFile . $suffix);
                            }
                        }

                        // Maintain only 5 backups
                        Config::rotateBackups($backupDir, 5);
                    }
                }

                // Initialise the database (creates the SQLite file + schema)
                try {
                    Database::getInstance();
                } catch (Exception $e) {
                    error_log('Database initialisation failed during setup: ' . $e->getMessage());
                    $error = 'Database initialisation failed. Please confirm the data directory is writable and try again.';
                    $step  = 'server';
                }

                if ($error === null) {
                    $step = 'security';
                    $sys = Config::checkSystem();
                    $securityChecks = $sys['security'];
                }
            }
        }
    } elseif ($step === 'finish') {
        $step = 'done';
        // Clear the force flag so the wizard cannot be re-entered without ?force=1
        unset($_SESSION['setup_force'], $_SESSION['setup_csrf']);

        // Move setup.php to the database directory for security (hardening)
        $dbDir = dirname(Config::resolveDbPath());
        if (is_dir($dbDir) && is_writable($dbDir)) {
            @rename(__FILE__, $dbDir . DIRECTORY_SEPARATOR . 'setup.php');
        } else {
            @unlink(__FILE__);
        }
    } elseif ($step === 'fix_permissions') {
        // Ensure a session is active so Config::checkSystem() can persist the
        // result cache, preventing a stale "issues found" banner in the main app.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        Config::fixPermissions();
        $step = 'security';
        $sys = Config::checkSystem();
        $securityChecks = $sys['security'];
    } elseif ($step === 'move_database') {
        $targetPath = trim($_POST['db_target_path'] ?? '');
        if ($targetPath === '') {
            $targetPath = Config::suggestSafeDbPath();
        }
        $result = Config::moveDatabase($targetPath);
        if (!$result['ok']) {
            $error = 'Failed to move database: ' . $result['error'];
        }
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
