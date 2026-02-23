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
        $path = Config::get('db_path', __DIR__ . '/../data/webymail.db');
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
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
                created_at   INTEGER NOT NULL DEFAULT (strftime('%s','now'))
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

            CREATE TABLE IF NOT EXISTS altcha_challenges (
                challenge  TEXT    PRIMARY KEY,
                expires_at INTEGER NOT NULL,
                used       INTEGER NOT NULL DEFAULT 0
            );

            CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
            CREATE INDEX IF NOT EXISTS idx_accounts_user ON accounts(user_id);
            CREATE INDEX IF NOT EXISTS idx_altcha_expires ON altcha_challenges(expires_at);
        ");

        // Lightweight migrations
        $this->ensureColumnExists('users', 'theme', "ALTER TABLE users ADD COLUMN theme TEXT NOT NULL DEFAULT 'system'");
    }

    private function ensureColumnExists(string $table, string $column, string $alterSql): void
    {
        $cols = $this->pdo->query("PRAGMA table_info({$table})")->fetchAll();
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === $column) {
                return;
            }
        }
        $this->pdo->exec($alterSql);
    }
}
