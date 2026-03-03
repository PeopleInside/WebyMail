<?php
declare(strict_types=1);

/**
 * SQLite database wrapper – creates schema on first use.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $path = Config::get('db_path', 'data/webymail.db');
        if (!str_starts_with($path, '/') && !str_contains($path, ':')) {
            $path = __DIR__ . '/../' . $path;
        }
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        $this->secureDatabaseFile($path);
        $this->initialize();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    private function initialize(): void
    {
        // 1. Create tables if they don't exist
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                email         TEXT    NOT NULL UNIQUE,
                display_name  TEXT    NOT NULL DEFAULT '',
                totp_secret   TEXT,
                totp_enabled  INTEGER NOT NULL DEFAULT 0,
                recovery_codes TEXT,
                signature     TEXT    NOT NULL DEFAULT '',
                theme         TEXT    NOT NULL DEFAULT 'system',
                created_at    INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                updated_at    INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            );

            CREATE TABLE IF NOT EXISTS accounts (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                label        TEXT    NOT NULL,
                sender_name  TEXT    NOT NULL DEFAULT '',
                signature    TEXT    NOT NULL DEFAULT '',
                email        TEXT    NOT NULL,
                imap_host    TEXT    NOT NULL,
                imap_port    INTEGER NOT NULL DEFAULT 993,
                imap_ssl     INTEGER NOT NULL DEFAULT 1,
                smtp_host    TEXT    NOT NULL,
                smtp_port    INTEGER NOT NULL DEFAULT 587,
                smtp_ssl     INTEGER NOT NULL DEFAULT 0,
                smtp_starttls INTEGER NOT NULL DEFAULT 1,
                username     TEXT    NOT NULL,
                password     TEXT    NOT NULL,
                is_primary   INTEGER NOT NULL DEFAULT 0,
                created_at   INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                validation_status TEXT NOT NULL DEFAULT 'pending'
            );

            CREATE TABLE IF NOT EXISTS sessions (
                token        TEXT    PRIMARY KEY,
                user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                account_id   INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
                ip_address   TEXT,
                user_agent   TEXT,
                created_at   INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                expires_at   INTEGER NOT NULL,
                last_seen    INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            );

            CREATE TABLE IF NOT EXISTS contacts (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                name_enc      TEXT    NOT NULL,
                email_enc     TEXT    NOT NULL,
                phone_enc     TEXT,
                address_enc   TEXT,
                notes_enc     TEXT,
                created_at    INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            );

            CREATE TABLE IF NOT EXISTS login_attempts (
                ip_address    TEXT    NOT NULL DEFAULT '',
                attempts      INTEGER NOT NULL DEFAULT 1,
                last_attempt  INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            );
        ");

        // 2. Lightweight migrations (ensure columns exist in case of upgrade)
        // Users table
        $this->ensureColumnExists('users', 'theme', "ALTER TABLE users ADD COLUMN theme TEXT NOT NULL DEFAULT 'system'");
        $this->ensureColumnExists('users', 'signature', "ALTER TABLE users ADD COLUMN signature TEXT NOT NULL DEFAULT ''");
        $this->ensureColumnExists('users', 'totp_secret', "ALTER TABLE users ADD COLUMN totp_secret TEXT");
        $this->ensureColumnExists('users', 'totp_enabled', "ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0");
        $this->ensureColumnExists('users', 'recovery_codes', "ALTER TABLE users ADD COLUMN recovery_codes TEXT");
        
        // Accounts table
        $this->ensureColumnExists('accounts', 'sender_name', "ALTER TABLE accounts ADD COLUMN sender_name TEXT NOT NULL DEFAULT ''");
        $this->ensureColumnExists('accounts', 'signature', "ALTER TABLE accounts ADD COLUMN signature TEXT NOT NULL DEFAULT ''");
        $this->ensureColumnExists('accounts', 'validation_status', "ALTER TABLE accounts ADD COLUMN validation_status TEXT NOT NULL DEFAULT 'pending'");
        
        // Contacts table
        $this->ensureColumnExists('contacts', 'phone_enc', "ALTER TABLE contacts ADD COLUMN phone_enc TEXT");
        $this->ensureColumnExists('contacts', 'address_enc', "ALTER TABLE contacts ADD COLUMN address_enc TEXT");
        $this->ensureColumnExists('contacts', 'notes_enc', "ALTER TABLE contacts ADD COLUMN notes_enc TEXT");
        
        // Sessions table
        $this->ensureColumnExists('sessions', 'ip_address', "ALTER TABLE sessions ADD COLUMN ip_address TEXT");
        $this->ensureColumnExists('sessions', 'user_agent', "ALTER TABLE sessions ADD COLUMN user_agent TEXT");
        $this->ensureColumnExists('sessions', 'account_id', "ALTER TABLE sessions ADD COLUMN account_id INTEGER REFERENCES accounts(id) ON DELETE SET NULL");
        
        // Login attempts table
        $this->ensureColumnExists('login_attempts', 'ip_address', "ALTER TABLE login_attempts ADD COLUMN ip_address TEXT NOT NULL DEFAULT ''");
        $this->ensureColumnExists('login_attempts', 'attempts', "ALTER TABLE login_attempts ADD COLUMN attempts INTEGER NOT NULL DEFAULT 1");
        $this->ensureColumnExists('login_attempts', 'last_attempt', "ALTER TABLE login_attempts ADD COLUMN last_attempt INTEGER NOT NULL DEFAULT (strftime('%s','now'))");

        // 3. Create indexes (must happen AFTER columns are ensured to exist)
        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
            CREATE INDEX IF NOT EXISTS idx_accounts_user ON accounts(user_id);
            CREATE INDEX IF NOT EXISTS idx_contacts_user ON contacts(user_id);
            CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address);
        ");
    }

    /**
     * Lightweight migration helper.
     * Adds a column to a table if it is missing.
     */
    private function ensureColumnExists(string $table, string $column, string $alterSql): void
    {
        $found = false;
        try {
            // Use PRAGMA table_info to check for column existence
            $stmt = $this->pdo->query("PRAGMA table_info($table)");
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cols as $col) {
                if (strtolower($col['name'] ?? '') === strtolower($column)) {
                    $found = true;
                    break;
                }
            }
        } catch (Exception $e) {
            // If table_info fails, we'll try the ALTER anyway and catch the error
        }
        
        if (!$found) {
            // Column missing, execute migration
            try {
                $this->pdo->exec($alterSql);
            } catch (PDOException $e) {
                // Ignore errors like "duplicate column name" which can happen in some edge cases
                if (!str_contains(strtolower($e->getMessage()), 'duplicate column name')) {
                    // We don't throw here to allow the rest of the initialization to proceed
                    // but in a real app we might want to log this.
                }
            }
        }
    }

    /**
     * @deprecated Use ensureColumnExists instead
     */
    private function ensureContactsColumnExists(string $column, string $alterSql): void
    {
        $this->ensureColumnExists('contacts', $column, $alterSql);
    }

    /**
     * @deprecated Use ensureColumnExists instead
     */
    private function ensureUsersColumnExists(string $column, string $alterSql): void
    {
        $this->ensureColumnExists('users', $column, $alterSql);
    }

    /**
     * @deprecated Use ensureColumnExists instead
     */
    private function ensureAccountsColumnExists(string $column, string $alterSql): void
    {
        $this->ensureColumnExists('accounts', $column, $alterSql);
    }

    /**
     * Best-effort filesystem hardening for the SQLite database file.
     */
    private function secureDatabaseFile(string $path): void
    {
        if (is_file($path)) {
            chmod($path, 0600);
        }
    }
}
