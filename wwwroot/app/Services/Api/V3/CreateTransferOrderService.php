<?php

namespace App\Services\Api\V3;

use Throwable;
use App\Models\BankCode;
use Illuminate\Support\Arr;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Traits\ServiceResponseTrait;
use App\Services\Enums\ErrorCodeEnum;
use Illuminate\Support\Facades\Cache;
use App\Services\Const\LogConstService;
use App\Services\IpWhite\CheckIpService;
use App\Jobs\SendTransferOrderPaymentJob;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ReportExceptionService;
use App\Jobs\SendTransferOrderTelegramConfirmJob;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Services\MerchantPayment\MerchantOrderRateService;
use App\Services\TransferOrder\TransferOrderStatusService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;
use App\Services\TransferOrder\MerchantOrderQueryConfirmService;
use App\Services\MerchantOrder\MerchantOrderDuplicateLockService;
use App\Services\TransferOrder\GetTransferMerchantChannelService;
use App\Services\TransferOrder\CheckTransferOrderSameCardNoNameService;
use App\Services\TransferOrder\TransferOrderOverSettingAmountNoticeTelegramConfirmService;

class CreateTransferOrderService
{
    use ServiceResponseTrait;

    public function excute(array $data, array $merchantInfo): array
    {
        $order = null;
        $logService = null;
        $duplicateLockService = App::make(MerchantOrderDuplicateLockService::class);

        try {
            // 校验银行编码是否支持当前商户币种，test/ob 属于特殊编码，不查询银行表。
            $saveData = Arr::except($data, ['sign']);
            $bankCodeValue = trim((string)($data['bank_code'] ?? ''));
            $bankCode = $this->bankCode($bankCodeValue, intval($merchantInfo['currency_id'] ?? 0));
            if (!$this->isSpecialBankCode($bankCodeValue) && !$bankCode) {
                return $this->fail(trans('api.bank_code.none'), '银行代码不存在', ErrorCodeEnum::SUBMIT_PARAM_INVALID);
            }

            //代付必须先配置并命中代付 IP 白名单，未通过时不创建订单。
            $whiteIpResult = $this->validateTransferWhiteIp($merchantInfo);
            if (!$whiteIpResult['success']) {
                return $whiteIpResult;
            }

            // 获取商户订单防重复锁，防止同一 mid + order_no 并发重复提交。
            if (!$duplicateLockService->lockTransfer($data['mid'], $data['order_no'])) {
                return $this->fail(trans("api.repeat_submit"), '请勿重复提交', ErrorCodeEnum::SUBMIT_ORDER_DUPLICATE);
            }

            // 组装代付订单入库基础字段，包含系统订单号、商户代理、币种、真实请求 IP 等信息。
            $saveData['ordernumber'] = bob_ordernumber('t');
            $saveData['status'] = 1;
            $saveData['amount'] = (string)$data['amount'];
            $saveData['bank_id'] = intval(optional($bankCode)->id);
            $saveData['merchant_agent1_id'] = $merchantInfo['agent_user_id'];
            $saveData['true_ip'] = bob_ip();
            $saveData['currency_id'] = $merchantInfo['currency_id'];
            $saveData['merchant_agent2_id'] = $merchantInfo['merchant_agent2_id'] ?? 0;
            $saveData['merchant_agent3_id'] = $merchantInfo['merchant_agent3_id'] ?? 0;
            $saveData['merchant_rate'] = 0;
            $saveData['merchant_agent1_rate'] = 0;
            $saveData['merchant_agent2_rate'] = 0;
            $saveData['merchant_agent3_rate'] = 0;

            // 非 test 订单先确认商户配置了代付费率，最终费率等选中渠道后再匹配。
            if (!$this->isTestBankCode($bankCodeValue)) {
                $rateResult = App::make(MerchantOrderRateService::class)->checkTransferRateConfigured($saveData);
                if (!$rateResult['success']) {
                    $duplicateLockService->releaseTransfer($data['mid'], $data['order_no']);
                    return $rateResult;
                }
            }

            // 创建订单后立即刷新订单缓存，并长期保留商户订单号防重复锁。
            $order = TransferOrder::create($saveData);
            $duplicateLockService->keepTransfer($data['mid'], $data['order_no'], $order->ordernumber);
            App::make(OrderCacheService::class)->putTransfer($order);

            // 创建订单日志，后续反查、风控、通道选择、返回商户参数都会继续写入同一个订单日志文件。
            $logService = App::makeWith(CreateTransferOrderLogService::class, [
                'filename' => LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id,
            ]);
            $logService->excute($order->id, "创建订单: " . bob_ip(), $data);
        } catch (\Exception $e) {
            // 未落库异常需要释放防重复锁，允许商户使用同一订单号重试。
            if (!$order) {
                $duplicateLockService->releaseTransfer($data['mid'], $data['order_no']);
            }
            App::make(ReportExceptionService::class)->report('代付订单提交失败', $e, [
                'data' => $data,
                'mid' => $data['mid'] ?? null,
                'order_no' => $data['order_no'] ?? null,
            ]);
            return $this->fail(trans("api.submit_failed_contact_kefu"), '订单提交失败，请联系客服!', ErrorCodeEnum::COMMON_ERROR);
        }

        try {
            $responseData = [
                'no' => $order->ordernumber,
                'order_no' => $order->order_no,
            ];

            // test 订单只验证创建流程，不执行反查、白名单、同卡同名风控、通道匹配和队列下发。
            if ($this->isTestBankCode($data['bank_code'] ?? '')) {
                $logService->excute($order->id, "测试订单跳过后续风控", [
                    'bank_code' => $data['bank_code'] ?? '',
                    'ordernumber' => $order->ordernumber,
                ], "debug");
                $logService->excute($order->id, "返回商户参数", $this->logSuccess('OK', $responseData));
                return $this->success($responseData);
            }

            // 商户开启反查时，同步请求商户侧查询接口，反查失败会标记订单失败并刷新缓存。
            if (isset($merchantInfo['check_order']) && $merchantInfo['check_order'] == 1) {
                $checkResult = $this->checkMerchantOrder($order, $logService, $saveData, $data);
                if (!$checkResult['success']) {
                    return $checkResult;
                }
            }

            // 创建后风控：同商户同卡同名频率超过配置时标记订单失败。
            $sameCardNameRiskResult = $this->checkSameCardNameRisk($order, $logService, $data);
            if (!$sameCardNameRiskResult['success']) {
                return $sameCardNameRiskResult;
            }

            // 商户关闭自动代付时，订单转为待处理并通知后台/群组人工处理。
            if (isset($merchantInfo['auto_transfer']) && $merchantInfo['auto_transfer'] == 0) {
                $manualPendingResult = $this->handleManualPendingTransfer($order, $logService);
                if (!$manualPendingResult['success']) {
                    return $manualPendingResult;
                }
                $order = $manualPendingResult['data']['order'] ?? $order;
                $order = TransferOrder::query()->whereKey($order->id)->first($this->manualPendingFields()) ?: $order;
                $this->writeLog($logService, $order->id, '商户关闭自动代付', [
                    '系统订单号' => $order->ordernumber,
                    '商户订单号' => $order->order_no,
                    '商户ID' => $order->mid,
                    '订单状态' => '待处理',
                    '商户手续费' => $order->merchant_fee,
                    '处理说明' => '商户关闭自动代付，订单已按代付费率扣款并进入待处理',
                ], 'error');
                $this->sendSystemTransferNotice(['success_text' => "代付订单待处理，订单号：" . $order->ordernumber, 'voice_id' => 'transfer_3', 'id' => 3]);
                return $this->success($responseData);
            }

            // 获取可用代付通道并扣减商户余额，失败时标记订单失败并返回具体错误。
            $channelResult = $this->selectTransferChannel($order, $logService);
            if (!$channelResult['success']) {
                return $channelResult;
            }
            $selectChannelResult = $channelResult['data'];

            // 大额代付确认：命中配置时转为待商户群确认，商户确认后再下发通道。
            if (App::make(TransferOrderOverSettingAmountNoticeTelegramConfirmService::class)->excute($order)) {
                $telegramGroupId = intval($merchantInfo['telegram_group_id'] ?? 0);
                $remark = $telegramGroupId === 0 ? "商户未绑定群主，未发送确认群消息" : "自动代付金额超过免审额度发给商家确认";
                App::make(TransferOrderStatusService::class)->markPendingConfirm($order, $selectChannelResult, $remark);

                if ($telegramGroupId !== 0) {
                    dispatch(new SendTransferOrderTelegramConfirmJob($order))->onQueue('notice')->afterCommit();
                } else {
                    $this->sendSystemTransferNotice(['error_text' => '代付订单需要商户确认，但商户未绑定 Telegram 群，订单号：' . $order->ordernumber, 'voice_id' => 'transfer_3', 'id' => 3]);
                }

                $this->writeTransferConfirmNoticeLog($logService, $order, $selectChannelResult, $telegramGroupId);
                return $this->success($responseData);
            }

            // 正常自动代付：异步队列请求通道，接口先返回商户创建成功。
            $this->dispatchTransferPaymentJob($order, $selectChannelResult, $logService);
            $logService->excute($order->id, "返回商户参数", $this->logSuccess('OK', $responseData));

            return $this->success($responseData);
        } catch (Throwable $e) {
            App::make(TransferOrderStatusService::class)->markFailed($order, '代付订单创建后处理异常');
            App::make(ReportExceptionService::class)->report('代付订单创建后处理异常', $e, [
                'data' => $data,
                'mid' => $data['mid'] ?? null,
                'order_no' => $data['order_no'] ?? null,
                'ordernumber' => $order->ordernumber ?? null,
            ]);
            if ($logService) {
                $logService->excute($order->id, "创建后处理异常", $e->getMessage(), "error");
                $logService->excute($order->id, "返回商户参数", $this->logError(trans("api.submit_failed_contact_kefu")));
            }

            return $this->fail(trans("api.submit_failed_contact_kefu"), '订单提交失败，请联系客服!', ErrorCodeEnum::SUBMIT_CHANNEL_UNAVAILABLE);
        }
    }

