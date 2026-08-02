<?php

namespace App\Services\SettlementOrder;

use App\Services\Common\ReportExceptionService;
use App\Services\TransferOrder\TransferOrderReverseService;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;

class SettlementOrderFailService
{
    use ServiceTraits;

    public function excute($order_id = 0, $remark = '', $hand_admin_id = 0)
    {
        try {
            return App::make(TransferOrderReverseService::class)->failSettlement($order_id, $remark, $hand_admin_id);
        } catch (\Exception $e) {
            app(ReportExceptionService::class)->report('结算订单失败异常', $e, [
                'order_id' => $order_id,
                'remark' => $remark,
            ]);
            throw $e;
        }
    }
}
