<?php

namespace App\Admin\Forms\AgentUser;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantInfo;
use App\Models\AgentBalanceLog;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;

class Delete extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('merchant-agent-delete')) throw new \Exception('非法操作');

            $id = intval($this->payload['id'] ?? 0);
            $password = $input['password'] ?? '';
            $google2faCode = $input['google_2fa_code'] ?? null;

            if ($id <= 0) throw new \Exception('代理参数错误');
            if (!Hash::check($password, $adminUser->password)) throw new \Exception('操作人登录密码错误');
            app(AdminGoogle2faService::class)->verify($google2faCode);

            [$model, $deletedLogCount] = DB::transaction(function () use ($id) {
                $model = AgentUser::query()->whereKey($id)->lockForUpdate()->first(['id', 'name', 'username']);
                if (!$model) throw new \Exception('代理不存在');
                if (AgentUser::query()->where('pid', $model->id)->exists()) throw new \Exception('存在下级代理无法删除');
                if (MerchantInfo::query()->where('agent_user_id', $model->id)->exists()) throw new \Exception('存在下级商户无法删除');

                // 删除代理已有 agent.user.delete 专项日志；关闭模型通用删除日志，避免一次操作记录两条审计。
                $model->disableLogging()->delete();
                $deletedLogCount = AgentBalanceLog::query()->where('agent_id', $model->id)->delete();

                return [$model, $deletedLogCount];
            });

            app(SystemLogService::class)->logAction(
                actionKey: 'agent.user.delete',
                text: '删除 商户代理',
                subject: $model,
                properties: [
                    'agent_user_id' => $model->id,
                    'deleted_balance_logs' => $deletedLogCount,
                ],
                remark: '删除 商户代理',
                logType: 'operation',
                actionMethod: 'DELETE',
                appType: 'admin',
                user: $adminUser
            );

            return $this->response()->success('删除成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-agent-delete');
    }

    public function form()
    {
        $agent = AgentUser::query()->whereKey(intval($this->payload['id'] ?? 0))->first(['id', 'name', 'username']);

        $this->confirm('确认删除', '同时删除<代理流水>相关数据');
        $this->display('name', '代理名称')->default($agent?->name ?: '-');
        $this->display('username', '代理账号')->default($agent?->username ?: '-');
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        return [
            'password' => '',
            'google_2fa_code' => '',
        ];
    }
}
