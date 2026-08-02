<?php

namespace App\Services\Api\V3;

use App\Services\Enums\ErrorCodeEnum;
use App\Traits\ServiceResponseTrait;
use Illuminate\Support\Facades\App;
use App\Services\Api\CheckMerchantSignService;
use App\Services\Api\CheckMerchantExistsService;

class MerchantRequestAuthService
{
    use ServiceResponseTrait;

    public function excute(array $data, string $signErrorText, int $signSpace = 1): array
    {
        $merchantInfo = App::make(CheckMerchantExistsService::class)->excute($data['mid']);
        if (empty($merchantInfo)) {
            return $this->fail(trans('api.none_merchant'), '商户不存在', ErrorCodeEnum::SUBMIT_MERCHANT_INVALID);
        }

        if (!App::make(CheckMerchantSignService::class)->excute($data, $merchantInfo, $signErrorText, $signSpace)) {
            return $this->fail(trans('api.sign_error'), '签名错误', ErrorCodeEnum::SUBMIT_SIGN_INVALID);
        }

        return $this->success(['merchant_info' => $merchantInfo]);
    }
}
