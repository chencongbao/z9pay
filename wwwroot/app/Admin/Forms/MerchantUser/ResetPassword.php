<?php

namespace App\Admin\Forms\MerchantUser;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantUser;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;

class ResetPassword extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('merchant-user-reset-password')) {
                throw new RuntimeException('非法操作');
            }

            $id = intval($this->payload['id'] ?? 0);
            $password = (string)($input['password'] ?? '');
            $passwordConfirm = (string)($input['password_confirm'] ?? '');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('商户参数错误');
            }
            if ($password !== $passwordConfirm) {
                throw new RuntimeException('两次密码输入不一致');
            }

            $user = MerchantUser::query()
                ->with(['merchant_info' => function ($query) {
                    $query->select(['merchant_user_id', 'name', 'coder']);
                }])
                ->whereKey($id)
                ->first(['id']);
            if (!$user) {
                throw new RuntimeException('商户不存在');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);
            $updated = MerchantUser::query()->whereKey($user->id)->update(['password' => bcrypt($password), 'session_id' => '']);
            if (!$updated) {
                throw new RuntimeException('密码更新失败');
            }

            $merchantInfo = $user->merchant_info;
            $nickname = (string)($merchantInfo->name ?? '');
            $coder = (string)($merchantInfo->coder ?? '');
            $remarkParts = array_filter([
                $nickname !== '' ? '昵称:' . $nickname : null,
                $coder !== '' ? '编码:' . $coder : null,
            ]);
            $remark = $remarkParts ? ('重置 商户密码（' . implode('，', $remarkParts) . '）') : '重置 商户密码';
            app(SystemLogService::class)->logAction(
                actionKey: 'merchant.user.reset_password',
                text: '重置 商户密码',
                subject: $user,
                properties: [
                    'merchant_user_id' => $user->id,
                    'merchant_name' => $nickname,
                    'coder' => $coder,
                ],
                remark: $remark,
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $adminUser
            );

            return $this->response()->success('修改成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-user-reset-password');
    }

    public function form()
    {
        $this->display('name', '商户名称');
        $this->password('password', '新密码')->minLength(6)->maxLength(20)->rules(['required', 'min:6', 'max:20', 'regex:/[A-Z]/'], ['required' => '请输入新密码', 'min' => '密码至少6位', 'max' => '密码最多20位', 'regex' => '密码至少包含一个大写字母'])->help('密码至少6位，且至少包含一个大写字母');
        $this->password('password_confirm', '确认密码')->same('password')->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        if ($id <= 0) {
            return [
                'name' => '',
                'password' => '',
                'password_confirm' => '',
            ];
        }

        $user = MerchantUser::query()
            ->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'name']);
            }])
            ->whereKey($id)
            ->first(['id']);

        return [
            'name' => optional(optional($user)->merchant_info)->name,
            'password' => '',
            'password_confirm' => '',
        ];
    }
}