    private function checkMerchantOrder(TransferOrder $order, $logService, array $saveData, array $data): array
    {
        $queryConfirmService = App::make(MerchantOrderQueryConfirmService::class);

        try {
            $result = $queryConfirmService->excute($saveData['mid'], $data);
            if (!$result) {
                App::make(TransferOrderStatusService::class)->markFailed($order, '反查订单未通过');
                $this->writeLog($logService, $order->id, "反查订单未通过", [
                    'error' => $this->logError('反查订单未通过'),
                    'context' => $this->merchantOrderQueryContext($order, $saveData, $data),
                    'http_result' => $queryConfirmService->httpResult(),
                ], "error");
                return $this->fail(trans("api.check_order_none_1"), '反查订单未通过', ErrorCodeEnum::SUBMIT_REQUERY_FAILED);
            }
        } catch (Throwable $e) {
            App::make(TransferOrderStatusService::class)->markFailed($order, '订单反查异常，请联系开发人员或关闭反查功能');
            App::make(ReportExceptionService::class)->report('订单反查异常，请联系开发人员或关闭反查功能', $e, [
                'mid' => $saveData['mid'] ?? null,
                'order_no' => $data['order_no'] ?? null,
                'ordernumber' => $order->ordernumber ?? null,
            ]);
            $this->writeLog($logService, $order->id, "订单反查异常，请联系开发人员或关闭反查功能", [
                'error' => $this->logError('订单反查未接入，请联系开发人员或关闭反查功能'),
                'exception' => $e->getMessage(),
                'context' => $this->merchantOrderQueryContext($order, $saveData, $data),
                'http_result' => $queryConfirmService->httpResult(),
            ], "error");
            return $this->fail(trans("api.check_order_none_2"), '订单反查异常，请联系开发人员或关闭反查功能', ErrorCodeEnum::SUBMIT_REQUERY_EXCEPTION);
        }

        return $this->success(['order' => $order]);
    }

