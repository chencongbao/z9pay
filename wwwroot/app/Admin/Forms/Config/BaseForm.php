<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;

class BaseForm extends Form
{
    public function handle(array $input)
    {
        $data = $this->normalizeInput($input);
        $validator = Validator::make($data, [
            'total_system_login_remember_switch' => ['required', 'in:0,1'],
            'merchant_system_login_remember_switch' => ['required', 'in:0,1'],
            'agent_system_login_remember_switch' => ['required', 'in:0,1'],
            'other_admin_operate_google_2fa_code_time' => ['required', 'integer', 'min:1'],
        ], [
            'total_system_login_remember_switch.required' => '超管系统登录记住密码值不合法',
            'total_system_login_remember_switch.in' => '超管系统登录记住密码值不合法',
            'merchant_system_login_remember_switch.required' => '商户系统登录记住密码值不合法',
            'merchant_system_login_remember_switch.in' => '商户系统登录记住密码值不合法',
            'agent_system_login_remember_switch.required' => '代理系统登录记住密码值不合法',
            'agent_system_login_remember_switch.in' => '代理系统登录记住密码值不合法',
            'other_admin_operate_google_2fa_code_time.required' => '系统操作免谷歌验证码时长不合法',
            'other_admin_operate_google_2fa_code_time.integer' => '系统操作免谷歌验证码时长不合法',
            'other_admin_operate_google_2fa_code_time.min' => '系统操作免谷歌验证码时长不能小于1分钟',
        ]);
        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }

        bob_admin_setting($data);

        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.base.update',
            text: '修改 基础配置',
            subject: null,
            properties: $data,
            remark: '修改 基础配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('设置成功')->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('config.base');
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');
        $this->radio('total_system_login_remember_switch', '超管系统登录记住密码')->options([0 => '关闭', 1 => '开启'])->width(8, 4);
        $this->radio('merchant_system_login_remember_switch', '商户系统登录记住密码')->options([0 => '关闭', 1 => '开启'])->width(8, 4);
        $this->radio('agent_system_login_remember_switch', '代理系统登录记住密码')->options([0 => '关闭', 1 => '开启'])->width(8, 4);
        $this->number('other_admin_operate_google_2fa_code_time', '系统操作免谷歌验证码时长')->help('单位:分钟,每操作一次系统自动延长1分钟')->width(8, 4);
    }

    public function default()
    {
        return [
            'other_admin_operate_google_2fa_code_time' => bob_admin_setting('other_admin_operate_google_2fa_code_time') ?: 10,
            'total_system_login_remember_switch' => intval(bob_admin_setting('total_system_login_remember_switch')),
            'merchant_system_login_remember_switch' => intval(bob_admin_setting('merchant_system_login_remember_switch')),
            'agent_system_login_remember_switch' => intval(bob_admin_setting('agent_system_login_remember_switch')),
        ];
    }

    private function normalizeInput(array $input): array
    {
        return [
            'total_system_login_remember_switch' => (int)($input['total_system_login_remember_switch'] ?? 0),
            'merchant_system_login_remember_switch' => (int)($input['merchant_system_login_remember_switch'] ?? 0),
            'agent_system_login_remember_switch' => (int)($input['agent_system_login_remember_switch'] ?? 0),
            'other_admin_operate_google_2fa_code_time' => (int)($input['other_admin_operate_google_2fa_code_time'] ?? 0),
        ];
    }
}
