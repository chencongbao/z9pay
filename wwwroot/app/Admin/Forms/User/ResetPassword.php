<?php

namespace App\Admin\Forms\User;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
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
            $admin = Admin::user();
            $id = (int)($this->payload['id'] ?? 0);
            $password = (string)($input['password'] ?? '');
            $passwordConfirm = (string)($input['password_confirm'] ?? '');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('金主参数错误');
            }
            if ($password !== $passwordConfirm) {
                throw new RuntimeException('两次密码输入不一致');
            }
            if (strlen($password) < 6 || strlen($password) > 20 || !preg_match('/[A-Z]/', $password)) {
                throw new RuntimeException('密码必须6-20位，且至少包含一个大写字母');
            }

            $user = User::query()->whereKey($id)->first(['id', 'name', 'username']);
            if (!$user) {
                throw new RuntimeException('金主不存在');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);
            $user->update(['password' => bcrypt($password), 'password_changed_at' => null]);
            $deletedTokens = $user->tokens()->delete();

            app(SystemLogService::class)->logAction(
                actionKey: 'user.reset_password',
                text: '重置 金主密码',
                subject: $user,
                properties: [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'deleted_tokens' => $deletedTokens,
                ],
                remark: '重置 金主密码',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('修改成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-reset-password');
    }

    public function form()
    {
        $this->display('name', '金主名称');
        $this->password('password', '新密码')->minLength(6)->maxLength(20)->rules(['required', 'min:6', 'max:20', 'regex:/[A-Z]/'], ['required' => '请输入新密码', 'min' => '密码至少6位', 'max' => '密码最多20位', 'regex' => '密码至少包含一个大写字母'])->help('密码至少6位，且至少包含一个大写字母');
        $this->password('password_confirm', '确认密码')->same('password')->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $user = User::query()->whereKey($id)->first(['id', 'name']);

        return [
            'name' => optional($user)->name,
            'password' => '',
            'password_confirm' => '',
            'google_2fa_code' => '',
        ];
    }
}
