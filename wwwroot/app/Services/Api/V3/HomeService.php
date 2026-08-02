<?php

namespace App\Services\Api\V3;

use App\Services\Enums\ErrorCodeEnum;
use App\Traits\ServiceResponseTrait;
use Illuminate\Support\Facades\App;

class HomeService
{
    use ServiceResponseTrait;

    public function depositsIndex(array $data): array
    {
        $auth = App::make(MerchantRequestAuthService::class)->excute($data, '代收订单提交签名错误');
        if (empty($auth['success'])) {
            return $auth;
        }

        return App::make(CreateDepositOrderService::class)->excute($data, $auth['data']['merchant_info']);
    }

    public function depositsQuery(array $data): array
    {
        $auth = App::make(MerchantRequestAuthService::class)->excute($data, '代收订单查询签名错误');
        if (empty($auth['success'])) {
            return $auth;
        }

        $result = App::make(MerchantOrderReadService::class)->getDepositOrder($data['order_no'], $data['mid']);
        if (empty($result)) {
            return $this->fail(trans('api.order_none'), '订单不存在', ErrorCodeEnum::SUBMIT_ORDER_NOT_FOUND);
        }

        return $this->success($result);
    }

    public function transfersIndex(array $data): array
    {
        $auth = App::make(MerchantRequestAuthService::class)->excute($data, '代付订单提交签名错误');
        if (empty($auth['success'])) {
            return $auth;
        }

        return App::make(CreateTransferOrderService::class)->excute($data, $auth['data']['merchant_info']);
    }

    public function transfersQuery(array $data): array
    {
        $auth = App::make(MerchantRequestAuthService::class)->excute($data, '代付订单查询签名错误');
        if (empty($auth['success'])) {
            return $auth;
        }

        $result = App::make(MerchantOrderReadService::class)->getTransferOrder($data['order_no'], $data['mid']);
        if (empty($result)) {
            return $this->fail(trans('api.order_none'), '订单不存在', ErrorCodeEnum::SUBMIT_ORDER_NOT_FOUND);
        }

        return $this->success($result);
    }

    public function balance(array $data): array
    {
        $auth = App::make(MerchantRequestAuthService::class)->excute($data, '余额查询签名错误');
        if (empty($auth['success'])) {
            return $auth;
        }

        return App::make(QueryMerchantBalanceService::class)->excute($data['mid']);
    }

    public function transferCheck(array $data): array
    {
        $result = App::make(TransferCheckService::class)->excute($data);
        if (!empty($result['success'])) {
            $result['message'] = '';
        }

        return $result;
    }

    public function submitUtr(array $data): array
    {
        $auth = App::make(MerchantRequestAuthService::class)->excute($data, '提交UTR签名错误');
        if (empty($auth['success'])) {
            return $auth;
        }

        return App::make(SubmitUtrService::class)->excute($data);
    }
}
