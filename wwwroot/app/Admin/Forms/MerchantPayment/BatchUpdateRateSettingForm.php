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

class BatchUpdateRateSettingForm extends Form implements LazyRenderable
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

            $changes = $this->buildChanges($input);
            if (empty($changes)) {
                throw new RuntimeException('至少修改一项');
            }

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
                    actionKey: 'merchant.payment.batch_update_rate',
                    text: '批量修改 通道费率',
                    subject: null,
                    properties: [
                        'ids' => $existsIds,
                        'changes' => $changes,
                        'saved_count' => $savedCount,
                    ],
                    remark: '批量修改 通道费率',
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
        return Admin::user()->can('merchant-payment-batch-rate');
    }

    public function form()
    {
        $this->rate('pay_rate', '支付费率')->rules(['numeric', 'between:-1,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '支付费率-1-100'])->default(-1)->required()->help('-1表示不修改');
        $this->rate('agent1_rate', '一级代理费率')->rules(['numeric', 'between:-1,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '一级代理费率-1-100'])->default(-1)->required()->help('-1表示不修改');
        $this->rate('agent2_rate', '二级代理费率')->rules(['numeric', 'between:-1,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '二级代理费率-1-100'])->default(-1)->required()->help('-1表示不修改');
        $this->rate('agent3_rate', '三级代理费率')->rules(['numeric', 'between:-1,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '三级代理费率-1-100'])->default(-1)->required()->help('-1表示不修改');
        $this->hidden('id')->attribute('id', 'merchant-payment-id');
    }

    public function default()
    {
        return [
            'pay_rate' => -1,
            'agent1_rate' => -1,
            'agent2_rate' => -1,
            'agent3_rate' => -1,
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

    private function buildChanges(array $input): array
    {
        $changes = [];
        foreach (['pay_rate', 'agent1_rate', 'agent2_rate', 'agent3_rate'] as $field) {
            if ($this->shouldUpdateRate($input[$field] ?? -1)) {
                $changes[$field] = bob_amount_format($input[$field]);
            }
        }

        return $changes;
    }

    private function shouldUpdateRate($value): bool
    {
        return $value !== null && $value !== '' && (float) $value >= 0;
    }

    private function refreshMerchantPaymentRateCache(array $groups): void
    {
        $service = app(RefreshMerchantPaymentRateCacheService::class);
        foreach ($groups as $mid => $paymentIds) {
            try {
                $service->excute($mid, $paymentIds);
            } catch (Throwable $e) {
                app(SystemNoticeService::class)->warning('merchant_payment_rate_cache_refresh_failed', [
                    'error' => '批量修改商户支付费率后刷新商户支付费率缓存失败',
                    'merchant_user_id' => intval($mid),
                    'payment_ids' => $paymentIds,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
