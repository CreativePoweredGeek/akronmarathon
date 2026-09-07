<?php

namespace BoldMinded\DataGrab\Service;

use Exception;

/**
 * Validates remote fetch URLs to mitigate SSRF and local file disclosure.
 *
 * Only http/https URLs are permitted, and the resolved host(s) must not fall
 * within private, loopback, link-local, or otherwise reserved IP ranges. The
 * checks can be relaxed for trusted intranet feeds via config flags.
 */
class UrlGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function __construct(
        private ?Logger $logger = null,
    ) {}

    /**
     * Validate a URL prior to fetching it. Throws if the URL is not allowed.
     * @throws Exception
     */
    public function assertAllowed(string $url): void
    {
        $url = trim($url);

        if ($url === '') {
            $this->fail('Empty fetch URL is not allowed.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === '' || !in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            $this->fail(sprintf('Disallowed URL scheme "%s". Only http and https are permitted.', $scheme));
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            $this->fail('Could not determine the host of the fetch URL.');
        }

        // Permit private/reserved targets only when explicitly opted in.
        if (bool_config_item('datagrab_allow_private_urls')) {
            return;
        }

        // Allow trusted hosts (the site's own host, plus any configured in
        // datagrab_allowed_hosts) to bypass the private/reserved IP block. This
        // keeps local development domains and same-host imports working.
        if (self::isAllowedHost($host)) {
            return;
        }

        foreach ($this->resolveHost($host) as $ip) {
            if (!self::isPublicIp($ip)) {
                $this->fail(sprintf('Fetch target resolves to a private or reserved address (%s).', $ip));
            }
        }
    }

    /**
     * Log the reason, then throw. Centralizes the "log before throwing" behavior.
     */
    private function fail(string $message): void
    {
        $this->logger?->log($message);

        throw new Exception($message);
    }

    /**
     * Whether a request-supplied (untrusted) value is permitted to drive a fetch.
     * Local filesystem paths and non-http schemes are rejected outright.
     */
    public static function isSafeRequestFilename(string $fileName): bool
    {
        $fileName = trim($fileName);

        if ($fileName === '' || $fileName[0] === '/' || $fileName[0] === '\\') {
            return false;
        }

        $scheme = strtolower((string) parse_url($fileName, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    private static function isAllowedHost(string $host): bool
    {
        $host = strtolower($host);

        foreach (self::allowedHosts() as $pattern) {
            $pattern = strtolower(trim($pattern));

            if ($pattern === '') {
                continue;
            }

            // Wildcard subdomain match, e.g. "*.ddev.site" matches "myapp.ddev.site".
            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1); // ".ddev.site"
                if ($host === substr($suffix, 1) || str_ends_with($host, $suffix)) {
                    return true;
                }
                continue;
            }

            if ($host === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function allowedHosts(): array
    {
        $hosts = ee()->config->item('datagrab_allowed_hosts') ?: [];

        if (!is_array($hosts)) {
            $hosts = [$hosts];
        }

        // The site's own host is always trusted so same-host / {base_url} imports
        // work regardless of environment.
        $baseHost = parse_url((string) ee()->config->item('base_url'), PHP_URL_HOST);

        if ($baseHost) {
            $hosts[] = $baseHost;
        }

        return $hosts;
    }

    /**
     * @return string[]
     */
    private function resolveHost(string $host): array
    {
        // Already a literal IP.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if (empty($ips)) {
            $this->fail(sprintf('Could not resolve fetch host "%s".', $host));
        }

        return $ips;
    }

    private static function isPublicIp(string $ip): bool
    {
        // The address must be public both as written AND, if it embeds an IPv4
        // address (IPv4-mapped/compatible/NAT64/6to4 — in any textual rendering),
        // that embedded IPv4 must also be public. PHP's NO_PRIV/NO_RES flags only
        // inspect the literal form, so an address like ::ffff:169.254.169.254,
        // fc00::1.2.3.4, or 64:ff9b::a9fe:a9fe could otherwise route to a private
        // target while reporting itself public.
        if (!self::passesRangeCheck($ip)) {
            return false;
        }

        $embedded = self::embeddedIpv4($ip);

        if ($embedded !== null && !self::passesRangeCheck($embedded)) {
            return false;
        }

        return true;
    }

    private static function passesRangeCheck(string $ip): bool
    {
        // Normalize through the packed form first. filter_var misclassifies IPv6
        // addresses written with a mixed dotted-quad tail (e.g. fc00::1.2.3.4 is
        // ULA but reports as public until collapsed to fc00::102:304).
        $packed = @inet_pton($ip);

        if ($packed !== false) {
            $ip = inet_ntop($packed);
        }

        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Extract an IPv4 address embedded in an IPv6 address, regardless of textual
     * rendering, by inspecting the packed 16-byte form. Covers IPv4-mapped
     * (::ffff:0:0/96), IPv4-compatible (::/96), NAT64 (64:ff9b::/96) and 6to4
     * (2002::/16) embeddings.
     */
    private static function embeddedIpv4(string $ip): ?string
    {
        if (strpos($ip, ':') === false) {
            return null;
        }

        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        // 6to4: 2002:V4V4:V4V4::/16 — the IPv4 sits in bytes 2-5.
        if ($packed[0] === "\x20" && $packed[1] === "\x02") {
            return inet_ntop(substr($packed, 2, 4));
        }

        // IPv4-mapped (::ffff:0:0/96), IPv4-compatible (::/96), NAT64 (64:ff9b::/96):
        // the IPv4 occupies the last 4 bytes.
        $prefix = substr($packed, 0, 12);
        $isMapped = $prefix === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
        $isCompatible = $prefix === str_repeat("\x00", 12);
        $isNat64 = substr($packed, 0, 4) === "\x00\x64\xff\x9b";

        if ($isMapped || $isCompatible || $isNat64) {
            $v4 = inet_ntop(substr($packed, 12, 4));

            // ::/96 compatible range includes ::1 and :: which are not IPv4 embeddings.
            if ($isCompatible && in_array($v4, ['0.0.0.0', '0.0.0.1'], true)) {
                return null;
            }

            return $v4;
        }

        return null;
    }
}
