<?php

namespace App\Services\SettlementOrder;

use App\Services\Common\ReportExceptionService;
use App\Services\TransferOrder\TransferOrderCompleteService;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;

class SettlementOrderSuccessService
{
    use ServiceTraits;

    public function excute($order_id = 0, $amount = 0, $remark = '', $hand_admin_id = 0, $hand_success = 0)
    {
        try {
            return App::make(TransferOrderCompleteService::class)->successSettlement($order_id, $amount, $remark, $hand_admin_id, $hand_success);
        } catch (\Exception $e) {
            app(ReportExceptionService::class)->report('结算订单成功异常', $e, [
                'order_id' => $order_id,
                'amount' => $amount,
                'remark' => $remark,
            ]);
            throw $e;
        }
    }
}
