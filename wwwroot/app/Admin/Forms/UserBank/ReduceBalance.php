<?php

namespace App\Admin\Forms\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
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

class ReduceBalance extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('user-bank-balance-reduce')) {
                throw new RuntimeException('无收款卡金额减项权限');
            }

            $id = (int)($this->payload['id'] ?? 0);
            $remark = trim((string)($input['remark'] ?? ''));
            $amount = (float)($input['amount'] ?? 0);
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('收款卡参数错误');
            }
            if ($amount <= 0) {
                throw new RuntimeException('金额必须大于0');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            DB::transaction(function () use ($admin, $id, $amount, $remark) {
                $userBank = UserBank::query()->whereKey($id)->lockForUpdate()->first(['id', 'user_id', 'name', 'balance_amount']);
                if (!$userBank) {
                    throw new RuntimeException('非法操作');
                }

                // 收款卡减项允许扣成负数，并按原逻辑同步扣减金主代收账户。
                app(UserBankBalanceChangeService::class)->excute([
                    'user_id' => $userBank->user_id,
                    'user_bank_id' => $userBank->id,
                    'amount' => -$amount,
                    'type' => 3,
                    'action_admin_id' => $admin->id,
                    'remark' => $remark,
                ]);
                app(UserBalanceChangeService::class)->excute([
                    'user_id' => $userBank->user_id,
                    'type' => 5,
                    'action_user_id' => $admin->id,
                    'remark' => $remark,
                    'amount' => $amount,
                    'type_id' => $userBank->id,
                ]);

                $desc = sprintf('收款卡减项 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'userbank.balance.reduce',
                    text: '收款卡减项',
                    subject: $userBank,
                    properties: [
                        'user_bank_id' => $userBank->id,
                        'user_id' => $userBank->user_id,
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
        return Admin::user()->can('user-bank-balance-reduce');
    }

    public function form()
    {
        $this->display('name', '收款人姓名');
        $this->text('amount', '减项金额')->rules(['required', 'numeric', 'min:0.01', 'max:9999999', new DecimalTwoPlaces()], ['required' => '请输入减项金额', 'numeric' => '减项金额不合法', 'min' => '减项金额必须大于0', 'max' => '减项金额不合法'])->required();
        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $userBank = UserBank::query()->whereKey($id)->first(['id', 'name', 'balance_amount']);

        return [
            'name' => optional($userBank)->name,
            'amount' => optional($userBank)->balance_amount,
            'remark' => '',
            'google_2fa_code' => '',
        ];
    }
}
