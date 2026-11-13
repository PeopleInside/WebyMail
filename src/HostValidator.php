<?php
declare(strict_types=1);

/**
 * Validates mail server hosts and ports to prevent SSRF and IMAP/SMTP
 * connection-string/mailbox injection attacks.
 */
class HostValidator
{
    /**
     * Validate a mail server hostname/IP and port, resolving DNS records and
     * blocking private, loopback, link-local, metadata, and reserved ranges
     * unless `allow_private_mail_hosts` is explicitly enabled in config
     * (link-local/metadata ranges are always blocked).
     *
     * @throws InvalidArgumentException If syntax/port/network rules fail.
     */
    public static function validateHostAndPort(string $host, int $port, ?bool $allowPrivate = null): string
    {
        $host = trim($host);
        if ($host === '') {
            throw new InvalidArgumentException('Server host cannot be empty.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Server port must be between 1 and 65535.');
        }

        // Reject control characters, whitespace, and IMAP/URI delimiter chars
        // that could alter {host:port/flags} or stream_socket_client URIs.
        if (preg_match('/[\x00-\x20\x7F{}\/\\\\@?#;,]/', $host)) {
            throw new InvalidArgumentException('Server host contains invalid characters.');
        }

        $cleanHost = $host;
        $isIpv6Literal = false;
        if (str_starts_with($host, '[') || str_ends_with($host, ']')) {
            if (!str_starts_with($host, '[') || !str_ends_with($host, ']')) {
                throw new InvalidArgumentException('Malformed IPv6 host syntax.');
            }
            $inner = substr($host, 1, -1);
            if (filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new InvalidArgumentException('Invalid IPv6 host address.');
            }
            $cleanHost = $inner;
            $isIpv6Literal = true;
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $isIpv6Literal = true;
            $host = '[' . $host . ']';
        } elseif (str_contains($host, ':')) {
            throw new InvalidArgumentException('Host must not contain a port or colon.');
        }

        if ($allowPrivate === null) {
            $allowPrivate = (bool) Config::get('allow_private_mail_hosts', false);
        }

        // 1. Literal IPv4 or IPv6
        if ($isIpv6Literal || filter_var($cleanHost, FILTER_VALIDATE_IP) !== false) {
            if (!self::isAllowedIp($cleanHost, $allowPrivate)) {
                throw new InvalidArgumentException('Connections to private, loopback, or restricted IP addresses are not permitted.');
            }
            return $host;
        }

        // 2. Hostname validation
        $asciiHost = $cleanHost;
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7E]/', $cleanHost)) {
            $converted = idn_to_ascii($cleanHost, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($converted !== false) {
                $asciiHost = $converted;
            }
        }

        if (str_ends_with($asciiHost, '.')) {
            throw new InvalidArgumentException('Trailing dot in server hostname is not allowed.');
        }

        if (
            strlen($asciiHost) > 253
            || filter_var($asciiHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            throw new InvalidArgumentException('Invalid mail server hostname.');
        }

        if (!$allowPrivate && strcasecmp($asciiHost, 'localhost') === 0) {
            throw new InvalidArgumentException('Connections to localhost are not permitted.');
        }

        // 3. Resolve DNS records (A & AAAA) and verify every returned IP
        $ips = self::resolveHostIps($asciiHost);
        if (empty($ips)) {
            throw new InvalidArgumentException('Unable to resolve mail server hostname.');
        }

        foreach ($ips as $ip) {
            if (!self::isAllowedIp($ip, $allowPrivate)) {
                throw new InvalidArgumentException('Mail server resolves to a restricted network address.');
            }
        }

        return $host;
    }

    /**
     * Resolve IPv4 and IPv6 addresses for a validated hostname.
     *
     * @return string[]
     */
    private static function resolveHostIps(string $hostname): array
    {
        $ips = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $rec) {
                    if (!empty($rec['ip']) && filter_var($rec['ip'], FILTER_VALIDATE_IP)) {
                        $ips[] = $rec['ip'];
                    } elseif (!empty($rec['ipv6']) && filter_var($rec['ipv6'], FILTER_VALIDATE_IP)) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }

        if (empty($ips)) {
            $v4 = @gethostbynamel($hostname);
            if (is_array($v4)) {
                foreach ($v4 as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Check whether an IP address is safe to connect to.
     * Cloud metadata / link-local ranges (169.254.0.0/16, fe80::/10, 0.0.0.0/8)
     * are unconditionally blocked even if allow_private_mail_hosts is enabled.
     */
    public static function isAllowedIp(string $ip, bool $allowPrivate = false): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        // Handle IPv4-mapped IPv6 addresses (::ffff:a.b.c.d) and 6to4/compatible
        if (strlen($packed) === 16) {
            $v4Prefix = str_repeat("\x00", 10) . "\xff\xff";
            $compatPrefix = str_repeat("\x00", 12);
            if (str_starts_with($packed, $v4Prefix) || str_starts_with($packed, $compatPrefix)) {
                $mappedV4 = @inet_ntop(substr($packed, 12, 4));
                if ($mappedV4 === false) {
                    return false;
                }
                return self::isAllowedIp($mappedV4, $allowPrivate);
            }
        }

        // Unconditionally block link-local / metadata / unspecified / multicast
        $alwaysBlocked = [
            '0.0.0.0/8',       // Current network
            '169.254.0.0/16',  // Link-local / Cloud metadata (AWS/GCP/Azure)
            '224.0.0.0/4',     // Multicast
            '240.0.0.0/4',     // Reserved / Broadcast
            '::/128',          // Unspecified
            'fe80::/10',       // IPv6 link-local
            'ff00::/8',        // IPv6 multicast
        ];
        foreach ($alwaysBlocked as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return false;
            }
        }

        if ($allowPrivate) {
            return true;
        }

        // Enforce strict public-only IP check + extra CGNAT/benchmarking ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $extraPrivateCidrs = [
            '100.64.0.0/10',   // Carrier-grade NAT (RFC 6598)
            '192.0.0.0/24',    // IETF Protocol Assignments
            '192.0.2.0/24',    // TEST-NET-1
            '198.18.0.0/15',   // Network benchmark tests
            '198.51.100.0/24', // TEST-NET-2
            '203.0.113.0/24',  // TEST-NET-3
            '::1/128',         // IPv6 loopback
            'fc00::/7',        // IPv6 unique local address
            '100::/64',        // Discard-only
            '2001:db8::/32',   // Documentation
        ];
        foreach ($extraPrivateCidrs as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            $a = @inet_pton($ip);
            $b = @inet_pton($cidr);
            return $a !== false && $b !== false && $a === $b;
        }

        [$subnet, $prefixStr] = explode('/', $cidr, 2);
        if (!ctype_digit($prefixStr)) {
            return false;
        }
        $prefix = (int) $prefixStr;

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        if ($prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remBits   = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remBits > 0) {
            $mask = (0xFF << (8 - $remBits)) & 0xFF;
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
