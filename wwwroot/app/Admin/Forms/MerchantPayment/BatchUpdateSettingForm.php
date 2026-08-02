<?php

namespace App\Admin\Forms\MerchantPayment;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantPayment;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Cache\MerchantPayment\RefreshMerchantPaymentRateCacheService;

class BatchUpdateSettingForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $keys = $this->normalizeIds($input['id'] ?? []);
            if (empty($keys)) {
                throw new RuntimeException('请选择数据项');
            }

            $minAmount = (float) bob_amount_format($input['min_limit_amount'] ?? 0);
            $maxAmount = (float) bob_amount_format($input['max_limit_amount'] ?? 0);
            if ($maxAmount < $minAmount) {
                throw new RuntimeException('单笔最高限额必须大于等于单笔最低限额');
            }

            $changes = [
                'min_limit_amount' => bob_amount_format($minAmount),
                'max_limit_amount' => bob_amount_format($maxAmount),
            ];

            $result = DB::transaction(function () use ($keys, $changes, $admin) {
                $items = MerchantPayment::query()->whereKey($keys)->get(['id', 'merchant_user_id', 'payment_id']);
                if ($items->isEmpty()) {
                    throw new RuntimeException('未找到可修改的数据项');
                }

                $existsIds = $items->pluck('id')->all();
                $cacheGroups = $items->groupBy('merchant_user_id')->map(function ($items) {
                    return $items->pluck('payment_id')->map(fn ($paymentId) => intval($paymentId))->unique()->values()->all();
                })->all();

                MerchantPayment::query()->whereKey($existsIds)->update($changes);
                $savedCount = $items->count();

                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.payment.batch_update_limit',
                    text: '批量修改 通道费率限额',
                    subject: null,
                    properties: [
                        'ids' => $existsIds,
                        'changes' => $changes,
                        'saved_count' => $savedCount,
                    ],
                    remark: '批量修改 通道费率限额',
                    logType: 'operation',
                    actionMethod: 'PUT',
                    appType: 'admin',
                    user: $admin
                );

                return [
                    'saved_count' => $savedCount,
                    'cache_groups' => $cacheGroups,
                ];
            });

            $this->refreshMerchantPaymentRateCache($result['cache_groups']);

            return $this->response()->success('修改成功，共修改' . $result['saved_count'] . '条')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-payment-batch-limit');
    }

    public function form()
    {
        $this->number('min_limit_amount', '单笔最低限额')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '单笔最低限额0-999999999'])->default(0)->required();
        $this->number('max_limit_amount', '单笔最高限额')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:min_limit_amount'], ['numeric' => '数值不合法', 'between' => '单笔最高限额0-999999999', 'gte' => '单笔充值最高限额必须大于等于单笔充值最低限额'])->default(0)->required();
        $this->hidden('id')->attribute('id', 'merchant-payment-id');
    }

    public function default()
    {
        return [
            'min_limit_amount' => 0,
            'max_limit_amount' => 0,
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

    // 设置主键为复选框选中的行ID数组
    action.options.key = key;
}
JS;
    }

    private function normalizeIds($ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        return array_values(array_unique(array_filter(array_map('intval', (array) $ids), fn ($id) => $id > 0)));
    }

    private function refreshMerchantPaymentRateCache(array $groups): void
    {
        $service = app(RefreshMerchantPaymentRateCacheService::class);
        foreach ($groups as $mid => $paymentIds) {
            try {
                $service->excute($mid, $paymentIds);
            } catch (Throwable $e) {
                app(SystemNoticeService::class)->warning('merchant_payment_rate_cache_refresh_failed', [
                    'error' => '批量修改商户支付限额后刷新商户支付费率缓存失败',
                    'merchant_user_id' => intval($mid),
                    'payment_ids' => $paymentIds,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
