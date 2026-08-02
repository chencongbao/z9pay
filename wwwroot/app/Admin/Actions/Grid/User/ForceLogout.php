<?php

namespace App\Admin\Actions\Grid\User;

use App\Models\User;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Dcat\Admin\Grid\RowAction;
use App\Services\Common\SystemLogService;

class ForceLogout extends RowAction
{
    protected $title = '<i class="feather icon-power"></i> 强制退出';

    protected ?string $permission = null;

    public function __construct(?string $permission = null)
    {
        parent::__construct();

        $this->permission = $permission;
    }

    public function handle(Request $request)
    {
        $adminUser = Admin::user();
        if ($this->permission !== null && $adminUser->cannot($this->permission)) {
            return $this->response()->error('非法操作');
        }

        $user = User::query()->find($this->getKey(), ['id', 'username']);
        if (!$user) {
            return $this->response()->error('非法操作');
        }

        // 清理金主/代理登录令牌，强制其重新登录。
        $deletedTokens = $user->tokens()->delete();

        app(SystemLogService::class)->logAction(
            actionKey: 'user.force_logout',
            text: '强制 金主(代理)退出登录',
            subject: $user,
            properties: [
                'user_id' => $user->id,
                'username' => $user->username,
                'deleted_tokens' => $deletedTokens,
            ],
            remark: '强制 金主(代理)退出登录',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $adminUser
        );

        return $this->response()->success('操作成功');
    }

    public function confirm()
    {
        return ['确认操作?', '强制金主退出'];
    }

    protected function authorize($user): bool
    {
        return $this->permission === null || Admin::user()->can($this->permission);
    }
}