    private function handleManualPendingTransfer(TransferOrder $order, $logService): array
    {
        $rateData = [
            'mid' => $order->mid,
            'bank_id' => $order->bank_id,
            'amount' => $order->amount,
        ];
        $rateResult = App::make(MerchantOrderRateService::class)->fillTransferFinalRate($rateData, 0);
        if (empty($rateResult['success'])) {
            $error = $rateResult['zh_message'] ?? '未匹配到代付费率,请联系客服确认代付金额';
            App::make(TransferOrderStatusService::class)->markFailed($order, $error);
            $this->writeLog($logService, $order->id, '商户关闭自动代付费率匹配失败', [
                '错误原因' => $error,
                '银行ID' => $order->bank_id,
                '订单金额' => $order->amount,
            ], 'error');
            return $this->fail($rateResult['message'] ?? $error, $error, intval($rateResult['error_code'] ?? ErrorCodeEnum::SUBMIT_RATE_ERROR));
        }

        try {
            DB::transaction(function () use (&$order, $rateData) {
                $order = TransferOrder::query()->whereKey($order->id)->lockForUpdate()->first($this->manualPendingFields());
                if (!$order || (int)$order->status !== 1) {
                    throw new \Exception('订单不存在或当前状态无法转为待处理');
                }

                $merchantFee = bob_amount_format((float)$order->amount * (float)$rateData['merchant_rate']);
                $order->fill([
                    'status' => 3,
                    'merchant_rate' => $rateData['merchant_rate'],
                    'merchant_agent1_rate' => $rateData['merchant_agent1_rate'],
                    'merchant_agent2_rate' => $rateData['merchant_agent2_rate'],
                    'merchant_agent3_rate' => $rateData['merchant_agent3_rate'],
                    'merchant_fee' => $merchantFee,
                    'merchant_extra_fee' => 0,
                    'remark' => '商户关闭自动代付，待人工处理',
                ]);

                $deductResult = App::make(MerchantBalanceChangeService::class)->deductTransferOrder($order, (float)$order->amount, (float)$merchantFee);
                if (empty($deductResult['success'])) {
                    throw new \Exception($deductResult['message'] ?? '商户代付扣款失败');
                }

                $order->save();
            });
        } catch (Throwable $e) {
            App::make(TransferOrderStatusService::class)->markFailed($order, $e->getMessage());
            $this->writeLog($logService, $order->id, '商户关闭自动代付扣款失败', [
                '错误原因' => $e->getMessage(),
                '订单金额' => $order->amount,
            ], 'error');
            return $this->fail(trans("api.balance_none"), $e->getMessage(), ErrorCodeEnum::SUBMIT_BALANCE_INSUFFICIENT);
        }

        App::make(OrderCacheService::class)->putTransfer($order, true);
        $this->writeLog($logService, $order->id, '商户关闭自动代付扣款完成', [
            '费率来源' => $rateResult['source'] ?? '',
            '商户费率' => $order->merchant_rate,
            '商户手续费' => $order->merchant_fee,
            '代付额外手续费' => $order->merchant_extra_fee,
            '订单状态' => '待处理',
        ], 'info');

        return $this->success(['order' => $order]);
    }

