<?php
declare(strict_types=1);

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

// If already set up, redirect to main app
if (Config::isSetup() && ($_GET['action'] ?? '') !== 'setup') {
    header('Location: index.php');
    exit;
}

$step  = 'welcome';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? 'welcome';

    if ($step === 'server') {
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
        $altchaOn  = !empty($_POST['altcha_enabled']);

        // New settings
        $loginSubtitle       = trim($_POST['login_subtitle']       ?? '');
        $loginFooter         = trim($_POST['login_footer']         ?? '');
        $showServerSettings  = !empty($_POST['show_server_settings']);
        $twoFactorEnabled    = !empty($_POST['two_factor_enabled']);
        $timezone            = trim($_POST['timezone']             ?? 'Europe/Rome');

        // Validate timezone
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Europe/Rome';
        }

        Config::set('app_name',             $appName);
        Config::set('imap_host',            $imapHost);
        Config::set('imap_port',            $imapPort);
        Config::set('imap_ssl',             $imapSsl);
        Config::set('smtp_host',            $smtpHost);
        Config::set('smtp_port',            $smtpPort);
        Config::set('smtp_ssl',             $smtpSsl);
        Config::set('smtp_starttls',        $smtpTls);
        Config::set('altcha_enabled',       $altchaOn);
        Config::set('login_subtitle',       $loginSubtitle);
        Config::set('login_footer',         $loginFooter);
        Config::set('show_server_settings', $showServerSettings);
        Config::set('two_factor_enabled',   $twoFactorEnabled);
        Config::set('timezone',             $timezone);
        Config::set('setup_complete',       true);

        // Ensure data directory is writable
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true)) {
            $error = 'Cannot create data/ directory. Please create it manually and make it writable.';
            $step  = 'server';
        } else {
            // Handle favicon upload
            $faviconFile = $_FILES['favicon'] ?? null;
            if (!empty($faviconFile['tmp_name']) && ($faviconFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $origName  = $faviconFile['name'] ?? '';
                $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $maxBytes  = 512 * 1024; // 512 KB limit

                if (!in_array($ext, ['ico', 'svg'], true)) {
                    $error = 'Favicon must be a .ico or .svg file.';
                    $step  = 'server';
                } elseif (($faviconFile['size'] ?? 0) > $maxBytes) {
                    $error = 'Favicon file exceeds the 512 KB size limit.';
                    $step  = 'server';
                } else {
                    // Verify actual MIME type
                    $finfo    = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($faviconFile['tmp_name']);
                    $allowed  = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml', 'image/ico', 'application/octet-stream'];
                    // ICO files are sometimes detected as application/octet-stream; SVG must be image/svg+xml
                    if ($ext === 'svg' && $mimeType !== 'image/svg+xml') {
                        $error = 'Uploaded file does not appear to be a valid SVG.';
                        $step  = 'server';
                    } elseif ($ext === 'ico' && !in_array($mimeType, $allowed, true)) {
                        $error = 'Uploaded file does not appear to be a valid ICO.';
                        $step  = 'server';
                    } else {
                        $fileContent = file_get_contents($faviconFile['tmp_name']);
                        // Strip <script> elements and event handlers from SVG
                        if ($ext === 'svg') {
                            $fileContent = preg_replace('/<script[\s\S]*?<\/script>/i', '', $fileContent);
                            $fileContent = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $fileContent);
                            $fileContent = preg_replace("/\s+on\w+\s*=\s*'[^']*'/i", '', $fileContent);
                        }
                        $destName = 'favicon.' . $ext;
                        $destPath = __DIR__ . '/assets/' . $destName;
                        if (file_put_contents($destPath, $fileContent) === false) {
                            $error = 'Could not save favicon file. Check that assets/ is writable.';
                            $step  = 'server';
                        } else {
                            Config::set('favicon_path', 'assets/' . $destName);
                        }
                    }
                }
            }

            if ($error === null) {
                Config::save();

                // Initialise the database (creates the SQLite file + schema)
                try {
                    Database::getInstance();
                } catch (Exception $e) {
                    $error = 'Database initialisation failed: ' . $e->getMessage();
                    $step  = 'server';
                }

                if ($error === null) {
                    $step = 'done';
                }
            }
        }
    }
}

// Render
ob_start();
include __DIR__ . '/templates/setup.php';
$content = ob_get_clean();

$pageTitle   = Config::get('app_name', 'WebyMail') . ' Setup';
$shellLayout = false;

include __DIR__ . '/templates/layout.php';
