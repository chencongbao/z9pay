<?php

namespace App\Admin\Forms\User;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\User\UserBalanceChangeService;

class AddCommissionBalance extends Form implements LazyRenderable
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

            $user = User::query()->whereKey($userId)->first(['id', 'name']);
            if (!$user) {
                throw new \Exception('非法操作');
            }

            DB::transaction(function () use ($admin, $user, $amount, $remark) {
                // 调用统一余额服务增加金主佣金，并写入操作日志。
                app(UserBalanceChangeService::class)->excute([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 3,
                    'action_user_id' => $admin->id,
                    'type_id' => $user->id,
                    'remark' => $remark,
                ]);

                $desc = sprintf('手动增项 金主佣金 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'user.commission.add',
                    text: '手动增项 金主佣金',
                    subject: $user,
                    properties: [
                        'user_id' => $user->id,
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
        return Admin::user()->can('user-commission-balance-add');
    }

    public function form()
    {
        $this->display('name', '金主');
        $this->text('amount', '增项金额')->rules(['required', 'numeric', 'min:0.01', 'max:9999999', new DecimalTwoPlaces()], ['required' => '请输入增项金额', 'numeric' => '增项金额不合法', 'min' => '增项金额必须大于0', 'max' => '增项金额不合法'])->required();
        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $userId = (int)($this->payload['id'] ?? 0);
        $user = User::query()->whereKey($userId)->first(['id', 'name', 'username']);

        return [
            'name' => optional($user)->bname,
            'amount' => '',
            'remark' => '',
        ];
    }
}
