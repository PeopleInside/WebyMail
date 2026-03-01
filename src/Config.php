<?php
declare(strict_types=1);

/**
 * Application configuration
 */
class Config
{
    public const VERSION = '1.6';
    public const UPDATE_URL = 'https://github.com/PeopleInside/WebyMail/releases/latest';
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

        // Security - Recursive scan
        $root = dirname(__DIR__);
        
        // Use a simple recursive function or RecursiveIterator
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $path = $item->getPathname();
                $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                
                // Skip some directories that might be large or irrelevant
                if (str_starts_with($relativePath, 'assets/') || str_starts_with($relativePath, 'node_modules/')) {
                    continue;
                }

                $perms = fileperms($path) & 0777;
                $isDir = $item->isDir();
                $ok = $isDir ? ($perms <= 0755) : ($perms <= 0644);
                
                $results['security'][] = [
                    'path' => $relativePath . ($isDir ? '/' : ''),
                    'perms' => sprintf('%o', $perms),
                    'ok' => $ok,
                    'type' => $isDir ? 'dir' : 'file'
                ];
                
                if (!$ok) {
                    $results['all_ok'] = false;
                }
            }
        } catch (Exception $e) {
            // Fallback if iterator fails
        }

        // .htaccess check
        $htaccessPath = $root . '/.htaccess';
        $htaccessOk = false;
        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            if (str_contains($content, 'RedirectMatch 404') || str_contains($content, 'Deny from all') || str_contains($content, 'Require all denied')) {
                $htaccessOk = true;
            }
        }
        if (!$htaccessOk) {
            $results['security'][] = ['path' => '.htaccess', 'ok' => false, 'type' => 'htaccess'];
            $results['all_ok'] = false;
        }

        // Update last check info
        self::set('last_system_check_at', time());
        self::set('last_system_check_ok', $results['all_ok']);
        self::save();

        return $results;
    }

    public static function shouldShowSecurityBanner(): bool
    {
        if (!empty($_SESSION['hide_security_banner'])) {
            return false;
        }

        $lastCheck = (int)self::get('last_system_check_at', 0);
        $lastOk    = (bool)self::get('last_system_check_ok', true);
        
        // Re-check every 4 hours during normal use
        if (time() - $lastCheck > 14400) {
            $check = self::checkSystem();
            return !$check['all_ok'];
        }
        
        return !$lastOk;
    }

    public static function getNewerVersion(): ?string
    {
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

    /**
     * Encrypt data using AES-256-GCM and the app_secret.
     */
    public static function encrypt(string $data): string
    {
        if ($data === '') return '';
        $key = hash('sha256', self::get('app_secret', ''), true);
        $iv  = random_bytes(12);
        $tag = '';
        $enc = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $enc);
    }

    /**
     * Decrypt data using AES-256-GCM and the app_secret.
     */
    public static function decrypt(string $data): string
    {
        if ($data === '') return '';
        $key = hash('sha256', self::get('app_secret', ''), true);
        $raw = base64_decode($data, true);
        if ($raw === false || strlen($raw) < 29) return '';
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $enc = substr($raw, 28);
        return openssl_decrypt($enc, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag) ?: '';
    }

    public static function fixPermissions(): bool
    {
        $root = dirname(__DIR__);
        $ok = true;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $path = $item->getPathname();
                $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                
                // Skip assets and node_modules
                if (str_starts_with($relativePath, 'assets/') || str_starts_with($relativePath, 'node_modules/')) {
                    continue;
                }

                if ($item->isDir()) {
                    if (!@chmod($path, 0755)) $ok = false;
                } else {
                    if (!@chmod($path, 0644)) $ok = false;
                }
            }
        } catch (Exception $e) {
            $ok = false;
        }

        // Specific fix for .htaccess if it exists
        $htaccessPath = $root . '/.htaccess';
        if (file_exists($htaccessPath)) {
            @chmod($htaccessPath, 0644);
        }

        // Re-run check
        self::checkSystem();
        return $ok;
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
