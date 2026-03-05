<?php
declare(strict_types=1);

/**
 * Self-hosted proof-of-work captcha.
 *
 * The server issues a challenge (random nonce) and a difficulty (leading hex
 * zeroes). The client must find an integer solution such that:
 *   sha256("<challenge>:<solution>") starts with the required zero prefix.
 *
 * The token is an HMAC of the challenge parameters so verification can
 * succeed even when the PHP session has been lost between GET and POST
 * (e.g. browser quirks, session expiry at the boundary).  The session is
 * still used as the primary store and provides replay-protection; the HMAC
 * path is a safe fallback.
 */
class Captcha
{
    private const SESSION_KEY = 'wm_pow_captcha';
    private const TTL_SECONDS = 300; // 5 minutes
    private const DEFAULT_DIFFICULTY = 4; // 4 leading hex zeros ≈ 65k hashes on average

    public function issue(int $difficulty = self::DEFAULT_DIFFICULTY): array
    {
        $challenge = bin2hex(random_bytes(16));
        $expires   = time() + self::TTL_SECONDS;
        $token     = $this->signChallenge($challenge, $difficulty, $expires);

        $_SESSION[self::SESSION_KEY] = [
            'token'      => $token,
            'challenge'  => $challenge,
            'difficulty' => $difficulty,
            'expires'    => $expires,
        ];

        return [
            'token'      => $token,
            'challenge'  => $challenge,
            'difficulty' => $difficulty,
            'expires'    => $expires,
        ];
    }

    /**
     * Verify a PoW submission.
     *
     * When the PHP session is still intact the stored challenge data is used
     * directly (primary path, also provides replay protection).  If the
     * session was lost the method falls back to HMAC verification using the
     * challenge metadata that the client sent back as hidden form fields.
     *
     * @param string $solution   The nonce found by the client.
     * @param string $token      HMAC token returned with the challenge.
     * @param string $challenge  Challenge string (fallback, from hidden field).
     * @param int    $difficulty Difficulty (fallback, from hidden field).
     * @param int    $expires    Expiry timestamp (fallback, from hidden field).
     */
    public function verify(
        string $solution,
        string $token,
        string $challenge  = '',
        int    $difficulty = 0,
        int    $expires    = 0
    ): bool {
        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        // Normalize client-provided fallback payload
        $fallbackChallenge  = (string) $challenge;
        $fallbackDifficulty = max(1, (int) $difficulty);
        $fallbackExpires    = (int) $expires;

        $challenge  = $fallbackChallenge;
        $difficulty = $fallbackDifficulty;
        $expires    = $fallbackExpires;
        $expected   = null;

        if ($stored !== null) {
            // Primary path: use session data.
            $challenge  = (string) ($stored['challenge'] ?? '');
            $difficulty = max(1, (int) ($stored['difficulty'] ?? self::DEFAULT_DIFFICULTY));
            $expires    = (int) ($stored['expires'] ?? 0);
            $expected   = (string) ($stored['token'] ?? '');
        }

        // Primary verification (session-backed). If it fails or session was lost,
        // fall back to client-provided metadata, which is HMAC-signed.
        $matched = false;
        if ($expected !== null && hash_equals($expected, $token)) {
            $matched = true;
        } elseif ($fallbackChallenge !== '' && $fallbackDifficulty > 0 && $fallbackExpires > 0) {
            $expected = $this->signChallenge($fallbackChallenge, $fallbackDifficulty, $fallbackExpires);
            if (hash_equals($expected, $token)) {
                $matched    = true;
                $challenge  = $fallbackChallenge;
                $difficulty = $fallbackDifficulty;
                $expires    = $fallbackExpires;
            }
        }

        if (!$matched) {
            return false;
        }
        if (time() > $expires) {
            return false;
        }
        if ($solution === '') {
            return false;
        }

        $hash   = hash('sha256', $challenge . ':' . $solution);
        $prefix = str_repeat('0', $difficulty);
        return str_starts_with($hash, $prefix);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function signChallenge(string $challenge, int $difficulty, int $expires): string
    {
        $secret = (string) Config::get('app_secret', '');
        if ($secret === '') {
            throw new \RuntimeException('app_secret is not configured; cannot sign captcha challenge.');
        }
        return hash_hmac('sha256', $challenge . ':' . $difficulty . ':' . $expires, $secret);
    }
}
