<?php

namespace App\Services\Api\V3;

use App\Services\Enums\ErrorCodeEnum;
use App\Traits\ServiceResponseTrait;
use Illuminate\Support\Facades\App;
use App\Services\Order\OrderCacheService;
use App\Services\DepositOrder\DepositOrderConfirmPayService;

class SubmitUtrService
{
    use ServiceResponseTrait;

    public function excute(array $data): array
    {
        $result = App::make(OrderCacheService::class)->getDepositByMerchantOrder($data['mid'], $data['order_no']);
        if (empty($result)) {
            return $this->fail(trans('api.order_none'), '订单不存在', ErrorCodeEnum::SUBMIT_ORDER_NOT_FOUND);
        }

        if (!in_array((int) ($result['status'] ?? 0), [1, 3], true)) {
            return $this->fail(trans('api.order_status_invalid'), '订单状态不允许操作');
        }

        return App::make(DepositOrderConfirmPayService::class)->confirmByOrderId((int) $result['id'], $data, (int) $data['mid'], $result);
    }
}
