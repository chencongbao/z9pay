<?php

namespace App\Services\BlackContent;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Cache\BlackContent\CachePayNameService;

class CheckPayNameService
{
    use ServiceTraits;

    public function excute($name = null, $mid = 0)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return false;
        }

        $result = App::make(CachePayNameService::class)->excute(false);
        if (empty($result) || !is_array($result)) {
            return false;
        }

        return $this->hasName($result[0] ?? [], $name) || $this->hasName($result[intval($mid)] ?? [], $name);
    }

    private function hasName($data, string $name): bool
    {
        if (!is_array($data) || empty($data)) {
            return false;
        }

        return isset($data[$name]) || in_array($name, $data, true);
    }
}
