<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;

class RiskForm extends Form
{
    private const DEPOSIT_REFRESH_KEYS = [
        1 => "同商户",
        2 => "同IP",
        3 => "同姓名",
        4 => "同金额",
    ];

    public function handle(array $input)
    {
        $data = $this->normalizeInput($input);
        $validator = Validator::make($data, [
            'base_deposit_refresh_order_switch' => ['required', 'integer', 'in:0,1'],
            'base_deposit_refresh_order_key' => ['exclude_unless:base_deposit_refresh_order_switch,1', 'required', 'array', 'min:2'],
            'base_deposit_refresh_order_key.*' => ['integer', 'in:' . implode(',', array_keys(self::DEPOSIT_REFRESH_KEYS))],
            'base_deposit_refresh_order_time' => ['exclude_unless:base_deposit_refresh_order_switch,1', 'required', 'integer', "min:1"],
            'base_deposit_refresh_order_number' => ['exclude_unless:base_deposit_refresh_order_switch,1', 'required', 'integer', "min:1"],
            'base_transfer_same_card_name_switch' => ['required', 'integer', 'in:0,1'],
            'base_transfer_same_card_name_number' => ['exclude_unless:base_transfer_same_card_name_switch,1', 'required', 'integer', 'min:1'],
            'base_transfer_same_card_name_time' => ['exclude_unless:base_transfer_same_card_name_switch,1', 'required', 'integer', 'min:1'],
        ], [
            "base_deposit_refresh_order_switch.required" => "代收刷单开关不合法",
            "base_deposit_refresh_order_switch.integer" => "代收刷单开关不合法",
            "base_deposit_refresh_order_switch.in" => "代收刷单开关不合法",
            "base_deposit_refresh_order_key.required" => "代收刷单维度不能为空",
            "base_deposit_refresh_order_key.array" => "代收刷单维度不合法",
            "base_deposit_refresh_order_key.min" => "代收刷单维度至少选择2项",
            "base_deposit_refresh_order_key.*.integer" => "代收刷单维度不合法",
            "base_deposit_refresh_order_key.*.in" => "代收刷单维度不合法",
            'base_deposit_refresh_order_time.required' => "代收刷单时长不合法",
            'base_deposit_refresh_order_time.integer' => "代收刷单时长不合法",
            'base_deposit_refresh_order_time.min' => "代收刷单时长不能小于1",
            'base_deposit_refresh_order_number.required' => "代收刷单单数不合法",
            'base_deposit_refresh_order_number.integer' => "代收刷单单数不合法",
            'base_deposit_refresh_order_number.min' => "代收刷单单数不能小于1",
            'base_transfer_same_card_name_switch.required' => "代付订单同卡同名限制开关值不合法",
            'base_transfer_same_card_name_switch.integer' => "代付订单同卡同名限制开关值不合法",
            'base_transfer_same_card_name_switch.in' => "代付订单同卡同名限制开关值不合法",
            'base_transfer_same_card_name_number.required' => "代付订单同卡同名限制次数不合法",
            'base_transfer_same_card_name_number.integer' => "代付订单同卡同名限制次数不合法",
            'base_transfer_same_card_name_number.min' => "代付订单同卡同名限制次数不能小于1次",
            'base_transfer_same_card_name_time.required' => "代付订单同卡同名限制时长不合法",
            'base_transfer_same_card_name_time.integer' => "代付订单同卡同名限制时长不合法",
            'base_transfer_same_card_name_time.min' => "代付订单同卡同名限制时长不能小于1分钟",
        ]);
        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }
        bob_admin_setting($data);

        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.risk.update',
            text: '修改 风控配置',
            subject: null,
            properties: [
                'base_deposit_refresh_order_switch' => (int)($data['base_deposit_refresh_order_switch'] ?? 0),
                'base_transfer_same_card_name_switch' => (int)($data['base_transfer_same_card_name_switch'] ?? 0),
                'base_transfer_same_card_name_number' => (int)($data['base_transfer_same_card_name_number'] ?? 0),
                'base_transfer_same_card_name_time' => (int)($data['base_transfer_same_card_name_time'] ?? 0),
            ],
            remark: '修改 风控配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success("设置成功")->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can("config.risk");
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');

