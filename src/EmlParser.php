<?php
declare(strict_types=1);

/**
 * EmlParser – RFC 822 / MIME email parser for .eml attachments.
 * Parses raw .eml message content into headers, HTML/text body, and nested attachments.
 */
class EmlParser
{
    /**
     * Parse raw RFC 822 .eml content.
     */
    public static function parse(string $rawContent): array
    {
        // Normalize line endings to CRLF
        $rawContent = str_replace(["\r\n", "\r"], "\n", $rawContent);
        
        // Split headers and body at the first blank line
        $parts = explode("\n\n", $rawContent, 2);
        $headerText = $parts[0] ?? '';
        $bodyText   = $parts[1] ?? '';

        $headers = self::parseHeaders($headerText);
        $contentType = $headers['content-type'] ?? 'text/plain';

        $parsedBody = self::parseBody($bodyText, $contentType, $headers['content-transfer-encoding'] ?? '');

        $subject = self::decodeMimeHeader($headers['subject'] ?? '(No subject)');
        $from    = self::decodeMimeHeader($headers['from'] ?? 'Unknown');
        $to      = self::decodeMimeHeader($headers['to'] ?? '');
        $cc      = self::decodeMimeHeader($headers['cc'] ?? '');
        $date    = $headers['date'] ?? '';
        if ($date !== '') {
            $ts = strtotime($date);
            if ($ts !== false && $ts > 0) {
                $date = date('D, d M Y H:i', $ts);
            }
        }

        return [
            'subject'     => $subject,
            'from'        => $from,
            'to'          => $to,
            'cc'          => $cc,
            'date'        => $date,
            'body_html'   => $parsedBody['html'],
            'body_text'   => $parsedBody['text'],
            'has_html'    => !empty($parsedBody['html']),
            'attachments' => $parsedBody['attachments'],
            'raw_headers' => $headerText,
        ];
    }

