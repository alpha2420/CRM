<?php

namespace App\Webhooks;

use Closure;

/**
 * Keeps webhooks pointed at the public internet. Without this, a webhook
 * URL could make our server call into private networks (SSRF): the URL
 * must be https, and every address its host resolves to must be public.
 * Checked when the webhook is saved and again before each delivery, which
 * then connects to the checked address (so DNS can't be switched between
 * the check and the request).
 */
final class UrlGuard
{
    /** @var Closure(string): list<string> */
    private Closure $resolve;

    /**
     * @param  (Closure(string): list<string>)|null  $resolve  host => IP addresses (replaceable in tests)
     */
    public function __construct(?Closure $resolve = null)
    {
        $this->resolve = $resolve ?? static function (string $host): array {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

            return array_values(array_filter(array_map(fn (array $r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
        };
    }

    /**
     * Why the URL can't be used, or null when it's fine.
     */
    public function problem(string $url): ?string
    {
        return $this->inspect($url)[1];
    }

    /**
     * The checked public address to connect to, or null if the URL isn't allowed.
     */
    public function safeAddress(string $url): ?string
    {
        [$address, $problem] = $this->inspect($url);

        return $problem === null ? $address : null;
    }

    /**
     * @return array{0: ?string, 1: ?string} [first public address, problem]
     */
    private function inspect(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return [null, 'Use a full https:// address without a username or password.'];
        }

        if (isset($parts['port']) && (int) $parts['port'] !== 443) {
            return [null, 'Only the standard HTTPS port (443) is allowed.'];
        }

        $addresses = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) ? [trim($host, '[]')] : ($this->resolve)($host);

        if ($addresses === []) {
            return [null, 'That address could not be found.'];
        }

        foreach ($addresses as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                return [null, 'That address points to a private or local network.'];
            }
        }

        return [$addresses[0], null];
    }
}
