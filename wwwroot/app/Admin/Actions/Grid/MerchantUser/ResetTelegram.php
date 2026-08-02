<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Http\Request;
use App\Models\MerchantInfo;
use App\Models\MerchantTelegramAdmin;
use Illuminate\Support\Facades\Cache;
use App\Services\Common\SystemLogService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ResetTelegram extends RowAction
{
    protected $title = '<i class="feather icon-gitlab"></i> 解绑机器人';

    public function handle(Request $request)
    {
        $model = MerchantInfo::query()->where('merchant_user_id', $this->getKey())->first(['merchant_user_id', 'telegram_group_id', 'name', 'coder']);
        if (!$model) {
            return $this->response()->error('非法操作');
        }

        $oldTelegramGroupId = intval($model->telegram_group_id);
        if ($oldTelegramGroupId === 0) {
            return $this->response()->error('商户未绑定机器人');
        }

        $model->telegram_group_id = 0;
        if (!$model->save()) {
            return $this->response()->error('解绑失败');
        }

        // 解绑商户群后同步清理群管理员授权，避免旧群成员继续拥有商户操作权限。
        $deletedTelegramAdminCount = MerchantTelegramAdmin::query()
            ->where('mid', $model->merchant_user_id)
            ->where('telegram_group_id', $oldTelegramGroupId)
            ->delete();

        // 解绑后清理 Telegram 群识别缓存，避免群里继续显示旧商户已绑定。
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $oldTelegramGroupId;
        foreach (['_id', '_ids', '_name', '_type'] as $suffix) {
            Cache::forget($key . $suffix);
        }
        Cache::forget(CacheConstPrefixService::TELEGRAM_GROUP_AND_MERCHAND_USER_ID . $oldTelegramGroupId);
        app(CacheMerchantBaseInfoService::class)->excute($model->merchant_user_id, true);

        $nickname = (string)($model->name ?? '');
        $coder = (string)($model->coder ?? '');
        $remarkParts = array_filter([
            $nickname !== '' ? '昵称:' . $nickname : null,
            $coder !== '' ? '编码:' . $coder : null,
        ]);
        $remark = $remarkParts ? ('解绑 机器人（' . implode('，', $remarkParts) . '）') : '解绑 机器人';

        app(SystemLogService::class)->logAction(
            actionKey: 'merchant.user.reset_telegram',
            text: '解绑 机器人',
            subject: $model,
            properties: [
                'merchant_user_id' => $model->merchant_user_id,
                'merchant_name' => $nickname,
                'coder' => $coder,
                'old_telegram_group_id' => $oldTelegramGroupId,
                'deleted_telegram_admin_count' => $deletedTelegramAdminCount,
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
        return Admin::user()->can('merchant-user-unbind-telegram');
    }

    public function confirm()
    {
        return ['提示?', '确认操作？'];
    }
}
