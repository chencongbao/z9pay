<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;

class OkxForm extends Form
{
    private const PAYMENT_METHOD_OPTIONS = ['all' => "全部", "wxPay" => "微信", "aliPay" => "支付宝", "bank" => "银行卡"];
    private const SIDE_OPTIONS = ['all' => "全部", 'sell' => "购买", "buy" => "出售"];
    private const USER_TYPE_OPTIONS = ['all' => "全部用户", "user" => "普通用户", "vip" => "VIP", "blockTrade" => "大宗交易", "merchant" => "认证商家"];
    private const FIELD_KEYS = [
        'okx_alipay_payment_method',
        'okx_alipay_side',
        'okx_alipay_user_type',
        'okx_bank_payment_method',
        'okx_bank_side',
        'okx_bank_user_type',
        'okx_weixin_payment_method',
        'okx_weixin_side',
        'okx_weixin_user_type',
        'okx_all_payment_method',
        'okx_all_side',
        'okx_all_user_type',
    ];

    public function handle(array $input)
    {
        $data = $this->normalizeInput($input);
        $validator = Validator::make($data, [
            'okx_alipay_payment_method' => ['required', 'in:' . implode(',', array_keys(self::PAYMENT_METHOD_OPTIONS))],
            'okx_alipay_side' => ['required', 'in:' . implode(',', array_keys(self::SIDE_OPTIONS))],
            'okx_alipay_user_type' => ['required', 'in:' . implode(',', array_keys(self::USER_TYPE_OPTIONS))],
            'okx_bank_payment_method' => ['required', 'in:' . implode(',', array_keys(self::PAYMENT_METHOD_OPTIONS))],
            'okx_bank_side' => ['required', 'in:' . implode(',', array_keys(self::SIDE_OPTIONS))],
            'okx_bank_user_type' => ['required', 'in:' . implode(',', array_keys(self::USER_TYPE_OPTIONS))],
            'okx_weixin_payment_method' => ['required', 'in:' . implode(',', array_keys(self::PAYMENT_METHOD_OPTIONS))],
            'okx_weixin_side' => ['required', 'in:' . implode(',', array_keys(self::SIDE_OPTIONS))],
            'okx_weixin_user_type' => ['required', 'in:' . implode(',', array_keys(self::USER_TYPE_OPTIONS))],
            'okx_all_payment_method' => ['required', 'in:' . implode(',', array_keys(self::PAYMENT_METHOD_OPTIONS))],
            'okx_all_side' => ['required', 'in:' . implode(',', array_keys(self::SIDE_OPTIONS))],
            'okx_all_user_type' => ['required', 'in:' . implode(',', array_keys(self::USER_TYPE_OPTIONS))],
        ], [
            'required' => '参数必填',
            'in' => '参数不合法',
        ]);
        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }
        bob_admin_setting($data);

        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.okx.update',
            text: '修改 欧易配置',
            subject: null,
            properties: $this->logProperties($data),
            remark: '修改 欧易配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success("设置成功")->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can("config.okx");
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');

        $this->fieldset('欧易-支付宝', function (Form $form) {
            $this->addOkxFields('okx_alipay');
        });
        $this->fieldset('欧易-银行卡', function (Form $form) {
            $this->addOkxFields('okx_bank');
        });
        $this->fieldset('欧易-微信', function (Form $form) {
            $this->addOkxFields('okx_weixin');
        });
        $this->fieldset('欧易-全部支付', function (Form $form) {
            $this->addOkxFields('okx_all');
        });
    }

    public function default()
    {
        return [
            'okx_alipay_payment_method' => bob_admin_setting('okx_alipay_payment_method') ?: 'all',
            'okx_alipay_side' => bob_admin_setting('okx_alipay_side') ?: 'all',
            'okx_alipay_user_type' => bob_admin_setting('okx_alipay_user_type') ?: 'all',

            'okx_bank_payment_method' => bob_admin_setting('okx_bank_payment_method') ?: 'all',
            'okx_bank_side' => bob_admin_setting('okx_bank_side') ?: 'all',
            'okx_bank_user_type' => bob_admin_setting('okx_bank_user_type') ?: 'all',

            'okx_weixin_payment_method' => bob_admin_setting('okx_weixin_payment_method') ?: 'all',
            'okx_weixin_side' => bob_admin_setting('okx_weixin_side') ?: 'all',
            'okx_weixin_user_type' => bob_admin_setting('okx_weixin_user_type') ?: 'all',

            'okx_all_payment_method' => bob_admin_setting('okx_all_payment_method') ?: 'all',
            'okx_all_side' => bob_admin_setting('okx_all_side') ?: 'all',
            'okx_all_user_type' => bob_admin_setting('okx_all_user_type') ?: 'all',
        ];
    }

    private function normalizeInput(array $input): array
    {
        $data = [];
        foreach (self::FIELD_KEYS as $key) {
            $data[$key] = trim((string)($input[$key] ?? ''));
        }

        return $data;
    }

    private function addOkxFields(string $prefix): void
    {
        $this->select($prefix . "_payment_method", "1. 支付方式")->options(self::PAYMENT_METHOD_OPTIONS)->required()->help("先确定当前分组要抓取的支付方式。");
        $this->select($prefix . "_side", "2. 模式")->options(self::SIDE_OPTIONS)->required()->help("按购买或出售方向过滤报价。");
        $this->select($prefix . "_user_type", "3. 类型")->options(self::USER_TYPE_OPTIONS)->required()->help("这一组参数会组合生效。");
    }

    private function logProperties(array $data): array
    {
        $properties = [];
        foreach (self::FIELD_KEYS as $key) {
            $properties[$key] = (string)($data[$key] ?? '');
        }

        return $properties;
    }
}
