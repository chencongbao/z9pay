<?php

namespace App\AgentAdmin\Form;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;

class UpdatePassword extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $user = Admin::user();
            $password = $input['password'] ?? '';
            $google2faCode = $input['google_2fa_code'] ?? null;

            if (empty($password)) {
                return $this->response()->error(admin_trans_label('input_password'));
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);
            AgentUser::whereKey($user->id)->update(['password' => bcrypt($password)]);
            app(SystemLogService::class)->logAction(
                actionKey: 'agent.user.update_password',
                text: '修改 登录密码',
                subject: $user,
                properties: ['agent_user_id' => $user->id],
                remark: '修改 登录密码',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'agent',
                user: $user
            );

            return $this->response()->success(admin_trans_label('update_success'))->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function form(): void
    {
        $this->setLocaleFromCookie();

        $this->display('username')->width(5, 3);
        $this->password('password', __('home.labels.password'))->rules('required|min:6|max:50', [
            'required' => admin_trans_label('password_required'),
            'min' => admin_trans_label('password_min'),
            'max' => admin_trans_label('password_max'),
        ])->placeholder(__('home.labels.password'))->setLabelClass('asterisk')->width(5, 3);
        $this->password('password_confirm', __('home.labels.password_confirm'))->same('password', __('home.labels.password_confirm_password'))->placeholder(__('home.labels.password_confirm'))->width(5, 3)->setLabelClass('asterisk');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    private function setLocaleFromCookie(): void
    {
        if (Cookie::has('locale')) {
            App::setLocale((string) Cookie::get('locale'));
        }
    }

    public function default(): array
    {
        $user = Admin::user();

        return [
            'username' => $user->username,
            'password' => '',
            'password_confirm' => '',
            'google_2fa_code' => '',
        ];
    }
}