        $this->fieldset('代收风控设置', function (Form $form) {
            $this->radio("base_deposit_refresh_order_switch", "1. 代收刷单开关")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("开启后，需要继续配置时长、维度和单数，这三项会一起生效。")
                ->when(1, function () {
                    $this->number("base_deposit_refresh_order_time", "2. 代收刷单时长")->min(1)->help("单位分钟")->width(8, 4);
                    $this->checkbox("base_deposit_refresh_order_key", "3. 代收刷单维度")->options(self::DEPOSIT_REFRESH_KEYS)->help("至少选择 2 项维度进行组合判断。")->width(8, 4);
                    $this->number("base_deposit_refresh_order_number", "4. 代收刷单单数")->min(1)->help("达到设定单数后触发刷单风控。")->width(8, 4);
                })->width(8, 4);
        });
        $this->fieldset('代付风控设置', function (Form $form) {
            $this->radio("base_transfer_same_card_name_switch", "1. 代付订单同卡同名限制开关")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("开启后，需要同时配置限制次数和限制时长。")
                ->when(1, function () {
                    $this->number("base_transfer_same_card_name_number", "2. 代付订单同卡同名限制次数")->min(1)->help("在限制时长内达到这个次数会触发限制。")->width(8, 4);
                    $this->number("base_transfer_same_card_name_time", "3. 代付订单同卡同名限制时长")->min(1)->help("单位分钟")->width(8, 4);
                })->width(8, 4);
        });
    }

    public function default()
    {
        return [
            'base_deposit_refresh_order_switch' => intval(bob_admin_setting('base_deposit_refresh_order_switch')),
            'base_deposit_refresh_order_time' => bob_admin_setting('base_deposit_refresh_order_time') ?: 1,
            'base_deposit_refresh_order_number' => bob_admin_setting('base_deposit_refresh_order_number') ?: 1,
            'base_deposit_refresh_order_key' => bob_admin_setting('base_deposit_refresh_order_key') ?: [1, 2, 4],
            'base_transfer_same_card_name_switch' => intval(bob_admin_setting('base_transfer_same_card_name_switch')),
            'base_transfer_same_card_name_number' => bob_admin_setting('base_transfer_same_card_name_number') ?: 1,
            'base_transfer_same_card_name_time' => bob_admin_setting('base_transfer_same_card_name_time') ?: 1,
        ];
    }

    private function normalizeInput(array $input): array
    {
        return [
            'base_deposit_refresh_order_switch' => $this->normalizeValue($input['base_deposit_refresh_order_switch'] ?? 0),
            'base_deposit_refresh_order_time' => $this->normalizeValue($input['base_deposit_refresh_order_time'] ?? 1),
            'base_deposit_refresh_order_key' => $this->normalizeRefreshKeys($input['base_deposit_refresh_order_key'] ?? []),
            'base_deposit_refresh_order_number' => $this->normalizeValue($input['base_deposit_refresh_order_number'] ?? 1),
            'base_transfer_same_card_name_switch' => $this->normalizeValue($input['base_transfer_same_card_name_switch'] ?? 0),
            'base_transfer_same_card_name_number' => $this->normalizeValue($input['base_transfer_same_card_name_number'] ?? 1),
            'base_transfer_same_card_name_time' => $this->normalizeValue($input['base_transfer_same_card_name_time'] ?? 1),
        ];
    }

    private function normalizeValue($value): string
    {
        return trim((string)$value);
    }

    private function normalizeRefreshKeys($keys): array
    {
        if (!is_array($keys)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $keys))));
    }
}
