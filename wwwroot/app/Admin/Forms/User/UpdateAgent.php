<?php

namespace App\Admin\Forms\User;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Cache\User\GetUserAgentListService;

class UpdateAgent extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $id = (int)($this->payload['id'] ?? 0);
            $newAgentId = (int)($input['new_agent_id'] ?? 0);
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('金主参数错误');
            }
            if ($newAgentId <= 0) {
                throw new RuntimeException('请选择新代理');
            }
            if ($id === $newAgentId) {
                throw new RuntimeException('新代理不能选择自己');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            $logData = DB::transaction(function () use ($id, $newAgentId) {
                $user = User::query()->with(['parent_user:id,name'])->whereKey($id)->lockForUpdate()->first(['id', 'pid', 'name', 'is_agent']);
                if (!$user) {
                    throw new RuntimeException('金主不存在');
                }
                if ((int)$user->is_agent !== 0) {
                    throw new RuntimeException('请选择金主账号');
                }

                $newAgent = User::query()->whereKey($newAgentId)->where('is_agent', 1)->first(['id', 'name']);
                if (!$newAgent) {
                    throw new RuntimeException('新代理不存在');
                }

                $oldAgentId = (int)$user->pid;
                $oldAgentName = (string)data_get($user->parent_user, 'name', '');
                if ($oldAgentId === $newAgentId) {
                    throw new RuntimeException('新代理与原代理一致');
                }

                // 调整闭包表代理关系，保证金主归属和层级关系同步更新。
                $user->moveTo($newAgentId);

                return [
                    'user' => $user,
                    'old_agent_id' => $oldAgentId,
                    'old_agent_name' => $oldAgentName,
                    'new_agent_id' => $newAgent->id,
                    'new_agent_name' => $newAgent->name,
                ];
            });

            app(SystemLogService::class)->logAction(
                actionKey: 'user.update_agent',
                text: '调整 金主代理',
                subject: $logData['user'],
                properties: [
                    'user_id' => $logData['user']->id,
                    'old_agent_id' => $logData['old_agent_id'],
                    'old_agent_name' => $logData['old_agent_name'],
                    'new_agent_id' => $logData['new_agent_id'],
                    'new_agent_name' => $logData['new_agent_name'],
                ],
                remark: sprintf('调整 金主代理（%s -> %s）', $logData['old_agent_name'] ?: '-', $logData['new_agent_name'] ?: '-'),
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('修改成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-update-agent');
    }

    public function form()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $agentOptions = collect(app(GetUserAgentListService::class)->excute())->reject(fn ($item) => (int)($item['id'] ?? 0) === $id)->mapWithKeys(fn ($item) => [(int)$item['id'] => '【' . $item['id'] . '】' . $item['name']])->all();

        $this->display('name', '金主名称');
        $this->display('agent_name', '原金主代理');
        $this->select('new_agent_id', '新金主代理')->options($agentOptions)->rules(['required', 'numeric', 'min:1'], ['required' => '请选择新代理', 'numeric' => '请选择新代理', 'min' => '请选择新代理'])->disableClearButton();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $user = User::query()->with(['parent_user:id,name'])->whereKey($id)->first(['id', 'pid', 'name']);

        return [
            'name' => optional($user)->name,
            'agent_name' => optional(optional($user)->parent_user)->name,
            'new_agent_id' => '',
            'google_2fa_code' => '',
        ];
    }
}
