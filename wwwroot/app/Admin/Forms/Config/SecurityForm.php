<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;
use App\Services\Telegram\TelegramManagerService;

class SecurityForm extends Form
{
    private const FIELD_KEYS = [
        'transfer_test_confirm_on',
        'transfer_test_confirm_telegram_group_id',
        'transfer_test_confirm_min_amount',
        'transfer_test_confirm_expire_minutes',
        'merchant_balance_adjust_confirm_on',
        'merchant_balance_adjust_confirm_telegram_group_id',
        'merchant_balance_adjust_confirm_min_amount',
        'merchant_balance_adjust_confirm_expire_minutes',
        'deposit_manual_success_confirm_on',
        'deposit_manual_success_confirm_telegram_group_id',
        'deposit_manual_success_confirm_min_amount',
        'deposit_manual_success_confirm_expire_minutes',
        'user_login_exception_notice_switch',
        'user_login_exception_notice_telegram_group_ids',
    ];

    public function handle(array $input)
    {
        $data = $this->normalizeInput($input);

        $validator = Validator::make($data, [
            'transfer_test_confirm_on' => ['required', 'integer', 'in:0,1'],
            'transfer_test_confirm_telegram_group_id' => ['exclude_unless:transfer_test_confirm_on,1', 'required'],
            'transfer_test_confirm_min_amount' => ['exclude_unless:transfer_test_confirm_on,1', 'required', 'numeric', 'min:0'],
            'transfer_test_confirm_expire_minutes' => ['exclude_unless:transfer_test_confirm_on,1', 'required', 'integer', 'min:1'],
            'merchant_balance_adjust_confirm_on' => ['required', 'integer', 'in:0,1'],
            'merchant_balance_adjust_confirm_telegram_group_id' => ['exclude_unless:merchant_balance_adjust_confirm_on,1', 'required'],
            'merchant_balance_adjust_confirm_min_amount' => ['exclude_unless:merchant_balance_adjust_confirm_on,1', 'required', 'numeric', 'min:0'],
            'merchant_balance_adjust_confirm_expire_minutes' => ['exclude_unless:merchant_balance_adjust_confirm_on,1', 'required', 'integer', 'min:1'],
            'deposit_manual_success_confirm_on' => ['required', 'integer', 'in:0,1'],
            'deposit_manual_success_confirm_telegram_group_id' => ['exclude_unless:deposit_manual_success_confirm_on,1', 'required'],
            'deposit_manual_success_confirm_min_amount' => ['exclude_unless:deposit_manual_success_confirm_on,1', 'required', 'numeric', 'min:0'],
            'deposit_manual_success_confirm_expire_minutes' => ['exclude_unless:deposit_manual_success_confirm_on,1', 'required', 'integer', 'min:1'],
            'user_login_exception_notice_switch' => ['required', 'integer', 'in:0,1'],
            'user_login_exception_notice_telegram_group_ids' => ['exclude_unless:user_login_exception_notice_switch,1', 'required'],
        ], [
            'transfer_test_confirm_on.required' => '代付测试确认开关值不合法',
            'transfer_test_confirm_on.integer' => '代付测试确认开关值不合法',
            'transfer_test_confirm_on.in' => '代付测试确认开关值不合法',
            'transfer_test_confirm_telegram_group_id.required' => '代付测试确认通知群ID必填',
            'transfer_test_confirm_min_amount.required' => '代付测试确认金额必填',
            'transfer_test_confirm_min_amount.numeric' => '代付测试确认金额不合法',
            'transfer_test_confirm_min_amount.min' => '代付测试确认金额不能小于0',
            'transfer_test_confirm_expire_minutes.required' => '代付测试确认过期时间必填',
            'transfer_test_confirm_expire_minutes.integer' => '代付测试确认过期时间不合法',
            'transfer_test_confirm_expire_minutes.min' => '代付测试确认过期时间不能小于1分钟',
            'merchant_balance_adjust_confirm_on.required' => '商户人工加项确认开关值不合法',
            'merchant_balance_adjust_confirm_on.integer' => '商户人工加项确认开关值不合法',
            'merchant_balance_adjust_confirm_on.in' => '商户人工加项确认开关值不合法',
            'merchant_balance_adjust_confirm_telegram_group_id.required' => '商户人工加项确认通知群ID必填',
            'merchant_balance_adjust_confirm_min_amount.required' => '商户人工加项确认金额必填',
            'merchant_balance_adjust_confirm_min_amount.numeric' => '商户人工加项确认金额不合法',
            'merchant_balance_adjust_confirm_min_amount.min' => '商户人工加项确认金额不能小于0',
            'merchant_balance_adjust_confirm_expire_minutes.required' => '商户人工加项确认过期时间必填',
            'merchant_balance_adjust_confirm_expire_minutes.integer' => '商户人工加项确认过期时间不合法',
            'merchant_balance_adjust_confirm_expire_minutes.min' => '商户人工加项确认过期时间不能小于1分钟',
            'deposit_manual_success_confirm_on.required' => '人工补单确认开关值不合法',
            'deposit_manual_success_confirm_on.integer' => '人工补单确认开关值不合法',
            'deposit_manual_success_confirm_on.in' => '人工补单确认开关值不合法',
            'deposit_manual_success_confirm_telegram_group_id.required' => '人工补单确认通知群必填',
            'deposit_manual_success_confirm_min_amount.required' => '人工补单确认金额必填',
            'deposit_manual_success_confirm_min_amount.numeric' => '人工补单确认金额不合法',
            'deposit_manual_success_confirm_min_amount.min' => '人工补单确认金额不能小于0',
            'deposit_manual_success_confirm_expire_minutes.required' => '人工补单确认过期时间必填',
            'deposit_manual_success_confirm_expire_minutes.integer' => '人工补单确认过期时间不合法',
            'deposit_manual_success_confirm_expire_minutes.min' => '人工补单确认过期时间不能小于1分钟',
            'user_login_exception_notice_switch.required' => '系统登录异常通知开关不合法',
            'user_login_exception_notice_switch.integer' => '系统登录异常通知开关不合法',
            'user_login_exception_notice_switch.in' => '系统登录异常通知开关不合法',
            'user_login_exception_notice_telegram_group_ids.required' => '系统登录异常通知群必填',
        ]);

        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }

