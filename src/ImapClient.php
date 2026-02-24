<?php
declare(strict_types=1);

/**
 * IMAP client wrapper using PHP's built-in imap_* extension.
 * Handles connection, folder listing, message retrieval, and mutations.
 */
class ImapClient
{
    /** @var resource|false */
    private mixed $conn = false;
    private string $host  = '';
    private string $user  = '';

    public function connect(
        string $host,
        int    $port,
        bool   $ssl,
        string $username,
        string $password
    ): void {
        if (!extension_loaded('imap')) {
            throw new RuntimeException('PHP IMAP extension is not installed.');
        }

        $flags = '/imap';
        if ($ssl) {
            $flags .= '/ssl';
        }
        $flags .= '/norsh/novalidate-cert';

        $mailbox = '{' . $host . ':' . $port . $flags . '}';
        $this->host = $mailbox;
        $this->user = $username;

        // Suppress warnings; check result manually
        $conn = @imap_open($mailbox, $username, $password, 0, 1);
        if ($conn === false) {
            $err = imap_last_error();
            throw new RuntimeException($err ?: 'Unknown IMAP error');
        }
        $this->conn = $conn;
    }

    public function disconnect(): void
    {
        if ($this->conn !== false) {
            imap_close($this->conn, CL_EXPUNGE);
            $this->conn = false;
        }
    }

    // -------------------------------------------------------------------------
    // Folders
    // -------------------------------------------------------------------------

    public function getFolders(): array
    {
        $this->assertConnected();
        $list = imap_list($this->conn, $this->host, '*');
        if (!is_array($list)) {
            return [];
        }
        return array_map(function (string $raw): array {
            $name = str_replace($this->host, '', $raw);
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF7-IMAP');
            return ['raw' => $raw, 'name' => $name, 'display' => $this->friendlyName($name)];
        }, $list);
    }

    // -------------------------------------------------------------------------
    // Message listing
    // -------------------------------------------------------------------------

