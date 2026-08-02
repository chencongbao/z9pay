<?php

namespace App\Services\DepositOrder;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Channel\CheckChannelCurrencyService;
use App\Services\MerchantPayment\MerchantOrderRateService;
use App\Services\Cache\Channel\ChannelInfoByChannelIdService;
use App\Services\DepositOrder\ChannelMode\DispatchModeService;
use App\Services\Cache\MerchantChannel\GetMerchantChannelListService;
use App\Services\Cache\ChannelAccount\CacheLastChannelAccountMapService;

class GetDepositMerchantChannelService
{
    use ServiceTraits;

    public $error;

    public function excute($order = "", $logService = "")
    {
        $this->error = null;
        $this->data = [];

        if (empty($order)) {
            $this->error = "订单信息未创建";
            return null;
        }

        // 预加载币种和支付方式映射，供筛选日志复用。
        $currencyMap = collect(config('default.currency', []))->keyBy('id');
        $paymentMap = collect(config('payment', []))->keyBy('id');

        $currency = $currencyMap->get($order->currency_id);
        $payment = $paymentMap->get($order->payment_id);
        $paymentText = ($payment['code'] ?? '未知支付编码') . ' / ' . ($payment['name'] ?? '未知支付方式');

        $logService->excute(
            $order->id,
            '开始获取商户可用代收渠道',
            [
                '订单号'          => $order->ordernumber,
                '商户ID'     => $order->mid,
                '支付方式'    => $paymentText,
                '支付币种'        => $currency['name'] ?? '未知币种',
                '订单金额'        => $order->amount,
                '是否已填写付款人姓名' => empty($order->pay_name) ? '否' : '是',
            ],
            'debug'
        );

        $merchant_channel_result = App::make(GetMerchantChannelListService::class)->excute($order->mid, $order->payment_id);

        if (empty($merchant_channel_result)) {
            $this->error = "商户未配置可用代收渠道";
            $logService->excute($order->id, "商户未配置可用代收渠道", [
                '处理建议' => '请客服检查商户后台代收渠道配置，并确认渠道已启用',
                '商户ID' => $order->mid,
                '支付方式' => $paymentText,
                '订单金额' => $order->amount,
            ], "error");
            return null;
        } else {
            $channelNames = array_values(array_filter(array_column($merchant_channel_result, 'channel_name')));
            $logService->excute($order->id, "支付方式【{$paymentText}】，商户已配置渠道", [
                '配置渠道数量' => count($channelNames),
                '配置渠道列表' => implode('、', $channelNames),
            ], "debug");
            $channelAccountMap = App::make(CacheLastChannelAccountMapService::class)->excute(
                array_column($merchant_channel_result, 'channel_id')
            );
            $currencyService = App::make(CheckChannelCurrencyService::class);
            $rateService = App::make(MerchantOrderRateService::class);

            foreach ($merchant_channel_result as $merchant_channel_item) {

                $channelId = (int) ($merchant_channel_item['channel_id'] ?? 0);

                // 校验渠道状态和可用账号。
                $channel_result = $this->channelInfo($merchant_channel_item);
                if (empty($channel_result)) {
                    $logService->excute($order->id, "手动配置渠道:channel_id={$channelId} 渠道不存在", [], "error");
                    continue;
                }
                if ($channel_result['status'] == 0) {
                    $logService->excute($order->id, "手动配置渠道:{$channel_result['name']} 渠道已禁用", [], "error");
                    continue;
                }

                $channel_account_result = $channelAccountMap[$channelId] ?? [];
                if (empty($channel_account_result)) {
                    $logService->excute($order->id, "手动配置渠道:{$channel_result['name']} 渠道账号未设置或已禁用", [], "error");
                    continue;
                }

                // 校验渠道支持币种和单笔金额范围。
                if (!$currencyService->excute($order->currency_id, $channel_result['currency'])) {
                    $logService->excute(
                        $order->id,
                        "手动配置渠道:{$channel_result['name']} 代收币种不支持",
                        [
                            '代收币种' => $currency['name'] ?? null,
                            "支持币种" => $this->parseCurrencyName($channel_result['currency'], $currencyMap)
                        ],
                        "error"
                    );
                    continue;
                }

                if (($merchant_channel_item['pay_min_amount'] ?? 0) > 0 && $this->amountLessThan($order->amount, $merchant_channel_item['pay_min_amount'])) {
                    $logService->excute($order->id, "手动配置渠道:{$channel_result['name']} 金额小于单笔下限", [
                        '过滤原因' => '订单金额小于渠道允许最小金额',
                        '订单金额' => $order->amount,
                        '渠道允许最小金额' => $merchant_channel_item['pay_min_amount'],
                    ], "error");
                    continue;
                }
                if (($merchant_channel_item['pay_max_amount'] ?? 0) > 0 && $this->amountGreaterThan($order->amount, $merchant_channel_item['pay_max_amount'])) {
                    $logService->excute($order->id, "手动配置渠道:{$channel_result['name']} 金额大于单笔上限", [
                        '过滤原因' => '订单金额大于渠道允许最大金额',
                        '订单金额' => $order->amount,
                        '渠道允许最大金额' => $merchant_channel_item['pay_max_amount'],
                    ], "error");
                    continue;
                }

                if (!$this->hasMatchedMerchantRate($order, $channelId, $logService, $channel_result['name'], $rateService)) {
                    continue;
                }

                $row = [
                    'payment_id'          => $order->payment_id,
                    'channel_id'          => $channelId,
                    'name'                => $channel_result['name'],
                    'classname'           => $channel_result['classname'],
                    'is_real_name'        => $channel_result['is_real_name'],
                    'deposit_order_id'    => $order->id,
                    'data_type'           => $order->data_type,
                    'pay_name'            => $order->pay_name,
                    'ordernumber'         => $order->ordernumber,
                    'status'              => $channelId === 1 ? 1 : 3,
                    'mid'                 => $order->mid,
                    'amount'              => $order->amount,
                    'merchant_extra_fee'  => $merchant_channel_item['deposit_fee'],
                    'float_status'        => $merchant_channel_item['float_status'],
                    'settlement_mode'     => $merchant_channel_item['settlement_mode'],
                    'settlement_time'     => $merchant_channel_item['settlement_time'],
                    "priority"            => $merchant_channel_item['priority'],
                    "merchant_channel_id" => $merchant_channel_item['id'],
                    "weight"              => (int) ($merchant_channel_item['weight'] ?? 0),
                ];
                $this->data[] = $row;
            }
        }

        if (empty($this->data)) {
            $this->error = "未获取到符合条件的渠道";
            $logService->excute($order->id, "未获取到符合条件的渠道", [], "error");
            return null;
        }

        $logService->excute($order->id, "最终符合条件的渠道", [
            '渠道数量' => count($this->data),
            '渠道信息' => $this->channelLogSummary($this->data),
        ], "debug");

        [$payInfo, $err] = app(DispatchModeService::class)->excute($order, $this->data, $logService);
        if (empty($payInfo)) {
            $this->error = $err;
            return null;
        }

        return $payInfo;
    }

