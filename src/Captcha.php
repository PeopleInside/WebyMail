<?php
declare(strict_types=1);

/**
 * Self-hosted proof-of-work captcha.
 *
 * The server issues a challenge (random nonce) and a difficulty (leading hex
 * zeroes). The client must find an integer solution such that:
 *   sha256("<challenge>:<solution>") starts with the required zero prefix.
 *
 * Challenges are stored in the session with a short TTL to prevent replay.
 */
class Captcha
{
    private const SESSION_KEY = 'wm_pow_captcha';
    private const TTL_SECONDS = 600;
    private const DEFAULT_DIFFICULTY = 4; // number of leading hex zeroes required

    public function issue(int $difficulty = self::DEFAULT_DIFFICULTY): array
    {
        $challenge = bin2hex(random_bytes(16));
        $token     = bin2hex(random_bytes(16));

        $_SESSION[self::SESSION_KEY] = [
            'token'      => $token,
            'challenge'  => $challenge,
            'difficulty' => $difficulty,
            'expires'    => time() + self::TTL_SECONDS,
        ];

        return [
            'token'      => $token,
            'challenge'  => $challenge,
            'difficulty' => $difficulty,
        ];
    }

    public function verify(string $solution, string $token): bool
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        if ($stored === null) {
            return false;
        }
        if (!hash_equals($stored['token'] ?? '', $token)) {
            return false;
        }
        if (time() > (int) ($stored['expires'] ?? 0)) {
            return false;
        }

        $difficulty = max(1, (int) ($stored['difficulty'] ?? self::DEFAULT_DIFFICULTY));
        $challenge  = (string) ($stored['challenge'] ?? '');
        if ($challenge === '' || $solution === '') {
            return false;
        }

        $hash = hash('sha256', $challenge . ':' . $solution);
        $prefix = str_repeat('0', $difficulty);
        return str_starts_with($hash, $prefix);
    }
}
