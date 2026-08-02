<?php

namespace App\Admin\Actions\Grid\User;

use App\Models\User;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Dcat\Admin\Grid\RowAction;
use App\Services\Common\SystemLogService;
use App\Services\Throttles\UserLoginThrottleService;

class UnlockUser extends RowAction
{
    protected $title = '<i class="feather icon-power"></i> 解锁登录';

    protected string $permission = 'users-index';

    public function __construct(string $permission = 'users-index')
    {
        parent::__construct();

        $this->permission = $permission;
    }

    public function handle(Request $request)
    {
        $adminUser = Admin::user();
        if ($adminUser->cannot($this->permission)) {
            return $this->response()->error('非法操作');
        }

        $user = User::query()->find($this->getKey(), ['id', 'username']);
        if (!$user) {
            return $this->response()->error('非法操作');
        }

        // 清理登录令牌并解除登录限流，让账号可以重新登录。
        $deletedTokens = $user->tokens()->delete();
        app(UserLoginThrottleService::class)->unlockByUsername($user->username);

        app(SystemLogService::class)->logAction(
            actionKey: 'user.unlock',
            text: '解锁 金主(代理)登录',
            subject: $user,
            properties: [
                'user_id' => $user->id,
                'username' => $user->username,
                'deleted_tokens' => $deletedTokens,
            ],
            remark: '解锁 金主(代理)登录',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $adminUser
        );

        return $this->response()->success('解锁成功');
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can($this->permission);
    }

    public function confirm()
    {
        return ['确认操作?', '解锁金主'];
    }
}
