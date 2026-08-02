<?php

namespace App\Services\TransferOrder;

use App\Services\Common\ReportExceptionService;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;

class TransferOrderFailService
{
    use ServiceTraits;

    public function excute($order_id = 0, $remark = '', $hand_admin_id = 0)
    {
        try {
            return App::make(TransferOrderReverseService::class)->failTransfer($order_id, $remark, $hand_admin_id);
        } catch (\Exception $e) {
            app(ReportExceptionService::class)->report('代付失败确认异常', $e, [
                'order_id' => $order_id,
                'remark' => $remark,
            ]);
            throw $e;
        }
    }
}
