<?php

namespace App\Admin\Forms\AgentBalanceLog;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Agent\AgentBalanceChangeService;

class AddBalance extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            $agentId = intval($this->payload['agent_id'] ?? 0);
            $remark = trim((string)($input['remark'] ?? ''));
            $amount = floatval($input['amount'] ?? 0);
            $google2faCode = $input['google_2fa_code'] ?? null;

            if ($agentId <= 0) throw new \Exception('代理参数错误');
            if ($amount <= 0) throw new \Exception('金额必须大于0');
            app(AdminGoogle2faService::class)->verify($google2faCode);

            DB::transaction(function () use ($adminUser, $agentId, $amount, $remark) {
                $agent = AgentUser::query()->find($agentId, ['id']);
                if (!$agent) {
                    throw new \Exception('非法操作');
                }

                app(AgentBalanceChangeService::class)->excute([
                    'mid' => 0,
                    'agent_id' => $agent->id,
                    'amount' => $amount,
                    'type' => 4,
                    'action_agent_id' => $adminUser->id,
                    'type_id' => $agent->id,
                    'remark' => $remark,
                ]);

                $desc = sprintf('手动增项 商户代理余额 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'agent.balance.add',
                    text: '手动增项 商户代理余额',
                    subject: $agent,
                    properties: [
                        'agent_user_id' => $agent->id,
                        'amount' => $amount,
                        'remark' => $remark,
                    ],
                    remark: $desc,
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $adminUser
                );
            });

            return $this->response()->success('操作成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-agent-balance-log-add');
    }

    public function form()
    {
        $this->display('name', '代理');
        $this->text('amount', '增项金额')->rules(['numeric', 'min:0.01', 'max:9999999', new DecimalTwoPlaces()], ['numeric' => '增项金额不合法', 'min' => '增项金额不能小于0.01', 'max' => '增项金额不合法'])->required();
        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $agentId = intval($this->payload['agent_id'] ?? 0);
        $agent = AgentUser::query()->find($agentId, ['id', 'name']);

        return [
            'name' => optional($agent)->name,
            'amount' => '',
            'remark' => '',
        ];
    }
}
