<?php

namespace App\Services\Partner;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

class WebhookDestination
{
    public function host(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || ($parts['port'] ?? 443) !== 443
            || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
            || ! in_array($host, config('partner.webhook_hosts', []), true)) {
            throw ValidationException::withMessages(['url' => 'Use HTTPS port 443 on an operator-approved webhook hostname, without credentials or fragments.']);
        }

        return $host;
    }

    public function addresses(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }

    public function options(string $url): array
    {
        $host = $this->host($url);
        $addresses = $this->addresses($host);
        if ($addresses === []) {
            throw new \RuntimeException('Webhook DNS unavailable.');
        }
        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::checkIp($ip, ['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'])) {
                throw new \RuntimeException('Webhook must resolve only to public IPv4 addresses.');
            }
        }
        if (! extension_loaded('curl')) {
            throw new \RuntimeException('Webhook delivery requires ext-curl.');
        }

        return ['allow_redirects' => false, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$addresses[0]]]];
    }
}
