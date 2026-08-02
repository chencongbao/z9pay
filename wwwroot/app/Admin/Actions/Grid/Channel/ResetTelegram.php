<?php

namespace App\Admin\Actions\Grid\Channel;

use Dcat\Admin\Admin;
use App\Models\Channel;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\Cache;
use App\Services\Common\SystemLogService;
use App\Services\Cache\CacheConstPrefixService;

class ResetTelegram extends RowAction
{
    protected $title = '<i class="feather icon-gitlab"></i> 解绑机器人';

    public function handle()
    {
        $model = Channel::query()->where('id', $this->getKey())->first(['id', 'code', 'name', 'telegram_user_id']);
        if (!$model) {
            return $this->response()->error('渠道不存在');
        }

        $oldTelegramUserId = intval($model->telegram_user_id);
        if ($oldTelegramUserId !== 0) {
            $this->clearGroupTypeCache($oldTelegramUserId);
        }

        $model->telegram_user_id = 0;
        if (!$model->save()) {
            return $this->response()->error('解绑失败');
        }

        $channelName = (string)($model->name ?? '');
        $remarkParts = array_filter([
            $channelName !== '' ? '名称:' . $channelName : null,
            !empty($oldTelegramUserId) ? '原Telegram会话ID:' . $oldTelegramUserId : null,
        ]);
        $remark = $remarkParts ? ('解绑 机器人（' . implode('，', $remarkParts) . '）') : '解绑 机器人';

        app(SystemLogService::class)->logAction(
            actionKey: 'channel.reset_telegram',
            text: '解绑 机器人',
            subject: $model,
            properties: [
                'channel_id' => $model->id,
                'channel_name' => $channelName,
                'old_telegram_user_id' => $oldTelegramUserId,
            ],
            remark: $remark,
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('操作成功')->refresh();
    }

    private function clearGroupTypeCache(int $chatId): void
    {
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $chatId;
        foreach (['', '_id', '_ids', '_name', '_type'] as $suffix) {
            Cache::forget($key . $suffix);
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('channel-reset-telegram');
    }

    public function confirm()
    {
        return ['提示?', '确认操作？'];
    }
}
