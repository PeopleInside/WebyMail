<?php
declare(strict_types=1);

/**
 * EmailAuthAnalyzer – Inspects Authentication-Results, Received-SPF,
 * SpamAssassin/Rspamd headers, and DKIM-Signature headers to evaluate
 * SPF, DKIM, and DMARC status and warn about potential spoofing.
 */
class EmailAuthAnalyzer
{
    private const MULTI_LABEL_SLDS = [
        'co', 'com', 'org', 'net', 'gov', 'edu', 'ac', 'mil', 'gob', 'nom',
    ];

    /**
     * Analyze raw email headers and return structured SPF / DKIM / DMARC results.
     */
    public static function analyze(string $rawHeaders, string $fromHeader = ''): array
    {
        $headers = self::parseHeaderList($rawHeaders);
        if ($fromHeader === '') {
            foreach ($headers as $h) {
                if ($h['name'] === 'from') {
                    $fromHeader = $h['value'];
                    break;
                }
            }
        }

        $fromDomain = self::extractDomainFromAddress($fromHeader);
        $returnPathDomain = '';
        $dkimSigDomain = '';
        foreach ($headers as $h) {
            if ($h['name'] === 'return-path' && $returnPathDomain === '') {
                $returnPathDomain = self::extractDomainFromAddress($h['value']);
            } elseif ($h['name'] === 'dkim-signature' && $dkimSigDomain === '') {
                if (preg_match('/(?:^|[;\s])d\s*=\s*([^;\s]+)/i', $h['value'], $dm)) {
                    $dkimSigDomain = self::normalizeDomainToken($dm[1]);
                }
            }
        }

        $spfInfo   = null; // ['raw' => ..., 'domain' => ...]
        $dkimList  = [];   // list of ['raw' => ..., 'domain' => ...]
        $dmarcInfo = null; // ['raw' => ..., 'domain' => ..., 'action' => ...]

        $authHeaderNames = [
            'authentication-results',
            'authentication-results-original',
            'x-authentication-results',
            'arc-authentication-results',
        ];
        $authHeaders = array_values(array_filter(
            $headers,
            static fn(array $h): bool => in_array($h['name'], $authHeaderNames, true)
        ));

        foreach ($authHeaders as $h) {
            $cleaned = self::stripComments($h['value']);
            $segments = explode(';', $cleaned);

            // Segment 0 is typically authserv-id (no '='), unless omitted (e.g., Exchange/Outlook)
            $startIdx = (count($segments) > 1 && !str_contains($segments[0], '=')) ? 1 : 0;

            $headerDkimEntries = [];
            for ($i = $startIdx; $i < count($segments); $i++) {
                $seg = trim($segments[$i]);
                if ($seg === '') {
                    continue;
                }

                if ($spfInfo === null && preg_match('/\bspf\s*=\s*(pass|fail|softfail|neutral|none|temperror|permerror)\b/i', $seg, $m)) {
                    $spfDom = '';
                    if (preg_match('/\bsmtp\.mailfrom\s*=\s*([^\s;]+)/i', $seg, $dm)) {
                        $spfDom = self::normalizeDomainToken($dm[1]);
                    } elseif (preg_match('/\bsmtp\.helo\s*=\s*([^\s;]+)/i', $seg, $dm)) {
                        $spfDom = self::normalizeDomainToken($dm[1]);
                    }
                    if ($spfDom === '') {
                        $spfDom = $returnPathDomain;
                    }
                    $spfInfo = [
                        'raw'    => strtolower($m[1]),
                        'domain' => $spfDom,
                    ];
                }

                if (preg_match('/\bdkim\s*=\s*(pass|fail|neutral|policy|temperror|permerror|none)\b/i', $seg, $m)) {
                    $dkimDom = '';
                    if (preg_match('/\bheader\.d\s*=\s*([^\s;]+)/i', $seg, $dm)) {
                        $dkimDom = self::normalizeDomainToken($dm[1]);
                    } elseif (preg_match('/\bheader\.i\s*=\s*([^\s;]+)/i', $seg, $dm)) {
                        $dkimDom = self::normalizeDomainToken($dm[1]);
                    }
                    $headerDkimEntries[] = [
                        'raw'    => strtolower($m[1]),
                        'domain' => $dkimDom,
                    ];
                }

                if ($dmarcInfo === null && preg_match('/\bdmarc\s*=\s*(pass|fail|bestguesspass|none|temperror|permerror)\b/i', $seg, $m)) {
                    $dmarcDom = '';
                    if (preg_match('/\bheader\.from\s*=\s*([^\s;]+)/i', $seg, $dm)) {
                        $dmarcDom = self::normalizeDomainToken($dm[1]);
                    }
                    $action = '';
                    if (preg_match('/\baction\s*=\s*([^\s;]+)/i', $seg, $am)) {
                        $action = strtolower(trim($am[1], " \t\"'()"));
                    }
                    $dmarcInfo = [
                        'raw'    => strtolower($m[1]),
                        'domain' => $dmarcDom,
                        'action' => $action,
                    ];
                }
            }

            if (empty($dkimList) && !empty($headerDkimEntries)) {
                $dkimList = $headerDkimEntries;
            }
        }

        // Fallback to Received-SPF if SPF wasn't in Authentication-Results
        if ($spfInfo === null) {
            foreach ($headers as $h) {
                if ($h['name'] === 'received-spf' || $h['name'] === 'x-received-spf') {
                    if (preg_match('/^\s*(pass|fail|softfail|neutral|none|temperror|permerror)\b/i', $h['value'], $m)) {
                        $spfDom = '';
                        if (preg_match('/\benvelope-from\s*=\s*<?([^>\s;]+)>?/i', $h['value'], $dm)) {
                            $spfDom = self::normalizeDomainToken($dm[1]);
                        }
                        if ($spfDom === '') {
                            $spfDom = $returnPathDomain;
                        }
                        $spfInfo = [
                            'raw'    => strtolower($m[1]),
                            'domain' => $spfDom,
                        ];
                    }
                    break;
                }
            }
        }

        // Fallback to SpamAssassin / Rspamd summary headers if mechanisms are still missing
        foreach ($headers as $h) {
            if (!in_array($h['name'], ['x-spam-status', 'x-spam-report', 'x-spamd-result'], true)) {
                continue;
            }
            $val = $h['value'];
            if ($spfInfo === null) {
                if (preg_match('/\b(?:SPF_FAIL|SPF_HELO_FAIL|R_SPF_FAIL)\b/i', $val)) {
                    $spfInfo = ['raw' => 'fail', 'domain' => $returnPathDomain];
                } elseif (preg_match('/\b(?:SPF_SOFTFAIL|SPF_HELO_SOFTFAIL|R_SPF_SOFTFAIL)\b/i', $val)) {
                    $spfInfo = ['raw' => 'softfail', 'domain' => $returnPathDomain];
                } elseif (preg_match('/\b(?:SPF_PASS|R_SPF_ALLOW)\b/i', $val)) {
                    $spfInfo = ['raw' => 'pass', 'domain' => $returnPathDomain];
                } elseif (preg_match('/\b(?:SPF_NONE|SPF_NEUTRAL|R_SPF_NEUTRAL)\b/i', $val)) {
                    $spfInfo = ['raw' => 'none', 'domain' => $returnPathDomain];
                }
            }
            if (empty($dkimList)) {
                if (preg_match('/\b(?:T_DKIM_INVALID|DKIM_INVALID|R_DKIM_REJECT)\b/i', $val)) {
                    $dkimList[] = ['raw' => 'fail', 'domain' => $dkimSigDomain];
                } elseif (preg_match('/\b(?:DKIM_VALID_AU|DKIM_VALID|R_DKIM_ALLOW)\b/i', $val)) {
                    $dkimList[] = ['raw' => 'pass', 'domain' => $dkimSigDomain];
                }
            }
            if ($dmarcInfo === null) {
                if (preg_match('/\b(?:DMARC_FAIL|DMARC_REJECT|DMARC_QUARANTINE|DMARC_POLICY_REJECT|DMARC_POLICY_QUARANTINE)\b/i', $val)) {
                    $dmarcInfo = ['raw' => 'fail', 'domain' => $fromDomain, 'action' => ''];
                } elseif (preg_match('/\b(?:DMARC_PASS|DMARC_POLICY_ALLOW)\b/i', $val)) {
                    $dmarcInfo = ['raw' => 'pass', 'domain' => $fromDomain, 'action' => ''];
                }
            }
        }

        $hasAuth = ($spfInfo !== null || !empty($dkimList) || $dmarcInfo !== null);

        // If no verification header exists at all, still return 3 neutral/orange indicators for UI display
        if (!$hasAuth) {
            $dkimDetail = $dkimSigDomain !== ''
                ? "DKIM-Signature present (d={$dkimSigDomain}), but no verification header was recorded by the receiving mail server."
                : 'No DKIM verification result recorded in message headers.';
            return [
                'has_auth'           => false,
                'banner_level'       => 'none',
                'banner_title'       => '',
                'banner_description' => '',
                'from_domain'        => $fromDomain,
                'checks'             => [
                    'spf' => [
                        'label'  => 'SPF',
                        'status' => 'warn',
                        'raw'    => 'missing',
                        'text'   => 'SPF —',
                        'detail' => 'No SPF verification header recorded by the receiving mail server.',
                    ],
                    'dkim' => [
                        'label'  => 'DKIM',
                        'status' => 'warn',
                        'raw'    => $dkimSigDomain !== '' ? 'unverified' : 'missing',
                        'text'   => $dkimSigDomain !== '' ? 'DKIM ?' : 'DKIM —',
                        'detail' => $dkimDetail,
                    ],
                    'dmarc' => [
                        'label'  => 'DMARC',
                        'status' => 'warn',
                        'raw'    => 'missing',
                        'text'   => 'DMARC —',
                        'detail' => 'No DMARC verification header recorded by the receiving mail server.',
                    ],
                ],
            ];
        }

        $dmarcRaw = $dmarcInfo['raw'] ?? null;
        $issuesDanger = [];
        $issuesWarn   = [];

        // ── Evaluate DMARC ───────────────────────────────────────────────────
        $dmarcCheck = [
            'label'  => 'DMARC',
            'status' => 'warn',
            'raw'    => $dmarcRaw ?? 'missing',
            'text'   => 'DMARC —',
            'detail' => 'No DMARC result recorded in message headers.',
        ];
        if ($dmarcInfo !== null) {
            $raw = $dmarcInfo['raw'];
            $dom = $dmarcInfo['domain'];
            $domNote = $dom !== '' ? " (header.from={$dom})" : '';
            if ($raw === 'pass') {
                if ($dom !== '' && $fromDomain !== '' && !self::domainsAlign($fromDomain, $dom)) {
                    $dmarcCheck['status'] = 'fail';
                    $dmarcCheck['text']   = 'DMARC mismatch';
                    $dmarcCheck['detail'] = "DMARC header.from ({$dom}) does not match From domain ({$fromDomain}).";
                    $issuesDanger[] = "DMARC domain ({$dom}) does not match the visible sender domain ({$fromDomain}).";
                } else {
                    $dmarcCheck['status'] = 'pass';
                    $dmarcCheck['text']   = 'DMARC ✓';
                    $dmarcCheck['detail'] = 'DMARC passed' . $domNote . '.';
                }
            } elseif ($raw === 'fail') {
                $dmarcCheck['status'] = 'fail';
                $dmarcCheck['text']   = 'DMARC fail';
                $dmarcCheck['detail'] = 'DMARC failed' . $domNote . '.';
                $issuesDanger[] = 'DMARC authentication failed (domain alignment or authentication did not pass).';
            } else {
                $dmarcCheck['status'] = 'warn';
                $dmarcCheck['text']   = 'DMARC ' . $raw;
                $dmarcCheck['detail'] = 'DMARC status: ' . $raw . $domNote . '.';
                if (in_array($raw, ['temperror', 'permerror'], true)) {
                    $issuesWarn[] = "DMARC verification returned {$raw}.";
                }
            }
        }

        // ── Evaluate DKIM ────────────────────────────────────────────────────
        $dkimCheck = [
            'label'  => 'DKIM',
            'status' => 'warn',
            'raw'    => $dkimSigDomain !== '' ? 'unverified' : 'missing',
            'text'   => $dkimSigDomain !== '' ? 'DKIM ?' : 'DKIM —',
            'detail' => $dkimSigDomain !== ''
                ? "DKIM-Signature present (d={$dkimSigDomain}), but no DKIM verification result was recorded."
                : 'No DKIM result recorded in message headers.',
        ];
        if (!empty($dkimList)) {
            $chosen = self::pickBestDkim($dkimList, $fromDomain);
            $raw    = $chosen['raw'];
            $dom    = $chosen['domain'];
            $domNote = $dom !== '' ? " (header.d={$dom})" : '';
            $dkimCheck['raw'] = $raw;

            if ($raw === 'pass') {
                if ($dom !== '' && $fromDomain !== '' && !self::domainsAlign($fromDomain, $dom)) {
                    $dkimCheck['status'] = 'warn';
                    $dkimCheck['text']   = 'DKIM mismatch';
                    $dkimCheck['detail'] = "DKIM passed for {$dom}, which does not match From domain ({$fromDomain}).";
                    if ($dmarcRaw !== 'pass') {
                        $issuesWarn[] = "DKIM signing domain ({$dom}) does not match sender domain ({$fromDomain}).";
                    }
                } else {
                    $dkimCheck['status'] = 'pass';
                    $dkimCheck['text']   = 'DKIM ✓';
                    $dkimCheck['detail'] = 'DKIM signature passed' . $domNote . '.';
                }
            } elseif ($raw === 'fail') {
                $dkimCheck['status'] = 'fail';
                $dkimCheck['text']   = 'DKIM fail';
                $dkimCheck['detail'] = 'DKIM signature verification failed' . $domNote . '.';
                $issuesDanger[] = 'DKIM signature failed verification.';
            } else {
                $dkimCheck['status'] = 'warn';
                $dkimCheck['text']   = 'DKIM ' . $raw;
                $dkimCheck['detail'] = 'DKIM status: ' . $raw . $domNote . '.';
                if (in_array($raw, ['temperror', 'permerror', 'policy'], true)) {
                    $issuesWarn[] = "DKIM verification returned {$raw}.";
                }
            }
        }

        // ── Evaluate SPF ─────────────────────────────────────────────────────
        $spfCheck = [
            'label'  => 'SPF',
            'status' => 'warn',
            'raw'    => 'missing',
            'text'   => 'SPF —',
            'detail' => 'No SPF result recorded in message headers.',
        ];
        if ($spfInfo !== null) {
            $raw = $spfInfo['raw'];
            $dom = $spfInfo['domain'];
            $domNote = $dom !== '' ? " ({$dom})" : '';
            $spfCheck['raw'] = $raw;

            if ($raw === 'pass') {
                $spfAligned = ($dom === '' || $fromDomain === '' || self::domainsAlign($fromDomain, $dom));
                if (!$spfAligned && $dmarcRaw !== 'pass') {
                    if ($dkimCheck['status'] === 'pass') {
                        $spfCheck['status'] = 'pass';
                        $spfCheck['text']   = 'SPF ✓';
                        $spfCheck['detail'] = "SPF passed for envelope domain {$dom} (DKIM matches {$fromDomain}).";
                    } else {
                        $spfCheck['status'] = 'warn';
                        $spfCheck['text']   = 'SPF mismatch';
                        $spfCheck['detail'] = "SPF passed for {$dom}, which does not match From domain ({$fromDomain}).";
                        $issuesWarn[] = "SPF envelope domain ({$dom}) does not match sender domain ({$fromDomain}).";
                    }
                } else {
                    $spfCheck['status'] = 'pass';
                    $spfCheck['text']   = 'SPF ✓';
                    $spfCheck['detail'] = 'SPF passed' . $domNote . '.';
                }
            } elseif ($raw === 'fail') {
                $spfCheck['status'] = 'fail';
                $spfCheck['text']   = 'SPF fail';
                $spfCheck['detail'] = 'SPF check failed' . $domNote . '.';
                $issuesDanger[] = 'SPF check failed (sending server is not authorized for this domain).';
            } elseif ($raw === 'softfail') {
                $spfCheck['status'] = 'warn';
                $spfCheck['text']   = 'SPF softfail';
                $spfCheck['detail'] = 'SPF returned softfail' . $domNote . '.';
                $issuesWarn[] = 'SPF returned softfail (sending server is not explicitly authorized).';
            } else {
                $spfCheck['status'] = 'warn';
                $spfCheck['text']   = 'SPF ' . $raw;
                $spfCheck['detail'] = 'SPF status: ' . $raw . $domNote . '.';
                if (in_array($raw, ['temperror', 'permerror'], true)) {
                    $issuesWarn[] = "SPF verification returned {$raw}.";
                }
            }
        }

        // If neither SPF nor DKIM passed (both missing/none/neutral), warn about unauthenticated sender
        if (empty($issuesDanger) && empty($issuesWarn)) {
            if ($spfCheck['status'] !== 'pass' && $dkimCheck['status'] !== 'pass' && $dmarcCheck['status'] !== 'pass') {
                $issuesWarn[] = 'Neither SPF nor DKIM authentication passed for this message.';
            }
        }

        $bannerLevel = 'none';
        $bannerTitle = '';
        $bannerDesc  = '';
        $allIssues   = array_merge($issuesDanger, $issuesWarn);

        if (!empty($issuesDanger)) {
            $bannerLevel = 'danger';
            $bannerTitle = 'Potential Spoofing Warning';
            $bannerDesc  = implode(' ', $allIssues);
        } elseif (!empty($issuesWarn)) {
            $bannerLevel = 'warning';
            $bannerTitle = 'Authentication Caution';
            $bannerDesc  = implode(' ', $allIssues);
        }

        return [
            'has_auth'           => true,
            'banner_level'       => $bannerLevel,
            'banner_title'       => $bannerTitle,
            'banner_description' => $bannerDesc,
            'from_domain'        => $fromDomain,
            'checks'             => [
                'spf'   => $spfCheck,
                'dkim'  => $dkimCheck,
                'dmarc' => $dmarcCheck,
            ],
        ];
    }

