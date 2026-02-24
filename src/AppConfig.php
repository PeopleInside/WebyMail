<?php
declare(strict_types=1);

/**
 * Application UI/behaviour preferences.
 *
 * Non-sensitive configuration is stored in a human-readable PHP file under
 * data/customconfig.php. Sensitive data (passwords, secrets, server credentials)
 * remains in config/config.php and in the encrypted SQLite database.
 *
 * Available keys and defaults:
 *   interfaceName    (string)  – Override the displayed app/brand name. Empty = use app_name.
 *   timezone         (string)  – PHP timezone identifier. Default: "Europe/Rome".
 *   customFooterText (string)  – HTML appended below the default footer (additive only).
 *                                Example: <a href="https://example.com" target="_blank">Custom text link</a>
 *   faviconPath      (string)  – Web-root-relative path to a .ico or .svg favicon.
 *   hideLoginOptions (bool)    – When true, hide ▸ Server settings on the login page.
 *   twoFactorForAll  (bool)    – When true, require 2FA for all users globally.
 *   setup            (bool)    – When true, re-enables setup.php for re-configuration.
 */
class AppConfig
{
    private static ?array $data = null;
    private static string $configFile = __DIR__ . '/../data/customconfig.php';

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
        $export  = var_export(self::$data, true);
        $content = "<?php\n"
            . "// WebyMail application preferences – non-sensitive configuration.\n"
            . "// Edit this file directly or set 'setup' => true to re-run setup.php.\n"
            . "// customFooterText HTML example: "
            . "<a href=\"https://example.com\" target=\"_blank\">Custom text link</a>\n"
            . "return {$export};\n";

        $dir = dirname(self::$configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        if (file_put_contents(self::$configFile, $content) === false) {
            throw new RuntimeException('Failed to write configuration to ' . self::$configFile);
        }
    }

    public static function isConfigured(): bool
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
            'interfaceName'    => '',
            'timezone'         => 'Europe/Rome',
            'customFooterText' => '',
            'faviconPath'      => '',
            'hideLoginOptions' => false,
            'twoFactorForAll'  => false,
            'setup'            => false,
        ];
    }
}
