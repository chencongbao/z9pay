<?php

namespace App\Admin\Forms\User;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\User\UserBalanceChangeService;

class ActionMethod extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $id = (int)($this->payload['id'] ?? 0);
            $google2faCode = (string)($input['google_2fa_code'] ?? '');
            $actionMethod = (int)($input['action_method'] ?? 0);
            $actionAmount = (float)($input['action_amount'] ?? 0);

            if ($id <= 0) {
                throw new \Exception('金主不存在');
            }
            if ($actionAmount <= 0) {
                throw new \Exception('金额必须大于0');
            }
            if (!in_array($actionMethod, [1, 2], true)) {
                throw new \Exception('请选择加减项');
            }

            $user = User::query()->whereKey($id)->first(['id', 'name']);
            if (!$user) {
                throw new \Exception('金主不存在');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            $type = $actionMethod === 1 ? 4 : 3;
            $amount = $actionMethod === 1 ? $actionAmount : -$actionAmount;
            $actionText = $actionMethod === 1 ? '加项' : '减项';

            app(UserBalanceChangeService::class)->excute([
                'mid' => 0,
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'action_user_id' => $admin->id,
                'type_id' => $user->id,
                'remark' => '手动' . $actionText,
            ]);

            app(SystemLogService::class)->logAction(
                actionKey: 'user.action_method',
                text: '金主余额手动' . $actionText,
                subject: $user,
                properties: [
                    'user_id' => $user->id,
                    'amount' => $actionAmount,
                    'type' => $type,
                    'action_method' => $actionMethod,
                ],
                remark: '金主余额手动' . $actionText,
                logType: 'operation',
                actionMethod: 'POST',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('修改成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function form()
    {
        $this->display('name', '金主名称');
        $this->select('action_method', '加减项')->options([1 => '加项', 2 => '减项'])->default(1)->rules(['required', 'in:1,2'], ['required' => '请选择加减项', 'in' => '请选择加减项'])->disableClearButton();
        $this->text('action_amount', '金额')->default(0)->rules(['required', 'numeric', 'min:0.01', 'max:99999', new DecimalTwoPlaces()], ['required' => '请输入金额', 'numeric' => '请输入合法的值', 'min' => '请输入合法的值', 'max' => '请输入合法的值'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $user = User::query()->whereKey($id)->first(['id', 'name']);

        return [
            'name' => optional($user)->name,
            'action_method' => 1,
            'action_amount' => 0,
            'google_2fa_code' => '',
        ];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('users-index');
    }
}
