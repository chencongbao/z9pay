<?php

namespace App\Admin\Forms\Config;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Form\NestedForm;
use Illuminate\Support\Facades\App;
use App\Services\Enums\LanguageEnum;
use App\Services\Common\SystemLogService;
use App\Services\Merchant\GetMerchantListService;
use App\Services\Telegram\MerchantBotOrderLookupRuleService;

class MerchantForm extends Form
{
    public function handle(array $input)
    {
        $showGcashMerchantNameConfig = $this->showGcashMerchantNameConfig();
        $data = $this->normalizeInput($input, $showGcashMerchantNameConfig);
        if ($error = $this->validateBalanceNoticeConfig($data['telegram_merchant_balance_notice_single'])) {
            return $this->response()->error($error);
        }
        if ($error = $this->validateOrderLimitConfig($data['api_merchant_order_limit'])) {
            return $this->response()->error($error);
        }
        $data['api_merchant_order_limit'] = $this->normalizeOrderLimitConfig($data['api_merchant_order_limit']);
        if ($error = $this->validateLangConfig($data['telegram_merchant_group_lang_config'])) {
            return $this->response()->error($error);
        }
        if ($error = $this->validateMerchantBotOrderLookupRules($data['telegram_merchant_bot_order_lookup_rules'])) {
            return $this->response()->error($error);
        }
        if ($showGcashMerchantNameConfig && $error = $this->validateGcashMerchantNameConfig($data['gcash_merchant_name_default'], $data['gcash_merchant_name_merchants'])) {
            return $this->response()->error($error);
        }

        bob_admin_setting($data);

        $adminUser = Admin::user();
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.merchant.update',
            text: '修改 商户配置',
            subject: null,
            properties: [
                'telegram_merchant_balance_notice_single_count' => count($data['telegram_merchant_balance_notice_single']),
                'api_merchant_order_limit_count' => count($data['api_merchant_order_limit']),
                'telegram_merchant_group_lang_config_count' => count($data['telegram_merchant_group_lang_config']),
                'telegram_merchant_bot_order_lookup_rules_count' => count($data['telegram_merchant_bot_order_lookup_rules']),
                'gcash_merchant_name_default' => $data['gcash_merchant_name_default'],
                'gcash_merchant_name_merchants_count' => count($data['gcash_merchant_name_merchants']),
            ],
            remark: '修改 商户配置',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $adminUser
        );

