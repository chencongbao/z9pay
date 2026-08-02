<?php

namespace App\Admin\Forms\TransferOrder;

use Throwable;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\TransferOrder\TransferOrderSuccessService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class OrderSuccess extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        $admin = Admin::user();
        $id = (int)($this->payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->response()->error('订单不存在');
        }

        try {
            $remark = trim((string)($input['remark'] ?? ''));
            $actualAmount = (float)($input['actual_amount'] ?? 0);

            $order = TransferOrder::query()->whereKey($id)->where('type', 0)->first(['id', 'status', 'order_no', 'ordernumber', 'amount']);
            if (!$order) {
                return $this->response()->error('非法操作');
            }
            if ((int)$order->status === 4) {
                return $this->response()->error('订单已经成功，请勿重复处理');
            }
            if ((int)$order->status === 5) {
                return $this->response()->error('订单已经失败，请勿重复处理');
            }

            // 统一走代付成功服务，状态、余额、佣金、回调和缓存由服务内部处理。
            $order = app(TransferOrderSuccessService::class)->excute($order->id, $actualAmount, $remark, $admin->id, 1);
            app(CreateTransferOrderLogService::class)->excute($order->id, '订单手动成功', '操作员：' . $admin->username);

            app(SystemLogService::class)->logAction(
                actionKey: 'transfer.order.success',
                text: '代付订单手动成功',
                subject: $order,
                properties: [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $order->amount,
                    'actual_amount' => $actualAmount,
                    'remark' => $remark,
                ],
                remark: '代付订单手动成功',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('操作成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('transfer-order-manual-success');
    }

    public function form()
    {
        $this->confirm('订单成功', '<span class="label" style="background:#21b978;">代付订单手动成功确认</span>');
        $this->display('order_no', '商户订单号');
        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->text('actual_amount', '实付金额')->rules(['numeric', 'between:0,99999999999', new DecimalTwoPlaces()], ['numeric' => '实付金额不合法', 'between' => '实付金额不合法'])->required();
        $this->textarea('remark', '备注')->required();
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $order = TransferOrder::query()->whereKey($id)->first(['id', 'order_no', 'ordernumber', 'amount']);

        return [
            'order_no' => optional($order)->order_no,
            'ordernumber' => optional($order)->ordernumber,
            'amount' => optional($order)->amount,
            'actual_amount' => optional($order)->amount,
            'remark' => '',
        ];
    }
}
