<?php
declare(strict_types=1);

/**
 * Self-hosted proof-of-work captcha (stateless HMAC-based).
 *
 * The server issues a challenge (random nonce) and a difficulty (leading hex
 * zeroes). The client must find an integer solution such that:
 *   sha256("<challenge>:<solution>") starts with the required zero prefix.
 *
 * Challenges are verified using an HMAC signature so no session storage is
 * required. This works correctly across multiple browser tabs and in private/
 * incognito browsing modes where sessions may not persist reliably.
 */
class Captcha
{
    private const TTL_SECONDS = 300; // 5 minutes
    private const DEFAULT_DIFFICULTY = 5;

    public function issue(int $difficulty = self::DEFAULT_DIFFICULTY): array
    {
        $challenge = bin2hex(random_bytes(16));
        $expires   = time() + self::TTL_SECONDS;
        $token     = $this->sign($challenge, $difficulty, $expires);

        return [
            'token'      => $token,
            'challenge'  => $challenge,
            'difficulty' => $difficulty,
            'expires'    => $expires,
        ];
    }

    /**
     * Verify a proof-of-work solution.
     *
     * @param string $solution   Nonce found by the client
     * @param string $challenge  Random challenge string from issue()
     * @param int    $difficulty Number of leading hex zeroes required
     * @param int    $expires    Unix timestamp when the challenge expires
     * @param string $token      HMAC token from issue() to prevent forgery
     */
    public function verify(
        string $solution,
        string $challenge,
        int    $difficulty,
        int    $expires,
        string $token
    ): bool {
        if ($challenge === '' || $solution === '' || $token === '') {
            return false;
        }

        // Verify the HMAC to ensure the challenge was issued by this server
        // and that difficulty/expires have not been tampered with.
        $expected = $this->sign($challenge, $difficulty, $expires);
        if (!hash_equals($expected, $token)) {
            return false;
        }

        // Verify expiry
        if (time() > $expires) {
            return false;
        }

        // Verify proof-of-work solution
        $difficulty = max(1, $difficulty);
        $hash       = hash('sha256', $challenge . ':' . $solution);
        $prefix     = str_repeat('0', $difficulty);
        return str_starts_with($hash, $prefix);
    }

    private function sign(string $challenge, int $difficulty, int $expires): string
    {
        $secret = Config::get('app_secret', '');
        if ($secret === '') {
            // app_secret must be set in config; this is generated automatically during setup.
            throw new \RuntimeException('app_secret is not configured. Captcha cannot be verified securely.');
        }
        $data = $challenge . '|' . $difficulty . '|' . $expires;
        return hash_hmac('sha256', $data, $secret);
    }
}
