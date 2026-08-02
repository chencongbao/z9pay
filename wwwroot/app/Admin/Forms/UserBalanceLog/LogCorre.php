<?php

namespace App\Admin\Forms\UserBalanceLog;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\UserBank;
use Dcat\Admin\Widgets\Form;
use App\Models\UserBalanceLog;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\User\UserBalanceChangeService;
use App\Services\UserBank\UserBankBalanceChangeService;

class LogCorre extends Form implements LazyRenderable
{
    use LazyWidget;

    protected array $reverseTypeMap = [
        2 => 3,
        3 => 2,
        5 => 6,
        6 => 5,
        8 => 9,
        9 => 8,
    ];

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('user-balance-log-corre')) {
                throw new RuntimeException('无金主流水冲正权限');
            }

            $id = (int)($this->payload['id'] ?? 0);
            $remark = trim((string)($input['remark'] ?? ''));
            $google2faCode = (string)($input['google_2fa_code'] ?? '');
            $userBankId = (int)($input['user_bank_id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('流水参数错误');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            DB::transaction(function () use ($admin, $id, $remark, $userBankId) {
                // 锁定原流水，避免并发重复冲正。
                $log = UserBalanceLog::query()->whereKey($id)->where('is_agent', 0)->lockForUpdate()->first([
                    'id', 'mid', 'user_id', 'user_bank_id', 'amount', 'type', 'is_corre', 'remark', 'ordernumber', 'order_type',
                ]);

                if (!$log) {
                    throw new RuntimeException('流水不存在');
                }

                $type = (int)$log->type;
                if (!isset($this->reverseTypeMap[$type])) {
                    throw new RuntimeException('当前流水类型不支持冲正');
                }
                if ((int)$log->is_corre === 1) {
                    throw new RuntimeException('当前流水已冲正，请勿重复操作');
                }

                $reverseType = $this->reverseTypeMap[$type];
                $reverseAmount = in_array($reverseType, [2, 6, 8], true) ? -abs((float)$log->amount) : abs((float)$log->amount);
                $needUserBank = in_array($type, [5, 6], true);
                $userBank = null;
                $userBankAmount = 0;
                $userBankType = 0;

                if ($needUserBank) {
                    if ($userBankId <= 0) {
                        throw new RuntimeException('请选择收款卡');
                    }

                    $userBank = UserBank::query()->whereKey($userBankId)->where('user_id', $log->user_id)->lockForUpdate()->first(['id', 'user_id', 'balance_amount']);
                    if (!$userBank) {
                        throw new RuntimeException('收款卡不存在');
                    }

                    $userBankAmount = $type === 5 ? abs((float)$log->amount) : -abs((float)$log->amount);
                    $userBankType = $type === 5 ? 2 : 3;
                    if ($userBankAmount < 0 && abs($userBankAmount) > (float)$userBank->balance_amount) {
                        throw new RuntimeException('收款卡余额不足，无法冲正');
                    }
                }

                $reverseRemark = $this->buildReverseRemark($log->id, $remark);

                // 生成金主反向流水，并在需要时同步冲正收款卡余额。
                $service = app(UserBalanceChangeService::class);
                $service->excute([
                    'mid' => $log->mid,
                    'user_id' => $log->user_id,
                    'amount' => $reverseAmount,
                    'type' => $reverseType,
                    'user_bank_id' => $userBank ? $userBank->id : (int)($log->user_bank_id ?? 0),
                    'action_user_id' => $admin->id,
                    'type_id' => $log->id,
                    'remark' => $reverseRemark,
                    'ordernumber' => $log->ordernumber,
                    'order_type' => $log->order_type,
                ]);

                $correLogId = (int)$service->balance_log_id;
                if ($correLogId <= 0) {
                    throw new RuntimeException('冲正流水生成失败');
                }

                if ($needUserBank) {
                    app(UserBankBalanceChangeService::class)->excute([
                        'user_id' => $log->user_id,
                        'user_bank_id' => $userBank->id,
                        'amount' => $userBankAmount,
                        'type' => $userBankType,
                        'type_id' => $log->id,
                        'action_admin_id' => $admin->id,
                        'remark' => $reverseRemark,
                    ]);
                }

                $originRemark = trim((string)$log->remark);
                $originAppend = $this->buildOriginAppend($correLogId, $remark);

                // 关联原流水和冲正流水，便于后续追溯。
                UserBalanceLog::query()->whereKey($log->id)->update([
                    'is_corre' => 1,
                    'corre_log_id' => $correLogId,
                    'remark' => $originRemark ? ($originRemark . '；' . $originAppend) : $originAppend,
                ]);

                UserBalanceLog::query()->whereKey($correLogId)->update([
                    'is_corre' => 0,
                    'corre_log_id' => $log->id,
                    'type' => 12,
                ]);

                app(SystemLogService::class)->logAction(
                    actionKey: 'user.balance.log.corre',
                    text: '金主流水冲正',
                    subject: $log,
                    properties: [
                        'user_balance_log_id' => $log->id,
                        'corre_log_id' => $correLogId,
                        'user_id' => $log->user_id,
                        'type' => $log->type,
                        'reverse_type' => $reverseType,
                        'amount' => $log->amount,
                        'remark' => $remark,
                    ],
                    remark: '金主流水冲正',
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $admin
                );
            });

            return $this->response()->success('冲正成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-balance-log-corre');
    }

    public function form()
    {
        $log = $this->getCurrentLog();

        $this->display('id', '流水ID');
        $this->display('user_name', '金主');
        $this->display('type_text', '交易类型');
        $this->display('amount', '交易金额');
        if ($log && in_array((int)$log->type, [5, 6], true)) {
            $this->select('user_bank_id', '金主收款卡')->options($this->getUserBankOptions())->help('默认返回原流水的收款卡；老流水未记录收款卡时请手动确认')->disableClearButton()->required();
        }
        $this->display('remark_old', '原备注');
        $this->textarea('remark', '冲正备注')->rules('required|max:200', ['required' => '冲正备注必填', 'max' => '冲正备注过长'])->required();

        app(AdminGoogle2faService::class)->appendField($this);

        $this->confirm('确认冲正', '确认提交当前金主流水冲正操作？');
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $log = UserBalanceLog::query()->whereKey($id)->where('is_agent', 0)->first(['id', 'user_id', 'user_bank_id', 'type', 'amount', 'remark']);
        $user = $log ? User::query()->whereKey($log->user_id)->first(['id', 'username', 'name']) : null;

        return [
            'id' => optional($log)->id,
            'user_name' => optional($user)->bname,
            'type_text' => config('default.user_balance_type')[$log->type ?? 0] ?? '',
            'amount' => optional($log)->amount,
            'user_bank_id' => (int)(optional($log)->user_bank_id ?: 0),
            'remark_old' => optional($log)->remark,
            'remark' => '',
            'google_2fa_code' => '',
        ];
    }

    protected function getUserBankOptions(): array
    {
        $log = $this->getCurrentLog();
        if (!$log) {
            return [];
        }

        return UserBank::query()->where('user_id', $log->user_id)->get(['id', 'name', 'card_no', 'balance_amount'])->pluck('bnamebalance', 'id')->toArray();
    }

    protected function getCurrentLog(): ?UserBalanceLog
    {
        $id = (int)($this->payload['id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        return UserBalanceLog::query()->whereKey($id)->where('is_agent', 0)->first(['id', 'user_id', 'type']);
    }

    private function buildReverseRemark(int $originLogId, string $remark): string
    {
        $reverseRemark = '冲正原流水#' . $originLogId;
        if ($remark !== '') {
            $reverseRemark .= '，备注：' . $remark;
        }

        return $reverseRemark;
    }

    private function buildOriginAppend(int $correLogId, string $remark): string
    {
        $originAppend = '已冲正[' . now()->toDateTimeString() . ']，对应流水#' . $correLogId;
        if ($remark !== '') {
            $originAppend .= '，备注：' . $remark;
        }

        return $originAppend;
    }
}
