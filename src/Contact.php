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
                'id'      => $row['id'],
                'name'    => Config::decrypt($row['name_enc']),
                'email'   => Config::decrypt($row['email_enc']),
                'phone'   => $row['phone_enc'] ? Config::decrypt($row['phone_enc']) : '',
                'address' => $row['address_enc'] ? Config::decrypt($row['address_enc']) : '',
                'notes'   => $row['notes_enc'] ? Config::decrypt($row['notes_enc']) : '',
                'created_at' => $row['created_at']
            ];
        }
        return $contacts;
    }

    public function add(string $name, string $email, string $phone = '', string $address = '', string $notes = ''): int
    {
        $this->db->query(
            "INSERT INTO contacts (user_id, name_enc, email_enc, phone_enc, address_enc, notes_enc) VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->userId,
                Config::encrypt($name),
                Config::encrypt($email),
                $phone ? Config::encrypt($phone) : null,
                $address ? Config::encrypt($address) : null,
                $notes ? Config::encrypt($notes) : null
            ]
        );
        return $this->db->lastInsertId();
    }

    public function edit(int $id, string $name, string $email, string $phone = '', string $address = '', string $notes = ''): bool
    {
        $this->db->query(
            "UPDATE contacts SET name_enc = ?, email_enc = ?, phone_enc = ?, address_enc = ?, notes_enc = ? WHERE id = ? AND user_id = ?",
            [
                Config::encrypt($name),
                Config::encrypt($email),
                $phone ? Config::encrypt($phone) : null,
                $address ? Config::encrypt($address) : null,
                $notes ? Config::encrypt($notes) : null,
                $id,
                $this->userId
            ]
        );
        return true;
    }

    public function delete(int $id): bool
    {
        $this->db->query("DELETE FROM contacts WHERE id = ? AND user_id = ?", [$id, $this->userId]);
        return true;
    }
}
