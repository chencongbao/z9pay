<?php

namespace App\Admin\Forms\TransferOrder;

use Throwable;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\TransferOrder\TransferOrderReverseService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class OrderCorre extends Form implements LazyRenderable
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
            $google2faCode = (string)($input['google_2fa_code'] ?? '');
            app(AdminGoogle2faService::class)->verify($google2faCode);

            // 统一走代付冲正服务，状态、余额、佣金和缓存由服务内部处理。
            $remark = trim((string)($input['remark'] ?? ''));
            $correRemark = empty($remark) ? '手动冲正' : '手动冲正，' . $remark;
            $order = app(TransferOrderReverseService::class)->correTransfer($id, $correRemark, $admin->id);

            app(CreateTransferOrderLogService::class)->excute($order->id, '订单手动冲正', '操作员：' . $admin->username);

            app(SystemLogService::class)->logAction(
                actionKey: 'transfer.order.corre',
                text: '代付订单冲正',
                subject: $order,
                properties: [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $order->amount,
                    'remark' => $correRemark,
                ],
                remark: '代付订单冲正',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('冲正成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('transfer-order-corre');
    }

    public function form()
    {
        $this->confirm('确认冲正', '确认提交当前代付订单冲正操作？');
        $this->display('order_no', '商户订单号');
        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->textarea('remark', '备注')->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $order = TransferOrder::query()->whereKey($id)->first(['id', 'order_no', 'ordernumber', 'amount']);

        return [
            'order_no' => optional($order)->order_no,
            'ordernumber' => optional($order)->ordernumber,
            'amount' => optional($order)->amount,
            'remark' => '',
            'google_2fa_code' => '',
        ];
    }
}
