<?php
declare(strict_types=1);

/**
 * Multi-account management.
 * The "primary" account is the one used for the initial IMAP login.
 * Additional accounts can be added and switched to without re-logging in.
 */
class Account
{
    private Database $db;
    private Auth     $auth;

    public function __construct()
    {
        $this->db   = Database::getInstance();
        $this->auth = new Auth();
    }

    public function get(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM accounts WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }
        $row['password_plain'] = $this->auth->decryptPassword($row['password']);
        return $row;
    }

    public function getForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT id, user_id, label, sender_name, signature, email, imap_host, imap_port, imap_ssl,
                    smtp_host, smtp_port, smtp_ssl, smtp_starttls, username, is_primary, created_at,
                    validation_status
              FROM accounts WHERE user_id = ? ORDER BY is_primary DESC, id ASC',
            [$userId]
        );
    }

    public function add(int $userId, array $data): int
    {
        // Validate credentials before adding
        $this->validateCredentials($data);

        $this->db->query(
            'INSERT INTO accounts
                (user_id, label, sender_name, signature, email, imap_host, imap_port, imap_ssl,
                 smtp_host, smtp_port, smtp_ssl, smtp_starttls, username, password, is_primary, validation_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, "valid")',
            [
                $userId,
                $data['label'],
                $data['sender_name'] ?? '',
                $data['signature'] ?? '',
                $data['email'],
                $data['imap_host'],
                (int) $data['imap_port'],
                (int) $data['imap_ssl'],
                $data['smtp_host'],
                (int) $data['smtp_port'],
                (int) $data['smtp_ssl'],
                (int) $data['smtp_starttls'],
                $data['username'],
                $this->auth->encryptPassword($data['password']),
            ]
        );
        return $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $existing = $this->get($id);
        if (!$existing) return false;

        // If credentials changed, validate them
        $credsChanged = ($data['username'] !== $existing['username'] || 
                         $data['imap_host'] !== $existing['imap_host'] ||
                         (int)$data['imap_port'] !== (int)$existing['imap_port'] ||
                         (int)$data['imap_ssl'] !== (int)$existing['imap_ssl'] ||
                         $data['smtp_host'] !== $existing['smtp_host'] ||
                         (int)$data['smtp_port'] !== (int)$existing['smtp_port'] ||
                         (int)$data['smtp_ssl'] !== (int)$existing['smtp_ssl'] ||
                         (int)$data['smtp_starttls'] !== (int)$existing['smtp_starttls'] ||
                         !empty($data['password']));
        
        if ($credsChanged) {
            $testData = array_merge($existing, $data);
            if (!empty($data['password'])) {
                $testData['password'] = $data['password'];
            } else {
                $testData['password'] = $existing['password_plain'];
            }
            $this->validateCredentials($testData);
        }

        $data['sender_name'] = $data['sender_name'] ?? '';
        $data['signature'] = $data['signature'] ?? '';
        $fields = ['label', 'sender_name', 'signature', 'email', 'imap_host', 'imap_port', 'imap_ssl',
                   'smtp_host', 'smtp_port', 'smtp_ssl', 'smtp_starttls', 'username'];
        $set    = implode(', ', array_map(fn($f) => "{$f} = ?", $fields));
        $values = array_map(fn($f) => $data[$f], $fields);

        if (!empty($data['password'])) {
            $set      .= ', password = ?';
            $values[]  = $this->auth->encryptPassword($data['password']);
        }

        // Set status to valid if we just validated it, otherwise keep existing
        if ($credsChanged) {
            $set .= ', validation_status = "valid"';
        }

        $values[] = $id;
        $values[] = $userId;

        $this->db->query(
            "UPDATE accounts SET {$set} WHERE id = ? AND user_id = ?",
            $values
        );
        return true;
    }

    public function validateCredentials(array $data): void
    {
        // IMAP check
        try {
            $imap = new ImapClient();
            $imap->connect(
                $data['imap_host'],
                (int)$data['imap_port'],
                (bool)$data['imap_ssl'],
                $data['username'],
                $data['password']
            );
            $imap->disconnect();
        } catch (Exception $e) {
            throw new RuntimeException("IMAP connection failed: " . $e->getMessage());
        }

        // SMTP check
        try {
            $smtp = new SmtpClient();
            $smtp->connect(
                $data['smtp_host'],
                (int)$data['smtp_port'],
                (bool)$data['smtp_ssl'],
                (bool)$data['smtp_starttls']
            );
            $smtp->authenticate($data['username'], $data['password']);
            $smtp->quit();
        } catch (Exception $e) {
            throw new RuntimeException("SMTP connection failed: " . $e->getMessage());
        }
    }

    public function checkCredentials(int $accountId, int $userId): string
    {
        $account = $this->get($accountId);
        if ($account === null || (int)$account['user_id'] !== $userId) {
            return 'error';
        }

        $this->db->query("UPDATE accounts SET validation_status = 'checking' WHERE id = ?", [$accountId]);

        try {
            $this->validateCredentials([
                'imap_host'     => $account['imap_host'],
                'imap_port'     => $account['imap_port'],
                'imap_ssl'      => $account['imap_ssl'],
                'smtp_host'     => $account['smtp_host'],
                'smtp_port'     => $account['smtp_port'],
                'smtp_ssl'      => $account['smtp_ssl'],
                'smtp_starttls' => $account['smtp_starttls'],
                'username'      => $account['username'],
                'password'      => $account['password_plain']
            ]);

            $this->db->query("UPDATE accounts SET validation_status = 'valid' WHERE id = ?", [$accountId]);
            return 'valid';
        } catch (Exception $e) {
            $this->db->query("UPDATE accounts SET validation_status = 'invalid' WHERE id = ?", [$accountId]);
            return 'invalid';
        }
    }

    public function checkAllUserAccounts(int $userId): void
    {
        $accounts = $this->getForUser($userId);
        foreach ($accounts as $acc) {
            $this->checkCredentials((int)$acc['id'], $userId);
        }
    }

    public function delete(int $id, int $userId): bool
    {
        // Cannot delete primary account
        $row = $this->db->fetch(
            'SELECT is_primary FROM accounts WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        if ($row === null || (int) $row['is_primary'] === 1) {
            return false;
        }
        $this->db->query('DELETE FROM accounts WHERE id = ? AND user_id = ?', [$id, $userId]);
        return true;
    }

    /**
     * Verify that an account belongs to the given user.
     */
    public function belongsToUser(int $accountId, int $userId): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM accounts WHERE id = ? AND user_id = ?',
            [$accountId, $userId]
        );
        return $row !== null;
    }

    /**
     * Build an ImapClient pre-connected to the given account.
     */
    public function imapConnect(int $accountId): ImapClient
    {
        $account = $this->get($accountId);
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }
        $imap = new ImapClient();
        $imap->connect(
            $account['imap_host'],
            (int) $account['imap_port'],
            (bool) $account['imap_ssl'],
            $account['username'],
            $account['password_plain']
        );
        return $imap;
    }

    /**
     * Build an array suitable for SmtpClient::send() for the given account.
     */
    public function smtpParams(int $accountId): array
    {
        $account = $this->get($accountId);
        if ($account === null) {
            throw new RuntimeException('Account not found.');
        }
        return [
            'smtp_host'     => $account['smtp_host'],
            'smtp_port'     => (int) $account['smtp_port'],
            'smtp_ssl'      => (bool) $account['smtp_ssl'],
            'smtp_starttls' => (bool) $account['smtp_starttls'],
            'username'      => $account['username'],
            'password'      => $account['password_plain'],
            'email'         => $account['email'],
        ];
    }
}
