<?php
declare(strict_types=1);

/**
 * TOTP (RFC 6238) Two-Factor Authentication – pure PHP, no external deps.
 *
 * Generates TOTP secrets, QR-code enrollment URLs, verifies codes,
 * and manages one-time recovery codes.
 */
class TwoFactor
{
    private const DIGITS   = 6;
    private const PERIOD   = 30;
    private const WINDOW   = 1;   // accept 1 step before/after for clock skew
    private const BASE32   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const RECOVERY_COUNT = 8;
    private const RECOVERY_LEN   = 10;

    /** Generate a new random Base32-encoded TOTP secret. */
    public function generateSecret(): string
    {
        $bytes  = random_bytes(20); // 160-bit secret as recommended by RFC 4226
        $secret = '';
        $carry  = 0;
        $bits   = 0;
        foreach (str_split($bytes) as $byte) {
            $carry = ($carry << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits  -= 5;
                $secret .= self::BASE32[($carry >> $bits) & 0x1F];
            }
        }
        if ($bits > 0) {
            $secret .= self::BASE32[($carry << (5 - $bits)) & 0x1F];
        }
        return $secret;
    }

    /**
     * Return an otpauth:// URL suitable for encoding as a QR code.
     */
    public function getOtpauthUrl(string $secret, string $email, string $issuer = 'WebyMail'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($email),
            rawurlencode($secret),
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    /**
     * Return a Google Charts QR code image URL for the otpauth URL.
     * Used for setup only; no external call is made at verification time.
     */
    public function getQRCodeUrl(string $secret, string $email, string $issuer = 'WebyMail'): string
    {
        $data = $this->getOtpauthUrl($secret, $email, $issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($data);
    }

    /**
     * Verify a 6-digit TOTP code against the secret.
     * Allows WINDOW steps of clock skew.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $key      = $this->base32Decode($secret);
        $timeStep = (int) floor(time() / self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals($this->compute($key, $timeStep + $offset), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a set of one-time recovery codes.
     * Returns plain-text codes (caller must hash and store them).
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            // Format: XXXXX-XXXXX (alphanumeric, uppercase)
            $raw    = strtoupper(bin2hex(random_bytes(self::RECOVERY_LEN)));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }
        return $codes;
    }

    /**
     * Hash recovery codes for storage (one hash per code, constant-time compare on use).
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $codes);
    }

    /**
     * Attempt to use a recovery code. Returns updated hashes array (with used
     * code removed) on success, or null on failure.
     */
    public function useRecoveryCode(string $code, array $hashedCodes): ?array
    {
        $code = strtoupper(trim($code));
        foreach ($hashedCodes as $i => $hash) {
            if (password_verify($code, $hash)) {
                array_splice($hashedCodes, $i, 1);
                return $hashedCodes;
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function compute(string $key, int $counter): string
    {
        $msg  = pack('J', $counter);           // big-endian 64-bit
        $hash = hash_hmac('sha1', $msg, $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$off])     & 0x7F) << 24) |
            ((ord($hash[$off + 1]) & 0xFF) << 16) |
            ((ord($hash[$off + 2]) & 0xFF) << 8)  |
             (ord($hash[$off + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $input  = strtoupper($input);
        $output = '';
        $carry  = 0;
        $bits   = 0;
        foreach (str_split($input) as $char) {
            $pos = strpos(self::BASE32, $char);
            if ($pos === false) {
                continue;
            }
            $carry = ($carry << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $output .= chr(($carry >> $bits) & 0xFF);
            }
        }
        return $output;
    }
}
