<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

use Exception;
use Dcat\Admin\Admin;
use App\Models\MerchantChannel;
use Dcat\Admin\Grid\BatchAction;
use Illuminate\Support\Facades\DB;
use App\Services\Common\SystemLogService;
use App\Services\Cache\MerchantChannel\GetMerchantChannelListService;

abstract class BatchToggleAction extends BatchAction
{
    protected $field;

    protected $value;

    protected $confirmText;

    protected $actionKey;

    protected $logText;

    protected string $permission = 'merchant-channels';

    public function handle()
    {
        try {
            $keys = $this->normalizeIds($this->getKey());
            if (empty($keys)) {
                throw new Exception('请选择操作项');
            }

            if (!in_array($this->field, ['status', 'float_status'], true)) {
                throw new Exception('批量操作字段不合法');
            }

            $result = DB::transaction(function () use ($keys) {
                $items = MerchantChannel::query()
                    ->whereIn('id', $keys)
                    ->get(['id', 'merchant_user_id', 'payment_id']);

                if ($items->isEmpty()) {
                    throw new Exception('未找到可操作的数据项');
                }

                $cachePairs = $items->map(function ($item) {
                    return [
                        'merchant_user_id' => (int) $item->merchant_user_id,
                        'payment_id' => (int) $item->payment_id,
                    ];
                })->unique(fn ($item) => $item['merchant_user_id'] . '_' . $item['payment_id'])->values()->all();

                MerchantChannel::query()->whereIn('id', $keys)->update([$this->field => $this->value]);
                $savedCount = $items->count();

                app(SystemLogService::class)->logAction(
                    actionKey: $this->actionKey,
                    text: $this->logText,
                    subject: null,
                    properties: [
                        'ids' => $keys,
                        'field' => $this->field,
                        'value' => $this->value,
                        'saved_count' => $savedCount,
                    ],
                    remark: $this->logText,
                    logType: 'operation',
                    actionMethod: 'PUT',
                    appType: 'admin',
                    user: Admin::user()
                );

                return [
                    'saved_count' => $savedCount,
                    'cache_pairs' => $cachePairs,
                ];
            });

            $this->refreshMerchantChannelCache($result['cache_pairs']);

            return $this->response()->success('操作成功，共处理' . $result['saved_count'] . '条')->refresh();
        } catch (Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function confirm()
    {
        return ['确定操作?', $this->confirmText];
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

    private function normalizeIds($ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $ids), fn ($id) => $id > 0)));
    }

    private function refreshMerchantChannelCache(array $pairs): void
    {
        $service = app(GetMerchantChannelListService::class);
        foreach ($pairs as $pair) {
            $service->update($pair['merchant_user_id'], $pair['payment_id']);
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can($this->permission);
    }
}
