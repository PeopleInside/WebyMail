<?php
declare(strict_types=1);

/**
 * Authentication – IMAP login, 2FA verification, user provisioning.
 */
class Auth
{
    private Database  $db;
    private Session   $session;
    private TwoFactor $tf;

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->session = new Session();
        $this->tf      = new TwoFactor();
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
        bool   $smtpStarttls
    ): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->isIpBanned($ip)) {
            return ['ok' => false, 'error' => 'Too many failed login attempts. Please try again in 15 minutes.'];
        }

        // Test IMAP credentials
        try {
            $imap = new ImapClient();
            // We pass allow_insecure_imap_cert from config if available
            $allowInsecure = (bool) Config::get('allow_insecure_imap_cert', false);
            $imap->connect($host, $port, $ssl, $username, $password, $allowInsecure);
            $imap->disconnect();
        } catch (RuntimeException $e) {
            $this->recordFailedAttempt($ip);
            return ['ok' => false, 'error' => 'IMAP login failed: ' . $e->getMessage()];
        }

        // Success: clear attempts for this IP
        $this->clearAttempts($ip);

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
                     smtp_host, smtp_port, smtp_ssl, smtp_starttls, username, password, is_primary, validation_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, "valid")',
                [
                    $userId, 'Primary', $username, '',
                    $username,
                    $host, $port, (int) $ssl,
                    $smtpHost, $smtpPort, (int) $smtpSsl, (int) $smtpStarttls,
                    $username, $this->encryptPassword($password),
                ]
            );
            $accountId = $this->db->lastInsertId();
        } else {
            $accountId = (int) $account['id'];
            // Update stored credentials / server settings
            $this->db->query(
                'UPDATE accounts SET imap_host=?, imap_port=?, imap_ssl=?,
                    smtp_host=?, smtp_port=?, smtp_ssl=?, smtp_starttls=?,
                    password=?, username=?, validation_status="valid"
                 WHERE id=?',
                [
                    $host, $port, (int) $ssl,
                    $smtpHost, $smtpPort, (int) $smtpSsl, (int) $smtpStarttls,
                    $this->encryptPassword($password), $username,
                    $accountId,
                ]
            );
        }

        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);

        if (Config::get('2fa_enabled', true) && (int) $user['totp_enabled'] === 1) {
            // Store pending auth in a short-lived token so the 2FA page can complete it
            $pending = bin2hex(random_bytes(16));
            $_SESSION['pending_2fa'] = [
                'token'      => $pending,
                'user_id'    => $userId,
                'account_id' => $accountId,
                'expires'    => time() + 300,
            ];
            return ['ok' => true, 'needs_2fa' => true, 'pending_token' => $pending];
        }

        $this->session->create($userId, $accountId);
        return ['ok' => true, 'needs_2fa' => false];
    }

    /**
     * Step 2 – complete login with a TOTP code or a recovery code.
     */
    public function completeTwoFactor(string $code): array
    {
        $pending = $_SESSION['pending_2fa'] ?? null;
        if ($pending === null || time() > $pending['expires']) {
            return ['ok' => false, 'error' => 'Session expired. Please log in again.'];
        }

        $userId    = (int) $pending['user_id'];
        $accountId = (int) $pending['account_id'];

        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user === null) {
            return ['ok' => false, 'error' => 'User not found.'];
        }

        // Try TOTP first
        if ($this->tf->verify($user['totp_secret'], $code)) {
            unset($_SESSION['pending_2fa']);
            $this->session->create($userId, $accountId);
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
            unset($_SESSION['pending_2fa']);
            $this->session->create($userId, $accountId);
            return ['ok' => true, 'recovery_used' => true, 'remaining' => count($updated)];
        }

        return ['ok' => false, 'error' => 'Invalid code.'];
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

    private function isIpBanned(string $ip): bool
    {
        $row = $this->db->fetch('SELECT * FROM login_attempts WHERE ip_address = ?', [$ip]);
        if (!$row) return false;

        $attempts = (int) $row['attempts'];
        $last     = (int) $row['last_attempt'];
        $window   = 15 * 60; // 15 minutes

        if ($attempts >= 5 && (time() - $last) < $window) {
            return true;
        }

        // If window passed, reset
        if ((time() - $last) >= $window) {
            $this->clearAttempts($ip);
            return false;
        }

        return false;
    }

    private function recordFailedAttempt(string $ip): void
    {
        $row = $this->db->fetch('SELECT * FROM login_attempts WHERE ip_address = ?', [$ip]);
        if ($row) {
            $this->db->query(
                'UPDATE login_attempts SET attempts = attempts + 1, last_attempt = ? WHERE ip_address = ?',
                [time(), $ip]
            );
        } else {
            $this->db->query(
                'INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, ?)',
                [$ip, time()]
            );
        }
    }

    private function clearAttempts(string $ip): void
    {
        $this->db->query('DELETE FROM login_attempts WHERE ip_address = ?', [$ip]);
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
