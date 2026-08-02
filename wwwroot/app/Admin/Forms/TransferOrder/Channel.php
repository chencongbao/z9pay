<?php

namespace App\Admin\Forms\TransferOrder;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\TransferOrder;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantChannel;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Models\Channel as ChannelModel;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Jobs\SendTransferOrderPaymentJob;
use App\Services\Common\SystemLogService;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\TransferOrder\TransferOrderMerchantDeductService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class Channel extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        // 校验手动代付渠道参数。
        $channelId = (int)($input['channel_id'] ?? 0);
        if ($channelId <= 0) {
            return $this->response()->error('请选择代付渠道');
        }

        $id = (int)($this->payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->response()->error('订单ID不存在');
        }

        $admin = Admin::user();
        $channel = ChannelModel::query()->whereKey($channelId)->first(['id', 'status', 'classname', 'name']);
        if (!$channel || $channel->status !== 1) {
            return $this->response()->error('渠道不可用或已关闭');
        }

        // 加处理锁，避免并发重复下发同一笔订单。
        $lockKey = CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $id;
        if (!Cache::add($lockKey, 1, now()->addMinutes(5))) {
            return $this->response()->error('订单均正在执行，请勿重复操作');
        }

        try {
            $order = DB::transaction(function () use ($id, $channel, $admin) {
                $order = TransferOrder::query()->whereKey($id)->lockForUpdate()->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
                if (!$order) {
                    throw new RuntimeException('订单不存在');
                }
                if ((int)$order->status !== 3) {
                    throw new RuntimeException('订单均不符合代付条件（仅支持状态=待处理）');
                }

                $logService = App::make(CreateTransferOrderLogService::class);

                // 重派时生成新的平台单号并重新绑定渠道费用。
                $originalOrderNumber = $order->ordernumber;
                $order->ordernumber = $this->splitOrder($order->ordernumber);
                $order->hand_admin_id = $admin->id;
                $order->remark = '手动代付操作人：' . $this->adminDisplayName($admin);
                App::make(TransferOrderMerchantDeductService::class)->deductForChannel($order, (int)$channel->id, (int)$admin->id, '代付出款：' . $order->ordernumber, $originalOrderNumber, $logService);

                // 保存订单、刷新缓存，并派发实际代付下发队列。
                $order->save();
                $this->refreshTransferCache($order);
                App::make(CreateTransferOrderLogService::class)->excute($order->id, '手动代付，请求代付渠道', $channel->name, 'debug');
                Cache::put(CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $order->id, 1, now()->addMinutes(5));
                dispatch(new SendTransferOrderPaymentJob($order, ['channel_id' => $channel->id, 'channel_name' => $channel->name, 'classname' => $channel->classname]))->onQueue('transfer')->afterCommit();

                return $order;
            });

            // 记录后台手动选择渠道操作。
            app(SystemLogService::class)->logAction(
                actionKey: 'transfer.order.channel',
                text: '代付订单手动选择渠道',
                subject: $order,
                properties: [
                    'order_id' => $order->id,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $order->amount,
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->name,
                ],
                remark: '代付订单手动选择渠道',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('操作成功')->refresh();
        } catch (Throwable $e) {
            Cache::forget($lockKey);
            return $this->response()->error($e->getMessage());
        }
    }

    protected function splitOrder($value)
    {
        $value = (string)$value;

        if ($value === '') {
            return '';
        }

        [$order, $num] = array_pad(explode('-', $value, 2), 2, 0);
        $num = is_numeric($num) ? (int)$num : 0;

        return $order . '-' . ($num + 1);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('transfer-order-channel');
    }

    public function form()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $order = TransferOrder::query()->whereKey($id)->first(['mid']);
        $channelIds = MerchantChannel::query()->where('merchant_user_id', optional($order)->mid)->where('status', 1)->where('payment_id', 7)->pluck('channel_id')->toArray();

        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->select('channel_id', '选择渠道')->options(ChannelModel::query()->whereRaw('FIND_IN_SET(?,payment_ids)', [7])->whereIn('id', $channelIds)->where('status', 1)->pluck('name', 'id'))->required()->disableClearButton();
    }

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

    private function refreshTransferCache(TransferOrder $order): void
    {
        App::make(OrderCacheService::class)->putTransfer($order, true);
    }

    private function adminDisplayName($admin): string
    {
        return trim((string)($admin->name ?: $admin->username ?: ('#' . $admin->id)));
    }
}