    private function parseCurrencyName($currency, $currencyMap): array
    {
        if (empty($currency)) {
            return $currencyMap->pluck('name')->all();
        }

        return collect(explode(",", $currency))->map(function ($item) use ($currencyMap) {
            return optional($currencyMap->get(trim($item)))->offsetGet('name');
        })->all();
    }

    private function channelInfo(array $merchantChannelItem): array
    {
        if (array_key_exists('channel_status', $merchantChannelItem)) {
            return [
                'id' => $merchantChannelItem['channel_id'] ?? null,
                'name' => $merchantChannelItem['channel_name'] ?? null,
                'status' => $merchantChannelItem['channel_status'] ?? null,
                'classname' => $merchantChannelItem['channel_classname'] ?? null,
                'currency' => $merchantChannelItem['channel_currency'] ?? ($merchantChannelItem['currency'] ?? ''),
                'is_real_name' => $merchantChannelItem['channel_is_real_name'] ?? null,
            ];
        }

        return App::make(ChannelInfoByChannelIdService::class)->excute($merchantChannelItem['channel_id'] ?? null);
    }

    private function amountLessThan($left, $right): bool
    {
        return $this->compareAmount($left, $right) < 0;
    }

    private function amountGreaterThan($left, $right): bool
    {
        return $this->compareAmount($left, $right) > 0;
    }

    private function compareAmount($left, $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$left, (string)$right, 2);
        }

        return (float)$left <=> (float)$right;
    }

    private function hasMatchedMerchantRate($order, int $channelId, $logService, string $channelName, MerchantOrderRateService $rateService): bool
    {
        $saveData = [
            'mid' => $order->mid,
            'payment_id' => $order->payment_id,
            'amount' => $order->amount,
            'merchant_rate' => $order->merchant_rate,
            'merchant_agent1_rate' => $order->merchant_agent1_rate,
            'merchant_agent2_rate' => $order->merchant_agent2_rate,
            'merchant_agent3_rate' => $order->merchant_agent3_rate,
        ];
        $rateResult = $rateService->fillDepositFinalRate($saveData, $channelId);
        if (!empty($rateResult['success'])) {
            return true;
        }

        $logService->excute($order->id, "手动配置渠道:{$channelName} 代收费率未匹配", [
            '过滤原因' => $rateResult['zh_message'] ?? '未匹配到通道费率',
            '订单金额' => $order->amount,
            '渠道ID' => $channelId,
        ], "error");

        return false;
    }

    private function channelLogSummary(array $channels): array
    {
        return collect($channels)->map(function ($item) {
            return [
                '渠道ID' => $item['channel_id'] ?? null,
                '渠道名称' => $item['name'] ?? null,
                '渠道状态' => $this->formatChannelStatus($item['status'] ?? null),
                '优先级' => $item['priority'] ?? null,
                '权重' => $item['weight'] ?? null,
                '商户额外手续费' => $item['merchant_extra_fee'] ?? null,
                '结算模式' => $this->formatSettlementMode($item['settlement_mode'] ?? null),
            ];
        })->all();
    }

    private function formatChannelStatus($status): string
    {
        if ((int)$status === 1) {
            return '等待填写付款人姓名';
        }

        if ((int)$status === 3) {
            return '已返回收款信息，待支付';
        }

        return '未知状态:' . ($status ?? '-');
    }

    private function formatSettlementMode($mode): string
    {
        if ((int)$mode === 0) {
            return '默认结算';
        }

        if ((int)$mode === 1) {
            return '按配置结算';
        }

        return '未知模式:' . ($mode ?? '-');
    }
}
