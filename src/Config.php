<?php
declare(strict_types=1);

/**
 * Application configuration
 */
class Config
{
    public const VERSION = '3.4.7';
    public const UPDATE_URL = 'https://github.com/PeopleInside/WebyMail/releases/latest';
    public const THEMES = ['system', 'light', 'dark'];
    private static ?array $data = null;
    private static string $configFile = __DIR__ . '/../config/config.php';

    public static function get(string $key, $default = null)
    {
        self::load();

        if ($key === 'app_secret') {
            if (empty(self::$data['app_secret'])) {
                self::$data['app_secret'] = bin2hex(random_bytes(32));
                self::save();
            }
            return self::$data['app_secret'];
        }

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
        // Atomic write: write to a temp file then rename so readers never see a partial file.
        $tmpFile = self::$configFile . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmpFile, $content, LOCK_EX) === false) {
            @unlink($tmpFile);
            throw new \RuntimeException('Config::save() failed to write temporary file.');
        }
        @chmod($tmpFile, 0600);
        if (!rename($tmpFile, self::$configFile)) {
            @unlink($tmpFile);
            throw new \RuntimeException('Config::save() failed to replace config file atomically.');
        }
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

        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        $results['requirements']['php'] = $phpOk;
        if (!$phpOk) {
            $results['all_ok'] = false;
            $results['security_suggestions'][] = [
                'type' => 'php',
                'message' => 'PHP ' . PHP_VERSION . ' is installed. Upgrade to PHP 8.1 or newer for improved security and support.',
                'action_url' => '?action=settings&tab=security'
            ];
        }

        // Security - Recursive scan
        $root = dirname(__DIR__);
        
        // Check root directory itself
        $rootPerms = fileperms($root) & 0777;
        $rootOk = ($rootPerms <= 0755);
        $results['security'][] = [
            'path' => '(root)/',
            'perms' => sprintf('%o', $rootPerms),
            'ok' => $rootOk,
            'type' => 'dir'
        ];
        if (!$rootOk) $results['all_ok'] = false;

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

        // .htaccess checks
        $htaccessFiles = [
            '/' => 'RedirectMatch 404',
            '/data/' => 'Deny from all'
        ];

        foreach ($htaccessFiles as $dir => $requiredContent) {
            $path = $root . $dir . '.htaccess';
            $ok = false;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                if (str_contains($content, $requiredContent) || str_contains($content, 'Require all denied')) {
                    $ok = true;
                }
            }
            
            if (!$ok) {
                $results['security'][] = ['path' => $dir . '.htaccess', 'ok' => false, 'type' => 'htaccess'];
                $results['all_ok'] = false;
            }
        }

        // 2FA and Captcha checks
        $results['security_suggestions'] = [];
        if (!self::get('captcha_enabled', true)) {
            $results['security_suggestions'][] = [
                'type' => 'captcha',
                'message' => 'CAPTCHA is disabled. Enabling it adds protection against automated brute-force attacks.',
                'action_url' => '?action=settings&tab=security'
            ];
        }
        
        // Note: 2FA check is per-user, but we can check if it's globally enabled
        if (!self::get('2fa_enabled', true)) {
            $results['security_suggestions'][] = [
                'type' => '2fa',
                'message' => 'Two-Factor Authentication is globally disabled. Enabling it significantly improves account security.',
                'action_url' => '?action=settings&tab=security'
            ];
        }

        // Check whether the database is stored inside the webroot
        $dbInWebroot = self::isDbInWebroot();
        $results['db_in_webroot'] = $dbInWebroot; // always reflects reality
        if ($dbInWebroot && !self::get('ignore_db_webroot_warning', false)) {
            $results['all_ok'] = false;
            $results['security'][] = [
                'path'  => 'Database (' . basename(self::resolveDbPath()) . ')',
                'perms' => '',
                'ok'    => false,
                'type'  => 'db_webroot',
            ];
            $results['security_suggestions'][] = [
                'type'       => 'db_in_webroot',
                'message'    => 'The database file is stored inside a web-accessible directory. On Nginx or misconfigured servers it could be downloaded directly. Move it outside the webroot.',
                'action_url' => '?action=settings&tab=system',
            ];
        }

        // Separate flag: are all file-permission checks passing?
        // Used by the UI to decide whether the "Fix Permissions" button should be active
        // (the DB-webroot issue requires a different action and must not affect the button).
        $results['perms_ok'] = empty(
            array_filter($results['security'], fn($c) => !$c['ok'] && ($c['type'] ?? '') !== 'db_webroot')
        );

        // Cache check results in session (avoids writing to config.php on every check)
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['system_check_cache'] = ['at' => time(), 'ok' => $results['all_ok']];
        }

        return $results;
    }

    public static function getSecuritySuggestions(): array
    {
        $suggestions = [];
        if (!self::get('captcha_enabled', true)) {
            $suggestions[] = [
                'type' => 'captcha',
                'label' => 'Enable CAPTCHA',
                'title' => 'Security Suggestion: Enable CAPTCHA protection',
                'url' => '?action=settings&tab=system'
            ];
        }
        if (!self::get('2fa_enabled', true)) {
            $suggestions[] = [
                'type' => '2fa',
                'label' => 'Enable 2FA',
                'title' => 'Security Suggestion: Enable Two-Factor Authentication',
                'url' => '?action=settings&tab=system'
            ];
        }
        return $suggestions;
    }

    public static function shouldShowSecurityBanner(): bool
    {
        // Use session cache to avoid re-running the check too often
        if (isset($_SESSION['system_check_cache']['at'], $_SESSION['system_check_cache']['ok'])) {
            $cache = $_SESSION['system_check_cache'];
            if (time() - (int)$cache['at'] <= 14400) {
                return !(bool)$cache['ok'];
            }
        }

        $check = self::checkSystem();
        return !$check['all_ok'];
    }

    public static function getNewerVersion(bool $forceRefresh = false): ?string
    {
        // Simple GitHub API check (cached for 24h in session to avoid rate limits)
        $cache = $_SESSION['github_version_check'] ?? null;
        if (
            !$forceRefresh
            && is_array($cache)
            && ($cache['expires'] ?? 0) > time()
            && array_key_exists('latest_version', $cache)
        ) {
            return $cache['version'] ?? null;
        }

        $newerVersion = null;
        $latestVersion = null;
        $repoAvailable = false;
        try {
            $opts = [
                'http' => [
                    'method'          => 'GET',
                    'header'          => ['User-Agent: WebyMail-Update-Checker'],
                    'timeout'         => 5,
                    'ignore_errors'   => true, // receive body even on 4xx/5xx
                ]
            ];
            $context = stream_context_create($opts);
            $apiResponse = @file_get_contents(
                'https://api.github.com/repos/PeopleInside/WebyMail/releases/latest',
                false,
                $context
            );

            // Determine HTTP status from response headers
            $httpStatus = 0;
            if (!empty($http_response_header) && is_array($http_response_header)) {
                if (preg_match('#HTTP/[\d.]+ (\d{3})#', $http_response_header[0], $m)) {
                    $httpStatus = (int) $m[1];
                }
            }

            if ($apiResponse !== false && $httpStatus === 200) {
                $repoAvailable = true;
                $data = json_decode($apiResponse, true);
                $latestTag = $data['tag_name'] ?? '';
                // Strip 'v' prefix if present
                $latestVersion = ltrim($latestTag, 'v') ?: null;

                if ($latestVersion !== null && version_compare($latestVersion, self::VERSION, '>')) {
                    $newerVersion = $latestVersion;
                }
            }
            // Any non-200 response (404, 403, 0 = network failure) → repo not available
        } catch (Exception $e) {
            // Ignore errors; $repoAvailable stays false
        }

        $_SESSION['github_version_check'] = [
            'version'        => $newerVersion,
            'latest_version' => $latestVersion,
            'repo_available' => $repoAvailable,
            'expires'        => time() + 86400 // 24 hours
        ];

        return $newerVersion;
    }

    public static function getLatestGitHubVersion(bool $forceRefresh = false): ?string
    {
        $cache = $_SESSION['github_version_check'] ?? null;
        if ($forceRefresh || !is_array($cache) || ($cache['expires'] ?? 0) <= time()) {
            self::getNewerVersion($forceRefresh);
            $cache = $_SESSION['github_version_check'] ?? null;
        }

        if (!is_array($cache) || (($cache['repo_available'] ?? false) !== true)) {
            return null;
        }

        $latest = $cache['latest_version'] ?? null;
        return (is_string($latest) && $latest !== '') ? $latest : null;
    }

    public static function isLocalVersionAheadOfGitHub(bool $forceRefresh = false): ?bool
    {
        $latest = self::getLatestGitHubVersion($forceRefresh);
        if ($latest === null) {
            return null;
        }
        return version_compare(self::VERSION, $latest, '>');
    }

    /**
     * Returns whether the public GitHub repository was reachable on the last
     * version check.  Returns null when no check has been performed yet in
     * this session.
     */
    public static function isGitHubRepoAvailable(): ?bool
    {
        if (!isset($_SESSION['github_version_check'])) {
            return null;
        }
        $cache = $_SESSION['github_version_check'];
        if (($cache['expires'] ?? 0) <= time()) {
            return null; // cache expired, status unknown
        }
        return isset($cache['repo_available']) ? (bool) $cache['repo_available'] : false;
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

        // Try fixing root itself
        @chmod($root, 0755);

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

        // Specific fix for .htaccess files
        $htaccessFiles = ['/.htaccess', '/data/.htaccess'];
        foreach ($htaccessFiles as $f) {
            $path = $root . $f;
            if (file_exists($path)) {
                @chmod($path, 0644);
            } else {
                // Create missing .htaccess with default rules
                if ($f === '/.htaccess') {
                    @file_put_contents($path, "RedirectMatch 404 /\\.(?!well-known).*$\n");
                } elseif ($f === '/data/.htaccess') {
                    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0750, true);
                    @file_put_contents($path, "Deny from all\n");
                }
                @chmod($path, 0644);
            }
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

    // -------------------------------------------------------------------------
    // Database path helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the configured db_path to an absolute filesystem path.
     */
    public static function resolveDbPath(): string
    {
        $path = self::get('db_path', 'data/webymail.db');
        if (!str_starts_with($path, '/') && !str_contains($path, ':')) {
            $path = dirname(__DIR__) . '/' . $path;
        }
        return realpath($path) ?: $path;
    }

    /**
     * Return true if the database file currently lives inside a web-accessible
     * directory.  Two cases are checked:
     *
     *  1. Direct: the DB is somewhere inside the application root directory tree.
     *  2. Container: the DB is inside a known webroot container (public_html, www,
     *     htdocs, html, web, public, webroot, website) that also contains the
     *     application root — for example, the app lives at
     *     /home/user/public_html/webymail/ and the DB is at
     *     /home/user/public_html/storage/webymail.db.
     */
    public static function isDbInWebroot(): bool
    {
        $appRoot = realpath(dirname(__DIR__));
        $dbPath  = self::resolveDbPath();
        if ($appRoot === false || $dbPath === '') {
            return false;
        }

        // Case 1: DB inside the app root itself
        if (str_starts_with($dbPath . '/', $appRoot . '/')) {
            return true;
        }

        // Case 2: both app and DB share a common webroot container ancestor
        $webrootNames = ['public_html', 'www', 'wwwroot', 'htdocs', 'html', 'web', 'public', 'webroot', 'website'];
        $appParts     = array_values(array_filter(explode('/', $appRoot)));
        for ($i = 0, $n = count($appParts); $i < $n; $i++) {
            if (in_array(strtolower($appParts[$i]), $webrootNames, true)) {
                $containerPath = '/' . implode('/', array_slice($appParts, 0, $i + 1));
                if (str_starts_with($dbPath . '/', $containerPath . '/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Suggest a safe absolute path for the database outside any detected webroot
     * container (public_html, www, etc.).  Falls back to one level above the app
     * root when no known webroot container is found in the path.
     */
    public static function suggestSafeDbPath(): string
    {
        $appRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);

        $webrootNames = ['public_html', 'www', 'wwwroot', 'htdocs', 'html', 'web', 'public', 'webroot', 'website'];
        $parts        = array_values(array_filter(explode('/', $appRoot)));

        // Walk from the end towards the root looking for the deepest webroot-like dir
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (in_array(strtolower($parts[$i]), $webrootNames, true)) {
                // Place the data directory as a sibling of the webroot container
                $aboveWebroot = '/' . implode('/', array_slice($parts, 0, $i));
                return $aboveWebroot . '/webymail_data/webymail.db';            }
        }

        // No webroot-like ancestor found: one level above the application root
        return dirname($appRoot) . '/webymail_data/webymail.db';    }

    /**
     * Move the SQLite database to a new location safely:
     *  1. Checkpoint the WAL so the single .db file is self-contained.
     *  2. Copy the file to the target path (creates parent dirs as needed).
     *  3. Update config.php to point at the new location.
     *  4. Rename the old file to .bak so it is no longer served but can be
     *     recovered if something goes wrong.
     *
     * Returns ['ok' => true, 'old' => string, 'new' => string]
     *      or ['ok' => false, 'error' => string].
     */
    public static function moveDatabase(string $targetPath): array
    {
        // Resolve the current absolute path
        $oldPath = self::resolveDbPath();
        if (!is_file($oldPath)) {
            return ['ok' => false, 'error' => 'Current database file not found at: ' . $oldPath];
        }

        // Resolve the target absolute path
        if (!str_starts_with($targetPath, '/') && !str_contains($targetPath, ':')) {
            $targetPath = dirname(__DIR__) . '/' . $targetPath;
        }

        // Normalise (realpath won't work for a non-existent file, so we clean it manually)
        $targetPath = rtrim(str_replace(['\\', '//'], ['/', '/'], $targetPath), '/');

        if ($targetPath === $oldPath) {
            return ['ok' => false, 'error' => 'Target path is the same as the current database location.'];
        }

        // Create target directory
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0700, true)) {
                return ['ok' => false, 'error' => 'Cannot create target directory: ' . $targetDir];
            }
        }

        if (!is_writable($targetDir)) {
            return ['ok' => false, 'error' => 'Target directory is not writable: ' . $targetDir];
        }

        // Checkpoint the WAL before copying so the destination is a clean, consistent file
        try {
            $pdo = new \PDO('sqlite:' . $oldPath);
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE);');
            $pdo = null;
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => 'WAL checkpoint failed: ' . $e->getMessage()];
        }

        // Copy the database file
        if (!@copy($oldPath, $targetPath)) {
            return ['ok' => false, 'error' => 'Failed to copy database to: ' . $targetPath];
        }
        @chmod($targetPath, 0600);

        // Verify the copy is readable and is a valid SQLite file
        $magic = @file_get_contents($targetPath, false, null, 0, 16);
        if ($magic === false || !str_starts_with($magic, 'SQLite format 3')) {
            @unlink($targetPath);
            return ['ok' => false, 'error' => 'Copied file does not appear to be a valid SQLite database.'];
        }

        // Store path as relative to app root when possible (more portable)
        $appRoot  = dirname(__DIR__);
        $savePath = $targetPath;
        if (str_starts_with($targetPath, $appRoot . '/')) {
            $savePath = substr($targetPath, strlen($appRoot) + 1);
        } elseif (str_starts_with($targetPath, $appRoot . DIRECTORY_SEPARATOR)) {
            $savePath = substr($targetPath, strlen($appRoot) + 1);
        }

        // Update configuration
        self::set('db_path', $savePath);
        try {
            self::save();
        } catch (\RuntimeException $e) {
            @unlink($targetPath);
            return ['ok' => false, 'error' => 'Config save failed: ' . $e->getMessage()];
        }

        // Delete the old database and its WAL/SHM sidecars so the file is no
        // longer accessible from the webroot.  Non-fatal: if deletion fails (e.g.
        // file is locked) we log it but do not roll back — the copy is valid and
        // the config already points at the new location.
        @unlink($oldPath);
        foreach (['-wal', '-shm'] as $suffix) {
            $sidecar = $oldPath . $suffix;
            if (is_file($sidecar)) {
                @unlink($sidecar);
            }
        }
        if (is_file($oldPath)) {
            error_log('WebyMail: could not delete old database after move: ' . $oldPath);
        }

        return ['ok' => true, 'old' => $oldPath, 'new' => $targetPath];
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
            'db_path'         => '../webymail_data/webymail.db', // outside webroot by default
            'setup_complete'  => false,
            'timezone'        => 'Europe/Rome',
            'hide_server_on_login' => true,
            'allow_insecure_imap_cert' => false,
        ];
    }
}
