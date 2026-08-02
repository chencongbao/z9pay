<?php

namespace App\Services\TransferOrder;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Services\Enums\ErrorCodeEnum;
use App\Services\Const\LogConstService;
use App\Services\Order\OrderCacheService;
use App\Services\Common\ReportExceptionService;
use App\Services\Channel\CheckChannelCurrencyService;
use App\Services\MerchantPayment\MerchantOrderRateService;
use App\Services\Cache\Channel\ChannelInfoByChannelIdService;
use App\Services\TransferOrder\ChannelMode\DispatchModeService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;
use App\Services\Cache\MerchantChannel\GetMerchantChannelListService;
use App\Services\Cache\ChannelAccount\CacheLastChannelAccountMapService;

class GetTransferMerchantChannelService
{
    use ServiceTraits;

    public string $error = '';

    public int $errorcode = 0;

    public function excute($order = "", $logService = "")
    {
        if (empty($order)) {
            $this->error = "订单信息未创建";
            return null;
        }
        $this->data = [];

        $this->writeLog($logService, $order->id, "开始获取商户可用代付渠道", [
            '系统订单号' => $order->ordernumber,
            '商户订单号' => $order->order_no,
            '商户ID' => $order->mid,
            '提交金额' => $order->amount,
            '银行ID' => $order->bank_id,
            '币种ID' => $order->currency_id,
        ], "debug");

        $merchant_channel_result = $this->merchantChannels($order->mid);
        if (empty($merchant_channel_result)) {
            $this->writeLog($logService, $order->id, "商户未配置可用代付渠道", [
                '商户ID' => $order->mid,
                '通道类型' => 7,
                '处理说明' => '未从商户通道缓存/配置中读取到可用代付渠道',
            ], "error");
        }

        if (!empty($merchant_channel_result)) {

            $all_chanel = collect($merchant_channel_result)->pluck('channel_name');
            $this->writeLog($logService, $order->id, "商户配置的代付渠道", [
                '渠道数量' => count($merchant_channel_result),
                '渠道名称' => $all_chanel,
            ], "debug");
            $channelAccountMap = App::make(CacheLastChannelAccountMapService::class)->excute(
                collect($merchant_channel_result)->pluck('channel_id')->all()
            );
            $transferPaymentService = App::make(CheckChannelTransferPaymentService::class);
            $currencyService = App::make(CheckChannelCurrencyService::class);

            foreach ($merchant_channel_result as $merchant_channel_item) {
                $channelId = $merchant_channel_item['channel_id'] ?? null;
                $channelName = $merchant_channel_item['channel_name'] ?? $channelId;

                //判断渠道是否禁用
                $channel_result = $this->channelInfo($merchant_channel_item);
                if (empty($channel_result)) {
                    $this->writeChannelRejectLog($logService, $order, $channelId, $channelName, '渠道不存在');
                    continue;
                }
                if (!empty($channel_result) && $channel_result['status'] == 0) {
                    $this->writeChannelRejectLog($logService, $order, $channelId, $channel_result['name'], '渠道已禁用');
                    continue;
                }

                //判断渠道账号
                $channel_account_result = $channelAccountMap[(int)$channelId] ?? [];
                if (empty($channel_account_result)) {
                    $this->writeChannelRejectLog($logService, $order, $channelId, $channel_result['name'], '渠道账号未设置或已禁用');
                    continue;
                }

                if (!$transferPaymentService->excute($order->bank_id, $channel_result['transfer_payment'])) {
                    $this->writeChannelRejectLog($logService, $order, $channelId, $channel_result['name'], '代付方式不支持', [
                        '支持代付方式' => $this->parseTransferPaymentName($channel_result['transfer_payment']),
                    ]);
                    continue;
                }

                if (!$currencyService->excute($order->currency_id, $channel_result['currency'])) {
                    $this->writeChannelRejectLog($logService, $order, $channelId, $channel_result['name'], '代付币种不支持', [
                        '代付币种' => optional(collect(config('default.currency'))->firstWhere('id', $order->currency_id))->offsetGet('name'),
                        '支持币种' => $this->parseCurrencyName($channel_result['currency']),
                    ]);
                    continue;
                }

                $minAmount = $merchant_channel_item['collection_min_amount'] ?? 0;
                $maxAmount = $merchant_channel_item['collection_max_amount'] ?? 0;
                if ($minAmount > 0 && $this->amountLessThan($order->amount, $minAmount)) {
                    $this->writeChannelRejectLog($logService, $order, $channelId, $channel_result['name'], '付款金额小于渠道单笔下限', [
                        '渠道允许最小金额' => $minAmount,
                    ]);
                    continue;
                }
                if ($maxAmount > 0 && $this->amountGreaterThan($order->amount, $maxAmount)) {
                    $this->writeChannelRejectLog($logService, $order, $channelId, $channel_result['name'], '付款金额大于渠道单笔上限', [
                        '渠道允许最大金额' => $maxAmount,
                    ]);
                    continue;
                }

                if (!$this->hasMatchedMerchantRate($order, (int)$channelId, $logService, $channel_result['name'])) {
                    continue;
                }

                $this->data[] = [
                    'channel_id' => $channelId,
                    'merchant_channel_id' => (int)($merchant_channel_item['id'] ?? $merchant_channel_item['merchant_channel_id'] ?? 0),
                    'channel_name' => $channelName,
                    'transfer_order_id' => $order->id,
                    'merchant_extra_fee' => $merchant_channel_item['fee'],
                    'collection_min_amount' => $minAmount,
                    'collection_max_amount' => $maxAmount,
                    'weight' => (int)($merchant_channel_item['weight'] ?? 0),
                    'classname' => $channel_result['classname'],
                    'ordernumber' => $order->ordernumber,
                    'mid' => $order->mid,
                ];

            }
        }
        return $this->selectChannel($order, $logService);
    }


