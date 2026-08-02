<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use App\Models\MerchantTelegramAdmin;
use App\Services\Common\SystemLogService;

class DeleteTelegramAdmin extends RowAction
{
    protected $title = '<i class="feather icon-trash-2"></i> 删除';

    public function handle()
    {
        $telegramAdmin = MerchantTelegramAdmin::query()->find($this->getKey());
        if (!$telegramAdmin) {
            return $this->response()->error('商户群管理员不存在');
        }

        app(SystemLogService::class)->logAction(
            actionKey: 'merchant.telegram_admin.delete',
            text: '删除 商户群管理员授权',
            subject: $telegramAdmin,
            properties: [
                'id' => $telegramAdmin->id,
                'mid' => $telegramAdmin->mid,
                'telegram_group_id' => $telegramAdmin->telegram_group_id,
                'telegram_user_id' => $telegramAdmin->telegram_user_id,
                'telegram_username' => $telegramAdmin->telegram_username,
                'telegram_name' => $telegramAdmin->telegram_name,
                'reviewed_by' => $telegramAdmin->reviewed_by,
                'reviewed_telegram_user_id' => $telegramAdmin->reviewed_telegram_user_id,
                'reviewed_telegram_name' => $telegramAdmin->reviewed_telegram_name,
            ],
            remark: '删除 商户群管理员授权',
            logType: 'operation',
            actionMethod: 'DELETE',
            appType: 'admin',
            user: Admin::user()
        );

        $telegramAdmin->delete();

        return $this->response()->success('删除成功')->refresh();
    }

    public function confirm()
    {
        return ['确认删除该商户群管理员？', '删除后将无法继续确认商户群操作。'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-user-delete-telegram-admin');
    }
}