    /**
     * Pick the most representative DKIM entry (aligned pass > pass > fail > warn).
     */
    private static function pickBestDkim(array $entries, string $fromDomain): array
    {
        foreach ($entries as $e) {
            if ($e['raw'] === 'pass' && $e['domain'] !== '' && $fromDomain !== '' && self::domainsAlign($fromDomain, $e['domain'])) {
                return $e;
            }
        }
        foreach ($entries as $e) {
            if ($e['raw'] === 'pass') {
                return $e;
            }
        }
        foreach ($entries as $e) {
            if ($e['raw'] === 'fail') {
                return $e;
            }
        }
        return $entries[0];
    }

    /**
     * Parse raw RFC 822 headers into an ordered list of unfolded ['name' => ..., 'value' => ...].
     */
    private static function parseHeaderList(string $rawHeaders): array
    {
        if ($rawHeaders === '') {
            return [];
        }
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawHeaders);
        // Stop at blank line if full message was passed
        $parts = explode("\n\n", ltrim($normalized, "\n"), 2);
        $headerBlock = $parts[0] ?? '';
        $unfolded = preg_replace("/\n[ \t]+/", ' ', $headerBlock);
        $lines = explode("\n", (string) $unfolded);

        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$name, $val] = explode(':', $line, 2);
            $out[] = [
                'name'  => strtolower(trim($name)),
                'value' => trim($val),
            ];
        }
        return $out;
    }

    private static function stripComments(string $val): string
    {
        $prev = null;
        $curr = $val;
        for ($i = 0; $i < 3 && $curr !== $prev; $i++) {
            $prev = $curr;
            $curr = (string) preg_replace('/\([^()]*\)/', ' ', $curr);
        }
        return $curr;
    }

    private static function extractDomainFromAddress(string $address): string
    {
        if ($address === '') {
            return '';
        }
        if (preg_match('/<[^<>@\s]+@([^<>@\s]+)>/', $address, $m)) {
            return self::normalizeDomainToken($m[1]);
        }
        if (preg_match('/[A-Za-z0-9._%+\-]+@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/', $address, $m)) {
            return self::normalizeDomainToken($m[1]);
        }
        return '';
    }

    private static function normalizeDomainToken(string $token): string
    {
        $t = trim($token, " \t\r\n<>\"'();");
        if (str_contains($t, '@')) {
            $parts = explode('@', $t);
            $t = (string) end($parts);
        }
        $t = strtolower(rtrim($t, '.'));
        if ($t === '' || !preg_match('/^[a-z0-9.\-]+$/', $t)) {
            return '';
        }
        return $t;
    }

    /**
     * Check relaxed organizational domain alignment (RFC 7489 style).
     */
    private static function domainsAlign(string $a, string $b): bool
    {
        $a = strtolower(rtrim($a, '.'));
        $b = strtolower(rtrim($b, '.'));
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        return self::organizationalDomain($a) === self::organizationalDomain($b);
    }

    private static function organizationalDomain(string $domain): string
    {
        $labels = array_values(array_filter(explode('.', $domain), static fn(string $l): bool => $l !== ''));
        $count = count($labels);
        if ($count <= 2) {
            return implode('.', $labels);
        }
        $tld = $labels[$count - 1];
        $sld = $labels[$count - 2];
        if (strlen($tld) === 2 && in_array($sld, self::MULTI_LABEL_SLDS, true) && $count >= 3) {
            return implode('.', array_slice($labels, -3));
        }
        return implode('.', array_slice($labels, -2));
    }
}
