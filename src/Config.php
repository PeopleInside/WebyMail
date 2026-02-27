<?php
declare(strict_types=1);

/**
 * Application configuration
 */
class Config
{
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
            'version'         => '0.4',
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
        ];
    }
}
