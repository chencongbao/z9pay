<?php

namespace App\Services\Header;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Merchant\CacheApKeyService;

class CheckMerchantHeaderMidService
{
    use ServiceTraits;

    public function excute()
    {
        $authorization = request()->header('Authorization');

        if (empty($authorization)) {
            return 0;
        }

        $parts = preg_split('/\s+/', trim($authorization), 2);
        $appkey = $parts[1] ?? $parts[0] ?? '';
        if ($appkey === '') {
            return 0;
        }

        return App::make(CacheApKeyService::class)->excute($appkey);
    }
}
