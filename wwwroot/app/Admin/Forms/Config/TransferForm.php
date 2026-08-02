<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Form\NestedForm;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use App\Services\Common\SystemLogService;
use App\Services\Merchant\GetMerchantListService;

class TransferForm extends Form
{
    private const CHANNEL_MODE_OPTIONS = [2 => "按随机", 3 => "按平均", 5 => "按权重"];
    private const FIELD_KEYS = [
        'other_transfer_channel_mode',
        'other_transfer_pending_status',
        'transfer_balance_insufficient_status',
        'telegram_transfor_order_fail_notice_telegram_group_on',
        'transfer_max_amount_notice_telegram_confirm',
    ];

    public function handle(array $input)
    {
        $data = $this->normalizeInput($input);
        $validator = Validator::make($data, [
            'other_transfer_channel_mode' => ['required', 'integer', 'in:' . implode(',', array_keys(self::CHANNEL_MODE_OPTIONS))],
            'other_transfer_pending_status' => ['required', 'integer', 'in:0,1'],
            'transfer_balance_insufficient_status' => ['required', 'integer', 'in:0,1'],
            'telegram_transfor_order_fail_notice_telegram_group_on' => ['required', 'integer', 'in:0,1'],
        ], [
            'other_transfer_channel_mode.required' => "代付渠道匹配模式不合法",
            'other_transfer_channel_mode.integer' => "代付渠道匹配模式不合法",
            'other_transfer_channel_mode.in' => "代付渠道匹配模式不合法",
            'other_transfer_pending_status.required' => "代付订单失败进入待处理值不合法",
            'other_transfer_pending_status.integer' => "代付订单失败进入待处理值不合法",
            'other_transfer_pending_status.in' => "代付订单失败进入待处理值不合法",
            'transfer_balance_insufficient_status.required' => "渠道余额不足进入待处理值不合法",
            'transfer_balance_insufficient_status.integer' => "渠道余额不足进入待处理值不合法",
            'transfer_balance_insufficient_status.in' => "渠道余额不足进入待处理值不合法",
            'telegram_transfor_order_fail_notice_telegram_group_on.required' => "代付订单失败原因通知不合法",
            'telegram_transfor_order_fail_notice_telegram_group_on.integer' => "代付订单失败原因通知不合法",
            'telegram_transfor_order_fail_notice_telegram_group_on.in' => "代付订单失败原因通知不合法",
        ]);
        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }

        if ($error = $this->validateConfirmSettings($data['transfer_max_amount_notice_telegram_confirm'])) {
            return $this->response()->error($error);
        }

        bob_admin_setting($data);

