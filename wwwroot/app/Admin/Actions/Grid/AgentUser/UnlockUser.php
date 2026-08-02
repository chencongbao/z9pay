<?php

namespace App\Admin\Actions\Grid\AgentUser;

use App\Models\AgentUser;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use App\Services\Common\SystemLogService;
use App\Services\Throttles\AgentUserLoginThrottleService;

class UnlockUser extends RowAction
{
    protected $title = '<i class="feather icon-power"></i> 解锁登录';

    public function handle()
    {
        if (Admin::user()->cannot('merchant-agent-unlock-login')) {
            return $this->response()->error('非法操作');
        }

        $user = AgentUser::query()->find($this->getKey(), ['id', 'username', 'name']);
        if (! $user) {
            return $this->response()->error('代理不存在');
        }

        app(AgentUserLoginThrottleService::class)->unlockByUsername($user->username);

        app(SystemLogService::class)->logAction(
            actionKey: 'agent.user.unlock',
            text: '解锁 商户代理登录',
            subject: $user,
            properties: [
                'agent_user_id' => $user->id,
                'username' => $user->username,
            ],
            remark: '解锁 商户代理登录',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('解锁成功');
    }

    public function confirm()
    {
        return ['确认操作?', '解锁商户代理登录'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-agent-unlock-login');
    }
}
