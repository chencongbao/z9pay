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

class UpdateCollectionMinMaxAmountForm extends Form implements LazyRenderable
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

            $minAmount = (float) bob_amount_format($input['collection_min_amount'] ?? 0);
            $maxAmount = (float) bob_amount_format($input['collection_max_amount'] ?? 0);

            if ($maxAmount < $minAmount) {
                throw new RuntimeException('代付单笔上限必须大于等于代付单笔下限');
            }

            $changes = [
                'collection_min_amount' => bob_amount_format($minAmount),
                'collection_max_amount' => bob_amount_format($maxAmount),
            ];

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
                    actionKey: 'merchant.channel.batch_update_collection_limit',
                    text: '批量修改 代付单笔限额',
                    subject: null,
                    properties: [
                        'ids' => $keys,
                        'changes' => $changes,
                        'saved_count' => $savedCount,
                    ],
                    remark: '批量修改 代付单笔限额',
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
        return Admin::user()->can('merchant-channel-batch-collection-limit');
    }

    public function form()
    {
        $this->number('collection_min_amount', '代付单笔下限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '数值不合法'])->default(0)->required();
        $this->number('collection_max_amount', '代付单笔上限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:collection_min_amount'], ['numeric' => '数值不合法', 'between' => '数值不合法', 'gte' => '代付单笔上限必须大于等于代付单笔下限'])->default(0)->required();
        $this->hidden('id')->attribute('id', 'merchant-channel-id');
    }

    public function default()
    {
        return [
            'collection_min_amount' => 0,
            'collection_max_amount' => 0,
        ];
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
                    'error' => '批量修改代付单笔限额后刷新商户通道缓存失败',
                    'merchant_user_id' => $pair['merchant_user_id'],
                    'payment_id' => $pair['payment_id'],
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
