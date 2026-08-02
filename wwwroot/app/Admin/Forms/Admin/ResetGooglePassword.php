<?php

namespace App\Admin\Forms\Admin;

use Dcat\Admin\Admin;
use App\Models\AdminUser;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Google\AdminResetGoogleService;

class ResetGooglePassword extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $id = $this->payload['id'] ?? 0;
            $password = $input['password'] ?? null;
            $google_2fa_code = $input['google_2fa_code'] ?? null;

            $user = AdminUser::query()->find($id, ['id', 'name', 'google_two_fa_bind', 'session_id']);
            if (!$user) {
                throw new \Exception('管理员不存在');
            }
            if (!Hash::check($password, Admin::user()->password)) {
                throw new \Exception('操作人登录密码错误');
            }

            app(\App\Services\Google\AdminGoogle2faService::class)->verify($google_2fa_code);
            app(AdminResetGoogleService::class)->resetAdmin($user);

            return $this->response()->success('重置成功，请让管理员退出账号，重新登录')->refresh();
        } catch (\Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('admin-user-reset-googlecode');
    }

    public function form()
    {
        $this->display('name', '管理员名称');
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(\App\Services\Google\AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = $this->payload['id'] ?? 0;
        $user = AdminUser::query()->find($id, ['id', 'name']);

        return [
            'name' => optional($user)->name,
            'password' => '',
            'google_2fa_code' => '',
        ];
    }
}
