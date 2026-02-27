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

// Check if version needs update in config/config.php
if (Config::isSetup()) {
    $storedVersion = Config::get('version');
    if ($storedVersion !== Config::VERSION) {
        Config::set('version', Config::VERSION);
        Config::save();
    }
}

// If already set up, redirect to main app unless force setup is requested
if (Config::get('setup_complete') && ($_GET['force'] ?? '') !== '1' && ($_GET['action'] ?? '') !== 'setup') {
    header('Location: index.php');
    exit;
}

$step  = (Config::get('setup_complete') && ($_GET['force'] ?? '') === '1') ? 'server' : 'welcome';
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
        Config::set('version', Config::VERSION);
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
            if (in_array($ext, ['ico', 'png', 'svg'])) {
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
        // Auto-rename setup.php to prevent accidental re-runs
        @rename(__FILE__, __FILE__ . '.bak');
    }
}

// Render
ob_start();
include __DIR__ . '/templates/setup.php';
$content = ob_get_clean();

$pageTitle   = Config::get('app_name', 'WebyMail') . ' Setup';
$shellLayout = false;

include __DIR__ . '/templates/layout.php';
