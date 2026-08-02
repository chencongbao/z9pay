<?php

namespace App\Admin\Forms\SelfChannel;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;

class ConfigForm extends Form
{
    private const SETTING_KEYS = [
        'telegram_user_deposit_balance_notice',
        'base_deposit_confirm_overtime',
        'base_transfer_pay_overtime',
        'base_user_confirm_transfer_order_confirmed_status',
        'push_advance_order_time',
        'push_cannel_or_cancel_order_number',
        'push_pay_order_total_amount',
        'push_pay_order_togather_amount',
        'pending_pay_order_time',
        'pending_pay_order_number',
    ];

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('selfchannels.config')) {
                throw new RuntimeException('非法操作');
            }

            $data = $this->validatedData($input);
            bob_admin_setting($data);

            app(SystemLogService::class)->logAction(
                actionKey: 'admin.selfchannel.config.update',
                text: '修改 自营配置',
                subject: null,
                properties: $data,
                remark: '修改 自营配置',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $adminUser
            );

            return $this->response()->success('设置成功')->location();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('selfchannels.config');
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');
        $this->fieldset('代收提单设置', function (Form $form) {
            $form->html(view('admin.config-form.rules1'))->width(9, 3);
            $form->html(view('admin.config-form.rules2'))->width(9, 3);
            $form->html(view('admin.config-form.rules'))->width(9, 3);
            $form->html(view('admin.config-form.rules3'))->width(9, 3);
        });

        $this->fieldset('其他设置', function (Form $form) {
            $form->number('base_transfer_pay_overtime', '金主代付超时时间')->width(8, 4)->help('单位分钟');
            $form->number('telegram_user_deposit_balance_notice', '金主押金通知金额')->width(8, 4)->help('0表示不通知，当金主押金少于当前值，将通知金主群');
            $form->radio('base_user_confirm_transfer_order_confirmed_status', '金主确认代付成功进入待确认状态')->options([0 => '关闭', 1 => '开启'])->help('默认关闭，金主确认代付订单之后立即成功')->width(8, 4);
            $form->number('base_deposit_confirm_overtime', '金主代收待确认超时时间')->help('单位分钟')->width(8, 4);
        });
    }

    public function default()
    {
        return [
            'base_transfer_pay_overtime' => $this->settingValue('base_transfer_pay_overtime', 15),
            'telegram_user_deposit_balance_notice' => $this->settingValue('telegram_user_deposit_balance_notice', 0),
            'base_user_confirm_transfer_order_confirmed_status' => $this->settingValue('base_user_confirm_transfer_order_confirmed_status', 0),
            'base_deposit_confirm_overtime' => $this->settingValue('base_deposit_confirm_overtime', 15),
        ];
    }

    private function validatedData(array $input): array
    {
        $data = array_merge(request()->only(self::SETTING_KEYS), array_intersect_key($input, array_flip(self::SETTING_KEYS)));
        $validator = Validator::make($data, $this->rules(), $this->messages());
        if ($validator->fails()) {
            throw new RuntimeException($validator->errors()->first());
        }

        return array_map('intval', $validator->validated());
    }

    private function rules(): array
    {
        return [
            'base_deposit_confirm_overtime' => ['required', 'numeric', 'min:1', 'integer'],
            'base_transfer_pay_overtime' => ['required', 'numeric', 'min:1', 'integer'],
            'telegram_user_deposit_balance_notice' => ['required', 'numeric', 'min:0', 'integer'],
            'base_user_confirm_transfer_order_confirmed_status' => ['required', 'in:0,1'],
            'push_advance_order_time' => ['required', 'numeric', 'min:0', 'integer'],
            'push_cannel_or_cancel_order_number' => ['required', 'numeric', 'min:0', 'integer'],
            'push_pay_order_total_amount' => ['required', 'numeric', 'min:0', 'integer'],
            'push_pay_order_togather_amount' => ['required', 'numeric', 'min:0', 'integer'],
            'pending_pay_order_time' => ['required', 'numeric', 'min:0', 'integer'],
            'pending_pay_order_number' => ['required', 'numeric', 'min:0', 'integer'],
        ];
    }

    private function messages(): array
    {
        return [
            'base_deposit_confirm_overtime.required' => '金主代收待确认超时时间不合法',
            'base_deposit_confirm_overtime.numeric' => '金主代收待确认超时时间不合法',
            'base_deposit_confirm_overtime.integer' => '金主代收待确认超时时间不合法',
            'base_deposit_confirm_overtime.min' => '金主代收待确认超时时间不能小于1分钟',
            'base_transfer_pay_overtime.required' => '金主代付超时时间不合法',
            'base_transfer_pay_overtime.numeric' => '金主代付超时时间不合法',
            'base_transfer_pay_overtime.integer' => '金主代付超时时间不合法',
            'base_transfer_pay_overtime.min' => '金主代付超时时间不能小于1分钟',
            'telegram_user_deposit_balance_notice.required' => '金主押金通知金额不合法',
            'telegram_user_deposit_balance_notice.numeric' => '金主押金通知金额不合法',
            'telegram_user_deposit_balance_notice.integer' => '金主押金通知金额不合法',
            'telegram_user_deposit_balance_notice.min' => '金主押金通知金额不能小于0',
            'base_user_confirm_transfer_order_confirmed_status.required' => '金主确认代付订单进入待确认状态不合法',
            'base_user_confirm_transfer_order_confirmed_status.in' => '金主确认代付订单进入待确认状态不合法',
            'push_advance_order_time.required' => '收款卡时间数值不合法',
            'push_advance_order_time.numeric' => '收款卡时间数值不合法',
            'push_advance_order_time.min' => '收款卡时间数值不能小于0',
            'push_advance_order_time.integer' => '收款卡时间数值不合法',
            'push_cannel_or_cancel_order_number.required' => '收款卡相同金额订单数数值不合法',
            'push_cannel_or_cancel_order_number.numeric' => '收款卡相同金额订单数数值不合法',
            'push_cannel_or_cancel_order_number.min' => '收款卡相同金额订单数数值不能小于0',
            'push_cannel_or_cancel_order_number.integer' => '收款卡相同金额订单数数值不合法',
            'push_pay_order_total_amount.required' => '金主代收待付款订单总金额数值不合法',
            'push_pay_order_total_amount.numeric' => '金主代收待付款订单总金额数值不合法',
            'push_pay_order_total_amount.min' => '金主代收待付款订单总金额数值不能小于0',
            'push_pay_order_total_amount.integer' => '金主代收待付款订单总金额数值不合法',
            'push_pay_order_togather_amount.required' => '金主代收待付款订单，相同金额的订单数量数值不合法',
            'push_pay_order_togather_amount.numeric' => '金主代收待付款订单，相同金额的订单数量数值不合法',
            'push_pay_order_togather_amount.min' => '金主代收待付款订单，相同金额的订单数量数值不能小于0',
            'push_pay_order_togather_amount.integer' => '金主代收待付款订单，相同金额的订单数量数值不合法',
            'pending_pay_order_time.required' => '收款卡时间数值不合法',
            'pending_pay_order_time.numeric' => '收款卡时间数值不合法',
            'pending_pay_order_time.min' => '收款卡时间数值不能小于0',
            'pending_pay_order_time.integer' => '收款卡时间数值不合法',
            'pending_pay_order_number.required' => '收款卡代收待付款订单数值不合法',
            'pending_pay_order_number.numeric' => '收款卡代收待付款订单数值不合法',
            'pending_pay_order_number.min' => '收款卡代收待付款订单数值不能小于0',
            'pending_pay_order_number.integer' => '收款卡代收待付款订单数值不合法',
        ];
    }

    private function settingValue(string $key, int $default): int
    {
        $value = bob_admin_setting($key);

        return $value === null || $value === '' ? $default : intval($value);
    }
}
