<?php
declare(strict_types=1);

/**
 * Custom cookie-based session management (6-month lifetime).
 * Token is stored in an HttpOnly, SameSite=Strict cookie and validated
 * against the sessions table in the database.
 */
class Session
{
    private const COOKIE_NAME           = 'wm_session';
    private const LIFETIME_DEFAULT      = 86400;        // 24 hours  (no "remember me")
    private const LIFETIME_EXTENDED     = 15552000;     // 6 months  ("remember me")
    private const IDLE_TIMEOUT_DEFAULT  = 7200;         // 2 hours   (no "remember me")
    private const IDLE_TIMEOUT_EXTENDED = 15552000;     // 6 months  ("remember me")

    private Database $db;
    private int $lifetime;

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->lifetime = (int) Config::get('session_lifetime', 15552000); // 6 months default
    }

    /**
     * Create a new session and set the cookie.
     * @param bool $rememberMe  When true the session lasts 6 months with a 30-day idle
     *                          timeout; when false it lasts 24 hours with a 2-hour idle timeout.
     */
    public function create(int $userId, int $accountId, bool $rememberMe = false): string
    {
        // Regenerate the PHP native session ID to prevent session fixation attacks.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->cleanup();

        // Enforce 30-session limit
        $sessions = $this->db->fetchAll(
            'SELECT token FROM sessions WHERE user_id = ? ORDER BY created_at ASC',
            [$userId]
        );
        if (count($sessions) >= 30) {
            $oldest = $sessions[0]['token'];
            $this->db->query('DELETE FROM sessions WHERE token = ?', [$oldest]);
        }

        // Lifetime and idle timeout depend on "remember me"
        $absoluteLifetime = $rememberMe ? self::LIFETIME_EXTENDED     : self::LIFETIME_DEFAULT;
        $idleTimeout      = $rememberMe ? self::IDLE_TIMEOUT_EXTENDED  : self::IDLE_TIMEOUT_DEFAULT;

        $token     = bin2hex(random_bytes(32));
        $expiresAt = time() + $absoluteLifetime;
        $ip        = Config::encrypt($_SERVER['REMOTE_ADDR'] ?? '');
        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $this->db->query(
            'INSERT INTO sessions (token, user_id, account_id, ip_address, user_agent, expires_at, remember_me, idle_timeout)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$token, $userId, $accountId, $ip, $ua, $expiresAt, (int) $rememberMe, $idleTimeout]
        );

        $this->setCookie($token, $expiresAt);
        return $token;
    }

    /**
     * Get all active sessions for a user.
     */
    public function getAllForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT token, ip_address, user_agent, created_at, last_seen, expires_at
             FROM sessions
             WHERE user_id = ? AND expires_at > ?
             ORDER BY last_seen DESC',
            [$userId, time()]
        );
    }

    /**
     * Revoke a specific session.
     */
    public function revoke(string $token, int $userId): void
    {
        $this->db->query(
            'DELETE FROM sessions WHERE token = ? AND user_id = ?',
            [$token, $userId]
        );
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
            'SELECT s.*, u.email, u.display_name, u.totp_enabled, u.signature, u.theme
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = ? AND s.expires_at > ?',
            [$token, time()]
        );

        if ($row === null) {
            return null;
        }

        // Idle-timeout check: if the session has been inactive for longer than its
        // idle_timeout, treat it as expired and destroy it.
        $idleTimeout = (int) ($row['idle_timeout'] ?? 0);
        if ($idleTimeout > 0 && (time() - (int) $row['last_seen']) > $idleTimeout) {
            $this->destroy();
            return null;
        }

        // Fingerprint validation: User-Agent check (tolerant to device emulation)
        $currentUa = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $storedFp  = $this->normalizeUserAgent($row['user_agent'] ?? '');
        $currentFp = $this->normalizeUserAgent($currentUa);
        $uaMatches = ($storedFp !== '' && $currentFp !== '')
            ? ($storedFp === $currentFp)
            : ($row['user_agent'] === $currentUa);

        if (!$uaMatches) {
            $this->destroy();
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
     * Destroy all sessions for a user except the current one.
     */
    public function destroyAllExceptCurrent(int $userId): void
    {
        $currentToken = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($currentToken !== '') {
            $this->db->query(
                'DELETE FROM sessions WHERE user_id = ? AND token <> ?',
                [$userId, $currentToken]
            );
            return;
        }

        $this->db->query('DELETE FROM sessions WHERE user_id = ?', [$userId]);
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
     * Get a CSRF token derived from the current session.
     */
    public function getCsrfToken(): string
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if ($token === '') {
            return '';
        }
        return hash_hmac('sha256', $token, Config::get('app_secret', ''));
    }

    /**
     * Remove expired sessions from the database.
     */
    public function cleanup(): void
    {
        $this->db->query('DELETE FROM sessions WHERE expires_at <= ?', [time()]);
    }

    private function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        // Only trust X-Forwarded-* headers from explicitly configured trusted proxies.
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) Config::get('trusted_proxies', ''))));
        if (!empty($trustedProxies)) {
            $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (in_array($remoteIp, $trustedProxies, true)) {
                $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
                $forwardedSsl   = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL']   ?? ''));
                if (str_contains($forwardedProto, 'https') || $forwardedSsl === 'on') {
                    return true;
                }
            }
        }

        return false;
    }

    private function setCookie(string $value, int $expires): void
    {
        $secure = $this->isSecure();
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /**
     * Reduce a User-Agent string to a stable fingerprint so that
     * developer tools' device emulation (which tweaks the UA string)
     * does not invalidate the session. We still keep browser family
     * and major version to guard against cross-browser reuse.
     */
    private function normalizeUserAgent(string $ua): string
    {
        $ua = trim($ua);
        if ($ua === '') {
            return '';
        }

        $ua = preg_replace('/\s+/', ' ', $ua);

        $patterns = [
            'edg'     => '/Edg[A-Za-z]*\/(\d+)/i',
            'chrome'  => '/Chrome\/(\d+)/i',
            'firefox' => '/Firefox\/(\d+)/i',
            'safari'  => '/Version\/(\d+)[^ ]* Safari\//i',
        ];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $ua, $m)) {
                return $label . ':' . $m[1];
            }
        }

        if (preg_match('/AppleWebKit\/(\d+)/i', $ua, $m)) {
            return 'webkit:' . $m[1];
        }

        // Fallback: keep a trimmed prefix for uncommon browsers so the fingerprint is still consistent
        return substr($ua, 0, 80);
    }
}
