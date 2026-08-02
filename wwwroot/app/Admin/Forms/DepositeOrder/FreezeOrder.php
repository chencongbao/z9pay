<?php

namespace App\Admin\Forms\DepositeOrder;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\FreezeOrder as FreezeOrderModel;
use App\Models\DepositOrder;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\DepositOrder\DepositOrderFreezeService;

class FreezeOrder extends Form implements LazyRenderable
{
    use LazyWidget;

    private ?array $freezeSummary = null;

    public function handle(array $input)
    {
        try {
            if (Admin::user()->cannot('deposit-order-freeze')) {
                throw new \Exception('无冻结代收订单权限');
            }

            $id = $this->orderId();
            $remark = trim((string)($input['remark'] ?? ''));
            $freezeAmount = bob_amount_format($input['freeze_amount'] ?? 0);
            $admin = Admin::user();
            $freezeOrder = App::make(DepositOrderFreezeService::class)->excute($id, $freezeAmount, $remark, $admin->id);
            $order = $freezeOrder->deposit_order;

            app(SystemLogService::class)->logAction(
                actionKey: 'deposit.order.freeze',
                text: '冻结 代收订单',
                subject: $order,
                properties: [
                    'order_id' => $order->id,
                    'freeze_order_id' => $freezeOrder->id,
                    'freeze_amount' => $freezeAmount,
                    'remark' => $remark,
                ],
                remark: sprintf('冻结 代收订单 %.2f', $freezeAmount),
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('冻结成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function form()
    {
        $summary = $this->getFreezeSummary();

        $this->confirm('提示', '确认冻结');
        $this->display('order_amount', "订单可冻结金额");
        $this->display('frozen_amount', "已冻结金额");
        $this->display('remaining_freeze_amount', "剩余冻结金额");
        $this->text('freeze_amount', "冻结金额")
            ->rules([
                'required',
                'numeric',
                'min:0.01',
                new DecimalTwoPlaces(),
                function ($attribute, $value, $fail) use ($summary) {
                    if ($summary['remaining_freeze_amount'] <= 0) {
                        $fail('订单已无剩余可冻结金额');
                        return;
                    }

                    if (bob_amount_format($value) > $summary['remaining_freeze_amount']) {
                        $remainingFreezeAmount = bob_unit_format($summary['remaining_freeze_amount']);
                        $fail('冻结金额不能大于剩余冻结金额：' . $remainingFreezeAmount);
                    }
                },
            ], [
                'required' => '请填写冻结金额',
                'numeric' => '冻结金额不合法',
                'min' => '冻结金额必须大于0',
            ])
            ->required();
        $this->textarea('remark', "备注")->required();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-freeze');
    }

    public function default()
    {
        $summary = $this->getFreezeSummary();

        return [
            'order_amount' => bob_unit_format($summary['order_amount']),
            'frozen_amount' => bob_unit_format($summary['frozen_amount']),
            'remaining_freeze_amount' => bob_unit_format($summary['remaining_freeze_amount']),
            'freeze_amount' => $summary['remaining_freeze_amount'],
            'remark' => '',
        ];
    }

    private function getFreezeSummary(): array
    {
        if ($this->freezeSummary !== null) {
            return $this->freezeSummary;
        }

        $id = $this->orderId();
        $order = $id > 0 ? DepositOrder::query()->whereKey($id)->first(['id', 'amount', 'actual_amount']) : null;
        if (!$order) {
            return $this->freezeSummary = [
                'order_amount' => 0,
                'frozen_amount' => 0,
                'remaining_freeze_amount' => 0,
            ];
        }

        $orderAmount = bob_amount_format(floatval($order->actual_amount) > 0 ? $order->actual_amount : $order->amount);
        $frozenAmount = bob_amount_format(FreezeOrderModel::where('deposit_order_id', $order->id)->where('status', 1)->sum('amount'));
        $remainingFreezeAmount = max(0, bob_amount_format($orderAmount - $frozenAmount));

        return $this->freezeSummary = [
            'order_amount' => $orderAmount,
            'frozen_amount' => $frozenAmount,
            'remaining_freeze_amount' => $remainingFreezeAmount,
        ];
    }

    private function orderId(): int
    {
        return intval($this->payload['id'] ?? 0);
    }
}
