<?php
declare(strict_types=1);

/**
 * IMAP client wrapper using PHP's built-in imap_* extension.
 * Handles connection, folder listing, message retrieval, and mutations.
 */
class ImapClient
{
    /** @var resource|false */
    private $conn = false;
    private string $host  = '';
    private string $user  = '';
    private const ENC_NONE = 0;
    private const ENC_BASE64 = 3;
    private const ENC_QPRINT = 4;

    public function connect(
        string $host,
        int    $port,
        bool   $ssl,
        string $username,
        string $password,
        bool   $allowInsecure = false
    ): void {
        if (!extension_loaded('imap')) {
            throw new RuntimeException('PHP IMAP extension is not installed.');
        }

        $flags = '/imap';
        if ($ssl) {
            $flags .= '/ssl';
        }
        $flags .= '/norsh';
        if ($allowInsecure) {
            $flags .= '/novalidate-cert';
        }

        $mailbox = '{' . $host . ':' . $port . $flags . '}';
        $this->host = $mailbox;
        $this->user = $username;

        // Set shorter timeouts for validation
        if (function_exists('imap_timeout')) {
            imap_timeout(IMAP_OPENTIMEOUT, 15);
            imap_timeout(IMAP_READTIMEOUT, 15);
            imap_timeout(IMAP_WRITETIMEOUT, 15);
            imap_timeout(IMAP_CLOSETIMEOUT, 15);
        }

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

    /** @return resource|false */
    public function getConnection()
    {
        return $this->conn;
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

        $messages = array_map([$this, 'summaryFromOverview'], $overview);
        
        // For each message, if has_attachments is false, do a quick structure check
        // to be more reliable, but only for the current page.
        foreach ($messages as &$msg) {
            $rawHeaders = imap_fetchheader($this->conn, $msg['msg_no']) ?: '';
            $msg['priority'] = $this->parsePriority($rawHeaders);

            if (!$msg['has_attachments']) {
                $struct = imap_fetchstructure($this->conn, $msg['msg_no']);
                if ($struct && isset($struct->parts)) {
                    foreach ($struct->parts as $part) {
                        if ($this->isAttachment($part)) {
                            $msg['has_attachments'] = true;
                            break;
                        }
                    }
                }
            }
        }

        $messages = array_reverse($messages);

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

        $header  = imap_headerinfo($this->conn, $msgNo) ?: null;
        $body    = $this->getBody($msgNo);
        $struct  = imap_fetchstructure($this->conn, $msgNo) ?: null;
        $rawHeaders = imap_fetchheader($this->conn, $msgNo) ?: '';

        // Mark as read
        imap_setflag_full($this->conn, (string) $msgNo, '\\Seen');

        return [
            'uid'         => imap_uid($this->conn, $msgNo),
            'msg_no'      => $msgNo,
            'subject'     => $this->decodeHeader($header?->subject ?? '(no subject)'),
            'from'        => $this->addressToString($header?->from ?? []),
            'to'          => $this->addressToString($header?->to ?? []),
            'cc'          => $this->addressToString($header?->cc ?? []),
            'reply_to'    => $this->addressToString($header?->reply_to ?? []),
            'date'        => date('D, d M Y H:i', $header?->udate ?? time()),
            'body_html'   => $body['html'],
            'body_text'   => $body['text'],
            'attachments' => $struct ? $this->getAttachments($msgNo, $struct) : [],
            'is_read'     => (bool) ($header?->Seen ?? false),
            'priority'    => $this->parsePriority($rawHeaders),
            'read_receipt_to' => $this->parseReadReceipt($rawHeaders),
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

    public function deleteMessageByUid(string $folder, int $uid): bool
    {
        $this->reopenFolder($folder);
        imap_delete($this->conn, (string) $uid, FT_UID);
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

    public function emptyFolder(string $folder): bool
    {
        $this->reopenFolder($folder);
        $total = imap_num_msg($this->conn);
        if ($total === 0) {
            return true;
        }
        // Mark all messages for deletion
        imap_delete($this->conn, '1:*');
        return imap_expunge($this->conn);
    }

    public function getUnreadCount(string $folder): int
    {
        $this->assertConnected();
        $status = imap_status($this->conn, $this->host . mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8'), SA_UNSEEN);
        return $status ? (int) $status->unseen : 0;
    }

    // -------------------------------------------------------------------------
    // Body parsing
    // -------------------------------------------------------------------------

    private function getBody(int $msgNo): array
    {
        $struct = imap_fetchstructure($this->conn, $msgNo);
        if ($struct === false) {
            error_log(sprintf('IMAP warning: fetchstructure failed for message %d (possibly malformed/DSN) - using raw body fallback.', $msgNo));
            $rawBody = imap_body($this->conn, $msgNo);
            // Headers are often still retrievable even when the structure cannot be parsed.
            $rawHeaders = imap_fetchheader($this->conn, $msgNo) ?: '';
            $encoding = $this->detectContentTransferEncoding($rawHeaders);
            $fallback = $this->decodeBodyPart($rawBody ?: '', $encoding);
            return ['html' => '', 'text' => $fallback ?: ''];
        }
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

    private function decodeBodyPart(string|bool $body, int $encoding): string
    {
        if ($body === false) return '';
        return match ($encoding) {
            self::ENC_BASE64 => base64_decode($body),
            self::ENC_QPRINT => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function detectContentTransferEncoding(string $rawHeaders): int
    {
        if (preg_match('/^Content-Transfer-Encoding:\\s*([a-z0-9_-]+)/mi', $rawHeaders, $matches)) {
            return match (strtolower($matches[1])) {
                'base64' => self::ENC_BASE64,
                'quoted-printable' => self::ENC_QPRINT,
                default => self::ENC_NONE,
            };
        }
        return self::ENC_NONE;
    }

    private function convertCharset(string $body, array $params): string
    {
        foreach ($params as $p) {
            if (strtolower($p->attribute) === 'charset') {
                $charset = trim((string) $p->value, " \t\n\r\0\x0B\"'");
                // Some malformed headers append extra parameters to the charset value
                if (str_contains($charset, ';')) {
                    $charset = substr($charset, 0, strpos($charset, ';'));
                }
                if ($charset === '' || strcasecmp($charset, 'UTF-8') === 0 || strcasecmp($charset, 'UTF8') === 0) {
                    return $body;
                }
                try {
                    $converted = mb_convert_encoding($body, 'UTF-8', $charset);
                    return $converted !== false ? $converted : $body;
                } catch (\Throwable $e) {
                    error_log(sprintf('IMAP: charset conversion failed for "%s": %s', $charset, $e->getMessage()));
                    return $body;
                }
            }
        }
        return $body;
    }

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------

    public function selectFolder(string $folder): void
    {
        $this->reopenFolder($folder);
    }

    public function fetchStructure(int $msgNo): object
    {
        $this->assertConnected();
        $struct = imap_fetchstructure($this->conn, $msgNo);
        if (!$struct) {
            throw new RuntimeException('Could not fetch structure for message ' . $msgNo);
        }
        return $struct;
    }

    public function getAttachments(int $msgNo, object $struct, string $prefix = ''): array
    {
        $attachments = [];
        if (!isset($struct->parts)) {
            return $attachments;
        }

        foreach ($struct->parts as $i => $part) {
            $section = $prefix === '' ? (string)($i + 1) : $prefix . '.' . ($i + 1);
            
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

                // Fallback for message/rfc822 which might not have a filename but is an .eml
                if (!$filename && strtolower($part->subtype ?? '') === 'rfc822') {
                    // Try to get subject from encapsulated headers
                    $encHeaders = imap_fetchbody($this->conn, $msgNo, $section . '.0');
                    if ($encHeaders) {
                        if (preg_match('/^Subject: (.*)$/mi', $encHeaders, $matches)) {
                            $filename = $this->decodeHeader(trim($matches[1]));
                            if ($filename) {
                                // Basic cleaning for filesystem safety
                                $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
                                if (!str_ends_with(strtolower($filename), '.eml')) {
                                    $filename .= '.eml';
                                }
                            }
                        }
                    }
                    
                    if (!$filename && !empty($part->description)) {
                        $filename = $this->decodeHeader($part->description);
                        if ($filename) {
                            $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $filename);
                            if (!str_ends_with(strtolower($filename), '.eml')) {
                                $filename .= '.eml';
                            }
                        }
                    }
                    
                    if (!$filename) {
                        $filename = 'message.eml';
                    }
                }

                $attachments[] = [
                    'section'  => $section,
                    'filename' => $filename ?: 'attachment-' . $section,
                    'size'     => $part->bytes ?? 0,
                    'type'     => $part->type ?? 0,
                    'subtype'  => $part->subtype ?? 'octet-stream',
                ];
            } elseif (isset($part->parts)) {
                // Recursively look for attachments in nested parts (common in SpamAssassin emails)
                $attachments = array_merge($attachments, $this->getAttachments($msgNo, $part, $section));
            }
        }
        return $attachments;
    }

    public function getRawHeaders(string $folder, int $msgNo): string
    {
        $this->reopenFolder($folder);
        return imap_fetchheader($this->conn, $msgNo) ?: '';
    }

    public function getRawMessage(string $folder, int $msgNo): string
    {
        $this->reopenFolder($folder);
        return imap_fetchbody($this->conn, $msgNo, "");
    }

    public function fetchAttachment(int $msgNo, string $section): string
    {
        $this->assertConnected();
        $struct = imap_fetchstructure($this->conn, $msgNo);
        if ($struct === false) {
            throw new RuntimeException(sprintf('IMAP: could not fetch structure for message %d (section %s - MIME part identifier)', $msgNo, $section));
        }
        $part   = $this->findPartBySection($struct, $section);
        
        if ($part && $part->type === 2 && strtolower($part->subtype ?? '') === 'rfc822') {
            // For message/rfc822 parts, imap_fetchbody(..., section) only returns the body.
            // We need to fetch section.0 to get the encapsulated headers.
            $headers = imap_fetchbody($this->conn, $msgNo, $section . '.0');
            $body    = imap_fetchbody($this->conn, $msgNo, $section);
            
            if ($headers === false) $headers = '';
            if ($body === false) $body = '';
            
            // Encapsulated messages are usually not encoded themselves (the parts inside are)
            return $headers . "\r\n" . $this->decodeBodyPart($body, $part->encoding ?? 0);
        }

        $encoding = $part ? ($part->encoding ?? 0) : 0;
        $raw = imap_fetchbody($this->conn, $msgNo, $section);
        return $this->decodeBodyPart($raw, $encoding);
    }

    private function findPartBySection(object $struct, string $section): ?object
    {
        $parts = explode('.', $section);
        $current = $struct;
        foreach ($parts as $p) {
            $idx = (int)$p - 1;
            if (!isset($current->parts[$idx])) {
                return null;
            }
            $current = $current->parts[$idx];
        }
        return $current;
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

    public function getLastUid(string $folder): int
    {
        $this->reopenFolder($folder);
        $total = imap_num_msg($this->conn);
        if ($total === 0) return 0;
        return (int) imap_uid($this->conn, $total);
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
        // Explicit attachment disposition
        if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
            return true;
        }

        // Check for filename in parameters or dparameters
        $params = array_merge($part->parameters ?? [], $part->dparameters ?? []);
        foreach ($params as $p) {
            if (isset($p->attribute) && (strtolower($p->attribute) === 'filename' || strtolower($p->attribute) === 'name')) {
                return true;
            }
        }

        // SpamAssassin often wraps original email as message/rfc822
        if (isset($part->subtype) && strtolower($part->subtype) === 'rfc822') {
            return true;
        }

        // Some parts might be marked as inline but have a filename, treat them as attachments too
        if (isset($part->disposition) && strtolower($part->disposition) === 'inline') {
            foreach ($params as $p) {
                if (isset($p->attribute) && (strtolower($p->attribute) === 'filename' || strtolower($p->attribute) === 'name')) {
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
        // Try to detect attachments from overview if possible (some servers support it)
        $hasAttachments = (bool) ($ov->has_attachment ?? false);

        return [
            'msg_no'  => $ov->msgno,
            'uid'     => $ov->uid,
            'subject' => $this->decodeHeader($ov->subject ?? '(no subject)'),
            'from'    => $this->decodeHeader($ov->from ?? ''),
            'date'    => isset($ov->udate) ? date('d M Y H:i', $ov->udate) : '',
            'is_read' => (bool) $ov->seen,
            'is_flagged' => (bool) ($ov->flagged ?? false),
            'size'    => (int) ($ov->size ?? 0),
            'has_attachments' => $hasAttachments,
            'priority' => 'normal', // Will be updated in getMessages loop
        ];
    }

    private function parsePriority(string $headers): string
    {
        if (preg_match('/^X-Priority:\s*([1-5])/mi', $headers, $m)) {
            return match ((int)$m[1]) {
                1, 2 => 'high',
                4, 5 => 'low',
                default => 'normal'
            };
        }
        if (preg_match('/^Importance:\s*(high|normal|low)/mi', $headers, $m)) {
            return strtolower($m[1]);
        }
        return 'normal';
    }

    private function parseReadReceipt(string $headers): ?string
    {
        if (preg_match('/^Disposition-Notification-To:\s*(.*)$/mi', $headers, $m)) {
            return trim($m[1]);
        }
        return null;
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

    public function appendMessage(string $folder, string $rawMessage): bool
    {
        $this->assertConnected();
        $folderRaw = $this->host . mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
        return imap_append($this->conn, $folderRaw, $rawMessage);
    }

    public function getFriendlyName(string $name): string
    {
        return $this->friendlyName($name);
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
