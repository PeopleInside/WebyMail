<?php
declare(strict_types=1);

/**
 * Authentication – IMAP login, 2FA verification, user provisioning.
 */
class Auth
{
    /** Maximum 2FA verification failures before the pending session is invalidated. */
    private const MAX_2FA_ATTEMPTS = 5;
    private Database  $db;
    private Session   $session;
    private TwoFactor $tf;
    private Logger    $logger;

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->session = new Session();
        $this->tf      = new TwoFactor();
        $this->logger  = new Logger();
    }

    // -------------------------------------------------------------------------
    // Login flow
    // -------------------------------------------------------------------------

    /**
     * Step 1 – validate credentials against IMAP.
     * Returns ['ok'=>true, 'needs_2fa'=>bool, 'user_id'=>int, 'account_id'=>int]
     * or ['ok'=>false, 'error'=>string].
     */
    public function loginWithImap(
        string $host,
        int    $port,
        bool   $ssl,
        string $username,
        string $password,
        string $smtpHost,
        int    $smtpPort,
        bool   $smtpSsl,
        bool   $smtpStarttls,
        bool   $rememberMe = false,
        bool   $allowInsecureImap = false,
        bool   $allowInsecureSmtp = false
    ): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->isBanned($ip, $username)) {
            return ['ok' => false, 'error' => 'Too many failed login attempts. Please try again in 15 minutes.'];
        }

        // Test IMAP credentials
        try {
            $imap = new ImapClient();
            $imap->connect($host, $port, $ssl, $username, $password, $allowInsecureImap);
            $imap->disconnect();
        } catch (RuntimeException $e) {
            $this->recordFailedAttempt($ip, $username);
            $this->logger->security('login_failure', ['username' => $username, 'error' => $e->getMessage()]);
            error_log('IMAP login failed for ' . $username . ' from ' . $ip . ': ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Invalid credentials or server unreachable. Please check your details.'];
        }

        // Success: clear attempts
        $this->clearAttempts($ip, $username);
        $this->logger->security('login_step1_success', ['username' => $username]);

        // Provision or retrieve the user record
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ?', [$username]);
        if ($user === null) {
            $this->db->query(
                'INSERT INTO users (email, display_name) VALUES (?, ?)',
                [$username, $username]
            );
            $userId = $this->db->lastInsertId();
        } else {
            $userId = (int) $user['id'];
        }

        // Provision or retrieve the primary account record
        $account = $this->db->fetch(
            'SELECT * FROM accounts WHERE user_id = ? AND is_primary = 1',
            [$userId]
        );
        if ($account === null) {
            $this->db->query(
                'INSERT INTO accounts
                    (user_id, label, sender_name, signature, email, imap_host, imap_port, imap_ssl,
                     smtp_host, smtp_port, smtp_ssl, smtp_starttls, username, password, is_primary, validation_status,
                     allow_insecure_imap, allow_insecure_smtp)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, "valid", ?, ?)',
                [
                    $userId, 'Primary', $username, '',
                    $username,
                    $host, $port, (int) $ssl,
                    $smtpHost, $smtpPort, (int) $smtpSsl, (int) $smtpStarttls,
                    $username, $this->encryptPassword($password),
                    (int) $allowInsecureImap, (int) $allowInsecureSmtp
                ]
            );
            $accountId = $this->db->lastInsertId();
        } else {
            $accountId = (int) $account['id'];
            // Update stored credentials / server settings
            $this->db->query(
                'UPDATE accounts SET imap_host=?, imap_port=?, imap_ssl=?,
                    smtp_host=?, smtp_port=?, smtp_ssl=?, smtp_starttls=?,
                    password=?, username=?, validation_status="valid",
                    allow_insecure_imap=?, allow_insecure_smtp=?
                 WHERE id=?',
                [
                    $host, $port, (int) $ssl,
                    $smtpHost, $smtpPort, (int) $smtpSsl, (int) $smtpStarttls,
                    $this->encryptPassword($password), $username,
                    (int) $allowInsecureImap, (int) $allowInsecureSmtp,
                    $accountId,
                ]
            );
        }

        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);

        if (Config::get('2fa_enabled', true) && (int) $user['totp_enabled'] === 1) {
            // Store pending auth in a short-lived token so the 2FA page can complete it
            $pending = bin2hex(random_bytes(16));
            $_SESSION['pending_2fa'] = [
                'token'       => $pending,
                'user_id'     => $userId,
                'account_id'  => $accountId,
                'expires'     => time() + 300,
                'attempts'    => 0,
                'remember_me' => $rememberMe,
            ];
            return ['ok' => true, 'needs_2fa' => true, 'pending_token' => $pending];
        }

        $this->session->create($userId, $accountId, $rememberMe);
        return ['ok' => true, 'needs_2fa' => false];
    }

    /**
     * Step 2 – complete login with a TOTP code or a recovery code.
     */
    public function completeTwoFactor(string $code): array
    {
        $pending = $_SESSION['pending_2fa'] ?? null;
        if ($pending === null || time() > $pending['expires']) {
            unset($_SESSION['pending_2fa']);
            return ['ok' => false, 'error' => 'Session expired. Please log in again.'];
        }

        // Brute-force protection: max attempts before invalidating the pending session
        $maxAttempts = self::MAX_2FA_ATTEMPTS;
        $attempts    = (int) ($pending['attempts'] ?? 0);
        if ($attempts >= $maxAttempts) {
            unset($_SESSION['pending_2fa']);
            return ['ok' => false, 'error' => 'Too many failed attempts. Please log in again.'];
        }

        $userId    = (int) $pending['user_id'];
        $accountId = (int) $pending['account_id'];
        $rememberMe = (bool) ($pending['remember_me'] ?? false);

        // IP-level rate limiting (shared with the IMAP login counter)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userRow = $this->db->fetch('SELECT email FROM users WHERE id = ?', [$userId]);
        $username = $userRow['email'] ?? '';
        if ($this->isBanned($ip, $username)) {
            return ['ok' => false, 'error' => 'Too many failed login attempts. Please try again in 15 minutes.'];
        }

        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user === null) {
            unset($_SESSION['pending_2fa']);
            return ['ok' => false, 'error' => 'User not found.'];
        }

        // Try TOTP first
        $verifiedAt = $this->tf->verify($user['totp_secret'], $code, $user['last_totp_at'] ? (int)$user['last_totp_at'] : null);
        if ($verifiedAt !== null) {
            $this->db->query(
                'UPDATE users SET last_totp_at = ? WHERE id = ?',
                [$verifiedAt, $userId]
            );
            $this->clearAttempts($ip);
            $this->logger->security('login_success', ['userId' => $userId, 'username' => $username, 'type' => 'totp']);
            unset($_SESSION['pending_2fa']);
            $this->session->create($userId, $accountId, $rememberMe);
            return ['ok' => true];
        }

        // Try recovery codes
        $hashes = json_decode($user['recovery_codes'] ?? '[]', true) ?: [];
        $updated = $this->tf->useRecoveryCode($code, $hashes);
        if ($updated !== null) {
            $this->db->query(
                'UPDATE users SET recovery_codes = ? WHERE id = ?',
                [json_encode($updated), $userId]
            );
            $this->clearAttempts($ip);
            $this->logger->security('login_success', ['userId' => $userId, 'username' => $username, 'type' => 'recovery']);
            unset($_SESSION['pending_2fa']);
            $this->session->create($userId, $accountId, $rememberMe);
            return ['ok' => true, 'recovery_used' => true, 'remaining' => count($updated)];
        }

        // Failed attempt: increment counter in pending session and record against IP and username
        $_SESSION['pending_2fa']['attempts'] = $attempts + 1;
        $this->recordFailedAttempt($ip, $username);
        $this->logger->security('login_2fa_failure', ['userId' => $userId, 'username' => $username]);

        $remaining = $maxAttempts - ($attempts + 1);
        if ($remaining <= 0) {
            unset($_SESSION['pending_2fa']);
            return ['ok' => false, 'error' => 'Too many failed attempts. Please log in again.'];
        }

        return ['ok' => false, 'error' => 'Invalid code. ' . $remaining . ' attempt(s) remaining.'];
    }

    /**
     * Enable 2FA for a user: stores the secret and hashed recovery codes,
     * then invalidates all existing sessions (security best practice).
     */
    public function enable2FA(int $userId, string $secret, array $recoveryCodes): void
    {
        $encrypted = $this->tf->encryptRecoveryCodes($recoveryCodes);
        $this->db->query(
            'UPDATE users SET totp_secret=?, totp_enabled=1, recovery_codes=? WHERE id=?',
            [$secret, json_encode($encrypted), $userId]
        );
        $this->session->destroyAll($userId);
    }

    /**
     * Disable 2FA for a user.
     */
    public function disable2FA(int $userId): void
    {
        $this->db->query(
            'UPDATE users SET totp_secret=NULL, totp_enabled=0, recovery_codes=NULL WHERE id=?',
            [$userId]
        );
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    // -------------------------------------------------------------------------
    // Rate Limiting
    // -------------------------------------------------------------------------

    private function isBanned(string $ip, string $username): bool
    {
        $window = 15 * 60; // 15 minutes
        $maxAttempts = 5;

        // Check by IP
        $rowIp = $this->db->fetch('SELECT * FROM login_attempts WHERE ip_address = ? AND username = \'\'', [$ip]);
        if ($rowIp) {
            $attempts = (int) $rowIp['attempts'];
            $last     = (int) $rowIp['last_attempt'];
            if ($attempts >= $maxAttempts && (time() - $last) < $window) {
                return true;
            }
            if ((time() - $last) >= $window) {
                $this->clearAttempts($ip, '');
            }
        }

        // Check by Username
        if ($username !== '') {
            $rowUser = $this->db->fetch('SELECT * FROM login_attempts WHERE username = ?', [$username]);
            if ($rowUser) {
                $attempts = (int) $rowUser['attempts'];
                $last     = (int) $rowUser['last_attempt'];
                if ($attempts >= $maxAttempts && (time() - $last) < $window) {
                    return true;
                }
                if ((time() - $last) >= $window) {
                    $this->clearAttempts('', $username);
                }
            }
        }

        return false;
    }

    private function recordFailedAttempt(string $ip, string $username): void
    {
        // Record for IP
        $rowIp = $this->db->fetch('SELECT * FROM login_attempts WHERE ip_address = ? AND username = \'\'', [$ip]);
        if ($rowIp) {
            $this->db->query(
                'UPDATE login_attempts SET attempts = attempts + 1, last_attempt = ? WHERE ip_address = ? AND username = \'\'',
                [time(), $ip]
            );
        } else {
            $this->db->query(
                'INSERT INTO login_attempts (ip_address, username, attempts, last_attempt) VALUES (?, \'\', 1, ?)',
                [$ip, time()]
            );
        }

        // Record for Username
        if ($username !== '') {
            $rowUser = $this->db->fetch('SELECT * FROM login_attempts WHERE username = ? AND ip_address = \'\'', [$username]);
            if ($rowUser) {
                $this->db->query(
                    'UPDATE login_attempts SET attempts = attempts + 1, last_attempt = ? WHERE username = ? AND ip_address = \'\'',
                    [time(), $username]
                );
            } else {
                $this->db->query(
                    'INSERT INTO login_attempts (ip_address, username, attempts, last_attempt) VALUES (\'\', ?, 1, ?)',
                    [$username, time()]
                );
            }
        }
    }

    private function clearAttempts(string $ip, string $username): void
    {
        if ($ip !== '') {
            $this->db->query('DELETE FROM login_attempts WHERE ip_address = ? AND username = \'\'', [$ip]);
        }
        if ($username !== '') {
            $this->db->query('DELETE FROM login_attempts WHERE username = ? AND ip_address = \'\'', [$username]);
        }
    }

    // -------------------------------------------------------------------------
    // Password helpers  (AES-256-GCM using the app_secret as key material)
    // -------------------------------------------------------------------------

    public function encryptPassword(string $plain): string
    {
        return Config::encrypt($plain);
    }

    public function decryptPassword(string $stored): string
    {
        return Config::decrypt($stored);
    }
}
