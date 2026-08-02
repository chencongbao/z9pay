<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Form\NestedForm;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use App\Services\Common\SystemLogService;
use App\Services\Cache\Channel\GetChannelListService;

class NoticeForm extends Form
{
    public function handle(array $input)
    {
        $data = $this->normalizeInput($input);

        $validator = Validator::make($data, [
            'notice_voice_on' => 'required|in:0,1',
            'notice_text_on' => 'required|in:0,1',
            'telegram_balance_notice_interval' => ['nullable', 'integer', 'min:0'],
            'telegram_channel_exception_notice_switch' => ['required', 'in:0,1'],
            'telegram_channel_exception_notice_telegram_group_ids' => ['exclude_unless:telegram_channel_exception_notice_switch,1', 'required'],
        ], [
            'notice_voice_on.required' => '语音通知开关不合法',
            'notice_voice_on.in' => '语音通知开关不合法',
            'notice_text_on.required' => "文本通知开关不合法",
            'notice_text_on.in' => "文本通知开关不合法",
            'telegram_balance_notice_interval.integer' => "余额预警重复通知间隔不合法",
            'telegram_balance_notice_interval.min' => "余额预警重复通知间隔不能小于0分钟",
            'telegram_channel_exception_notice_switch.required' => "渠道订单异常通知开关不合法",
            "telegram_channel_exception_notice_switch.in" => "渠道订单异常通知开关不合法",
            "telegram_channel_exception_notice_telegram_group_ids.required" => "渠道订单异常通知群必填",
        ]);
        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }

        if ($error = $this->validateChannelBalanceConfig($data['telegram_channel_balance_notice_single'])) {
            return $this->response()->error($error);
        }

        bob_admin_setting($data);

