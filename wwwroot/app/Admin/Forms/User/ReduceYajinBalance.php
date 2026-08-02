<?php

namespace App\Admin\Forms\User;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use App\Models\UserDepositDetail;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Jobs\CheckUserDepositAmountNoticeTelegramJob;

class ReduceYajinBalance extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $userId = (int)($this->payload['id'] ?? 0);
            $remark = trim((string)($input['remark'] ?? ''));
            $amount = (float)($input['amount'] ?? 0);
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($userId <= 0) {
                throw new \Exception('非法操作');
            }
            if ($amount <= 0) {
                throw new \Exception('金额必须大于0');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            // 锁定金主，扣减保证金余额并写入保证金明细。
            $handledUserId = DB::transaction(function () use ($admin, $userId, $amount, $remark) {
                $user = User::query()->whereKey($userId)->lockForUpdate()->first(['id', 'deposit_amount']);
                if (!$user) {
                    throw new \Exception('非法操作');
                }
                if ($amount > (float)$user->deposit_amount) {
                    throw new \Exception('减项金额不能大于押金余额');
                }

                $user->deposit_amount = bob_amount_format($user->deposit_amount - $amount);
                $user->save();

                UserDepositDetail::create([
                    'user_id' => $user->id,
                    'amount' => -$amount,
                    'admin_id' => $admin->id,
                    'remark' => $remark,
                    'balance_amount' => $user->deposit_amount,
                ]);

                $desc = sprintf('手动减项 金主保证金 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'user.deposit_amount.reduce',
                    text: '手动减项 金主保证金',
                    subject: $user,
                    properties: [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'remark' => $remark,
                        'balance_amount' => $user->deposit_amount,
                    ],
                    remark: $desc,
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $admin
                );

                return $user->id;
            });

            dispatch(new CheckUserDepositAmountNoticeTelegramJob($handledUserId))->onQueue('query')->afterCommit();
            return $this->response()->success('操作成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-deposit-balance-reduce');
    }

    public function form()
    {
        $this->display('name', '金主');
        $this->text('amount', '减项金额')->rules(['required', 'numeric', 'min:0.01', 'max:9999999', new DecimalTwoPlaces()], ['required' => '请输入减项金额', 'numeric' => '减项金额不合法', 'min' => '减项金额必须大于0', 'max' => '减项金额不合法'])->required();
        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $userId = (int)($this->payload['id'] ?? 0);
        $user = User::query()->whereKey($userId)->first(['id', 'username', 'name', 'deposit_amount']);

        return [
            'name' => optional($user)->bname,
            'amount' => floatval(optional($user)->deposit_amount),
            'remark' => '',
        ];
    }
}