        return $this->response()->success("设置成功")->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can("config.merchant");
    }

    public function form()
    {
        $this->confirm('提示', '确定提交？');
        $merchantOptions = App::make(GetMerchantListService::class)->excute(['currency_id'], true);

        $this->fieldset('商户余额预警', function (Form $form) use ($merchantOptions) {
            $this->table("telegram_merchant_balance_notice_single", "1. 商户余额通知单个设置", function (NestedForm $table) use ($merchantOptions) {
                $table->select('mid', "所属商户")->options($merchantOptions)->required();
                $table->select('compare', "通知条件")->options(['lt' => '小于金额通知', 'gt' => '大于金额通知'])->default('lt');
                $table->text('value', "通知金额")->required()->help("必填，支持设置为负数和0。");
            })->help("按商户单独配置通知阈值；旧配置默认按“小于金额通知”处理，通知金额支持负数。");
        });

        $this->fieldset('商户提单限制', function (Form $form) use ($merchantOptions) {
            $this->table("api_merchant_order_limit", "1. 商户提单限制/分钟", function (NestedForm $table) use ($merchantOptions) {
                $table->select('mid', "所属商户")->options($merchantOptions)->required();
                $table->number('deposit_order', "代收订单/分钟")->default(0)->min(0)->help("填 0 表示不单独限制，继续使用系统默认值。");
                $table->number('transfer_order', "代付订单/分钟")->default(0)->min(0)->help("填 0 表示不单独限制，继续使用系统默认值。");
            })->help("这一组是按商户做单独限流；未配置的商户继续按系统默认限制执行。");
        });

        $this->fieldset('商户群多语言', function (Form $form) use ($merchantOptions) {
            $this->table("telegram_merchant_group_lang_config", "1. 商户群消息多语言配置", function (NestedForm $table) use ($merchantOptions) {
                $table->select('lang', "语言")->options(LanguageEnum::all())->required();
                $table->multipleSelect('mids', "选择商户")->options($merchantOptions)->required();
            })->help("按“语言 -> 多个商户”的方式配置；未配置语言的商户默认英文兜底，一个商户只能归属一个语言配置。");
        });

        $this->fieldset('商户机器人查单识别配置', function (Form $form) use ($merchantOptions) {
            $this->table('telegram_merchant_bot_order_lookup_rules', '1. 订单号识别规则', function (NestedForm $table) use ($merchantOptions) {
                $table->select('mid', '所属商户')->options($merchantOptions)->required();
                $table->radio('status', '状态')->options([0 => '关闭', 1 => '开启'])->default(1)->required();
                $table->textarea('order_no_rules', '订单号规则')->rows(3)->required()->help('每行一个正则。已配置商户按这里的规则提取订单号；未配置商户沿用系统原有查单解析。示例：(E[A-Z0-9]{16,40}|CZ[A-Z0-9]{12,40})');
            })->help('配置并开启时按商户专属规则识别；配置后关闭则不识别；完全未配置时沿用原有客服查单解析和转发流程。');
        });

        if ($this->showGcashMerchantNameConfig()) {
            $this->fieldset('菲律宾 GCash 展示名称配置', function (Form $form) use ($merchantOptions) {
                $this->text('gcash_merchant_name_default', '1. 全局默认名称')->help('用于菲律宾 GCash QR 唤醒/扫码时展示的商户名称。商户未单独配置时，默认使用这里的名称；留空则后续接入时继续使用通道返回或原有名称。');
                $this->table('gcash_merchant_name_merchants', '2. 商户单独配置', function (NestedForm $table) use ($merchantOptions) {
                    $table->text('merchant_name', 'GCash展示名称')->required()->help('优先级高于全局默认名称，最多 64 个字符。');
                    $table->multipleSelect('mids', '适用商户')->options($merchantOptions)->required()->help('可选择多个商户共用同一个 GCash 展示名称。');
                })->help('一行名称可绑定多个商户；同一个商户不能出现在多行。优先级：商户单独配置 > 全局默认名称 > 原有通道名称。当前仅保存后台配置，后续接入菲律宾通道后生效。');
            });
        }
    }

    public function default()
    {
        return [
            'telegram_merchant_balance_notice_single' => bob_admin_setting('telegram_merchant_balance_notice_single') ?: [],
            'api_merchant_order_limit' => bob_admin_setting('api_merchant_order_limit') ?: [],
            'telegram_merchant_group_lang_config' => bob_admin_setting('telegram_merchant_group_lang_config') ?: [],
            'telegram_merchant_bot_order_lookup_rules' => bob_admin_setting('telegram_merchant_bot_order_lookup_rules') ?: [],
            'gcash_merchant_name_default' => bob_admin_setting('gcash_merchant_name_default') ?: '',
            'gcash_merchant_name_merchants' => $this->normalizeGcashMerchantNameDefaultRows(bob_admin_setting('gcash_merchant_name_merchants') ?: []),
        ];
    }

    private function normalizeInput(array $input, bool $showGcashMerchantNameConfig): array
    {
        return [
            'telegram_merchant_balance_notice_single' => array_map(function (array $item) {
                $compare = $item['compare'] ?? 'lt';

                return [
                    'mid' => intval($item['mid'] ?? 0),
                    'compare' => in_array($compare, ['lt', 'gt'], true) ? $compare : 'lt',
                    'value' => trim((string)($item['value'] ?? '')),
                ];
            }, $this->activeRows($input['telegram_merchant_balance_notice_single'] ?? [])),
            'api_merchant_order_limit' => array_map(function (array $item) {
                return [
                    'mid' => intval($item['mid'] ?? 0),
                    'deposit_order' => trim((string)($item['deposit_order'] ?? '0')),
                    'transfer_order' => trim((string)($item['transfer_order'] ?? '0')),
                ];
            }, $this->activeRows($input['api_merchant_order_limit'] ?? [])),
            'telegram_merchant_group_lang_config' => array_map(function (array $item) {
                return [
                    'lang' => trim((string)($item['lang'] ?? '')),
                    'mids' => array_values(array_unique(array_filter(array_map('intval', $item['mids'] ?? [])))),
                ];
            }, $this->activeRows($input['telegram_merchant_group_lang_config'] ?? [])),
            'telegram_merchant_bot_order_lookup_rules' => array_map(function (array $item) {
                return [
                    'mid' => intval($item['mid'] ?? 0),
                    'status' => intval($item['status'] ?? 0) === 1 ? 1 : 0,
                    'order_no_rules' => trim((string)($item['order_no_rules'] ?? '')),
                ];
            }, $this->activeRows($input['telegram_merchant_bot_order_lookup_rules'] ?? [])),
            'gcash_merchant_name_default' => $showGcashMerchantNameConfig ? trim((string)($input['gcash_merchant_name_default'] ?? '')) : (string)(bob_admin_setting('gcash_merchant_name_default') ?: ''),
            'gcash_merchant_name_merchants' => $showGcashMerchantNameConfig ? array_map(function (array $item) {
                return [
                    'merchant_name' => trim((string)($item['merchant_name'] ?? '')),
                    'mids' => array_values(array_unique(array_filter(array_map('intval', $item['mids'] ?? [])))),
                ];
            }, $this->activeRows($input['gcash_merchant_name_merchants'] ?? [])) : $this->settingArray('gcash_merchant_name_merchants'),
        ];
    }

    private function activeRows(array $rows): array
    {
        return array_values(array_filter($rows, fn ($item) => is_array($item) && intval($item['_remove_'] ?? 0) === 0));
    }

    private function validateBalanceNoticeConfig(array $items): string
    {
        foreach ($items as $item) {
            if (intval($item['mid'] ?? 0) <= 0) {
                return '商户余额通知必须选择商户';
            }
            if (($item['value'] ?? '') === '') {
                return '商户余额通知金额不能为空';
            }
            if (!is_numeric($item['value'])) {
                return '商户余额通知金额必须是数字';
            }
        }

        return '';
    }

    private function validateOrderLimitConfig(array $items): string
    {
        foreach ($items as $item) {
            if (intval($item['mid'] ?? 0) <= 0) {
                return '商户提单限制必须选择商户';
            }
            if (!$this->isNonNegativeInteger($item['deposit_order'] ?? null)) {
                return '商户提单限制代收订单/分钟必须是大于等于0的整数';
            }
            if (!$this->isNonNegativeInteger($item['transfer_order'] ?? null)) {
                return '商户提单限制代付订单/分钟必须是大于等于0的整数';
            }
        }

        return '';
    }

    private function normalizeOrderLimitConfig(array $items): array
    {
        return array_map(fn (array $item) => [
            'mid' => intval($item['mid'] ?? 0),
            'deposit_order' => intval($item['deposit_order'] ?? 0),
            'transfer_order' => intval($item['transfer_order'] ?? 0),
        ], $items);
    }

    private function isNonNegativeInteger($value): bool
    {
        $value = trim((string)$value);

        return $value !== '' && ctype_digit($value);
    }

    private function validateLangConfig(array $items): string
    {
        $langOptions = LanguageEnum::all();
        $merchantLangMap = [];

        foreach ($items as $item) {
            $lang = trim((string)($item['lang'] ?? ''));
            $mids = array_values(array_filter(array_map('intval', $item['mids'] ?? [])));

            if ($lang === '' || empty($langOptions[$lang])) {
                return '商户群消息多语言配置中的语言不合法';
            }
            if (empty($mids)) {
                return '商户群消息多语言配置中，语言【' . ($langOptions[$lang] ?? $lang) . '】必须至少选择一个商户';
            }

            foreach ($mids as $mid) {
                if (isset($merchantLangMap[$mid]) && $merchantLangMap[$mid] !== $lang) {
                    return '商户ID【' . $mid . '】已重复配置到多个语言，请检查';
                }
                $merchantLangMap[$mid] = $lang;
            }
        }

        return '';
    }

    private function validateMerchantBotOrderLookupRules(array $items): string
    {
        $ruleService = app(MerchantBotOrderLookupRuleService::class);
        foreach ($items as $item) {
            if (intval($item['mid'] ?? 0) <= 0) {
                return '商户机器人查单识别配置必须选择商户';
            }

            $rules = preg_split('/\r\n|\r|\n/', (string)($item['order_no_rules'] ?? '')) ?: [];
            $rules = array_values(array_filter(array_map('trim', $rules)));
            if (empty($rules)) {
                return '商户机器人查单识别配置的订单号规则不能为空';
            }

            foreach ($rules as $rule) {
                if (!$ruleService->isValidRule($rule)) {
                    return '商户机器人查单识别配置的订单号规则不合法：' . $rule;
                }
            }
        }

        return '';
    }

    private function validateGcashMerchantNameConfig(string $defaultName, array $items): string
    {
        if ($defaultName !== '' && strlen($defaultName) > 64) {
            return '菲律宾渠道全局默认名称不能超过64个字符';
        }

        $merchantMap = [];
        foreach ($items as $item) {
            $merchantName = trim((string)($item['merchant_name'] ?? ''));
            $mids = array_values(array_filter(array_map('intval', $item['mids'] ?? [])));

            if ($merchantName === '') {
                return '菲律宾渠道商户单独配置名称不能为空';
            }
            if (strlen($merchantName) > 64) {
                return '菲律宾渠道商户单独配置名称不能超过64个字符';
            }
            if (empty($mids)) {
                return '菲律宾渠道商户单独配置必须至少选择一个商户';
            }

            foreach ($mids as $mid) {
                if (isset($merchantMap[$mid])) {
                    return '商户ID【' . $mid . '】已重复配置菲律宾渠道名称，请检查';
                }

                $merchantMap[$mid] = true;
            }
        }

        return '';
    }

    private function normalizeGcashMerchantNameDefaultRows($items): array
    {
        $items = $this->normalizeArrayValue($items);
        $grouped = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $merchantName = trim((string)($item['merchant_name'] ?? ''));
            if ($merchantName === '') {
                continue;
            }

            $mids = [];
            if (!empty($item['mids']) && is_array($item['mids'])) {
                $mids = array_values(array_unique(array_filter(array_map('intval', $item['mids']))));
            } elseif (!empty($item['mid'])) {
                $mids = [intval($item['mid'])];
            }

            if (empty($mids)) {
                continue;
            }

            $grouped[$merchantName] = array_values(array_unique(array_merge($grouped[$merchantName] ?? [], $mids)));
        }

        return array_map(fn ($merchantName, $mids) => [
            'merchant_name' => $merchantName,
            'mids' => $mids,
        ], array_keys($grouped), array_values($grouped));
    }

    private function settingArray(string $key): array
    {
        return $this->normalizeArrayValue(bob_admin_setting($key) ?: []);
    }

    private function normalizeArrayValue($items): array
    {
        if (is_array($items)) {
            return $items;
        }

        if (is_string($items)) {
            $decoded = json_decode($items, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function showGcashMerchantNameConfig(): bool
    {
        return (bool)config('default.gcash_merchant_name_config_visible', false);
    }
}
