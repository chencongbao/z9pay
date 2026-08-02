<?php

namespace App\Admin\Forms\DepositeOrder;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\DepositOrder;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Jobs\MerchantDepositCallbackJob;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\DepositOrder\DepositOrderStatusService;
use App\Services\DepositOrderLog\CreateDepositOrderLogService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class OrderFail extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('deposit-order-manual-fail')) {
                throw new RuntimeException('无手动失败代收订单权限');
            }

            $id = intval($this->payload['id'] ?? 0);
            $remark = trim((string) ($input['remark'] ?? '')) ?: '订单手动失败';

            if ($id <= 0) {
                throw new RuntimeException('订单参数错误');
            }

            $order = DB::transaction(function () use ($id, $remark, $admin) {
                $order = DepositOrder::whereKey($id)->lockForUpdate()->first(['id', 'status', 'user_id', 'order_no', 'ordernumber', 'amount']);

                if (!$order) {
                    throw new RuntimeException('订单不存在');
                }

                if ((int) $order->status === 5) {
                    throw new RuntimeException('订单已经成功，请勿重复处理');
                }

                if ((int) $order->status === 6) {
                    throw new RuntimeException('订单已经失败，请勿重复处理');
                }

                // 手动失败统一走状态服务，状态落库后立即刷新订单缓存。
                App::make(DepositOrderStatusService::class)->markFailed($order, $remark);

                if ($order->user_id > 0) {
                    App::make(GetUserDaifukuanDepositOrderListService::class)->remove($order->user_id, $order);
                }

                App::make(CreateDepositOrderLogService::class)->excute($order->id, '订单手动失败', '操作员：' . $admin->username);

                DB::afterCommit(function () use ($order) {
                    dispatch(new MerchantDepositCallbackJob($order->id))->onQueue('callback');
                });

                return $order;
            });

            app(SystemLogService::class)->logAction(
                actionKey: 'deposit.order.fail',
                text: '代收订单手动失败',
                subject: $order,
                properties: [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $order->amount,
                    'remark' => $remark,
                ],
                remark: '代收订单手动失败',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('订单处理成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-manual-fail');
    }

    public function form()
    {
        $this->confirm('订单失败', '<span class="label" style="background:#ef5228">代收订单手动失败确认</span>');
        $this->display('order_no', '商户订单号');
        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->textarea('remark', '备注')->required();
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        $order = $id > 0 ? DepositOrder::whereKey($id)->first(['order_no', 'ordernumber', 'amount']) : null;

        return [
            'order_no' => optional($order)->order_no,
            'ordernumber' => optional($order)->ordernumber,
            'amount' => optional($order)->amount,
            'remark' => '',
        ];
    }
}
