<?php

namespace App\Admin\Forms\User;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\User\UserBalanceChangeService;
use App\Services\UserBank\UserBankBalanceChangeService;

class ReduceDepositBalance extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $userId = (int)($this->payload['id'] ?? 0);
            $remark = trim((string)($input['remark'] ?? ''));
            $amount = (float)($input['amount'] ?? 0);
            $userBankId = (int)($input['user_bank_id'] ?? 0);
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($userId <= 0) {
                throw new \Exception('非法操作');
            }
            if ($userBankId <= 0) {
                throw new \Exception('请选择收款卡');
            }
            if ($amount <= 0) {
                throw new \Exception('金额必须大于0');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            DB::transaction(function () use ($admin, $userId, $userBankId, $amount, $remark) {
                $user = User::query()->whereKey($userId)->lockForUpdate()->first(['id', 'name', 'deposit_balance_amount']);
                if (!$user) {
                    throw new \Exception('非法操作');
                }

                $userBank = UserBank::query()->whereKey($userBankId)->where('user_id', $user->id)->lockForUpdate()->first(['id', 'user_id', 'balance_amount']);
                if (!$userBank) {
                    throw new \Exception('请选择收款卡');
                }
                // 代收账户减项允许扣成负数，只记录账户和收款卡的真实方向。
                app(UserBalanceChangeService::class)->excute([
                    'mid' => 0,
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 5,
                    'user_bank_id' => $userBank->id,
                    'action_user_id' => $admin->id,
                    'type_id' => $user->id,
                    'remark' => $remark,
                ]);
                app(UserBankBalanceChangeService::class)->excute([
                    'user_id' => $userBank->user_id,
                    'user_bank_id' => $userBank->id,
                    'amount' => -$amount,
                    'type' => 3,
                    'action_admin_id' => $admin->id,
                    'remark' => $remark,
                ]);

                $desc = sprintf('手动减项 金主代收账户 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'user.deposit.reduce',
                    text: '手动减项 金主代收账户',
                    subject: $user,
                    properties: [
                        'user_id' => $user->id,
                        'user_bank_id' => $userBank->id,
                        'amount' => $amount,
                        'remark' => $remark,
                    ],
                    remark: $desc,
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $admin
                );
            });

            return $this->response()->success('操作成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-collection-balance-reduce');
    }

    public function form()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $this->display('name', '金主');
        $this->text('amount', '减项金额')->rules(['required', 'numeric', 'min:0.01', 'max:9999999', new DecimalTwoPlaces()], ['required' => '请输入减项金额', 'numeric' => '减项金额不合法', 'min' => '减项金额必须大于0', 'max' => '减项金额不合法'])->required();
        $this->select('user_bank_id', '金主收款卡')->options(UserBank::query()->where('user_id', $id)->get(['id', 'name', 'card_no', 'balance_amount'])->pluck('bnamebalance', 'id'))->disableClearButton();
        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $userId = (int)($this->payload['id'] ?? 0);
        $user = User::query()->whereKey($userId)->first(['id', 'username', 'name', 'deposit_balance_amount']);

        return [
            'name' => optional($user)->bname,
            'amount' => floatval(optional($user)->deposit_balance_amount),
            'remark' => '',
        ];
    }
}
