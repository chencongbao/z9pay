<?php

namespace App\Services\BlackContent;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Cache\BlackContent\CacheAreaService;
use App\Services\Cache\BlackContent\CacheIpService;
use App\Services\Cache\BlackContent\CachePayNameService;

class ResetBlackContentCacheService
{
    use ServiceTraits;

    public function excute(): array
    {
        return [
            'ip' => App::make(CacheIpService::class)->excute(true),
            'pay_name' => App::make(CachePayNameService::class)->excute(true),
            'area' => App::make(CacheAreaService::class)->excute(true),
        ];
    }
}
