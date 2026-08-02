<?php

namespace App\Admin\Forms\AgentUser;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
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
            if (Admin::user()->cannot('merchant-agent-reset-googlecode')) {
                throw new \Exception('非法操作');
            }

            $id = $this->payload['id'] ?? 0;
            $password = $input['password'] ?? null;
            $google2faCode = $input['google_2fa_code'] ?? null;

            $user = AgentUser::query()->find($id, ['id', 'name']);
            if (!$user) {
                throw new \Exception('代理不存在');
            }
            if (!Hash::check($password, Admin::user()->password)) {
                throw new \Exception('操作人登录密码错误');
            }

            app(\App\Services\Google\AdminGoogle2faService::class)->verify($google2faCode);
            app(AdminResetGoogleService::class)->resetAgent($user);

            return $this->response()->success('重置成功，请让代理退出账号，重新登录')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-agent-reset-googlecode');
    }

    public function form()
    {
        $this->display('name', '代理名称');
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(\App\Services\Google\AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = $this->payload['id'] ?? 0;
        $user = AgentUser::query()->find($id, ['id', 'name']);

        return [
            'name' => optional($user)->name,
            'password' => '',
            'google_2fa_code' => '',
        ];
    }
}
