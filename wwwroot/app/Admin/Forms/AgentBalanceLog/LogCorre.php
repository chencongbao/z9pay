<?php

namespace App\Admin\Forms\AgentBalanceLog;

use Throwable;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Models\AgentBalanceLog;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Agent\AgentBalanceChangeService;

class LogCorre extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            $id = (int)($this->payload['id'] ?? 0);
            $remark = trim((string)($input['remark'] ?? ''));
            $google2faCode = $input['google_2fa_code'] ?? null;

            if ($id <= 0) throw new \Exception('流水参数错误');
            app(AdminGoogle2faService::class)->verify($google2faCode);

            DB::transaction(function () use ($adminUser, $id, $remark) {
                // 锁定原流水，避免并发重复冲正。
                $log = AgentBalanceLog::query()->whereKey($id)->lockForUpdate()->first([
                    'id', 'mid', 'agent_id', 'amount', 'type', 'is_corre', 'remark', 'ordernumber',
                ]);

                if (!$log) {
                    throw new \Exception('流水不存在');
                }

                if (!in_array((int)$log->type, [3, 4], true)) {
                    throw new \Exception('当前流水类型不支持冲正');
                }

                if ((int)$log->is_corre === 1) {
                    throw new \Exception('当前流水已冲正，请勿重复操作');
                }

                // 生成反向流水，金额方向由余额服务按 type 统一处理。
                $service = app(AgentBalanceChangeService::class);
                $service->excute([
                    'mid' => $log->mid,
                    'agent_id' => $log->agent_id,
                    'amount' => abs((float)$log->amount),
                    'type' => 6,
                    'action_agent_id' => $adminUser->id,
                    'type_id' => $log->id,
                    'remark' => $this->buildReverseRemark($log->id, $remark),
                    'ordernumber' => $log->ordernumber,
                ]);

                $correLogId = (int)$service->balance_log_id;
                if ($correLogId <= 0) {
                    throw new \Exception('冲正流水生成失败');
                }

                $originRemark = trim((string)$log->remark);
                $originAppend = '已冲正[' . now()->toDateTimeString() . ']，对应流水#' . $correLogId;
                if ($remark !== '') {
                    $originAppend .= '，备注：' . $remark;
                }

                // 关联原流水和冲正流水，便于后续追溯。
                AgentBalanceLog::query()->whereKey($log->id)->update([
                    'is_corre' => 1,
                    'corre_log_id' => $correLogId,
                    'remark' => $originRemark ? ($originRemark . '；' . $originAppend) : $originAppend,
                ]);

                AgentBalanceLog::query()->whereKey($correLogId)->update([
                    'is_corre' => 0,
                    'corre_log_id' => $log->id,
                ]);

                app(SystemLogService::class)->logAction(
                    actionKey: 'agent.balance.log.corre',
                    text: '商户代理流水冲正',
                    subject: $log,
                    properties: [
                        'agent_balance_log_id' => $log->id,
                        'corre_log_id' => $correLogId,
                        'agent_id' => $log->agent_id,
                        'type' => $log->type,
                        'reverse_type' => 6,
                        'amount' => $log->amount,
                        'remark' => $remark,
                    ],
                    remark: '商户代理流水冲正',
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $adminUser
                );
            });

            return $this->response()->success('冲正成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-agent-balance-log-corre');
    }

    public function form()
    {
        $this->display('id', '流水ID');
        $this->display('agent_name', '代理');
        $this->display('type_text', '交易类型');
        $this->display('amount', '交易金额');
        $this->display('remark_old', '原备注');
        $this->textarea('remark', '冲正备注')->rules('required|max:200', ['required' => '冲正备注必填', 'max' => '冲正备注过长'])->required();

        app(AdminGoogle2faService::class)->appendField($this);

        $this->confirm('确认冲正', '确认提交当前商户代理流水冲正操作？');
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $log = AgentBalanceLog::query()->with(['agent_user:id,name'])->find($id, ['id', 'agent_id', 'type', 'amount', 'remark']);

        return [
            'id' => optional($log)->id,
            'agent_name' => optional($log?->agent_user)->name,
            'type_text' => config('default.agent_balance_type')[$log->type ?? 0] ?? '',
            'amount' => optional($log)->amount,
            'remark_old' => optional($log)->remark,
            'remark' => '',
            'google_2fa_code' => '',
        ];
    }

    private function buildReverseRemark(int $originLogId, string $remark): string
    {
        $reverseRemark = '冲正原流水#' . $originLogId;
        if ($remark !== '') {
            $reverseRemark .= '，备注：' . $remark;
        }

        return $reverseRemark;
    }
}
