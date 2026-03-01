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

            CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
            CREATE INDEX IF NOT EXISTS idx_accounts_user ON accounts(user_id);

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
            CREATE INDEX IF NOT EXISTS idx_contacts_user ON contacts(user_id);
        ");

        // Lightweight migrations
        $this->ensureUsersColumnExists('theme', "ALTER TABLE users ADD COLUMN theme TEXT NOT NULL DEFAULT 'system'");
        $this->ensureAccountsColumnExists('sender_name', "ALTER TABLE accounts ADD COLUMN sender_name TEXT NOT NULL DEFAULT ''");
        $this->ensureAccountsColumnExists('signature', "ALTER TABLE accounts ADD COLUMN signature TEXT NOT NULL DEFAULT ''");
        $this->ensureAccountsColumnExists('validation_status', "ALTER TABLE accounts ADD COLUMN validation_status TEXT NOT NULL DEFAULT 'pending'");
        $this->ensureContactsColumnExists('phone_enc', "ALTER TABLE contacts ADD COLUMN phone_enc TEXT");
        $this->ensureContactsColumnExists('address_enc', "ALTER TABLE contacts ADD COLUMN address_enc TEXT");
    }

    /**
     * Lightweight migration helper for the contacts table.
     */
    private function ensureContactsColumnExists(string $column, string $alterSql): void
    {
        $cols = $this->pdo->query("PRAGMA table_info(contacts)")->fetchAll();
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === $column) {
                return;
            }
        }
        $this->pdo->exec($alterSql);
    }

    /**
     * Lightweight migration helper for the users table.
     * Adds a column if it is missing by executing the provided ALTER TABLE statement.
     */
    private function ensureUsersColumnExists(string $column, string $alterSql): void
    {
        // Scoped to the users table to keep migration surface minimal and avoid dynamic table names.
        $cols = $this->pdo->query("PRAGMA table_info(users)")->fetchAll();
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === $column) {
                return;
            }
        }
        $this->pdo->exec($alterSql);
    }

    /**
     * Lightweight migration helper for the accounts table.
     */
    private function ensureAccountsColumnExists(string $column, string $alterSql): void
    {
        $cols = $this->pdo->query("PRAGMA table_info(accounts)")->fetchAll();
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === $column) {
                return;
            }
        }
        $this->pdo->exec($alterSql);
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
