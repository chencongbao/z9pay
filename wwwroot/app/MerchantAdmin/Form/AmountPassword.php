<?php

namespace App\MerchantAdmin\Form;

use App\Models\MerchantUser;
use App\Services\Common\SystemLogService;
use Dcat\Admin\Admin;
use Dcat\Admin\Contracts\LazyRenderable;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use App\Services\Google\AdminGoogle2faService;

class AmountPassword extends Form implements LazyRenderable
{

    use LazyWidget;
    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return mixed
     */
    public function handle(array $input)
    {
        try {
            [$password] = $this->validatePasswordInput($input);
            $google_2fa_code = $input['google_2fa_code'] ?? null;
            app(AdminGoogle2faService::class)->verify($google_2fa_code);
            MerchantUser::where('id',Admin::user()->id)->update(['amount_password' => Hash::make($password)]);
            app(SystemLogService::class)->logAction(
                actionKey: 'merchant.user.update_amount_password',
                text: '修改 资金密码',
                subject: Admin::user(),
                properties: ['merchant_user_id' => Admin::user()->id],
                remark: '修改 资金密码',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'merchant',
                user: Admin::user()
            );
            return $this->response()->success(admin_trans_label("update_success"))->refresh();
        }catch (\Exception $e){
            return $this->response()->error($e->getMessage());
        }
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->setLocaleFromCookie();

        $this->display('username')->width(5,3);
        $this->password('current_login_password', __('handle-form.labels.current_login_password'))->rules('required', ['required' => __('handle-form.labels.current_login_password_required')])->placeholder(__('handle-form.labels.current_login_password'))->setLabelClass("asterisk")->width(5,3);
        $this->password('password', __('home.labels.password'))->rules('required|min:6|max:50',['required'=>__('handle-form.labels.password_required'),'min'=>__('handle-form.labels.password_min'),'max'=>__('handle-form.labels.password_max')])->placeholder(__('home.labels.password'))->setLabelClass("asterisk")->width(5,3);
        $this->password('password_confirm', __('home.labels.password_confirm'))
            ->rules('required|same:password', [
                'required' => __('handle-form.labels.password_confirm_required'),
                'same' => __('handle-form.labels.password_confirm_password'),
            ])
            ->same('password', __('home.labels.password_confirm_password'))
            ->placeholder(__('home.labels.password_confirm'))
            ->width(5,3)
            ->setLabelClass("asterisk");
        app(AdminGoogle2faService::class)->appendField($this);
    }

    private function validatePasswordInput(array $input): array
    {
        $currentPassword = (string)($input['current_login_password'] ?? '');
        $password = (string)($input['password'] ?? '');
        $passwordConfirm = (string)($input['password_confirm'] ?? '');

        if ($currentPassword === '') {
            throw new \Exception(__('handle-form.labels.current_login_password_required'));
        }
        if (!Hash::check($currentPassword, Admin::user()->password)) {
            throw new \Exception(__('handle-form.labels.current_login_password_error'));
        }
        if ($password === '') {
            throw new \Exception(__('handle-form.labels.password_required'));
        }
        if (mb_strlen($password) < 6) {
            throw new \Exception(__('handle-form.labels.password_min'));
        }
        if (mb_strlen($password) > 50) {
            throw new \Exception(__('handle-form.labels.password_max'));
        }
        if ($password !== $passwordConfirm) {
            throw new \Exception(__('handle-form.labels.password_confirm_password'));
        }

        return [$password];
    }

    private function setLocaleFromCookie(): void
    {
        if (Cookie::has('locale')) {
            App::setLocale((string) Cookie::get('locale'));
        }
    }

    /**
     * The data of the form.
     *
     * @return array
     */
    public function default()
    {
        return [
            'username'  => Admin::user()->username,
            'current_login_password' => '',
            'password' => '',
            'password_confirm' => '',
            'google_2fa_code' => ''
        ];
    }
}
