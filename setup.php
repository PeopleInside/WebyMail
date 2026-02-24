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

        Config::set('app_name',       $appName);
        Config::set('imap_host',      $imapHost);
        Config::set('imap_port',      $imapPort);
        Config::set('imap_ssl',       $imapSsl);
        Config::set('smtp_host',      $smtpHost);
        Config::set('smtp_port',      $smtpPort);
        Config::set('smtp_ssl',       $smtpSsl);
        Config::set('smtp_starttls',  $smtpTls);
        Config::set('altcha_enabled', $altchaOn);
        Config::set('setup_complete', true);

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
                $step = 'done';
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
