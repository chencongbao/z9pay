<?php

namespace App\Services\Common;

class DomainConfigService
{
    public function apiAllowedHosts(): array
    {
        return $this->hostsFromValues([
            config('app.url'),
            config('default.cashier_domain'),
            config('default.api_domain'),
            config('default.api_extra_domains'),
            config('admin.route.domain'),
        ]);
    }

    public function cashierLocalRootDomains(?string $requestHost = null): array
    {
        return $this->rootDomainsFromValues([
            $requestHost,
            config('default.cashier_domain'),
            config('default.api_domain'),
        ]);
    }

    public function isHostAllowed(string $host, array $allowed): bool
    {
        $host = $this->normalizeHost($host);
        foreach ($allowed as $pattern) {
            $pattern = $this->normalizeHost($pattern);
            if ($pattern === '') {
                continue;
            }

            if (str_starts_with($pattern, '*.') && strlen($pattern) > 2) {
                $base = substr($pattern, 2);
                if ($this->endsWith($host, '.' . $base) && substr_count($host, '.') >= substr_count($base, '.') + 1) {
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

    public function rootDomain(string $host): string
    {
        $host = $this->normalizeHost($host);
        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return $host;
        }

        return implode('.', array_slice($parts, -2));
    }

    public function hostsFromValues(array $values): array
    {
        $hosts = [];
        foreach ($values as $value) {
            foreach ($this->splitDomains($value) as $domain) {
                $host = $this->normalizeHost($domain);
                if ($host !== '') {
                    $hosts[] = $host;
                }
            }
        }

        return array_values(array_unique($hosts));
    }

    public function rootDomainsFromValues(array $values): array
    {
        $domains = [];
        foreach ($this->hostsFromValues($values) as $host) {
            $domains[] = $this->rootDomain($host);
        }

        return array_values(array_unique(array_filter($domains)));
    }

    public function splitDomains($domains): array
    {
        if (is_array($domains)) {
            return $domains;
        }

        return array_filter(array_map('trim', preg_split('/[,，]+/', (string) $domains)));
    }

    private function normalizeHost(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $host = parse_url($value, PHP_URL_HOST) ?: $value;

        return strtolower(trim($host));
    }

    private function endsWith(string $haystack, string $needle): bool
    {
        $len = strlen($needle);

        return $len === 0 ? true : (substr($haystack, -$len) === $needle);
    }
}