        $adminUser = Admin::user();
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.notice.update',
            text: '修改 通知配置',
            subject: null,
            properties: [
                'notice_voice_on' => (int)($data['notice_voice_on'] ?? 0),
                'notice_text_on' => (int)($data['notice_text_on'] ?? 0),
                'telegram_balance_notice_interval' => (int)($data['telegram_balance_notice_interval'] ?? 3),
                'telegram_channel_exception_notice_switch' => (int)($data['telegram_channel_exception_notice_switch'] ?? 0),
                'telegram_channel_balance_notice_single_count' => count($data['telegram_channel_balance_notice_single']),
                'telegram_channel_balance_notice_telegram_group_ids' => (string)($data['telegram_channel_balance_notice_telegram_group_ids'] ?? ''),
            ],
            remark: '修改 通知配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $adminUser
        );

        return $this->response()->success("设置成功")->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can("config.notice");
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');
        $channelOptions = collect(App::make(GetChannelListService::class)->excute())->pluck('bname', 'id')->toArray();

        $this->fieldset('语音通知', function (Form $form) {
            $this->radio("notice_voice_on", "1. 语音通知开关")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("开启后，还需要继续勾选下方对应的通知项。")
                ->when(1, function () {
                    $this->checkbox("notice_deposit_voice_notice", "2. 代收订单语音通知项")->options(
                        [
                            2 => "刷单",
                            4 => "超时",
                            6 => "失败",
                            7 => "待确认",
                            9 => "回调失败",
                            10 => "渠道返回错误",
                        ]
                    )->help("按需勾选需要播报的代收场景。")->width(8, 4);
                    $this->checkbox("notice_transter_voice_notice", "3. 代付订单语音通知项")->options(
                        [
                            3 => "待处理",
                            5 => "失败",
                            8 => "回调失败",
                            9 => "渠道返回错误",
                            10 => "金主代付待确认",
                        ]
                    )->help("按需勾选需要播报的代付场景。")->width(8, 4);
                    $this->checkbox("notice_settlement_voice_notice", "4. 结算订单语音通知项")->options(
                        [
                            3 => "待处理",
                            5 => "失败",
                            6 => "处理中",
                            10 => "金主代付待确认",
                        ]
                    )->help("按需勾选需要播报的结算场景。")->width(8, 4);
                })->width(8, 4);
        });

        $this->fieldset('文本通知', function (Form $form) {
            $this->radio("notice_text_on", "1. 文本通知开关")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("开启后，还需要继续勾选下方对应的通知项。")
                ->when(1, function () {
                    $this->checkbox("notice_deposit_text_notice", "2. 代收订单文本通知项")->options(
                        [
                            2 => "刷单",
                            4 => "超时",
                            6 => "失败",
                            7 => "待确认",
                            9 => "回调失败",
                            10 => "渠道返回错误",
                        ]
                    )->help("按需勾选需要发送文本通知的代收场景。")->width(8, 4);
                    $this->checkbox("notice_transter_text_notice", "3. 代付订单文本通知项")->options(
                        [
                            3 => "待处理",
                            5 => "失败",
                            8 => "回调失败",
                            9 => "渠道返回错误",
                            10 => "金主代付待确认",
                        ]
                    )->help("按需勾选需要发送文本通知的代付场景。")->width(8, 4);
                    $this->checkbox("notice_settlement_text_notice", "4. 结算订单文本通知项")->options(
                        [
                            3 => "待处理",
                            5 => "失败",
                            6 => "处理中",
                            10 => "金主代付待确认",
                        ]
                    )->help("按需勾选需要发送文本通知的结算场景。")->width(8, 4);
                })->width(8, 4);
        });

        $this->fieldset('渠道余额预警（这一组需要一起配置）', function (Form $form) use ($channelOptions) {
            $this->number("telegram_balance_notice_interval", "余额预警重复通知间隔")
                ->min(0)
                ->help("单位：分钟；商户余额预警和渠道余额预警共用，默认3分钟；设置0表示不限制，达到条件实时发送。")
                ->width(8, 4);

            $this->table("telegram_channel_balance_notice_single", "1. 渠道余额阈值设置", function (NestedForm $table) use ($channelOptions) {
                $table->select('cid', "所属渠道")->options($channelOptions)->required();
                $table->number('value', "通知金额")->min(0)->required();
            })->help("先新增渠道和对应通知金额，只有这里配置过的渠道才会触发余额预警。")->width(8, 4);

            $this->text("telegram_channel_balance_notice_telegram_group_ids", "2. 渠道余额通知群")
                ->help("多个请用逗号隔开，填写个人ID或群组ID；这一项用于接收上面余额阈值触发后的预警消息。")
                ->width(8, 4);
        });

        $this->fieldset('渠道异常通知（开关和接收群需要配套）', function (Form $form) {
            $this->radio("telegram_channel_exception_notice_switch", "1. 渠道订单异常通知开关")
                ->options([0 => "关闭", 1 => "开启"])
                ->help("开启后，必须继续填写下方通知群。")
                ->width(8, 4)
                ->when(1, function () {
                    $this->text("telegram_channel_exception_notice_telegram_group_ids", "2. 渠道订单异常通知群")
                        ->help("多个请用逗号隔开，填写个人ID或群组ID。")
                        ->width(8, 4);
                });
        });
    }

    public function default()
    {
        $balanceNoticeInterval = bob_admin_setting('telegram_balance_notice_interval');

        return [
            'notice_voice_on' => intval(bob_admin_setting('notice_voice_on')),
            'notice_deposit_voice_notice' => bob_admin_setting('notice_deposit_voice_notice') ?: '',
            'notice_transter_voice_notice' => bob_admin_setting('notice_transter_voice_notice') ?: '',
            'notice_settlement_voice_notice' => bob_admin_setting('notice_settlement_voice_notice') ?: '',
            'notice_deposit_text_notice' => bob_admin_setting('notice_deposit_text_notice') ?: '',
            'notice_transter_text_notice' => bob_admin_setting('notice_transter_text_notice') ?: '',
            'notice_settlement_text_notice' => bob_admin_setting('notice_settlement_text_notice') ?: '',
            'notice_text_on' => intval(bob_admin_setting('notice_text_on')),
            'telegram_balance_notice_interval' => $balanceNoticeInterval === null || $balanceNoticeInterval === '' ? 3 : $balanceNoticeInterval,
            'telegram_channel_balance_notice_single' => bob_admin_setting('telegram_channel_balance_notice_single') ?: [],
            'telegram_channel_balance_notice_telegram_group_ids' => bob_admin_setting('telegram_channel_balance_notice_telegram_group_ids') ?: '',
            'telegram_channel_exception_notice_switch' => intval(bob_admin_setting('telegram_channel_exception_notice_switch')),
            'telegram_channel_exception_notice_telegram_group_ids' => bob_admin_setting("telegram_channel_exception_notice_telegram_group_ids") ?: '',
        ];
    }

    private function normalizeInput(array $input): array
    {
        $balanceNoticeInterval = $input['telegram_balance_notice_interval'] ?? null;

        return [
            'notice_voice_on' => intval($input['notice_voice_on'] ?? 0),
            'notice_deposit_voice_notice' => $this->normalizeCheckbox($input['notice_deposit_voice_notice'] ?? []),
            'notice_transter_voice_notice' => $this->normalizeCheckbox($input['notice_transter_voice_notice'] ?? []),
            'notice_settlement_voice_notice' => $this->normalizeCheckbox($input['notice_settlement_voice_notice'] ?? []),
            'notice_text_on' => intval($input['notice_text_on'] ?? 0),
            'notice_deposit_text_notice' => $this->normalizeCheckbox($input['notice_deposit_text_notice'] ?? []),
            'notice_transter_text_notice' => $this->normalizeCheckbox($input['notice_transter_text_notice'] ?? []),
            'notice_settlement_text_notice' => $this->normalizeCheckbox($input['notice_settlement_text_notice'] ?? []),
            'telegram_balance_notice_interval' => $balanceNoticeInterval === null || $balanceNoticeInterval === '' ? 3 : intval($balanceNoticeInterval),
            'telegram_channel_balance_notice_single' => array_map(function (array $item) {
                return [
                    'cid' => intval($item['cid'] ?? 0),
                    'value' => trim((string)($item['value'] ?? '')),
                ];
            }, $this->activeRows($input['telegram_channel_balance_notice_single'] ?? [])),
            'telegram_channel_balance_notice_telegram_group_ids' => trim((string)($input['telegram_channel_balance_notice_telegram_group_ids'] ?? '')),
            'telegram_channel_exception_notice_switch' => intval($input['telegram_channel_exception_notice_switch'] ?? 0),
            'telegram_channel_exception_notice_telegram_group_ids' => trim((string)($input['telegram_channel_exception_notice_telegram_group_ids'] ?? '')),
        ];
    }

    private function normalizeCheckbox($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $values), fn ($value) => $value > 0));
    }

    private function activeRows(array $rows): array
    {
        return array_values(array_filter($rows, fn ($item) => is_array($item) && intval($item['_remove_'] ?? 0) === 0));
    }

    private function validateChannelBalanceConfig(array $items): string
    {
        foreach ($items as $item) {
            if (intval($item['cid'] ?? 0) <= 0) {
                return '渠道余额阈值必须选择渠道';
            }
            if (($item['value'] ?? '') === '') {
                return '渠道余额通知金额不能为空';
            }
            if (!is_numeric($item['value']) || floatval($item['value']) < 0) {
                return '渠道余额通知金额不合法';
            }
        }

        return '';
    }
}
