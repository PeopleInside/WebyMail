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
            'SELECT id, user_id, label, sender_name, email, imap_host, imap_port, imap_ssl,
                    smtp_host, smtp_port, smtp_ssl, smtp_starttls, username, is_primary, created_at
             FROM accounts WHERE user_id = ? ORDER BY is_primary DESC, id ASC',
            [$userId]
        );
    }

    public function add(int $userId, array $data): int
    {
        $this->db->query(
            'INSERT INTO accounts
                (user_id, label, sender_name, email, imap_host, imap_port, imap_ssl,
                 smtp_host, smtp_port, smtp_ssl, smtp_starttls, username, password, is_primary)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)',
            [
                $userId,
                $data['label'],
                $data['sender_name'] ?? '',
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
        $fields = ['label', 'sender_name', 'email', 'imap_host', 'imap_port', 'imap_ssl',
                   'smtp_host', 'smtp_port', 'smtp_ssl', 'smtp_starttls', 'username'];
        $set    = implode(', ', array_map(fn($f) => "{$f} = ?", $fields));
        $values = array_map(fn($f) => $data[$f], $fields);

        if (!empty($data['password'])) {
            $set      .= ', password = ?';
            $values[]  = $this->auth->encryptPassword($data['password']);
        }

        $values[] = $id;
        $values[] = $userId;

        $this->db->query(
            "UPDATE accounts SET {$set} WHERE id = ? AND user_id = ?",
            $values
        );
        return true;
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
