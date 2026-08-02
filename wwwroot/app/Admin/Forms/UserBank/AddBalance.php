<?php

namespace App\Admin\Forms\UserBank;

use Throwable;
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

class AddBalance extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('user-bank-balance-add')) {
                throw new \Exception('无收款卡金额加项权限');
            }

            $id = intval($this->payload['id'] ?? 0);
            $remark = trim((string)($input['remark'] ?? ''));
            $amount = floatval($input['amount'] ?? 0);
            $google2faCode = $input['google_2fa_code'] ?? null;

            if ($id <= 0) throw new \Exception('收款卡参数错误');
            if ($amount <= 0) throw new \Exception('金额必须大于0');
            app(AdminGoogle2faService::class)->verify($google2faCode);

            DB::transaction(function () use ($adminUser, $id, $amount, $remark) {
                $userBank = UserBank::query()->whereKey($id)->lockForUpdate()->first(['id', 'user_id', 'name']);
                if (!$userBank) throw new \Exception('非法操作');

                // 同步增加收款卡余额，并按原逻辑调整金主代收账户。
                app(UserBankBalanceChangeService::class)->excute([
                    'user_id' => $userBank->user_id,
                    'user_bank_id' => $userBank->id,
                    'amount' => $amount,
                    'type' => 2,
                    'action_admin_id' => $adminUser->id,
                    'remark' => $remark,
                ]);
                app(UserBalanceChangeService::class)->excute([
                    'user_id' => $userBank->user_id,
                    'type' => 6,
                    'action_user_id' => $adminUser->id,
                    'remark' => $remark,
                    'amount' => -$amount,
                    'type_id' => $userBank->id,
                ]);

                $desc = sprintf('收款卡加项 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'userbank.balance.add',
                    text: '收款卡加项',
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
        return Admin::user()->can('user-bank-balance-add');
    }

    public function form()
    {
        $this->display('name', '收款人姓名');
        $this->text('amount', '增项金额')->rules(['numeric', 'min:0.01', 'max:9999999', new DecimalTwoPlaces()], ['numeric' => '增项金额不合法', 'min' => '增项金额必须大于0', 'max' => '增项金额不合法'])->required();
        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        $userBank = UserBank::query()->find($id, ['id', 'name']);

        return [
            'name' => optional($userBank)->name,
            'amount' => '',
            'remark' => '',
        ];
    }
}
