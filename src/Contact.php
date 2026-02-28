<?php
declare(strict_types=1);

/**
 * Secure Contact management with encryption
 */
class Contact
{
    private int $userId;
    private Database $db;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->db = Database::getInstance();
    }

    public function list(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM contacts WHERE user_id = ? ORDER BY created_at DESC", [$this->userId]);
        $contacts = [];
        foreach ($rows as $row) {
            $contacts[] = [
                'id'    => $row['id'],
                'name'  => $this->decrypt($row['name_enc']),
                'email' => $this->decrypt($row['email_enc']),
                'notes' => $row['notes_enc'] ? $this->decrypt($row['notes_enc']) : '',
                'created_at' => $row['created_at']
            ];
        }
        return $contacts;
    }

    public function add(string $name, string $email, string $notes = ''): int
    {
        $this->db->query(
            "INSERT INTO contacts (user_id, name_enc, email_enc, notes_enc) VALUES (?, ?, ?, ?)",
            [
                $this->userId,
                $this->encrypt($name),
                $this->encrypt($email),
                $notes ? $this->encrypt($notes) : null
            ]
        );
        return $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $this->db->query("DELETE FROM contacts WHERE id = ? AND user_id = ?", [$id, $this->userId]);
        return true;
    }

    private function encrypt(string $data): string
    {
        $key = $this->getEncryptionKey();
        $ivLength = openssl_cipher_iv_length('aes-256-gcm');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';
        $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    private function decrypt(string $data): string
    {
        $key = $this->getEncryptionKey();
        $decoded = base64_decode($data);
        $ivLength = openssl_cipher_iv_length('aes-256-gcm');
        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, 16);
        $ciphertext = substr($decoded, $ivLength + 16);
        return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag) ?: '';
    }

    private function getEncryptionKey(): string
    {
        $secret = Config::get('app_secret', 'fallback-secret');
        return hash('sha256', $secret, true);
    }
}
