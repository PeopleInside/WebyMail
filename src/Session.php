<?php
declare(strict_types=1);

/**
 * Custom cookie-based session management (6-month lifetime).
 * Token is stored in an HttpOnly, SameSite=Strict cookie and validated
 * against the sessions table in the database.
 */
class Session
{
    private const COOKIE_NAME = 'wm_session';

    private Database $db;
    private int $lifetime;

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->lifetime = (int) Config::get('session_lifetime', 15552000);
    }

    /**
     * Create a new session and set the cookie.
     */
    public function create(int $userId, int $accountId): string
    {
        $this->cleanup();

        $token     = bin2hex(random_bytes(32));
        $expiresAt = time() + $this->lifetime;
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $this->db->query(
            'INSERT INTO sessions (token, user_id, account_id, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$token, $userId, $accountId, $ip, $ua, $expiresAt]
        );

        $this->setCookie($token, $expiresAt);
        return $token;
    }

    /**
     * Validate the current session token from cookie.
     * Returns session row or null if invalid/expired.
     */
    public function current(): ?array
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($token === '') {
            return null;
        }
        return $this->validate($token);
    }

    /**
     * Validate a specific token.
     */
    public function validate(string $token): ?array
    {
        if (!ctype_xdigit($token) || strlen($token) !== 64) {
            return null;
        }

        $row = $this->db->fetch(
            'SELECT s.*, u.email, u.display_name, u.totp_enabled, u.signature
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = ? AND s.expires_at > ?',
            [$token, time()]
        );

        if ($row === null) {
            return null;
        }

        // Refresh last_seen periodically (every 5 minutes) to avoid DB write on every request
        if (time() - (int)$row['last_seen'] > 300) {
            $this->db->query(
                'UPDATE sessions SET last_seen = ? WHERE token = ?',
                [time(), $token]
            );
        }

        return $row;
    }

    /**
     * Destroy the current session.
     */
    public function destroy(): void
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($token !== '') {
            $this->db->query('DELETE FROM sessions WHERE token = ?', [$token]);
        }
        $this->setCookie('', time() - 3600);
    }

    /**
     * Destroy all sessions for a user (e.g., password change / 2FA enable).
     */
    public function destroyAll(int $userId): void
    {
        $this->db->query('DELETE FROM sessions WHERE user_id = ?', [$userId]);
        $this->setCookie('', time() - 3600);
    }

    /**
     * Switch the active account within the current session.
     */
    public function switchAccount(int $accountId): void
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($token !== '') {
            $this->db->query(
                'UPDATE sessions SET account_id = ? WHERE token = ?',
                [$accountId, $token]
            );
        }
    }

    /**
     * Remove expired sessions from the database.
     */
    public function cleanup(): void
    {
        $this->db->query('DELETE FROM sessions WHERE expires_at <= ?', [time()]);
    }

    private function setCookie(string $value, int $expires): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