    private function manualPendingFields(): array
    {
        return array_values(array_unique(array_merge(CacheConstPrefixService::CACHE_TRANSFER_FILED, [
            'id',
            'status',
            'mid',
            'bank_id',
            'amount',
            'currency_id',
            'ordernumber',
            'order_no',
            'merchant_rate',
            'merchant_agent1_rate',
            'merchant_agent2_rate',
            'merchant_agent3_rate',
            'merchant_fee',
            'merchant_extra_fee',
            'remark',
        ])));
    }

    private function validateTransferWhiteIp(array $merchantInfo): array
    {
        if (empty($merchantInfo['pay_white_ip'])) {
            return $this->fail(trans("api.ip_white_none"), '请先配置代付IP白名单', ErrorCodeEnum::SUBMIT_IP_FORBIDDEN);
        }

        $serverIp = bob_ip();
        $whiteIps = bob_format_muti_data_to_array($merchantInfo['pay_white_ip']);
        if (App::make(CheckIpService::class)->excute($whiteIps, $serverIp)) {
            return $this->success([]);
        }

        return $this->fail(trans("api.ip_white_none"), '服务器IP不在白名单内', ErrorCodeEnum::SUBMIT_IP_FORBIDDEN);
    }

    private function selectTransferChannel(TransferOrder $order, $logService): array
    {
        $selectChannelService = App::make(GetTransferMerchantChannelService::class);
        $selectChannelResult = $selectChannelService->excute($order, $logService);

        if ($selectChannelResult) {
            return $this->success($selectChannelResult);
        }

        $error = $this->formatErrorMessage($selectChannelService->error ?? null, '未获取到符合条件的渠道');
        $errorCode = intval($selectChannelService->errorcode ?: ErrorCodeEnum::COMMON_ERROR);

        App::make(TransferOrderStatusService::class)->markFailed($order, $error);
        $this->writeLog($logService, $order->id, "返回商户参数", $this->logError($error), "error");

        return $this->fail($error, '', $errorCode);
    }

