<?php

namespace App\Admin\Actions\Grid\User;

use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\Cache;
use App\Services\Common\SystemLogService;
use App\Services\Cache\CacheConstPrefixService;

class ResetTelegram extends RowAction
{
    protected $title = '<i class="feather icon-gitlab"></i> 解绑机器人';

    public function handle()
    {
        $model = User::query()->where('id', $this->getKey())->first(['id', 'username', 'name', 'telegram_group_id', 'telegram_user_id']);
        if (!$model) {
            return $this->response()->error('非法操作');
        }

        $oldTelegramGroupId = intval($model->telegram_group_id);
        $oldTelegramUserId = intval($model->telegram_user_id);
        if ($oldTelegramGroupId <= 0 && $oldTelegramUserId <= 0) {
            return $this->response()->error('当前账号未绑定机器人');
        }

        $model->telegram_group_id = 0;
        $model->telegram_user_id = 0;
        if (!$model->save()) {
            return $this->response()->error('解绑失败');
        }

        $this->clearTelegramBindCache($oldTelegramGroupId, $oldTelegramUserId);

        $name = (string)($model->name ?? '');
        $username = (string)($model->username ?? '');
        $remarkParts = array_filter([
            $name !== '' ? '名称:' . $name : null,
            $username !== '' ? '账号:' . $username : null,
            $oldTelegramGroupId > 0 ? '原Telegram群ID:' . $oldTelegramGroupId : null,
            $oldTelegramUserId > 0 ? '原Telegram用户ID:' . $oldTelegramUserId : null,
        ]);
        $remark = $remarkParts ? ('解绑 金主(代理)机器人（' . implode('，', $remarkParts) . '）') : '解绑 金主(代理)机器人';

        app(SystemLogService::class)->logAction(
            actionKey: 'user.reset_telegram',
            text: '解绑 金主(代理)机器人',
            subject: $model,
            properties: [
                'user_id' => $model->id,
                'username' => $username,
                'name' => $name,
                'old_telegram_group_id' => $oldTelegramGroupId,
                'old_telegram_user_id' => $oldTelegramUserId,
            ],
            remark: $remark,
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('解绑成功')->refresh();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-unbind-telegram');
    }

    private function clearTelegramBindCache(int $chatId, int $fromId): void
    {
        if ($chatId !== 0) {
            $this->clearGroupTypeCache($chatId);
        }

        if ($fromId > 0) {
            $this->clearGroupTypeCache($fromId);
            Cache::forget(CacheConstPrefixService::TELEGRAM_GROUP_AND_USER_ID . $fromId);
        }

        if ($chatId !== 0 && $fromId > 0) {
            Cache::forget(CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $fromId . '_missing_' . $chatId);
        }
    }

    private function clearGroupTypeCache(int $id): void
    {
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $id;
        foreach (['', '_id', '_ids', '_name', '_type'] as $suffix) {
            Cache::forget($key . $suffix);
        }
    }

    public function confirm()
    {
        return ['提示?', '确认操作？'];
    }
}
