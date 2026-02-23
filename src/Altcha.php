<?php
declare(strict_types=1);

/**
 * ALTCHA Proof-of-Work captcha – server-side implementation.
 *
 * Compatible with the official ALTCHA JS widget.
 * Spec: https://altcha.org/docs/api/
 *
 * Algorithm:
 *   challenge  = HMAC-SHA256(salt, hmac_key)
 *   Client finds: number N where SHA256("SHA-256:challenge:N") <= maxhash
 *   signature  = HMAC-SHA256("challenge:N", hmac_key)
 */
class Altcha
{
    private string $hmacKey;
    private Database $db;

    public function __construct()
    {
        $this->hmacKey = Config::get('altcha_hmac_key', '');
        $this->db      = Database::getInstance();
        $this->cleanupExpired();
    }

    /**
     * Create a new challenge payload (JSON) to embed in the page.
     *
     * @param int $complexity  Max number the client must search up to (higher = harder).
     * @param int $ttl         Seconds until the challenge expires.
     */
    public function createChallenge(int $complexity = 50000, int $ttl = 600): array
    {
        $expiresAt = time() + $ttl;
        $salt      = bin2hex(random_bytes(12)) . '?expires=' . $expiresAt;
        $challenge = hash_hmac('sha256', $salt, $this->hmacKey);

        // Store challenge so we can prevent replay attacks
        $this->db->query(
            'INSERT OR IGNORE INTO altcha_challenges (challenge, expires_at) VALUES (?, ?)',
            [$challenge, $expiresAt]
        );

        return [
            'algorithm' => 'SHA-256',
            'challenge' => $challenge,
            'salt'      => $salt,
            'signature' => hash_hmac('sha256', $challenge, $this->hmacKey),
            'maxnumber' => $complexity,
        ];
    }

    /**
     * Verify a base64-encoded ALTCHA payload from the client.
     */
    public function verify(string $payload): bool
    {
        if ($payload === '') {
            return false;
        }

        $json = base64_decode($payload, true);
        if ($json === false) {
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return false;
        }

        $algorithm = $data['algorithm'] ?? '';
        $challenge = $data['challenge'] ?? '';
        $number    = isset($data['number']) ? (int) $data['number'] : -1;
        $salt      = $data['salt']      ?? '';
        $signature = $data['signature'] ?? '';
        $maxNumber = isset($data['maxnumber']) ? (int) $data['maxnumber'] : 0;

        if ($algorithm !== 'SHA-256' || $challenge === '' || $number < 0 || $salt === '') {
            return false;
        }
        if ($maxNumber > 0 && $number > $maxNumber) {
            return false;
        }

        // Verify the challenge was issued by this server
        $expectedChallenge = hash_hmac('sha256', $salt, $this->hmacKey);
        if (!hash_equals($expectedChallenge, $challenge)) {
            return false;
        }

        // Check expiry embedded in the salt (e.g. "abc123?expires=1700000000")
        if (preg_match('/[?&]expires=(\d+)/', $salt, $m)) {
            if (time() > (int) $m[1]) {
                return false;
            }
        }

        // Verify the signature by re-computing the expected hash on the server.
        // Accept both documented client formats (SHA-256) and the HMAC variant for added authenticity:
        // 1) "algorithm:challenge:number" (current ALTCHA docs)
        // 2) "salt:number" (legacy examples)
        // 3) HMAC(challenge:number, hmacKey) for backwards compatibility with earlier implementation
        $expectedA   = hash('sha256', "{$algorithm}:{$challenge}:{$number}");
        $expectedB   = hash('sha256', "{$salt}:{$number}");
        $expectedHmac = hash_hmac('sha256', "{$challenge}:{$number}", $this->hmacKey);
        if ($signature === '' || (!hash_equals($expectedA, $signature)
            && !hash_equals($expectedB, $signature)
            && !hash_equals($expectedHmac, $signature))) {
            return false;
        }

        // Replay protection: mark challenge as used
        $row = $this->db->fetch(
            'SELECT used, expires_at FROM altcha_challenges WHERE challenge = ?',
            [$challenge]
        );

        if ($row === null) {
            // Challenge not in DB (was never issued by us or already cleaned up)
            return false;
        }
        if ((int) $row['used'] === 1) {
            return false; // Replay attack
        }
        if (time() > (int) $row['expires_at']) {
            return false;
        }

        $this->db->query(
            'UPDATE altcha_challenges SET used = 1 WHERE challenge = ?',
            [$challenge]
        );

        return true;
    }

    private function cleanupExpired(): void
    {
        $this->db->query(
            'DELETE FROM altcha_challenges WHERE expires_at < ?',
            [time() - 3600]
        );
    }
}
