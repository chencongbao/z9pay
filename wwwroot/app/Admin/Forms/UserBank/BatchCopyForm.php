<?php

namespace App\Admin\Forms\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;

class BatchCopyForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            if (Admin::user()->cannot('user-bank-batch-copy')) {
                throw new \Exception('无批量复制金主收款卡权限');
            }

            $keys = $this->parseIds($input['id'] ?? '');
            if (empty($keys)) {
                throw new \Exception('请选择数据项');
            }

            $paymentId = $input['payment_id'] ?? 0;
            $limintMinAmount = $input['limint_min_amount'] ?? 0;
            $limintMaxAmount = $input['limint_max_amount'] ?? 0;
            $collectionStatus = $input['collection_status'] ?? 0;

            // 批量复制收款卡，只覆盖表单指定的通道、状态和限额字段。
            $copiedCount = DB::transaction(function () use ($keys, $paymentId, $limintMinAmount, $limintMaxAmount, $collectionStatus) {
                $userBanks = UserBank::query()->whereKey($keys)->lockForUpdate()->get();
                if ($userBanks->count() !== count($keys)) {
                    throw new \Exception('部分收款卡不存在，请刷新后重试');
                }

                $this->checkDuplicates($userBanks, (int)$paymentId);

                foreach ($userBanks as $userBank) {
                    $userBank->replicate()->fill([
                        'payment_id' => $paymentId,
                        'collection_status' => $collectionStatus,
                        'limint_min_amount' => $limintMinAmount,
                        'limint_max_amount' => $limintMaxAmount,
                        'balance_amount' => 0,
                        'doing_status' => 0,
                        'last_collection_time' => null,
                        'today_stat_date' => null,
                        'today_total_amount' => 0,
                        'today_total_number' => 0,
                        'today_total_income' => 0,
                    ])->save();
                }

                return $userBanks->count();
            });

            app(SystemLogService::class)->logAction(
                actionKey: 'userbank.batch.copy',
                text: '批量复制 收款卡',
                subject: null,
                properties: [
                    'ids' => $keys,
                    'copied_count' => $copiedCount,
                    'payment_id' => $paymentId,
                    'collection_status' => $collectionStatus,
                    'limint_min_amount' => $limintMinAmount,
                    'limint_max_amount' => $limintMaxAmount,
                ],
                remark: '批量复制 收款卡',
                logType: 'operation',
                actionMethod: 'POST',
                appType: 'admin',
                user: Admin::user()
            );

            return $this->response()->success('复制成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-batch-copy');
    }

    public function form()
    {
        $this->hidden('id')->attribute('id', 'user-bank-ids');
        $this->select('payment_id', '通道类型')->options(collect(config('payment'))->pluck('name', 'id'))->default(1)->disableClearButton()->required();
        $this->number('limint_min_amount', '单笔最低限额')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '数值不合法'])->default(0)->required()->help('0为不限制');
        $this->number('limint_max_amount', '单笔最高限额')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces(), 'gte:limint_min_amount'], ['numeric' => '数值不合法', 'between' => '数值不合法', 'gte' => '单笔最高限额必须大于等于单笔最低限额'])->default(0)->required()->help('0为不限制');
        $this->radio('collection_status', '收款状态')->options([0 => '收单停止', 1 => '收单启动']);
    }

    public function default()
    {
        return [
            'payment_id' => 1,
            'limint_min_amount' => 0,
            'limint_max_amount' => 0,
            'collection_status' => 1,
        ];
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

    private function parseIds($ids): array
    {
        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    private function checkDuplicates($userBanks, int $paymentId): void
    {
        $cardNos = $userBanks
            ->map(fn (UserBank $userBank) => trim((string)$userBank->card_no))
            ->filter()
            ->values();

        if ($cardNos->isEmpty()) {
            return;
        }

        $duplicatedCardNo = $cardNos->duplicates()->first();
        if ($duplicatedCardNo) {
            throw new \Exception('批量复制失败：所选收款卡中存在重复收款账号【' . $duplicatedCardNo . '】');
        }

        $exists = UserBank::query()
            ->whereIn('card_no', $cardNos->all())
            ->lockForUpdate()
            ->get(['id', 'card_no', 'payment_id'])
            ->firstWhere('payment_id', $paymentId);

        if ($exists) {
            throw new \Exception('批量复制失败：收款账号【' . $exists->card_no . '】在目标通道下已存在');
        }
    }
}
