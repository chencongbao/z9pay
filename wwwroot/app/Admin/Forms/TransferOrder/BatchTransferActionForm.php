<?php

namespace App\Admin\Forms\TransferOrder;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\Channel;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Jobs\SendTransferOrderPaymentJob;
use App\Services\Common\SystemLogService;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\TransferOrder\TransferOrderMerchantDeductService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class BatchTransferActionForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        // 校验批量代付参数。
        $channelId = (int)($input['channel_id'] ?? 0);
        if ($channelId <= 0) {
            return $this->response()->error('请选择代付渠道');
        }

        $mid = (int)($input['mid'] ?? 0);
        if ($mid <= 0) {
            return $this->response()->error('请选择商户');
        }

        $ids = $input['id'] ?? '';
        if (!$ids) {
            return $this->response()->error('请选择数据项');
        }

        $keys = is_array($ids) ? $ids : explode(',', (string)$ids);
        $keys = collect($keys)->map(fn($v) => (int)trim((string)$v))->filter(fn($v) => $v > 0)->unique()->values()->all();

        if (empty($keys)) {
            return $this->response()->error('请选择数据项');
        }

        $admin = Admin::user();
        $successCount = 0;
        $skipCount = 0;
        $failures = [];
        $transferLogService = app(CreateTransferOrderLogService::class);

        // 校验本次指定的代付渠道。
        $channel = Channel::query()->whereKey($channelId)->first(['id', 'status', 'name', 'classname']);
        if (!$channel || $channel->status !== 1) {
            return $this->response()->error('渠道不可用或已关闭');
        }

        // 加载选中订单，缺失订单、非当前商户、非待处理订单都记录到失败明细。
        $orders = TransferOrder::query()->where('type', 0)->whereIn('id', $keys)->get(['id', 'mid', 'status', 'ordernumber']);

        if ($orders->isEmpty()) {
            return $this->response()->error('请选择数据项');
        }

        $foundOrderIds = $orders->pluck('id')->map(fn($id) => (int)$id)->all();
        foreach (array_values(array_diff($keys, $foundOrderIds)) as $missingId) {
            $failures[] = ['id' => $missingId, 'ordernumber' => '', 'error' => '订单不存在'];
        }

        foreach ($orders as $row) {
            $orderId = (int)$row->id;
            if ((int)$row->mid !== $mid) {
                $reason = '存在非该商户订单';
                $this->writeBatchFailureLog($orderId, $reason, ['选择商户ID' => $mid, '订单商户ID' => $row->mid], false);
                $failures[] = ['id' => $orderId, 'ordernumber' => $row->ordernumber ?? '', 'error' => $reason];
                continue;
            }

            if ((int)$row->status !== 3) {
                $reason = '存在非待处理订单';
                $this->writeBatchFailureLog($orderId, $reason, ['当前状态' => $row->status], false);
                $failures[] = ['id' => $orderId, 'ordernumber' => $row->ordernumber ?? '', 'error' => $reason];
                continue;
            }

            // 加处理锁，避免并发重复下发同一笔订单。
            $lockKey = CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $orderId;
            if (!Cache::add($lockKey, 1, now()->addMinutes(5))) {
                $skipCount++;
                continue;
            }

            try {
                $result = DB::transaction(function () use ($orderId, $channel, $admin, $transferLogService) {
                    // 事务内重新锁单，保证状态、余额和派发队列一致。
                    $order = TransferOrder::query()->whereKey($orderId)->lockForUpdate()->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
                    if (!$order) {
                        throw new RuntimeException('订单不存在');
                    }

                    if ((int)$order->status !== 3) {
                        throw new RuntimeException('订单均不符合代付条件（仅支持状态=待处理）');
                    }

                    // 重派时生成新的平台单号并重新绑定渠道费用。
                    $originalOrderNumber = $order->ordernumber;
                    $order->ordernumber = $this->splitOrder($order->ordernumber);
                    $order->hand_admin_id = $admin->id;
                    $order->remark = '批量代付操作人：' . $this->adminDisplayName($admin);
                    App::make(TransferOrderMerchantDeductService::class)->deductForChannel($order, (int)$channel->id, (int)$admin->id, '', $originalOrderNumber, $transferLogService);

                    // 保存订单并派发实际代付下发队列。
                    $order->save();
                    $this->refreshTransferCache($order);
                    $transferLogService->excute($order->id, '批量代付，请求代付渠道', $channel->name, 'debug');
                    Cache::put(CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $order->id, 1, now()->addMinutes(5));
                    dispatch(new SendTransferOrderPaymentJob($order, ['channel_id' => $channel->id, 'channel_name' => $channel->name, 'classname' => $channel->classname]))->onQueue('transfer')->afterCommit();

                    return ['success' => true, 'message' => 'OK'];
                });

                if (empty($result['success'])) {
                    Cache::forget($lockKey);
                    $reason = $result['message'] ?? '批量代付失败';
                    $this->writeBatchFailureLog($orderId, $reason, ['通道ID' => $channelId, '通道名称' => $channel->name]);
                    $failures[] = ['id' => $orderId, 'ordernumber' => $row->ordernumber ?? '', 'error' => $reason];
                    continue;
                }

                $successCount++;
            } catch (Throwable $e) {
                Cache::forget($lockKey);
                $this->writeBatchFailureLog($orderId, $e->getMessage(), ['通道ID' => $channelId, '通道名称' => $channel->name]);
                $failures[] = ['id' => $orderId, 'ordernumber' => $row->ordernumber ?? '', 'error' => $e->getMessage()];
            }
        }

        // 记录后台批量代付操作结果，方便排查部分失败。
        app(SystemLogService::class)->logAction(
            actionKey: 'transfer.order.batch_submit',
            text: '批量代付提交',
            subject: null,
            properties: [
                'ids' => $keys,
                'mid' => $mid,
                'channel_id' => $channelId,
                'success_count' => $successCount,
                'skip_count' => $skipCount,
                'fail_count' => count($failures),
                'failures' => $failures,
            ],
            remark: '批量代付提交',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $admin
        );

        $message = '批量代付完成，成功' . $successCount . '笔，跳过' . $skipCount . '笔，失败' . count($failures) . '笔';
        if (!empty($failures)) {
            return $this->response()->warning($message)->refresh();
        }

        return $this->response()->success($message)->refresh();
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
        return Admin::user()->can('transfer-order-batch-submit');
    }

    public function form()
    {
        $merchantOptions = collect(App::make(GetMerchantListInfoService::class)->excute())->pluck('bname', 'id')->toArray();

        $this->select('mid', '请选择商户')->options($merchantOptions)->load('channel_id', '/ajax/getMerchantTransferChannel')->required();
        $this->select('channel_id', '请选择渠道')->required();
        $this->hidden('id')->attribute('id', 'transfer-id');
    }

    public function default()
    {
        return [];
    }

    private function refreshTransferCache(TransferOrder $order): void
    {
        App::make(OrderCacheService::class)->putTransfer($order, true);
    }

    private function writeBatchFailureLog(int $orderId, string $reason, array $context = [], bool $updateRemark = true): void
    {
        if ($updateRemark && $order = TransferOrder::query()->whereKey($orderId)->first(['id', 'remark'])) {
            $order->remark = $reason . '；批量代付操作人：' . $this->adminDisplayName(Admin::user());
            $order->save();
            $this->refreshTransferCache($order);
        }

        App::make(CreateTransferOrderLogService::class)->excute($orderId, '批量代付失败', array_merge(['失败原因' => $reason], $context), 'error');
    }

    private function adminDisplayName($admin): string
    {
        return trim((string)($admin->name ?: $admin->username ?: ('#' . $admin->id)));
    }

    public function actionScript()
    {
        $warning = __('请选择操作项!');

        return <<<JS
function (data, target, action) {
    var key = {$this->getSelectedKeysScript()}

    if (key.length === 0) {
        Dcat.error('{$warning}');
        return false;
    }

    action.options.key = key;
}
JS;
    }
}