        $adminUser = Admin::user();
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.transfer.update',
            text: '修改 代付配置',
            subject: null,
            properties: [
                'other_transfer_channel_mode' => (int)($data['other_transfer_channel_mode'] ?? 0),
                'other_transfer_pending_status' => (int)($data['other_transfer_pending_status'] ?? 0),
                'transfer_balance_insufficient_status' => (int)($data['transfer_balance_insufficient_status'] ?? 0),
                'telegram_transfor_order_fail_notice_telegram_group_on' => (int)($data['telegram_transfor_order_fail_notice_telegram_group_on'] ?? 0),
                'transfer_max_amount_notice_telegram_confirm_count' => count($data['transfer_max_amount_notice_telegram_confirm']),
            ],
            remark: '修改 代付配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $adminUser
        );

        return $this->response()->success("设置成功")->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can("config.transfer");
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');
        $merchantOptions = App::make(GetMerchantListService::class)->excute(['currency_id'], true);

        $this->fieldset('代付渠道策略', function (Form $form) {
            $this->radio("other_transfer_channel_mode", "1. 代付渠道匹配模式")
                ->options(self::CHANNEL_MODE_OPTIONS)
                ->help("按随机：符合条件的渠道随机返回。<br/>按平均：符合条件的渠道尽量平均返回。<br/>按权重：按已配置权重在当天尽量平均分配。")
                ->width(8, 4);
        });

        $this->fieldset('代付失败处理', function (Form $form) {
            $this->radio("other_transfer_pending_status", "1. 代付订单失败进入待处理")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("关闭时，代付订单失败默认直接进入失败状态。")
                ->width(8, 4);
            $this->radio("transfer_balance_insufficient_status", "2. 渠道余额不足进入待处理")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("关闭时，渠道余额不足默认直接进入失败状态。")
                ->width(8, 4);
            $this->radio("telegram_transfor_order_fail_notice_telegram_group_on", "3. 代付订单失败通知到商户群")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("开启后，商户群会收到代付失败通知。")
                ->width(8, 4);
        });

        $this->fieldset('大额代付确认', function (Form $form) use ($merchantOptions) {
            $this->table("transfer_max_amount_notice_telegram_confirm", "1. 代付超过金额通知群确认", function (NestedForm $table) use ($merchantOptions) {
                $table->multipleSelect('mids', "选择商户")->options($merchantOptions)->required();
                $table->text('value', "通知金额")->required()->help('请填写大于0的金额，例如：5000000');
            })->width(8, 4)->help("按“金额 -> 多个商户”的方式配置；达到金额后才会触发商户群确认通知。");
        });
    }

    public function default()
    {
        return [
            'other_transfer_channel_mode' => bob_admin_setting('other_transfer_channel_mode') ?: 2,
            'other_transfer_pending_status' => intval(bob_admin_setting('other_transfer_pending_status')),
            'transfer_max_amount_notice_telegram_confirm' => bob_admin_setting('transfer_max_amount_notice_telegram_confirm') ?: [],
            'transfer_balance_insufficient_status' => intval(bob_admin_setting('transfer_balance_insufficient_status')) ?: 0,
            'telegram_transfor_order_fail_notice_telegram_group_on' => intval(bob_admin_setting('telegram_transfor_order_fail_notice_telegram_group_on')),
        ];
    }

    private function normalizeInput(array $input): array
    {
        $data = [];
        foreach (self::FIELD_KEYS as $key) {
            $data[$key] = $input[$key] ?? '';
        }

        $data['other_transfer_channel_mode'] = $this->normalizeValue($data['other_transfer_channel_mode'], '2');
        $data['other_transfer_pending_status'] = $this->normalizeValue($data['other_transfer_pending_status'], '0');
        $data['transfer_balance_insufficient_status'] = $this->normalizeValue($data['transfer_balance_insufficient_status'], '0');
        $data['telegram_transfor_order_fail_notice_telegram_group_on'] = $this->normalizeValue($data['telegram_transfor_order_fail_notice_telegram_group_on'], '0');
        $data['transfer_max_amount_notice_telegram_confirm'] = array_map(function (array $item) {
            return [
                'mids' => $this->normalizeMerchantIds($item['mids'] ?? []),
                'value' => trim((string)($item['value'] ?? '')),
            ];
        }, $this->activeRows(is_array($data['transfer_max_amount_notice_telegram_confirm']) ? $data['transfer_max_amount_notice_telegram_confirm'] : []));

        return $data;
    }

    private function normalizeValue($value, string $default): string
    {
        $value = trim((string)$value);

        return $value === '' ? $default : $value;
    }

    private function activeRows(array $rows): array
    {
        return array_values(array_filter($rows, fn ($item) => is_array($item) && intval($item['_remove_'] ?? 0) === 0));
    }

    private function normalizeMerchantIds($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,，\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        }

        return array_values(array_unique(array_filter(array_map('intval', (array)$value))));
    }

    private function validateConfirmSettings(array $items): string
    {
        foreach ($items as $item) {
            if (empty($item['mids'])) {
                return '大额代付确认必须选择商户';
            }
            if (($item['value'] ?? '') === '') {
                return '大额代付确认金额不能为空';
            }
            if (!is_numeric($item['value']) || floatval($item['value']) <= 0) {
                return '大额代付确认金额必须大于0';
            }
        }

        return '';
    }
}
