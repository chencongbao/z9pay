<?php

namespace App\Admin\Actions\Grid\SettlementOrder;

use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use App\Models\TransferOrder;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\App;
use App\Services\Const\LogConstService;
use App\Services\Common\SystemLogService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class Cliam extends RowAction
{
    protected $title = '认领';

    public function handle(Request $request)
    {
        $order = TransferOrder::query()->where('id', $this->getKey())->where('status', 6)->first(['id', 'order_no', 'ordernumber', 'amount']);
        if (!$order) {
            return $this->response()->error('非法操作');
        }

        $updated = TransferOrder::query()->where('id', $order->id)->where('status', 6)->update(['status' => 3, 'hand_admin_id' => Admin::user()->id]);
        if (!$updated) {
            return $this->response()->error('订单状态已变化，请刷新后重试');
        }

        $logService = App::makeWith(CreateTransferOrderLogService::class, ['filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id]);
        $logService->excute($order->id, '订单被认领', '操作员：' . Admin::user()->username);

        app(SystemLogService::class)->logAction(
            actionKey: 'settlement.order.claim',
            text: '结算订单认领',
            subject: $order,
            properties: [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'ordernumber' => $order->ordernumber,
                'amount' => $order->amount,
            ],
            remark: '结算订单认领',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        bob_send_system_settlement_notice(['success_text' => '结算订单待处理，订单号：' . $order->ordernumber, 'voice_id' => 'settlement_3', 'id' => 3]);

        return $this->response()->success('操作成功')->refresh();
    }

    public function confirm()
    {
        return ['提示?', '确认认领？'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('settlement-order-claim');
    }
}