    private function selectChannel($order, $logService)
    {
        $transfer_order_id = $order->id;
        if (empty($this->data)) {
            $this->error = "未获取到符合条件的渠道";
            $this->errorcode = ErrorCodeEnum::SUBMIT_CHANNEL_UNAVAILABLE;
            return null;
        }

        $this->writeLog($logService, $transfer_order_id, "最终符合条件的手动配置的渠道", [
            '渠道数量' => count($this->data),
            '渠道信息' => $this->channelLogSummary($this->data),
        ], "debug");

        $dispatchModeService = App::make(DispatchModeService::class);
        $result = $dispatchModeService->execute($order, $this->data, $logService);

        if (empty($result)) {
            $this->error = $dispatchModeService->getError() ?: "未获取到符合条件的渠道";
            $this->errorcode = ErrorCodeEnum::SUBMIT_CHANNEL_UNAVAILABLE;
            $this->writeLog($logService, $order->id, $this->error, $this->data, "error");
            return null;
        }

        DB::beginTransaction();
        try {
            App::make(TransferOrderMerchantDeductService::class)->deductForChannel($order, (int)$result['channel_id'], 0, '', $order->ordernumber, $logService, $result['merchant_extra_fee'] ?? null);
            $order->save();

            DB::commit();
            $this->refreshTransferCache($order);
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->writeLog($logService, $order->id, "代付请求验证失败", ['error' => $e->getMessage(), '付款金额' => $order->amount], "error");
            $this->error = $e->getMessage();
            return null;
        }
    }

    private function parseTransferPaymentName($transfer_payment = "")
    {
        if ($transfer_payment == "" || $transfer_payment == Null) return array_values(config('default.transfer_payment'));
        return collect(explode(",", $transfer_payment))->map(function ($item) {
            return optional(config('default.transfer_payment'))->offsetGet($item);
        })->all();
    }

    private function parseCurrencyName($currency = "")
    {
        if (empty($currency)) return collect(config('default.currency'))->pluck('name');
        return collect(explode(",", $currency))->map(function ($item) {
            return optional(collect(config('default.currency'))->firstWhere('id', $item))->offsetGet('name');
        })->all();
    }

    private function merchantChannels($mid): array
    {
        $service = App::make(GetMerchantChannelListService::class);
        $channels = $service->excute($mid, 7);
        if (!empty($channels) && !array_key_exists('collection_min_amount', $channels[0])) {
            return $service->excute($mid, 7, true);
        }

        return $channels ?: [];
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
                'transfer_payment' => $merchantChannelItem['transfer_payment'] ?? '',
            ];
        }

        return App::make(ChannelInfoByChannelIdService::class)->excute($merchantChannelItem['channel_id'] ?? null);
    }

    private function hasMatchedMerchantRate($order, int $channelId, $logService, string $channelName): bool
    {
        $saveData = [
            'mid' => $order->mid,
            'bank_id' => $order->bank_id,
            'amount' => $order->amount,
            'merchant_rate' => $order->merchant_rate,
            'merchant_agent1_rate' => $order->merchant_agent1_rate,
            'merchant_agent2_rate' => $order->merchant_agent2_rate,
            'merchant_agent3_rate' => $order->merchant_agent3_rate,
        ];

        $rateResult = App::make(MerchantOrderRateService::class)->fillTransferFinalRate($saveData, $channelId);
        if (!empty($rateResult['success'])) {
            return true;
        }

        $this->writeChannelRejectLog($logService, $order, $channelId, $channelName, '代付费率未匹配', [
            '错误原因' => $rateResult['zh_message'] ?? '未匹配到代付费率',
            '银行ID' => $order->bank_id,
            '订单金额' => $order->amount,
        ]);
        return false;
    }

    private function channelLogSummary(array $channels): array
    {
        return collect($channels)->map(function ($item) {
            return [
                '渠道ID' => $item['channel_id'] ?? null,
                '商户通道ID' => $item['merchant_channel_id'] ?? null,
                '渠道名称' => $item['channel_name'] ?? null,
                '代付额外手续费' => $item['merchant_extra_fee'] ?? 0,
                '代付单笔下限' => $item['collection_min_amount'] ?? 0,
                '代付单笔上限' => $item['collection_max_amount'] ?? 0,
                '权重' => $item['weight'] ?? 0,
            ];
        })->all();
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

    private function writeLog($logService, $orderId, string $title, $content = [], string $type = 'debug'): void
    {
        try {
            if ($logService && method_exists($logService, 'excute')) {
                $logService->excute($orderId, $title, $content, $type);
                return;
            }

            App::makeWith(CreateTransferOrderLogService::class, [
                'filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $orderId,
            ])->excute($orderId, $title, $content, $type);
        } catch (\Throwable $e) {
            App::make(ReportExceptionService::class)->report('代付通道选择日志写入失败', $e, [
                'order_id' => $orderId,
                'title' => $title,
                'content' => $content,
                'type' => $type,
            ]);
        }
    }

    private function writeChannelRejectLog($logService, $order, $channelId, $channelName, string $reason, array $extra = []): void
    {
        $this->writeLog($logService, $order->id, "手动配置渠道:{$channelName},{$reason}", array_merge([
            '过滤原因' => $reason,
            '渠道ID' => $channelId,
            '渠道名称' => $channelName,
            '订单号' => $order->ordernumber,
            '商户ID' => $order->mid,
            '提交金额' => $order->amount,
        ], $extra), 'error');
    }

    private function refreshTransferCache($order): void
    {
        App::make(OrderCacheService::class)->putTransfer($order);
    }

}
