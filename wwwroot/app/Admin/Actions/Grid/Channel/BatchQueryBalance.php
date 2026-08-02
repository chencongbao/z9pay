<?php

namespace App\Admin\Actions\Grid\Channel;

use Throwable;
use App\Models\Channel;
use Dcat\Admin\Grid\BatchAction;
use App\Services\Channel\QueryChannelBalanceService;

class BatchQueryBalance extends BatchAction
{
    protected $title = '<button class="btn btn-primary"><i class="feather icon-search"></i> 批量查询余额</button>';

    public function handle()
    {
        $keys = array_filter((array)$this->getKey());

        if (empty($keys)) {
            return $this->response()->error('请选择操作项');
        }

        $successCount = 0;
        $failMessages = [];
        $service = app(QueryChannelBalanceService::class);

        foreach (Channel::query()->whereIn('id', $keys)->get(['id', 'name', 'classname']) as $channel) {
            try {
                $service->execute($channel);
                $successCount++;
            } catch (Throwable $e) {
                $failMessages[] = $channel->name . '：' . $e->getMessage();
            }
        }

        $failCount = count($failMessages);
        $message = "成功 {$successCount} 条";

        if ($failCount > 0) {
            $message .= "，失败 {$failCount} 条";
        }

        if (!empty($failMessages)) {
            $message .= '；' . implode('；', $failMessages);
        }

        return $this->response()->success($message)->refresh();
    }

    public function confirm()
    {
        return ['确定操作?', '批量查询选中渠道余额'];
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
