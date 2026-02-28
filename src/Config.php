<?php
declare(strict_types=1);

/**
 * Application configuration
 */
class Config
{
    public const VERSION = '1.2';
    public const UPDATE_URL = 'https://github.com/PeopleInside/WebyMail/releases';
    public const THEMES = ['system', 'light', 'dark'];
    private static ?array $data = null;
    private static string $configFile = __DIR__ . '/../config/config.php';

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$data[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::load();
        self::$data[$key] = $value;
    }

    public static function save(): void
    {
        self::load();
        // Remove null values (cleanup)
        self::$data = array_filter(self::$data, fn($v) => $v !== null);
        
        $export = var_export(self::$data, true);
        $content = "<?php\nreturn {$export};\n";
        if (!is_dir(dirname(self::$configFile))) {
            mkdir(dirname(self::$configFile), 0750, true);
        }
        file_put_contents(self::$configFile, $content);
    }

    public static function isSetup(): bool
    {
        return file_exists(self::$configFile);
    }

    public static function checkSystem(): array
    {
        $results = [
            'requirements' => [],
            'security' => [],
            'all_ok' => true
        ];

        // Requirements
        $needed = ['imap', 'pdo_sqlite', 'openssl', 'mbstring', 'iconv'];
        foreach ($needed as $ext) {
            $ok = extension_loaded($ext);
            $results['requirements'][$ext] = $ok;
            if (!$ok) $results['all_ok'] = false;
        }

        // Security - Dirs
        $root = dirname(__DIR__);
        $dirs = ['config' => $root . '/config', 'data' => $root . '/data'];
        foreach ($dirs as $name => $path) {
            if (is_dir($path)) {
                $perms = fileperms($path) & 0777;
                $ok = ($perms <= 0755);
                $results['security']['dir_' . $name] = ['path' => $name . '/', 'perms' => sprintf('%o', $perms), 'ok' => $ok];
                if (!$ok) $results['all_ok'] = false;
            }
        }

        // Security - Files
        $files = ['config/config.php' => $root . '/config/config.php', 'data/webymail.db' => $root . '/data/webymail.db'];
        foreach ($files as $name => $path) {
            if (file_exists($path)) {
                $perms = fileperms($path) & 0777;
                $ok = ($perms <= 0644);
                $results['security']['file_' . $name] = ['path' => $name, 'perms' => sprintf('%o', $perms), 'ok' => $ok];
                if (!$ok) $results['all_ok'] = false;
            }
        }

        // .htaccess
        $htaccessPath = $root . '/.htaccess';
        $htaccessOk = false;
        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            // Check for common protection patterns
            if (str_contains($content, 'RedirectMatch 404') || str_contains($content, 'Deny from all') || str_contains($content, 'Require all denied')) {
                $htaccessOk = true;
            }
        }
        $results['security']['htaccess'] = ['path' => '.htaccess', 'ok' => $htaccessOk];
        if (!$htaccessOk) $results['all_ok'] = false;

        // Update last check info
        self::set('last_system_check_at', time());
        self::set('last_system_check_ok', $results['all_ok']);
        self::save();

        return $results;
    }

    public static function shouldShowSecurityBanner(): bool
    {
        if (self::get('ignore_security_banner', false)) {
            return false;
        }
        
        $lastCheck = (int)self::get('last_system_check_at', 0);
        $lastOk    = (bool)self::get('last_system_check_ok', true);
        
        // Re-check every 4 hours during normal use
        if (time() - $lastCheck > 14400) {
            $check = self::checkSystem();
            self::set('last_system_check_at', time());
            self::set('last_system_check_ok', $check['all_ok']);
            self::save();
            return !$check['all_ok'];
        }
        
        return !$lastOk;
    }

    public static function getNewerVersion(): ?string
    {
        if (self::get('ignore_update_banner', false)) {
            return null;
        }
        // Simple GitHub API check (cached for 24h in session to avoid rate limits)
        if (isset($_SESSION['github_version_check']) && $_SESSION['github_version_check']['expires'] > time()) {
            return $_SESSION['github_version_check']['version'];
        }

        $newerVersion = null;
        try {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: WebyMail-Update-Checker'
                    ],
                    'timeout' => 5
                ]
            ];
            $context = stream_context_create($opts);
            $apiResponse = @file_get_contents('https://api.github.com/repos/PeopleInside/WebyMail/releases/latest', false, $context);
            
            if ($apiResponse) {
                $data = json_decode($apiResponse, true);
                $latestTag = $data['tag_name'] ?? '';
                // Strip 'v' prefix if present
                $latestVersion = ltrim($latestTag, 'v');
                
                if (version_compare($latestVersion, self::VERSION, '>')) {
                    $newerVersion = $latestVersion;
                }
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        $_SESSION['github_version_check'] = [
            'version' => $newerVersion,
            'expires' => time() + 86400 // 24 hours
        ];

        return $newerVersion;
    }

    private static function load(): void
    {
        if (self::$data !== null) {
            return;
        }
        if (file_exists(self::$configFile)) {
            self::$data = require self::$configFile;
        } else {
            self::$data = self::defaults();
        }
    }

    private static function defaults(): array
    {
        return [
            'app_name'        => 'WebyMail',
            'update_url'      => self::UPDATE_URL,
            'app_secret'      => bin2hex(random_bytes(32)),
            'captcha_enabled'  => true,
            '2fa_enabled'      => true,
            'session_lifetime' => 15552000, // 6 months in seconds
            'imap_host'       => '',
            'imap_port'       => 993,
            'imap_ssl'        => true,
            'smtp_host'       => '',
            'smtp_port'       => 465,
            'smtp_ssl'        => true,
            'smtp_starttls'   => false,
            'db_path'         => 'data/webymail.db',
            'setup_complete'  => false,
            'timezone'        => 'Europe/Rome',
            'hide_server_on_login' => true,
            'last_system_check_at' => 0,
            'last_system_check_ok' => true,
        ];
    }
}
