<?php

namespace App\Admin\Forms\TransferOrder;

use Throwable;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\TransferOrder\TransferOrderFailService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class OrderFail extends Form implements LazyRenderable
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
            $remark = $remark === '' ? '订单手动取消' : $remark;

            $order = TransferOrder::query()
                ->whereKey($id)
                ->where('type', 0)
                ->first(['id', 'status', 'child_count', 'order_no', 'ordernumber', 'amount']);
            if (!$order) {
                return $this->response()->error('订单不存在');
            }
            if ((int)$order->status === 4) {
                return $this->response()->error('订单已经成功，请勿重复处理');
            }
            if ((int)$order->status === 5) {
                return $this->response()->error('订单已经失败，请勿重复处理');
            }
            if (Cache::has(CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $order->id)) {
                return $this->response()->error('订单正在执行代付操作，请勿操作');
            }
            if ((int)$order->child_count > 0 && $this->hasActiveChildOrder((int)$order->id)) {
                return $this->response()->error('请先关闭子订单');
            }

            // 统一走代付失败服务，状态、余额、佣金、回调和缓存由服务内部处理。
            $order = app(TransferOrderFailService::class)->excute($order->id, $remark, $admin->id);
            app(CreateTransferOrderLogService::class)->excute($order->id, '订单手动失败', '操作员：' . $admin->username);

            app(SystemLogService::class)->logAction(
                actionKey: 'transfer.order.fail',
                text: '代付订单手动失败',
                subject: $order,
                properties: [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $order->amount,
                    'remark' => $remark,
                ],
                remark: '代付订单手动失败',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('订单关闭成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('transfer-order-manual-fail');
    }

    public function form()
    {
        $this->confirm('订单失败', '<span class="label" style="background:#ef5228">代付订单手动失败确认</span>');
        $this->display('order_no', '商户订单号');
        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->textarea('remark', '备注');
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
        ];
    }

    private function hasActiveChildOrder(int $orderId): bool
    {
        return TransferOrder::query()->where('pid', $orderId)->whereIn('status', [1, 2, 3, 6])->exists();
    }
}
