<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Validator;
use App\Services\Common\SystemLogService;
use App\Services\Enums\DepositChannelModeEnum;

class DepositForm extends Form
{
    public function handle(array $input)
    {
        $data = $this->normalizeInput($input);
        $validator = Validator::make($data, [
            'deposit_order_switch' => ['required', 'in:0,1'],
            'base_deposit_over_time' => ['required', 'integer', 'min:1'],
            'other_deposit_channel_mode' => ['required', 'in:1,2,3,4,5'],
        ], [
            'deposit_order_switch.required' => '代收开关值不合法',
            'deposit_order_switch.in' => '代收开关值不合法',
            'base_deposit_over_time.required' => '代收订单超时时间不合法',
            'base_deposit_over_time.integer' => '代收订单超时时间不合法',
            'base_deposit_over_time.min' => '代收订单超时时间不能小于1',
            'other_deposit_channel_mode.required' => '代收渠道匹配模式不合法',
            'other_deposit_channel_mode.in' => '代收渠道匹配模式不合法',
        ]);
        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }

        bob_admin_setting($data);

        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.deposit.update',
            text: '修改 代收配置',
            subject: null,
            properties: $data,
            remark: '修改 代收配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('设置成功')->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('config.deposit');
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');
        $this->radio('deposit_order_switch', '代收开关')->options([0 => '收款关闭', 1 => '收款开启'])->default(1)->help('代收订单总开关，此设置全局生效，谨慎操作')->width(8, 4);
        $this->number('base_deposit_over_time', '代收订单超时时间')->help('单位分钟')->width(8, 4);
        $this->radio('other_deposit_channel_mode', '代收渠道匹配模式')->options(DepositChannelModeEnum::MAP)->help('模式说明<br/>1.按优先级是指按照系统设置的优先级从小到大顺序,符合条件的返回<br/>2.按随机是指所有符合条件的渠道随机返回<br/>3.按平均是指符合条件的渠道按平均返回，<br/>4.按平均轮询是指按照平均来轮询渠道')->width(8, 4);
    }

    public function default()
    {
        return [
            'deposit_order_switch' => intval(bob_admin_setting('deposit_order_switch')),
            'base_deposit_over_time' => bob_admin_setting('base_deposit_over_time') ?: 15,
            'other_deposit_channel_mode' => bob_admin_setting('other_deposit_channel_mode') ?: 1,
        ];
    }

    private function normalizeInput(array $input): array
    {
        return [
            'deposit_order_switch' => (int)($input['deposit_order_switch'] ?? 0),
            'base_deposit_over_time' => (int)($input['base_deposit_over_time'] ?? 0),
            'other_deposit_channel_mode' => (int)($input['other_deposit_channel_mode'] ?? 0),
        ];
    }
}
