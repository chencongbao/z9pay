<?php

namespace App\Admin\Forms\SettlementOrder;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\Channel as PaymentChannel;
use Illuminate\Support\Arr;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use App\Models\MerchantChannel;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Const\LogConstService;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;
use App\Services\TransferOrder\TransferOrderMerchantDeductService;

class Channel extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        $admin = Admin::user();
        $channelId = (int)($input['channel_id'] ?? 0);
        if ($channelId <= 0) {
            return $this->response()->error('请选择结算渠道');
        }

        $id = (int)($this->payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->response()->error('订单ID不存在');
        }

        $channel = PaymentChannel::query()->whereKey($channelId)->first(['id', 'status', 'classname', 'name']);
        if (!$channel || $channel->status !== 1) {
            return $this->response()->error('渠道不可用或已关闭');
        }

        $lockKey = CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $id;
        if (!Cache::add($lockKey, 1, now()->addMinutes(5))) {
            return $this->response()->error('订单正在执行，请勿重复操作');
        }

        try {
            [$order, $fee, $filename] = DB::transaction(function () use ($id, $channel, $admin) {
                $order = TransferOrder::query()->whereKey($id)->lockForUpdate()->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
                if (!$order) {
                    throw new RuntimeException('订单不存在');
                }

                if (!in_array((int)$order->status, [3, 6])) {
                    throw new RuntimeException('订单均不符合代付条件（仅支持状态=处理中,待处理）');
                }

                $filename = LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id;

                // 原始订单号
                $originalOrderNumber = $order->ordernumber;
                // 订单号递增
                $order->ordernumber = $this->splitOrder($order->ordernumber);
                $order->hand_admin_id = $admin->id;
                $deductResult = App::make(TransferOrderMerchantDeductService::class)->deductSettlementForChannel($order, (int)$channel->id, (int)$admin->id, '结算出款：' . $order->ordernumber, $originalOrderNumber);
                $order->save();

                return [$order, $deductResult['merchant_extra_fee'], $filename];
            });

            $orderCacheService = App::make(OrderCacheService::class);
            $orderCacheService->putTransfer($order);

            // 记录请求日志
            $createTransferOrderLogService = App::make(CreateTransferOrderLogService::class);
            $createTransferOrderLogService->excute($order->id, '请求结算渠道', $channel->name, 'debug');
            try {
                $classname = 'Richard\\Payment\\Channel\\' . $channel->classname;
                $pay = new $classname($filename);
                $data = $pay->transfer($order->id);
            } catch (Throwable $e) {
                $order->hand_admin_id = $admin->id;
                $order->status = 3;
                $order->remark = $e->getMessage();
                $order->save();
                $orderCacheService->putTransfer($order);
                $createTransferOrderLogService->excute($order->id, '结算渠道请求异常', [
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                ], 'error');

                throw new RuntimeException('结算渠道请求异常：' . $e->getMessage());
            }

            if (empty($data)) {
                // 渠道请求失败，仅更新订单状态与日志
                $order->hand_admin_id = $admin->id;
                $order->status = 3; // 保留你原来的业务状态
                $order->remark = $pay->error ?? '';
                $order->save();
                $createTransferOrderLogService->excute($order->id, '结算渠道请求失败', $pay->error ?? '', 'error');
            } else {
                // 渠道请求成功，更新订单信息
                $order->fill(Arr::collapse([
                    [
                        'status' => 2,
                        'hand_admin_id' => $admin->id,
                        'merchant_extra_fee' => $fee,
                    ],
                    $data,
                ]));
                $order->save();

                $createTransferOrderLogService->excute(
                    $order->id,
                    '结算渠道请求成功，等待支付',
                    $data,
                    'debug'
                );
            }
            $orderCacheService->putTransfer($order);
            app(SystemLogService::class)->logAction(
                actionKey: 'settlement.order.channel',
                text: '结算订单手动选择渠道',
                subject: $order,
                properties: [
                    'order_id' => $order->id,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $order->amount,
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                ],
                remark: '结算订单手动选择渠道',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('操作成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * 订单号拆分规则：
     * - 第一次代付：不增加 "-1"，保持原样
     * - 第二次代付起：按 原号-1 → 原号-2 → ... 递增
     */
    protected function splitOrder($value)
    {
        $value = (string)$value;

        if ($value === '') {
            return '';
        }

        // 第一次代付：没有 "-"，则不拆分，保持原样
        if (strpos($value, '-') === false) {
            return $value . '-1';
        }

        // 已经拆过：继续递增
        [$order, $num] = array_pad(explode('-', $value, 2), 2, 0);
        $num = is_numeric($num) ? (int)$num : 0;

        return $order . '-' . ($num + 1);
    }


    /**
     * Build a form here.
     */
    public function form()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $order = TransferOrder::query()->whereKey($id)->first(['mid']);
        $channelIds = MerchantChannel::query()
            ->where('merchant_user_id', optional($order)->mid)
            ->where('status', 1)
            ->where('payment_id', 7)
            ->pluck('channel_id')
            ->toArray();

        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->select('channel_id', '选择渠道')->options(
            PaymentChannel::query()
                ->whereRaw('FIND_IN_SET(?,payment_ids)', [7])
                ->whereIn('id', $channelIds)
                ->where('status', 1)
                ->pluck('name', 'id')
        )->required()->disableClearButton();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('settlement-order-channel');
    }

    /**
     * The data of the form.
     *
     * @return array
     */
    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $order = TransferOrder::query()->whereKey($id)->first(['id', 'ordernumber', 'amount', 'mid']);
        return [
            'ordernumber' => optional($order)->ordernumber,
            'amount' => optional($order)->amount,
            'channel_id' => '',
        ];
    }
}