    /**
     * Parse header lines into an associative array with unfolded lines.
     */
    private static function parseHeaders(string $headerText): array
    {
        $headers = [];
        // Unfold multiline headers (lines beginning with space or tab)
        $unfolded = preg_replace("/\n[ \t]+/", ' ', $headerText);
        $lines = explode("\n", (string)$unfolded);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$name, $val] = explode(':', $line, 2);
            $key = strtolower(trim($name));
            $val = trim($val);
            if (isset($headers[$key])) {
                $headers[$key] .= ', ' . $val;
            } else {
                $headers[$key] = $val;
            }
        }

        return $headers;
    }

    /**
     * Parse body content according to Content-Type and transfer encoding.
     */
    private static function parseBody(string $body, string $contentTypeHeader, string $transferEncoding): array
    {
        $html = '';
        $text = '';
        $attachments = [];

        // Check if multipart
        if (preg_match('/multipart\/[a-z0-9_-]+/i', $contentTypeHeader)) {
            // Extract boundary parameter
            if (preg_match('/boundary\s*=\s*["\']?([^"\'\s;]+)["\']?/i', $contentTypeHeader, $m)) {
                $boundary = $m[1];
                $parts = explode('--' . $boundary, $body);

                foreach ($parts as $p) {
                    $p = trim($p);
                    if ($p === '' || $p === '--') {
                        continue;
                    }
                    $subParts = explode("\n\n", $p, 2);
                    $subHeadersText = $subParts[0] ?? '';
                    $subContent = $subParts[1] ?? '';

                    $subHeaders = self::parseHeaders($subHeadersText);
                    $subCtype   = $subHeaders['content-type'] ?? 'text/plain';
                    $subEncoding = $subHeaders['content-transfer-encoding'] ?? '';
                    $subDisp    = $subHeaders['content-disposition'] ?? '';

                    // Check if attachment
                    $filename = self::extractFilename($subDisp, $subCtype);
                    if ($filename !== '') {
                        $decodedAtt = self::decodeContent($subContent, $subEncoding);
                        $attachments[] = [
                            'filename' => $filename,
                            'size'     => strlen($decodedAtt),
                            'type'     => explode(';', $subCtype)[0],
                        ];
                        continue;
                    }

                    // Check for nested multipart
                    if (preg_match('/multipart\/[a-z0-9_-]+/i', $subCtype)) {
                        $nested = self::parseBody($subContent, $subCtype, $subEncoding);
                        if ($nested['html'] !== '' && $html === '') $html = $nested['html'];
                        if ($nested['text'] !== '' && $text === '') $text = $nested['text'];
                        $attachments = array_merge($attachments, $nested['attachments']);
                        continue;
                    }

                    $decodedPart = self::decodeContent($subContent, $subEncoding);
                    $decodedPart = self::convertCharset($decodedPart, $subCtype);

                    if (stripos($subCtype, 'text/html') !== false) {
                        if ($html === '') $html = $decodedPart;
                    } elseif (stripos($subCtype, 'text/plain') !== false) {
                        if ($text === '') $text = $decodedPart;
                    }
                }
            }
        } else {
            // Single-part message
            $decoded = self::decodeContent($body, $transferEncoding);
            $decoded = self::convertCharset($decoded, $contentTypeHeader);

            if (stripos($contentTypeHeader, 'text/html') !== false) {
                $html = $decoded;
            } else {
                $text = $decoded;
            }
        }

        // Fallback plain text from HTML if text is empty
        if ($text === '' && $html !== '') {
            $text = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)));
        }

        return [
            'html'        => $html,
            'text'        => $text,
            'attachments' => $attachments,
        ];
    }

    /**
     * Extract attachment filename from Content-Disposition or Content-Type headers.
     */
    private static function extractFilename(string $disposition, string $contentType): string
    {
        $filename = '';
        if (preg_match('/filename\*?=(?:UTF-8\'\')?["\']?([^"\'\r\n;]+)["\']?/i', $disposition, $m)) {
            $filename = rawurldecode(trim($m[1]));
        } elseif (preg_match('/name\*?=(?:UTF-8\'\')?["\']?([^"\'\r\n;]+)["\']?/i', $contentType, $m)) {
            $filename = rawurldecode(trim($m[1]));
        }

        if ($filename !== '') {
            $filename = self::decodeMimeHeader($filename);
            return basename(str_replace(["\0", "\r", "\n"], '', $filename));
        }

        if (stripos($disposition, 'attachment') !== false) {
            return 'attachment';
        }

        return '';
    }

    /**
     * Decode body transfer encoding (quoted-printable, base64, etc.).
     */
    private static function decodeContent(string $data, string $encoding): string
    {
        $enc = strtolower(trim($encoding));
        if ($enc === 'base64') {
            return base64_decode($data) ?: '';
        }
        if ($enc === 'quoted-printable') {
            return quoted_printable_decode($data);
        }
        return $data;
    }

    /**
     * Convert character set to UTF-8 if specified in Content-Type.
     */
    private static function convertCharset(string $data, string $contentType): string
    {
        if (preg_match('/charset\s*=\s*["\']?([^"\'\s;]+)["\']?/i', $contentType, $m)) {
            $charset = strtoupper(trim($m[1]));
            if ($charset !== 'UTF-8' && $charset !== 'US-ASCII' && function_exists('mb_convert_encoding')) {
                try {
                    $converted = @mb_convert_encoding($data, 'UTF-8', $charset);
                    if ($converted !== false && $converted !== '') {
                        return $converted;
                    }
                } catch (\Throwable $e) {
                    // Fall back to original
                }
            }
        }
        return $data;
    }

    /**
     * Decode MIME encoded headers (e.g. =?UTF-8?B?...?= or =?ISO-8859-1?Q?...?=).
     */
    public static function decodeMimeHeader(string $str): string
    {
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($str);
        }
        if (function_exists('iconv_mime_decode')) {
            return (string) iconv_mime_decode($str, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        }
        return $str;
    }
}
