<?php

namespace App\Admin\Forms\MerchantChannel;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantChannel;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Cache\MerchantChannel\GetMerchantChannelListService;

class BatchUpdatePayRateForm extends Form implements LazyRenderable
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

            $fee = $this->normalizeOptionalAmount($input['fee'] ?? -1);
            $depositFee = $this->normalizeOptionalAmount($input['deposit_fee'] ?? -1);

            $changes = array_filter([
                'fee' => $fee,
                'deposit_fee' => $depositFee,
            ], fn ($v) => $v !== null);

            if (empty($changes)) {
                throw new RuntimeException('至少修改一项');
            }

            $result = DB::transaction(function () use ($keys, $changes, $admin) {
                $items = MerchantChannel::query()
                    ->whereKey($keys)
                    ->get(['id', 'merchant_user_id', 'payment_id']);

                if ($items->isEmpty()) {
                    throw new RuntimeException('未找到可修改的数据项');
                }

                $cachePairs = $items->map(function ($item) {
                    return [
                        'merchant_user_id' => (int) $item->merchant_user_id,
                        'payment_id' => (int) $item->payment_id,
                    ];
                })
                    ->unique(fn ($item) => $item['merchant_user_id'] . '_' . $item['payment_id'])
                    ->values()
                    ->all();

                MerchantChannel::query()->whereKey($keys)->update($changes);
                $savedCount = $items->count();

                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.channel.batch_update_rate',
                    text: '批量修改 额外手续费',
                    subject: null,
                    properties: [
                        'ids' => $keys,
                        'changes' => $changes,
                        'saved_count' => $savedCount,
                    ],
                    remark: '批量修改 额外手续费',
                    logType: 'operation',
                    actionMethod: 'PUT',
                    appType: 'admin',
                    user: $admin
                );

                return [
                    'saved_count' => $savedCount,
                    'cache_pairs' => $cachePairs,
                ];
            });

            $this->refreshMerchantChannelCache($result['cache_pairs']);

            return $this->response()->success('修改成功，共修改' . $result['saved_count'] . '条')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-channel-batch-rate');
    }

    public function form()
    {
        $this->number('fee', '代付额外手续费')->rules(['numeric', 'between:-1,999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代付额外手续费-1-999999'])->required()->help('-1表示不修改，最多保留2位小数');
        $this->number('deposit_fee', '代收额外手续费')->rules(['numeric', 'between:-1,999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代收额外手续费-1-999999'])->required()->help('-1表示不修改，最多保留2位小数');
        $this->hidden('id')->attribute('id', 'merchant-channel-id');
    }

    public function default()
    {
        return [
            'fee' => -1,
            'deposit_fee' => -1,
        ];
    }

    private function normalizeOptionalAmount($value): ?float
    {
        if ($value === null || $value === '' || (float) $value < 0) {
            return null;
        }

        return (float) bob_amount_format($value);
    }

    private function normalizeIds($ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        return array_values(array_unique(array_filter(array_map('intval', (array) $ids), fn ($id) => $id > 0)));
    }

    private function refreshMerchantChannelCache(array $pairs): void
    {
        $service = app(GetMerchantChannelListService::class);
        foreach ($pairs as $pair) {
            try {
                $service->update($pair['merchant_user_id'], $pair['payment_id']);
            } catch (Throwable $e) {
                app(SystemNoticeService::class)->warning('merchant_channel_cache_refresh_failed', [
                    'error' => '批量修改商户通道额外手续费后刷新商户通道缓存失败',
                    'merchant_user_id' => $pair['merchant_user_id'],
                    'payment_id' => $pair['payment_id'],
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
