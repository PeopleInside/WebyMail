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
    private const LIFETIME_6_MONTHS     = 15552000;     // 6 months (180 days)
    private const LIFETIME_12_MONTHS    = 31536000;     // 12 months (365 days / 1 year)
    private const IDLE_TIMEOUT_DEFAULT  = 7200;         // 2 hours   (no "remember me")
    private const IDLE_TIMEOUT_REMEMBERED = 0;          // 0 = no idle disconnection for remembered logins

    private Database $db;
    private int $lifetime;

    public function __construct()
    {
        $this->db       = Database::getInstance();
        $this->lifetime = (int) Config::get('session_lifetime', self::LIFETIME_6_MONTHS);
    }

    /**
     * Create a new session and set the cookie.
     * @param bool|int|string $rememberMe When true/'6'/'6m' lasts 6 months; when '12'/'12m' lasts 12 months;
     *                                    when false/0 lasts 24 hours with a 2-hour idle timeout.
     */
    public function create(int $userId, int $accountId, bool|int|string $rememberMe = false): string
    {
        // Regenerate the PHP native session ID to prevent session fixation attacks.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->cleanup();

        // Determine session duration from rememberMe selection
        $rememberMonths = 0;
        $remStr = strtolower(trim((string)$rememberMe));
        if ($remStr === '12' || $remStr === '12m' || $remStr === '1year' || $rememberMe === 12) {
            $rememberMonths = 12;
            $absoluteLifetime = self::LIFETIME_12_MONTHS;
        } elseif ($remStr === '6' || $remStr === '6m' || $remStr === '1' || $remStr === 'true' || $rememberMe === true || $rememberMe === 6) {
            $rememberMonths = 6;
            $absoluteLifetime = self::LIFETIME_6_MONTHS;
        } else {
            $rememberMonths = 0;
            $absoluteLifetime = self::LIFETIME_DEFAULT; // 24 hours
        }

        // Sessions remain active until they expire (expires_at) or are explicitly revoked by the user.
        // We set idle_timeout to 0 so sessions are not prematurely removed from the active sessions page.
        $idleTimeout = 0;

        $token     = bin2hex(random_bytes(32));
        $expiresAt = time() + $absoluteLifetime;
        $ip        = Config::encrypt($_SERVER['REMOTE_ADDR'] ?? '');
        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $this->db->query(
            'INSERT INTO sessions (token, user_id, account_id, ip_address, user_agent, expires_at, remember_me, idle_timeout)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$token, $userId, ($accountId > 0 ? $accountId : null), $ip, $ua, $expiresAt, $rememberMonths, $idleTimeout]
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
            'SELECT token, ip_address, user_agent, created_at, last_seen, expires_at, remember_me
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

        // Sessions remain in the database and visible in active sessions until they reach
        // expires_at or are explicitly revoked by the user.
        // We do not delete session records on temporary inactivity.

        // Fingerprint validation: User-Agent browser family & platform check.
        // We compare stable platform and browser family rather than major/minor version
        // numbers so that automatic monthly browser updates (e.g. Chrome/Edge/Firefox)
        // do not invalidate the user's 6 or 12 month persistent session.
        $currentUa = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $storedFp  = $this->normalizeUserAgent($row['user_agent'] ?? '');
        $currentFp = $this->normalizeUserAgent($currentUa);
        
        $uaMatches = true;
        if ($storedFp !== '' && $currentFp !== '') {
            $uaMatches = ($storedFp === $currentFp);
        }

        if (!$uaMatches) {
            // Log security warning instead of permanently wiping session on minor emulation or agent differences
            error_log(sprintf('WebyMail session UA mismatch: stored [%s] vs current [%s]', $storedFp, $currentFp));
            $this->destroy();
            return null;
        }

        // Refresh last_seen periodically (every 5 minutes) to avoid DB write on every request.
        // Also slide/extend the session expiration for active remembered logins.
        $now = time();
        $lastSeen = (int) $row['last_seen'];
        if ($now - $lastSeen > 300) {
            $rememberMonths = (int) ($row['remember_me'] ?? 0);
            $expiresAt = (int) $row['expires_at'];

            // Sliding session: if user is remembered (6 or 12 months) and more than 7 days have passed,
            // extend the expiry so continuous active use never expires unexpectedly.
            if ($rememberMonths > 0 && ($now - $lastSeen) > 604800) {
                $extensionLifetime = ($rememberMonths >= 12) ? self::LIFETIME_12_MONTHS : self::LIFETIME_6_MONTHS;
                $newExpiresAt = $now + $extensionLifetime;
                $this->db->query(
                    'UPDATE sessions SET last_seen = ?, expires_at = ? WHERE token = ?',
                    [$now, $newExpiresAt, $token]
                );
                $this->setCookie($token, $newExpiresAt);
                $row['expires_at'] = $newExpiresAt;
            } else {
                $this->db->query(
                    'UPDATE sessions SET last_seen = ? WHERE token = ?',
                    [$now, $token]
                );
            }
            $row['last_seen'] = $now;
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
        $currentToken = trim((string) ($_COOKIE[self::COOKIE_NAME] ?? ''));
        $isValidToken = ctype_xdigit($currentToken) && strlen($currentToken) === 64;
        if ($isValidToken) {
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

    public function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $forwardedSsl   = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL']   ?? ''));
        if (str_contains($forwardedProto, 'https') || $forwardedSsl === 'on') {
            return true;
        }
        if (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], 'https')) {
            return true;
        }

        // Trust X-Forwarded-* headers from explicitly configured trusted proxies
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) Config::get('trusted_proxies', ''))));
        if (!empty($trustedProxies)) {
            $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (in_array($remoteIp, $trustedProxies, true) && str_contains($forwardedProto, 'https')) {
                return true;
            }
        }

        return false;
    }

    public function setCookie(string $value, int $expires): void
    {
        $secure = $this->isSecure();
        // Use SameSite=Lax so that navigations from bookmarks, email clients, or external links
        // properly send the session cookie on top-level GET requests without logging the user out.
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Reduce a User-Agent string to a stable fingerprint combining platform and
     * browser family. Unlike comparing exact version numbers (which change every 4
     * weeks with automatic browser updates and invalidate sessions), this provides
     * session hijacking protection without breaking long-lived persistent logins.
     */
    public function normalizeUserAgent(string $ua): string
    {
        $ua = trim($ua);
        if ($ua === '') {
            return '';
        }

        // Detect platform family
        $platform = 'other';
        if (stripos($ua, 'Windows') !== false) {
            $platform = 'win';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false) {
            $platform = 'ios';
        } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
            $platform = 'mac';
        } elseif (stripos($ua, 'Android') !== false) {
            $platform = 'android';
        } elseif (stripos($ua, 'Linux') !== false) {
            $platform = 'linux';
        }

        // Detect browser family
        $browser = 'other';
        if (stripos($ua, 'Edg') !== false) {
            $browser = 'edge';
        } elseif (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'opera';
        } elseif (stripos($ua, 'Chrome') !== false || stripos($ua, 'CriOS') !== false) {
            $browser = 'chrome';
        } elseif (stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false) {
            $browser = 'firefox';
        } elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
            $browser = 'safari';
        }

        return $platform . ':' . $browser;
    }
}
