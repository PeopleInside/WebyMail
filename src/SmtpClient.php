<?php
declare(strict_types=1);

/**
 * Minimal SMTP client using PHP streams.
 * Supports plain, SSL (port 465) and STARTTLS (port 587).
 * No external dependencies required.
 */
class SmtpClient
{
    /** @var resource|false */
    private mixed $socket = false;
    private string $log   = '';

    public function send(array $account, array $message): bool
    {
        $host      = $account['smtp_host'];
        $port      = (int)   $account['smtp_port'];
        $ssl       = (bool)  $account['smtp_ssl'];
        $starttls  = (bool)  $account['smtp_starttls'];
        $username  = $account['username'];
        $password  = $account['smtp_password'] ?? $account['password'] ?? '';
        $from      = $account['email'];

        try {
            $this->connect($host, $port, $ssl, $starttls);
            $this->authenticate($username, $password);
            $this->sendMessage($from, $message);
            $this->quit();
            return true;
        } catch (RuntimeException $e) {
            $this->log .= 'Error: ' . $e->getMessage() . "\n";
            $this->close();
            return false;
        }
    }

    public function getLog(): string
    {
        return $this->log;
    }

    // -------------------------------------------------------------------------
    // Connection
    // -------------------------------------------------------------------------

    private function connect(string $host, int $port, bool $ssl, bool $starttls): void
    {
        $proto  = $ssl ? 'ssl' : 'tcp';
        $ctx    = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $this->socket = @stream_socket_client(
            "{$proto}://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if ($this->socket === false) {
            throw new RuntimeException("Cannot connect to {$host}:{$port}: {$errstr}");
        }

        stream_set_timeout($this->socket, 30);
        $this->expect('220');

        // Send EHLO
        $domain = gethostname() ?: 'localhost';
        $this->cmd("EHLO {$domain}");
        $this->expect('250');

        if ($starttls && !$ssl) {
            $this->cmd('STARTTLS');
            $this->expect('220');

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new RuntimeException('STARTTLS failed.');
            }

            // Re-send EHLO after STARTTLS
            $this->cmd("EHLO {$domain}");
            $this->expect('250');
        }
    }

    private function authenticate(string $username, string $password): void
    {
        $this->cmd('AUTH LOGIN');
        $this->expect('334');
        $this->cmd(base64_encode($username));
        $this->expect('334');
        $this->cmd(base64_encode($password));
        $this->expect('235');
    }

    // -------------------------------------------------------------------------
    // Message transmission
    // -------------------------------------------------------------------------

    private function sendMessage(string $from, array $msg): void
    {
        $fromAddr = $this->extractAddress($from);
        $this->cmd("MAIL FROM:<{$fromAddr}>");
        $this->expect('250');

        $recipients = $this->parseRecipients($msg);
        foreach ($recipients as $addr) {
            $this->cmd("RCPT TO:<{$addr}>");
            $this->expect('250');
        }

        $this->cmd('DATA');
        $this->expect('354');

        $raw = $this->buildRaw($from, $msg);
        // Dot-stuffing: lines starting with '.' need an extra '.' (RFC 5321)
        $raw = preg_replace('/^\./m', '..', $raw);
        $this->write($raw . "\r\n.\r\n");
        $this->expect('250');
    }

    private function buildRaw(string $from, array $msg): string
    {
        $date     = date('r');
        $msgId    = '<' . bin2hex(random_bytes(16)) . '@webymail>';
        $fromFmt  = $msg['from_name']
            ? '=?UTF-8?B?' . base64_encode($msg['from_name']) . '?= <' . $this->extractAddress($from) . '>'
            : $from;

        $headers  = "Date: {$date}\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";
        $headers .= "From: {$fromFmt}\r\n";
        $headers .= "To: {$msg['to']}\r\n";
        if (!empty($msg['cc'])) {
            $headers .= "Cc: {$msg['cc']}\r\n";
        }
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($msg['subject']) . "?=\r\n";
        if (!empty($msg['reply_to'])) {
            $headers .= "Reply-To: {$msg['reply_to']}\r\n";
        }
        if (!empty($msg['in_reply_to'])) {
            $headers .= "In-Reply-To: {$msg['in_reply_to']}\r\n";
            $headers .= "References: {$msg['in_reply_to']}\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n";

        $attachments = $msg['attachments'] ?? [];
        $hasAttachments = !empty($attachments);

        $boundaryAlt   = '---=_WebyMail_ALT_' . bin2hex(random_bytes(8));
        $boundaryMixed = '---=_WebyMail_MIX_' . bin2hex(random_bytes(8));

        if ($hasAttachments) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundaryMixed}\"\r\n";
        } else {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n";
        }

        $plainText = $msg['body_text'] ?? strip_tags($msg['body_html'] ?? '');
        $htmlBody  = $msg['body_html'] ?? nl2br(htmlspecialchars($plainText));

        $body = '';

        if ($hasAttachments) {
            $body .= "--{$boundaryMixed}\r\n";
            $body .= "Content-Type: multipart/alternative; boundary=\"{$boundaryAlt}\"\r\n\r\n";
        }

        $body .= "--{$boundaryAlt}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($plainText)) . "\r\n";

        $body .= "--{$boundaryAlt}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $body .= "--{$boundaryAlt}--\r\n";

        if ($hasAttachments) {
            foreach ($attachments as $att) {
                $data = chunk_split(base64_encode($att['data']));
                $type = preg_replace('/[^A-Za-z0-9\\-\\.\\/\\+]+/', '', $att['type'] ?? '') ?: 'application/octet-stream';
                $name = addcslashes($att['name'] ?? 'attachment', "\"\\");
                $body .= "--{$boundaryMixed}\r\n";
                $body .= "Content-Type: {$type}; name=\"{$name}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
                $body .= $data . "\r\n";
            }
            $body .= "--{$boundaryMixed}--\r\n";
        }

        return $headers . "\r\n" . $body;
    }

    private function parseRecipients(array $msg): array
    {
        $all   = ($msg['to'] ?? '') . ',' . ($msg['cc'] ?? '') . ',' . ($msg['bcc'] ?? '');
        $addrs = [];
        foreach (explode(',', $all) as $raw) {
            $a = $this->extractAddress(trim($raw));
            if ($a !== '') {
                $addrs[] = $a;
            }
        }
        return array_unique($addrs);
    }

    private function extractAddress(string $str): string
    {
        if (preg_match('/<([^>]+)>/', $str, $m)) {
            return $m[1];
        }
        return trim($str);
    }

    // -------------------------------------------------------------------------
    // I/O helpers
    // -------------------------------------------------------------------------

    private function cmd(string $command): void
    {
        $this->write($command . "\r\n");
    }

    private function write(string $data): void
    {
        if ($this->socket === false) {
            throw new RuntimeException('Not connected.');
        }
        fwrite($this->socket, $data);
        $this->log .= '> ' . substr($data, 0, 80) . "\n";
    }

    private function read(): string
    {
        $response = '';
        while ($this->socket !== false && !feof($this->socket)) {
            $line      = fgets($this->socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            $this->log .= '< ' . $line;
            // Continue reading multi-line responses (4th char is '-')
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    private function expect(string $code): string
    {
        $response = $this->read();
        if (strpos($response, $code) !== 0) {
            throw new RuntimeException("Expected {$code}, got: " . trim($response));
        }
        return $response;
    }

    private function quit(): void
    {
        $this->cmd('QUIT');
        $this->close();
    }

    private function close(): void
    {
        if ($this->socket !== false) {
            fclose($this->socket);
            $this->socket = false;
        }
    }
}
