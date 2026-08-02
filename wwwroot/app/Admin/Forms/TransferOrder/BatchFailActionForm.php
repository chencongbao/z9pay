<?php

namespace App\Admin\Forms\TransferOrder;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\TransferOrder\TransferOrderFailService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class BatchFailActionForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        // 校验批量驳回参数。
        $remark = trim((string)($input['remark'] ?? ''));
        if (!$remark) {
            return $this->response()->error('请填写驳回原因');
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
        $allowStatuses = [1, 2, 3];
        $successCount = 0;
        $skipCount = 0;
        $failures = [];
        $adminName = $admin->name ?? $admin->username ?? '';
        $failService = app(TransferOrderFailService::class);
        $logService = app(CreateTransferOrderLogService::class);

        // 加载本次选中的代付订单，缺失或非代付订单记录到失败明细。
        $orders = TransferOrder::query()->where('type', 0)->whereIn('id', $keys)->get(['id', 'status', 'ordernumber', 'child_count']);

        if ($orders->isEmpty()) {
            return $this->response()->error('订单不存在或不是代付订单');
        }

        $foundOrderIds = $orders->pluck('id')->map(fn($id) => (int)$id)->all();
        foreach (array_values(array_diff($keys, $foundOrderIds)) as $missingId) {
            $failures[] = ['id' => $missingId, 'ordernumber' => '', 'error' => '订单不存在或不是代付订单'];
        }

        foreach ($orders as $row) {
            $orderId = (int)$row->id;
            // 无效状态不阻断整批，单独记录失败原因。
            if (!in_array((int)$row->status, $allowStatuses, true)) {
                $failures[] = ['id' => $orderId, 'ordernumber' => $row->ordernumber ?? '', 'error' => '订单状态不允许批量驳回'];
                continue;
            }

            // 加处理锁，避免并发重复驳回同一笔订单。
            $lockKey = CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $orderId;
            if (!Cache::add($lockKey, 1, now()->addMinutes(5))) {
                $skipCount++;
                continue;
            }

            try {
                if ((int)$row->child_count > 0 && $this->hasActiveChildOrder($orderId)) {
                    throw new RuntimeException('请先关闭子订单');
                }

                // 调用统一失败服务处理状态、余额冲正、缓存和回调。
                $failService->excute($orderId, $remark, $admin->id);
                $logService->excute($orderId, '批量驳回', '驳回人：' . $adminName . '[#' . $admin->id . ']，原因：' . $remark, 'debug');
                $successCount++;
            } catch (Throwable $e) {
                $failures[] = ['id' => $orderId, 'ordernumber' => $row->ordernumber ?? '', 'error' => $e->getMessage()];
            } finally {
                Cache::forget($lockKey);
            }
        }

        // 记录后台批量操作结果，方便后续排查部分失败。
        app(SystemLogService::class)->logAction(
            actionKey: 'transfer.order.batch_fail',
            text: '批量驳回代付提交',
            subject: null,
            properties: [
                'ids' => $keys,
                'success_count' => $successCount,
                'skip_count' => $skipCount,
                'fail_count' => count($failures),
                'failures' => $failures,
            ],
            remark: '批量驳回提交',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $admin
        );

        $message = '批量驳回完成，成功' . $successCount . '笔，跳过' . $skipCount . '笔，失败' . count($failures) . '笔';
        if (!empty($failures)) {
            return $this->response()->warning($message)->refresh();
        }

        return $this->response()->success($message)->refresh();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('transfer-order-batch-fail');
    }

    public function form()
    {
        $this->textarea('remark', '驳回原因')->required();
        $this->hidden('id')->attribute('id', 'transfer-id');
    }

    public function default()
    {
        return [];
    }

    private function hasActiveChildOrder(int $orderId): bool
    {
        return TransferOrder::query()->where('pid', $orderId)->whereIn('status', [1, 2, 3, 6])->exists();
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
