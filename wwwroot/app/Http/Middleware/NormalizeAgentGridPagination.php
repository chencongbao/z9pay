<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeAgentGridPagination
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->query->all() as $key => $value) {
            if ($this->isPaginationKey($key, 'per_page')) {
                $this->normalizePerPage($request, $key, $value);
                continue;
            }

            if ($this->isPaginationKey($key, 'page') && !$this->isPositiveIntegerString($value)) {
                $request->query->remove($key);
            }
        }

        return $next($request);
    }

    private function normalizePerPage(Request $request, string $key, mixed $value): void
    {
        if (!$this->isPositiveIntegerString($value)) {
            $request->query->remove($key);
            return;
        }

        $max = max(1, (int) config('agent-admin.grid.per_page_max', 200));
        if ((int) $value > $max) {
            $request->query->set($key, (string) $max);
        }
    }

    private function isPaginationKey(mixed $key, string $name): bool
    {
        return is_string($key) && ($key === $name || str_ends_with($key, '_' . $name));
    }

    private function isPositiveIntegerString(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[1-9]\d*$/D', $value) === 1
            && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }
}
