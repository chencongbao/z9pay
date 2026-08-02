<?php

namespace App\Admin\Forms\FreezeOrder;

use Throwable;
use RuntimeException;
use Dcat\Admin\Admin;
use App\Models\FreezeOrder;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\DepositOrder\DepositOrderFreezeService;

class UnFreezeOrder extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $id = intval($this->payload['id'] ?? 0);
            $admin = Admin::user();
            $remark = trim((string) ($input['remark'] ?? ''));

            if ($id <= 0) {
                throw new RuntimeException('冻结订单参数错误');
            }

            // 解冻统一走冻结服务，保证余额流水、冻结状态和订单缓存一致。
            $freezeOrder = App::make(DepositOrderFreezeService::class)->unfreeze($id, $remark, $admin->id);

            app(SystemLogService::class)->logAction(
                actionKey: 'freeze.order.unfreeze',
                text: '解冻订单',
                subject: $freezeOrder,
                properties: [
                    'freeze_order_id' => $freezeOrder->id,
                    'deposit_order_id' => $freezeOrder->deposit_order_id,
                    'amount' => $freezeOrder->amount,
                    'remark' => $remark,
                ],
                remark: sprintf('解冻订单 %.2f', $freezeOrder->amount),
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('解冻成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('freeze-order-unfreeze');
    }

    public function form()
    {
        $this->display('amount', '订单金额');
        $this->display('freeze_amount', '冻结金额');
        $this->textarea('remark', '备注')->required();
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        $order = $id > 0
            ? FreezeOrder::with(['deposit_order' => fn($query) => $query->select(['id', 'actual_amount'])])->whereKey($id)->first(['id', 'amount', 'deposit_order_id'])
            : null;

        return [
            'amount' => optional(optional($order)->deposit_order)->actual_amount,
            'freeze_amount' => optional($order)->amount,
            'remark' => '',
        ];
    }
}
