<?php

namespace App\Admin\Actions\Grid\Admin;

use Dcat\Admin\Admin;
use App\Models\AdminUser;
use Dcat\Admin\Grid\RowAction;
use App\Services\Common\SystemLogService;
use App\Services\Throttles\AdminUserLoginThrottleService;

class UnlockUser extends RowAction
{
    protected $title = '<i class="feather icon-power"></i> 解锁登录';

    public function handle()
    {
        $user = AdminUser::query()->find($this->getKey(), ['id', 'username', 'name']);
        if (! $user) {
            return $this->response()->error('管理员不存在');
        }

        app(AdminUserLoginThrottleService::class)->unlockByUsername($user->username);

        app(SystemLogService::class)->logAction(
            actionKey: 'admin.user.unlock',
            text: '解锁 管理员登录',
            subject: $user,
            properties: [
                'admin_user_id' => $user->id,
                'username' => $user->username,
            ],
            remark: '解锁 管理员登录',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('解锁成功');
    }

    public function confirm()
    {
        return ['确认操作?', '解锁管理员登录'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('system-auth-users');
    }
}
