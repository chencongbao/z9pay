<?php

namespace App\Admin\Forms\UserAgent;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Models\UserBalanceLog;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\User\GetUserAgentListService;

class Delete extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('user-agent-delete')) throw new \Exception('非法操作');

            $id = intval($this->payload['id'] ?? 0);
            $password = $input['password'] ?? null;
            $google2faCode = $input['google_2fa_code'] ?? null;
            if ($id <= 0) throw new \Exception('代理参数错误');
            if (!Hash::check($password, $adminUser->password)) throw new \Exception('操作人登录密码错误');

            app(AdminGoogle2faService::class)->verify($google2faCode);

            DB::transaction(function () use ($id, $adminUser) {
                $agent = User::query()->select(['id', 'username', 'name'])->whereKey($id)->lockForUpdate()->first();
                if (!$agent) throw new \Exception('代理不存在');

                if (User::query()->where('pid', $agent->id)->exists()) {
                    throw new \Exception('存在下级代理或金主无法删除');
                }

                $agentId = (int)$agent->id;

                // 删除代理账号及其余额流水，并记录后台操作日志。
                $agent->delete();
                $deletedBalanceLogs = UserBalanceLog::query()->where('user_id', $agent->id)->delete();

                app(SystemLogService::class)->logAction(
                    actionKey: 'user.agent.delete',
                    text: '删除 金主代理',
                    subject: $agent,
                    properties: [
                        'user_id' => $agent->id,
                        'username' => $agent->username ?? null,
                        'name' => $agent->name ?? null,
                        'deleted_balance_logs' => $deletedBalanceLogs,
                    ],
                    remark: sprintf('删除 金主代理（昵称:%s，账号:%s）', $agent->name ?? '-', $agent->username ?? '-'),
                    logType: 'operation',
                    actionMethod: 'DELETE',
                    appType: 'admin',
                    user: $adminUser
                );

                DB::afterCommit(function () use ($agentId) {
                    app(GetUserDetailService::class)->forget($agentId);
                    app(GetUserAgentListService::class)->excute(true);
                });
            });

            return $this->response()->success('删除成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function form()
    {
        $agent = User::query()->whereKey(intval($this->payload['id'] ?? 0))->first(['id', 'name', 'username']);

        $this->confirm('确认删除', '删除将不可恢复');
        $this->display('name', '代理名称')->default($agent?->name ?: '-');
        $this->display('username', '代理账号')->default($agent?->username ?: '-');
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-agent-delete');
    }

    public function default()
    {
        return [
            'password' => '',
            'google_2fa_code' => '',
        ];
    }
}