    private function checkSameCardNameRisk(TransferOrder $order, $logService, array $data): array
    {
        if (!App::make(CheckTransferOrderSameCardNoNameService::class)->excute($data)) {
            return $this->success([]);
        }

        $limit = intval(bob_admin_setting("base_transfer_same_card_name_number"));
        $message = '同卡同名的账号代付次数不能大于' . $limit . "次";

        App::make(TransferOrderStatusService::class)->markFailed($order, $message);
        $this->writeLog($logService, $order->id, "代付请求验证失败", [
            '错误原因' => $message,
            '持卡人姓名' => $data['holder_name'] ?? '',
            '银行卡号' => $data['card_no'] ?? '',
            '限制次数' => $limit,
        ], "error");

        return $this->fail(trans("api.transfer_same_card_and_name", [
            'config_transfer_same_card_name_number' => $limit,
        ]), $message, ErrorCodeEnum::SUBMIT_RISK_BRUSHING);
    }

    private function formatErrorMessage($error, string $default = '系统错误'): string
    {
        if (empty($error)) {
            return $default;
        }

        if (is_string($error)) {
            return $error;
        }

        if (is_scalar($error)) {
            return (string)$error;
        }

        $message = json_encode($error, JSON_UNESCAPED_UNICODE);

        return $message ?: $default;
    }

    private function merchantOrderQueryContext(TransferOrder $order, array $saveData, array $data): array
    {
        return [
            'mid' => $saveData['mid'] ?? $order->mid,
            'order_no' => $data['order_no'] ?? $order->order_no,
            'ordernumber' => $order->ordernumber,
            'card_no' => $data['card_no'] ?? '',
            'withdrawQueryUrl' => $data['withdrawQueryUrl'] ?? '',
        ];
    }

    private function writeLog($logService, int $orderId, string $title, $content, string $type = 'info'): void
    {
        if ($logService) {
            $logService->excute($orderId, $title, $content, $type);
        }
    }

    private function writeTransferConfirmNoticeLog($logService, TransferOrder $order, array $channelInfo, int $telegramGroupId): void
    {
        $this->writeLog($logService, $order->id, '代付大额商户确认通知', [
            '系统订单号' => $order->ordernumber,
            '商户订单号' => $order->order_no,
            '商户ID' => $order->mid,
            '订单金额' => bob_unit_format($order->amount),
            '订单状态' => '待商户群确认',
            '商户群ID' => $telegramGroupId ?: '',
            '通道ID' => $channelInfo['channel_id'] ?? '',
            '商户通道ID' => $channelInfo['merchant_channel_id'] ?? '',
            '通道名称' => $channelInfo['name'] ?? ($channelInfo['channel_name'] ?? ''),
            '通知状态' => $telegramGroupId === 0 ? '未发送，商户未绑定群' : '已派发 Telegram 确认消息',
        ], $telegramGroupId === 0 ? 'error' : 'info');
    }

    private function dispatchTransferPaymentJob(TransferOrder $order, array $channelInfo, $logService): void
    {
        Cache::put(CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION . $order->id, 1, now()->addMinutes(5));

        $this->writeLog($logService, $order->id, '自动代付下发队列', [
            '系统订单号' => $order->ordernumber,
            '商户订单号' => $order->order_no,
            '通道ID' => $channelInfo['channel_id'] ?? '',
            '通道名称' => $channelInfo['name'] ?? ($channelInfo['channel_name'] ?? ''),
            '通道类名' => $channelInfo['classname'] ?? '',
            '队列状态' => '已派发',
            '处理中锁' => '已设置 5 分钟',
        ], 'debug');

        dispatch(new SendTransferOrderPaymentJob($order, $channelInfo))->onQueue('transfer')->afterCommit();
    }

    private function bankCode(string $code, int $currencyId): ?BankCode
    {
        if ($this->isSpecialBankCode($code)) {
            return null;
        }

        return BankCode::query()
            ->where('code', strtoupper($code))
            ->where('currency_id', $currencyId)
            ->first(['id']);
    }

    private function isSpecialBankCode(string $code): bool
    {
        return in_array(strtolower($code), ['test', 'ob'], true);
    }

    private function isTestBankCode(string $code): bool
    {
        return strtolower($code) === 'test';
    }

    private function sendSystemTransferNotice(array $message): void
    {
        try {
            bob_send_system_transfer_notice($message);
        } catch (Throwable $e) {
            App::make(ReportExceptionService::class)->report('代付系统通知发送失败', $e, [
                'message' => $message,
            ]);
        }
    }
}