    /**
     * Return paginated message summaries for a folder.
     */
    public function getMessages(string $folder, int $page = 1, int $perPage = 30): array
    {
        $this->reopenFolder($folder);

        $total = imap_num_msg($this->conn);
        if ($total === 0) {
            return ['messages' => [], 'total' => 0, 'pages' => 0];
        }

        // Messages are numbered 1..N; we list newest first
        $from  = max(1, $total - ($page * $perPage) + 1);
        $to    = max(1, $total - (($page - 1) * $perPage));

        $overview = imap_fetch_overview($this->conn, "{$from}:{$to}", 0);
        if (!is_array($overview)) {
            return ['messages' => [], 'total' => $total, 'pages' => (int) ceil($total / $perPage)];
        }

        $messages = array_reverse(array_map([$this, 'summaryFromOverview'], $overview));

        return [
            'messages' => $messages,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Fetch and parse a full message.
     */
    public function getMessage(string $folder, int $msgNo): array
    {
        $this->reopenFolder($folder);

        $header  = imap_headerinfo($this->conn, $msgNo);
        $body    = $this->getBody($msgNo);
        $struct  = imap_fetchstructure($this->conn, $msgNo);

        // Mark as read
        imap_setflag_full($this->conn, (string) $msgNo, '\\Seen');

        return [
            'uid'         => imap_uid($this->conn, $msgNo),
            'msg_no'      => $msgNo,
            'subject'     => $this->decodeHeader($header->subject ?? '(no subject)'),
            'from'        => $this->addressToString($header->from ?? []),
            'to'          => $this->addressToString($header->to ?? []),
            'cc'          => $this->addressToString($header->cc ?? []),
            'reply_to'    => $this->addressToString($header->reply_to ?? []),
            'date'        => date('D, d M Y H:i', $header->udate ?? time()),
            'body_html'   => $body['html'],
            'body_text'   => $body['text'],
            'attachments' => $this->getAttachments($msgNo, $struct),
            'is_read'     => (bool) ($header->Seen ?? false),
        ];
    }

    // -------------------------------------------------------------------------
    // Mutations
    // -------------------------------------------------------------------------

    public function deleteMessage(string $folder, int $msgNo): bool
    {
        $this->reopenFolder($folder);
        imap_delete($this->conn, (string) $msgNo);
        return imap_expunge($this->conn);
    }

    public function moveMessage(string $folder, int $msgNo, string $dest): bool
    {
        $this->reopenFolder($folder);
        $destRaw = mb_convert_encoding($dest, 'UTF7-IMAP', 'UTF-8');
        return (bool) imap_mail_move($this->conn, (string) $msgNo, $destRaw);
    }

    public function markRead(string $folder, int $msgNo, bool $read): bool
    {
        $this->reopenFolder($folder);
        if ($read) {
            imap_setflag_full($this->conn, (string) $msgNo, '\\Seen');
        } else {
            imap_clearflag_full($this->conn, (string) $msgNo, '\\Seen');
        }
        return true;
    }

    public function getUnreadCount(string $folder): int
    {
        $this->reopenFolder($folder);
        $status = imap_status($this->conn, $this->host . mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8'), SA_UNSEEN);
        return $status ? (int) $status->unseen : 0;
    }

    // -------------------------------------------------------------------------
    // Body parsing
    // -------------------------------------------------------------------------

    private function getBody(int $msgNo): array
    {
        $struct = imap_fetchstructure($this->conn, $msgNo);
        $html   = '';
        $text   = '';

        if (!isset($struct->parts)) {
            // Single-part message
            $body = imap_body($this->conn, $msgNo);
            $body = $this->decodeBodyPart($body, $struct->encoding ?? 0);
            $body = $this->convertCharset($body, $struct->parameters ?? []);
            if (strtolower($struct->subtype ?? 'plain') === 'html') {
                $html = $body;
            } else {
                $text = $body;
            }
        } else {
            $this->walkParts($msgNo, $struct->parts, '0', $html, $text);
        }

        if ($html === '' && $text === '') {
            $fallback = $this->decodeBodyPart(imap_body($this->conn, $msgNo), $struct->encoding ?? 0);
            $text = $this->convertCharset($fallback, $struct->parameters ?? []);
        }

        return ['html' => $html, 'text' => $text];
    }

    private function walkParts(int $msgNo, array $parts, string $prefix, string &$html, string &$text): void
    {
        foreach ($parts as $i => $part) {
            $section = $prefix === '0' ? (string)($i + 1) : $prefix . '.' . ($i + 1);
            $type    = strtolower($part->subtype ?? 'plain');

            if (isset($part->parts)) {
                $this->walkParts($msgNo, $part->parts, $section, $html, $text);
                continue;
            }

            // Skip attachments (those with a filename/name)
            if ($this->isAttachment($part)) {
                continue;
            }

            if ($part->type === 0) { // text/*
                $raw  = imap_fetchbody($this->conn, $msgNo, $section);
                $body = $this->decodeBodyPart($raw, $part->encoding ?? 0);
                $body = $this->convertCharset($body, $part->parameters ?? []);
                if ($type === 'html') {
                    $html = $body;
                } else {
                    $text = $body;
                }
            }
        }
    }

    private function decodeBodyPart(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body),
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function convertCharset(string $body, array $params): string
    {
        foreach ($params as $p) {
            if (strtolower($p->attribute) === 'charset') {
                $charset = strtoupper($p->value);
                if ($charset !== 'UTF-8' && $charset !== 'UTF8') {
                    $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
                    return $converted !== false ? $converted : $body;
                }
            }
        }
        return $body;
    }

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------

    private function getAttachments(int $msgNo, object $struct): array
    {
        $attachments = [];
        if (!isset($struct->parts)) {
            return $attachments;
        }
        foreach ($struct->parts as $i => $part) {
            if ($this->isAttachment($part)) {
                $filename = '';
                foreach (($part->dparameters ?? []) as $dp) {
                    if (strtolower($dp->attribute) === 'filename') {
                        $filename = $this->decodeHeader($dp->value);
                    }
                }
                foreach (($part->parameters ?? []) as $p) {
                    if (strtolower($p->attribute) === 'name') {
                        $filename = $filename ?: $this->decodeHeader($p->value);
                    }
                }
                $attachments[] = [
                    'section'  => (string)($i + 1),
                    'filename' => $filename ?: 'attachment',
                    'size'     => $part->bytes ?? 0,
                    'type'     => $part->type ?? 0,
                    'subtype'  => $part->subtype ?? 'octet-stream',
                ];
            }
        }
        return $attachments;
    }

    public function getRawHeaders(string $folder, int $msgNo): string
    {
        $this->reopenFolder($folder);
        return imap_fetchheader($this->conn, $msgNo) ?: '';
    }

    public function fetchAttachment(int $msgNo, string $section): string
    {
        $raw = imap_fetchbody($this->conn, $msgNo, $section);
        return base64_decode($raw);
    }

    public function appendToFolder(string $folder, string $raw, string $flags = "\\Seen"): bool
    {
        $this->assertConnected();
        $folderRaw = $this->host . mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
        $ok = imap_append($this->conn, $folderRaw, $raw, $flags);
        if ($ok === false) {
            throw new RuntimeException('Failed to append message to folder ' . $folder);
        }
        return true;
    }

    public function createFolder(string $name): bool
    {
        $this->assertConnected();
        $nameRaw = $this->host . mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');
        $ok = imap_createmailbox($this->conn, $nameRaw);
        if ($ok === false) {
            throw new RuntimeException('Failed to create folder: ' . $name);
        }
        return true;
    }

    public function renameFolder(string $oldName, string $newName): bool
    {
        $this->assertConnected();
        $oldRaw = $this->host . mb_convert_encoding($oldName, 'UTF7-IMAP', 'UTF-8');
        $newRaw = $this->host . mb_convert_encoding($newName, 'UTF7-IMAP', 'UTF-8');
        $ok = imap_renamemailbox($this->conn, $oldRaw, $newRaw);
        if ($ok === false) {
            throw new RuntimeException('Failed to rename folder: ' . $oldName);
        }
        return true;
    }

    public function deleteFolder(string $name): bool
    {
        $this->assertConnected();
        $nameRaw = $this->host . mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');
        $ok = imap_deletemailbox($this->conn, $nameRaw);
        if ($ok === false) {
            throw new RuntimeException('Failed to delete folder: ' . $name);
        }
        return true;
    }

    private function isAttachment(object $part): bool
    {
        if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
            return true;
        }
        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $dp) {
                if (strtolower($dp->attribute) === 'filename') {
                    return true;
                }
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Header helpers
    // -------------------------------------------------------------------------

    private function summaryFromOverview(object $ov): array
    {
        return [
            'msg_no'  => $ov->msgno,
            'uid'     => $ov->uid,
            'subject' => $this->decodeHeader($ov->subject ?? '(no subject)'),
            'from'    => $this->decodeHeader($ov->from ?? ''),
            'date'    => isset($ov->udate) ? date('d M Y H:i', $ov->udate) : '',
            'is_read' => (bool) $ov->seen,
            'is_flagged' => (bool) ($ov->flagged ?? false),
            'size'    => (int) ($ov->size ?? 0),
        ];
    }

    private function decodeHeader(string $str): string
    {
        $decoded = imap_mime_header_decode($str);
        $out     = '';
        foreach ($decoded as $part) {
            $charset = $part->charset === 'default' ? 'UTF-8' : $part->charset;
            $text    = @mb_convert_encoding($part->text, 'UTF-8', $charset);
            $out    .= $text !== false ? $text : $part->text;
        }
        return $out;
    }

    private function addressToString(array $addresses): string
    {
        return implode(', ', array_map(function (object $a): string {
            $name = $this->decodeHeader($a->personal ?? '');
            $addr = ($a->mailbox ?? '') . '@' . ($a->host ?? '');
            return $name ? "{$name} <{$addr}>" : $addr;
        }, $addresses));
    }

    private function friendlyName(string $name): string
    {
        $map = [
            'INBOX'       => 'Inbox',
            'Sent'        => 'Sent',
            'Sent Items'  => 'Sent',
            'Drafts'      => 'Drafts',
            'Trash'       => 'Trash',
            'Deleted'     => 'Trash',
            'Deleted Items' => 'Trash',
            'Spam'        => 'Spam',
            'Junk'        => 'Spam',
            'Junk E-mail' => 'Spam',
        ];
        return $map[$name] ?? $name;
    }

    private function reopenFolder(string $folder): void
    {
        $this->assertConnected();
        $folderRaw = $this->host . mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
        if (!imap_reopen($this->conn, $folderRaw)) {
            throw new RuntimeException('Cannot open folder: ' . $folder);
        }
    }

    private function assertConnected(): void
    {
        if ($this->conn === false) {
            throw new RuntimeException('Not connected to IMAP server.');
        }
    }
}
