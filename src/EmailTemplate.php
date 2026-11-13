<?php
declare(strict_types=1);

/**
 * Secure Email Template management stored in SQLite with encryption.
 */
class EmailTemplate
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
        $rows = $this->db->fetchAll(
            'SELECT * FROM email_templates WHERE user_id = ? ORDER BY updated_at DESC, id DESC',
            [$this->userId]
        );
        $templates = [];
        foreach ($rows as $row) {
            $templates[] = $this->hydrate($row);
        }
        usort($templates, static function (array $a, array $b): int {
            return strnatcasecmp($a['name'], $b['name']);
        });
        return $templates;
    }

    public function get(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM email_templates WHERE id = ? AND user_id = ?',
            [$id, $this->userId]
        );
        return $row ? $this->hydrate($row) : null;
    }

    public function add(string $name, string $subject = '', string $bodyHtml = ''): int
    {
        $now = time();
        $this->db->query(
            'INSERT INTO email_templates (user_id, name_enc, subject_enc, body_enc, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $this->userId,
                Config::encrypt($name),
                $subject !== '' ? Config::encrypt($subject) : null,
                $bodyHtml !== '' ? Config::encrypt($bodyHtml) : null,
                $now,
                $now,
            ]
        );
        return $this->db->lastInsertId();
    }

    public function edit(int $id, string $name, string $subject = '', string $bodyHtml = ''): bool
    {
        $this->db->query(
            'UPDATE email_templates SET name_enc = ?, subject_enc = ?, body_enc = ?, updated_at = ? WHERE id = ? AND user_id = ?',
            [
                Config::encrypt($name),
                $subject !== '' ? Config::encrypt($subject) : null,
                $bodyHtml !== '' ? Config::encrypt($bodyHtml) : null,
                time(),
                $id,
                $this->userId,
            ]
        );
        return true;
    }

    public function delete(int $id): bool
    {
        $this->db->query(
            'DELETE FROM email_templates WHERE id = ? AND user_id = ?',
            [$id, $this->userId]
        );
        return true;
    }

    private function hydrate(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'name'       => Config::decrypt((string) $row['name_enc']),
            'subject'    => !empty($row['subject_enc']) ? Config::decrypt((string) $row['subject_enc']) : '',
            'body_html'  => !empty($row['body_enc']) ? Config::decrypt((string) $row['body_enc']) : '',
            'created_at' => (int) ($row['created_at'] ?? 0),
            'updated_at' => (int) ($row['updated_at'] ?? 0),
        ];
    }
}
