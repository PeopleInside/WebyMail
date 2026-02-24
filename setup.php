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
require_once __DIR__ . '/src/AppConfig.php';

// Apply configured timezone (defaults to Europe/Rome)
date_default_timezone_set(AppConfig::get('timezone', 'Europe/Rome'));

// Allow access when:
//  (a) First run – no server config exists yet, OR
//  (b) Admin set the 'setup' flag to true in data/customconfig.php to re-run setup.
if (Config::isSetup() && !AppConfig::get('setup', false)) {
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
        // Validate and save server/sensitive config
        $appName   = trim($_POST['app_name']   ?? 'WebyMail');
        $imapHost  = trim($_POST['imap_host']  ?? '');
        $imapPort  = (int) ($_POST['imap_port'] ?? 993);
        $imapSsl   = !empty($_POST['imap_ssl']);
        $smtpHost  = trim($_POST['smtp_host']  ?? '');
        $smtpPort  = (int) ($_POST['smtp_port'] ?? 587);
        $smtpSsl   = !empty($_POST['smtp_ssl']);
        $smtpTls   = !empty($_POST['smtp_starttls']);
        $altchaOn  = !empty($_POST['altcha_enabled']);

        // If SMTP port 465 is selected, force SSL on and STARTTLS off
        if ($smtpPort === 465) {
            $smtpSsl = true;
            $smtpTls = false;
        }

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

        // Save non-sensitive preferences to data/customconfig.php
        $timezone        = trim($_POST['timezone']         ?? 'Europe/Rome');
        $interfaceName   = trim($_POST['interface_name']   ?? '');
        $faviconPath     = trim($_POST['favicon_path']     ?? '');
        $footerText      = trim($_POST['custom_footer_text'] ?? '');
        $hideLogin       = !empty($_POST['hide_login_options']);
        $twoFactorForAll = !empty($_POST['two_factor_for_all']);

        // Validate timezone using DateTimeZone to avoid loading the full identifiers list
        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            $timezone = 'Europe/Rome';
        }

        AppConfig::set('interfaceName',    $interfaceName);
        AppConfig::set('timezone',         $timezone);
        AppConfig::set('customFooterText', $footerText);
        AppConfig::set('faviconPath',      $faviconPath);
        AppConfig::set('hideLoginOptions', $hideLogin);
        AppConfig::set('twoFactorForAll',  $twoFactorForAll);
        AppConfig::set('setup',            false); // disable re-run after save

        // Ensure data directory is writable
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir) && !mkdir($dataDir, 0750, true)) {
            $error = 'Cannot create data/ directory. Please create it manually and make it writable.';
            $step  = 'server';
        } else {
            Config::save();
            AppConfig::save();

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
