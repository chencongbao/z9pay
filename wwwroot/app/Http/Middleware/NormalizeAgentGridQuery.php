<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeAgentGridQuery
{
    public function handle(Request $request, Closure $next)
    {
        $rules = $this->rulesForRequest($request);
        if (empty($rules)) {
            return $next($request);
        }

        $request->query->replace($this->normalizeArray($request->query->all(), $rules));

        return $next($request);
    }

    public function normalizeArray(array $query, array $rules): array
    {
        foreach (array_unique(array_merge((array)($rules['string'] ?? []), (array)($rules['scalar'] ?? []))) as $key) {
            $this->normalizeString($query, $key);
        }

        foreach ((array)($rules['int'] ?? []) as $key) {
            $this->normalizeInteger($query, $key);
        }

        foreach ((array)($rules['decimal'] ?? []) as $key) {
            $this->normalizeDecimal($query, $key);
        }

        foreach ((array)($rules['range'] ?? []) as $key) {
            $this->normalizeRange($query, $key);
        }

        foreach ((array)($rules['date_scalar'] ?? []) as $key) {
            $this->normalizeDateScalar($query, $key);
        }

        foreach ($query as $key => $value) {
            if ($this->isSortKey($key)) {
                $this->normalizeSort($query, $key, $value, (array)($rules['sort'] ?? []));
            }
        }

        return $query;
    }

    private function rulesForRequest(Request $request): array
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $path = trim($request->path(), '/');
        if ($prefix !== '' && !str_starts_with($path, $prefix . '/')) {
            return [];
        }

        $relativePath = $prefix === '' ? $path : substr($path, strlen($prefix) + 1);
        $resource = explode('/', $relativePath, 2)[0] ?? '';

        return (array) config("agent-admin.grid.request_rules.{$resource}", []);
    }

    private function normalizeString(array &$query, string $key): void
    {
        if (!array_key_exists($key, $query)) {
            return;
        }

        if (!is_scalar($query[$key])) {
            unset($query[$key]);
            return;
        }

        $value = trim((string)$query[$key]);
        if ($value !== '' && (mb_strlen($value) > 191 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value))) {
            unset($query[$key]);
            return;
        }

        $query[$key] = $value;
    }

    private function normalizeInteger(array &$query, string $key): void
    {
        if (!array_key_exists($key, $query)) {
            return;
        }

        if (!is_scalar($query[$key]) || !preg_match('/^\d+$/', trim((string)$query[$key]))) {
            unset($query[$key]);
            return;
        }

        $query[$key] = trim((string)$query[$key]);
    }

    private function normalizeDecimal(array &$query, string $key): void
    {
        if (!array_key_exists($key, $query)) {
            return;
        }

        $value = trim((string)(is_scalar($query[$key]) ? $query[$key] : ''));
        if ($value === '' || !preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            unset($query[$key]);
            return;
        }

        $query[$key] = $value;
    }

    private function normalizeRange(array &$query, string $key): void
    {
        if (!array_key_exists($key, $query)) {
            return;
        }

        $value = $query[$key];
        if (!is_array($value)) {
            unset($query[$key]);
            return;
        }

        $range = [];
        $invalid = false;
        foreach (['start', 'end'] as $bound) {
            if (!array_key_exists($bound, $value)) {
                continue;
            }

            if (!is_scalar($value[$bound]) || !$this->isDateLike((string)$value[$bound])) {
                $invalid = true;
                break;
            }

            $range[$bound] = $value[$bound];
        }

        if ($invalid || empty($range) || $this->isReversedRange($range)) {
            unset($query[$key]);
            return;
        }

        $query[$key] = $range;
    }

    private function normalizeDateScalar(array &$query, string $key): void
    {
        if (!array_key_exists($key, $query)) {
            return;
        }

        if (!is_scalar($query[$key]) || !$this->isDateLike((string)$query[$key])) {
            unset($query[$key]);
        }
    }

    private function normalizeSort(array &$query, string $key, mixed $value, array $allowedColumns): void
    {
        $valid = is_array($value)
            && empty(array_diff(array_keys($value), ['column', 'type']))
            && is_string($value['column'] ?? null)
            && is_string($value['type'] ?? null)
            && in_array($value['column'], $allowedColumns, true)
            && in_array($value['type'], ['asc', 'desc'], true);

        if (!$valid) {
            unset($query[$key]);
        }
    }

    private function isSortKey(mixed $key): bool
    {
        return is_string($key) && ($key === '_sort' || str_ends_with($key, '__sort'));
    }

    private function isDateLike(string $value): bool
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/', $value)) {
            return false;
        }

        $format = strlen($value) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s';
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    private function isReversedRange(array $range): bool
    {
        if (!isset($range['start'], $range['end'])) {
            return false;
        }

        return strtotime((string)$range['start']) > strtotime((string)$range['end']);
    }
}