        if ($this->enabled($data, 'transfer_test_confirm_on') && $data['transfer_test_confirm_telegram_group_id'] === '') {
            return $this->response()->error('开启代付测试确认后，通知群ID必填');
        }

        if ($this->enabled($data, 'merchant_balance_adjust_confirm_on') && $data['merchant_balance_adjust_confirm_telegram_group_id'] === '') {
            return $this->response()->error('开启商户人工加项确认后，通知群ID必填');
        }

        if ($this->enabled($data, 'deposit_manual_success_confirm_on') && $data['deposit_manual_success_confirm_telegram_group_id'] === '') {
            return $this->response()->error('开启人工补单确认后，通知群ID必填');
        }

        if ($this->anyConfirmEnabled($data) && empty(app(TelegramManagerService::class)->superManagerIds())) {
            return $this->response()->error('开启飞机确认后，请先在后台管理员账号里设置飞机命令超级管理员');
        }

        bob_admin_setting($data);

        $adminUser = Admin::user();
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.security.update',
            text: '修改 安全配置',
            subject: null,
            properties: [
                'transfer_test_confirm_on' => (int) ($data['transfer_test_confirm_on'] ?? 0),
                'transfer_test_confirm_telegram_group_id' => (string) ($data['transfer_test_confirm_telegram_group_id'] ?? ''),
                'transfer_test_confirm_min_amount' => floatval($data['transfer_test_confirm_min_amount'] ?? 0),
                'transfer_test_confirm_expire_minutes' => (int) ($data['transfer_test_confirm_expire_minutes'] ?? 30),
                'merchant_balance_adjust_confirm_on' => (int) ($data['merchant_balance_adjust_confirm_on'] ?? 0),
                'merchant_balance_adjust_confirm_telegram_group_id' => (string) ($data['merchant_balance_adjust_confirm_telegram_group_id'] ?? ''),
                'merchant_balance_adjust_confirm_min_amount' => floatval($data['merchant_balance_adjust_confirm_min_amount'] ?? 0),
                'merchant_balance_adjust_confirm_expire_minutes' => (int) ($data['merchant_balance_adjust_confirm_expire_minutes'] ?? 30),
                'deposit_manual_success_confirm_on' => (int) ($data['deposit_manual_success_confirm_on'] ?? 0),
                'deposit_manual_success_confirm_telegram_group_id' => (string) ($data['deposit_manual_success_confirm_telegram_group_id'] ?? ''),
                'deposit_manual_success_confirm_min_amount' => floatval($data['deposit_manual_success_confirm_min_amount'] ?? 0),
                'deposit_manual_success_confirm_expire_minutes' => (int) ($data['deposit_manual_success_confirm_expire_minutes'] ?? 30),
                'user_login_exception_notice_switch' => (int) ($data['user_login_exception_notice_switch'] ?? 0),
                'user_login_exception_notice_telegram_group_ids' => (string) ($data['user_login_exception_notice_telegram_group_ids'] ?? ''),
            ],
            remark: '修改 安全配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $adminUser
        );

        return $this->response()->success('设置成功')->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can("config-security");
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');

        $this->html($this->sectionHtml('高风险操作确认', '以下开关开启后，后台提交操作不会立即生效，需要发送到指定飞机群，由飞机超级管理员点击确认后执行。'))->width(12, 0);

        $this->radio('transfer_test_confirm_on', '代付测试确认开关')
            ->options([0 => '关闭', 1 => '开启'])
            ->help('控制“代付测试”是否需要飞机群二次确认')
            ->when(1, function () {
                $this->text('transfer_test_confirm_telegram_group_id', '代付测试确认通知群ID')
                    ->help('只支持单个群ID；开启后必填')
                    ->width(8, 4);
                $this->number('transfer_test_confirm_min_amount', '代付测试确认金额')
                    ->min(0)
                    ->help('代付测试金额大于等于该金额时发送确认群审核；设置0表示所有代付测试都需要审核')
                    ->width(8, 4);
                $this->number('transfer_test_confirm_expire_minutes', '代付测试确认过期时间')
                    ->min(1)
                    ->help('单位：分钟；超时后确认消息失效')
                    ->width(8, 4);
            })->width(8, 4);

        $this->html($this->dividerHtml())->width(12, 0);

        $this->radio('merchant_balance_adjust_confirm_on', '商户人工加项确认开关')
            ->options([0 => '关闭', 1 => '开启'])
            ->help('控制“商户余额人工加项”是否需要飞机群二次确认')
            ->when(1, function () {
                $this->text('merchant_balance_adjust_confirm_telegram_group_id', '商户人工加项确认通知群ID')
                    ->help('只支持单个群ID；开启后必填')
                    ->width(8, 4);
                $this->number('merchant_balance_adjust_confirm_min_amount', '商户人工加项确认金额')
                    ->min(0)
                    ->help('增项金额大于等于该金额时发送确认群审核；设置0表示所有商户人工加项都需要审核')
                    ->width(8, 4);
                $this->number('merchant_balance_adjust_confirm_expire_minutes', '商户人工加项确认过期时间')
                    ->min(1)
                    ->help('单位：分钟；超时后确认消息失效')
                    ->width(8, 4);
            })->width(8, 4);

        $this->html($this->dividerHtml())->width(12, 0);

        $this->radio('deposit_manual_success_confirm_on', '人工补单确认开关')
            ->options([0 => '关闭', 1 => '开启'])
            ->help('控制“代收订单人工补单”是否需要飞机群二次确认')
            ->when(1, function () {
                $this->text('deposit_manual_success_confirm_telegram_group_id', '人工补单确认通知群')
                    ->help('只支持单个群ID；开启后必填')
                    ->width(8, 4);
                $this->number('deposit_manual_success_confirm_min_amount', '人工补单确认金额')
                    ->min(0)
                    ->help('实付金额大于等于该金额时发送确认群审核；设置0表示所有人工补单都需要审核')
                    ->width(8, 4);
                $this->number('deposit_manual_success_confirm_expire_minutes', '人工补单确认过期时间')
                    ->min(1)
                    ->help('单位：分钟；超时后确认消息失效')
                    ->width(8, 4);
            })->width(8, 4);

        $this->html($this->sectionHtml('异常通知', '配置系统登录异常通知，和上面的高风险确认流程相互独立。'))->width(12, 0);

        $this->radio('user_login_exception_notice_switch', '系统登录异常通知开关')
            ->options([0 => '关闭', 1 => '开启'])
            ->when(1, function () {
                $this->text('user_login_exception_notice_telegram_group_ids', '系统登录异常通知群')
                    ->help('个人ID或群组ID')
                    ->width(8, 4);
            })->help('商户、系统管理员、金主、商户代理、金主代理登录异常通知设置飞机群')
            ->width(8, 4);
    }

    protected function sectionHtml(string $title, string $desc): string
    {
        return <<<HTML
<div style="margin: 18px 0 12px; padding: 10px 14px; border-left: 4px solid #3085d6; background: #f7fbff;">
    <div style="font-weight: 600; color: #2b3e50;">{$title}</div>
    <div style="margin-top: 4px; color: #6c757d;">{$desc}</div>
</div>
HTML;
    }

    protected function dividerHtml(): string
    {
        return '<hr style="margin: 16px 0; border-top: 1px solid #edf2f7;">';
    }

    public function default()
    {
        return [
            'transfer_test_confirm_on' => intval(bob_admin_setting('transfer_test_confirm_on')) ?: 0,
            'transfer_test_confirm_telegram_group_id' => bob_admin_setting('transfer_test_confirm_telegram_group_id') ?: '',
            'transfer_test_confirm_min_amount' => floatval(bob_admin_setting('transfer_test_confirm_min_amount')),
            'transfer_test_confirm_expire_minutes' => intval(bob_admin_setting('transfer_test_confirm_expire_minutes')) ?: 30,
            'merchant_balance_adjust_confirm_on' => intval(bob_admin_setting('merchant_balance_adjust_confirm_on')) ?: 0,
            'merchant_balance_adjust_confirm_telegram_group_id' => bob_admin_setting('merchant_balance_adjust_confirm_telegram_group_id') ?: '',
            'merchant_balance_adjust_confirm_min_amount' => floatval(bob_admin_setting('merchant_balance_adjust_confirm_min_amount')),
            'merchant_balance_adjust_confirm_expire_minutes' => intval(bob_admin_setting('merchant_balance_adjust_confirm_expire_minutes')) ?: 30,
            'deposit_manual_success_confirm_on' => intval(bob_admin_setting('deposit_manual_success_confirm_on')) ?: 0,
            'deposit_manual_success_confirm_telegram_group_id' => bob_admin_setting('deposit_manual_success_confirm_telegram_group_id') ?: '',
            'deposit_manual_success_confirm_min_amount' => floatval(bob_admin_setting('deposit_manual_success_confirm_min_amount')),
            'deposit_manual_success_confirm_expire_minutes' => intval(bob_admin_setting('deposit_manual_success_confirm_expire_minutes')) ?: 30,
            'user_login_exception_notice_switch' => intval(bob_admin_setting('user_login_exception_notice_switch')),
            'user_login_exception_notice_telegram_group_ids' => bob_admin_setting('user_login_exception_notice_telegram_group_ids') ?: '',
        ];
    }

    private function normalizeInput(array $input): array
    {
        $data = [];
        foreach (self::FIELD_KEYS as $key) {
            $data[$key] = trim((string)($input[$key] ?? ''));
        }

        $data['transfer_test_confirm_on'] = $data['transfer_test_confirm_on'] === '' ? '0' : $data['transfer_test_confirm_on'];
        $data['merchant_balance_adjust_confirm_on'] = $data['merchant_balance_adjust_confirm_on'] === '' ? '0' : $data['merchant_balance_adjust_confirm_on'];
        $data['deposit_manual_success_confirm_on'] = $data['deposit_manual_success_confirm_on'] === '' ? '0' : $data['deposit_manual_success_confirm_on'];
        $data['user_login_exception_notice_switch'] = $data['user_login_exception_notice_switch'] === '' ? '0' : $data['user_login_exception_notice_switch'];
        $data['transfer_test_confirm_expire_minutes'] = $data['transfer_test_confirm_expire_minutes'] === '' ? '30' : $data['transfer_test_confirm_expire_minutes'];
        $data['merchant_balance_adjust_confirm_expire_minutes'] = $data['merchant_balance_adjust_confirm_expire_minutes'] === '' ? '30' : $data['merchant_balance_adjust_confirm_expire_minutes'];
        $data['deposit_manual_success_confirm_expire_minutes'] = $data['deposit_manual_success_confirm_expire_minutes'] === '' ? '30' : $data['deposit_manual_success_confirm_expire_minutes'];
        $data['transfer_test_confirm_min_amount'] = $data['transfer_test_confirm_min_amount'] === '' ? '0' : $data['transfer_test_confirm_min_amount'];
        $data['merchant_balance_adjust_confirm_min_amount'] = $data['merchant_balance_adjust_confirm_min_amount'] === '' ? '0' : $data['merchant_balance_adjust_confirm_min_amount'];
        $data['deposit_manual_success_confirm_min_amount'] = $data['deposit_manual_success_confirm_min_amount'] === '' ? '0' : $data['deposit_manual_success_confirm_min_amount'];

        return $data;
    }

    private function enabled(array $data, string $key): bool
    {
        return intval($data[$key] ?? 0) === 1;
    }

    private function anyConfirmEnabled(array $data): bool
    {
        return $this->enabled($data, 'transfer_test_confirm_on')
            || $this->enabled($data, 'merchant_balance_adjust_confirm_on')
            || $this->enabled($data, 'deposit_manual_success_confirm_on');
    }
}
