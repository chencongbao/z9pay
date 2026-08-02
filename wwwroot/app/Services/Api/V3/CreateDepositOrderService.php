<?php

namespace App\Services\Api\V3;

use Illuminate\Support\Arr;
use App\Models\DepositOrder;
use Illuminate\Support\Facades\App;
use App\Traits\ServiceResponseTrait;
use App\Services\Enums\ErrorCodeEnum;
use App\Services\Order\OrderCacheService;
use App\Services\Common\ReportExceptionService;
use App\Services\IpWhite\RandomIpFromDbService;
use App\Services\DepositOrder\GetCashierUrlService;
use App\Services\DepositOrder\DepositOrderRiskService;
use App\Services\DepositOrder\DepositOrderStatusService;
use App\Services\MerchantPayment\MerchantOrderRateService;
use App\Services\DepositOrderLog\CreateDepositOrderLogService;
use App\Services\DepositOrder\GetDepositMerchantChannelService;
use App\Services\MerchantOrder\MerchantOrderDuplicateLockService;
use App\Services\DepositOrder\HandleDepositOrderCreatedSuccessService;

class CreateDepositOrderService
{
    use ServiceResponseTrait;

    private const CHANNEL_RESPONSE_FIELDS = [
        'bankCode',
        'bankName',
        'cardNo',
        'cardName',
        'qrCodeUrl',
        'bankBranch',
        'appUrl',
        'qrCodeData'
    ];

    private const CHANNEL_ORDER_UPDATE_FIELDS = [
        'status',
        'channel_id',
        'channel_account_id',
        'channel_pay_url',
        'pay_amount',
        'show_amount',
        'channel_ordernumber',
        'collection_bank_code',
        'collection_bank_branch',
        'collection_bank_name',
        'collection_card_no',
        'collection_name',
        'collection_app_link',
        'collection_qrcode',
        'collection_app_info',
        'collection_qrcode_url',
        'account_type',
        'user_bank_id',
        'user_id',
        'bank_id',
        'alipay_uid',
        'channel_info',
        'user_rate',
        'user_agent1_rate',
        'user_agent2_rate',
        'user_agent3_rate',
        'user_agent4_rate',
        'user_agent5_rate',
        'user_agent1_id',
        'user_agent2_id',
        'user_agent3_id',
        'user_agent4_id',
        'user_agent5_id',
        'merchant_extra_fee',
        'settlement_mode',
        'settlement_time',
    ];

    public function excute(array $data, array $merchantInfo): array
    {
        $order = null;
        $logService = null;
        $duplicateLockService = App::make(MerchantOrderDuplicateLockService::class);

        try {
            // 检查代收总开关，关闭时直接拒绝创建订单。
            $depositOrderSwitchSetting = bob_admin_setting('deposit_order_switch');
            $depositOrderSwitch = is_null($depositOrderSwitchSetting) ? 1 : intval($depositOrderSwitchSetting);
            if ($depositOrderSwitch == 0) {
                return $this->fail(trans("api.deposit_order_closed"), '收款已关闭，请稍后重试', ErrorCodeEnum::COMMON_ERROR);
            }
            $baseDepositOverTime = floatval(bob_admin_setting("base_deposit_over_time"));

            // 获取商户订单防重复锁，防止同一 mid + order_no 并发重复提交。
            $saveData = Arr::except($data, ['gateway', 'sign']);
            if (!$duplicateLockService->lockDeposit($data['mid'], $data['order_no'])) {
                return $this->fail(trans("api.repeat_submit"), '请勿重复提交', ErrorCodeEnum::SUBMIT_ORDER_DUPLICATE);
            }

            // 组装订单入库基础字段，包含系统订单号、商户代理、金额浮动、过期时间等信息。
            $saveData['ordernumber'] = bob_ordernumber('d');
            $saveData['status'] = 1;
            $saveData['hour'] = intval(date('H'));
            $saveData['true_ip'] = bob_ip();
            $saveData['pay_status'] = 1;
            $saveData['pay_name'] = $data['name'] ?? '';
            $saveData['merchant_agent1_id'] = $merchantInfo['agent_user_id'];
            $saveData['order_type'] = 1;
            $saveData['expired_time'] = time() + $baseDepositOverTime * 60;
            $saveData['amount'] = $this->normalizeDecimalAmount($data['amount']);
            $saveData['pay_amount'] = $saveData['amount'];
            if (bccomp($saveData['pay_amount'], '0.00', 2) <= 0) {
                $duplicateLockService->releaseDeposit($data['mid'], $data['order_no']);
                return $this->fail(trans("api.amount.min"), '金额错误', ErrorCodeEnum::COMMON_ERROR);
            }
            $saveData['show_amount'] = $saveData['pay_amount'];
            $saveData['currency_id'] = $merchantInfo['currency_id'];
            $saveData['merchant_agent2_id'] = $merchantInfo['merchant_agent2_id'] ?? 0;
            $saveData['merchant_agent3_id'] = $merchantInfo['merchant_agent3_id'] ?? 0;
            $saveData['merchant_rate'] = 0;
            $saveData['merchant_agent1_rate'] = 0;
            $saveData['merchant_agent2_rate'] = 0;
            $saveData['merchant_agent3_rate'] = 0;
            $saveData['payment_id'] = $this->paymentIdByGateway($data['gateway']);
            if ($saveData['payment_id'] === null) {
                $duplicateLockService->releaseDeposit($data['mid'], $data['order_no']);
                return $this->fail(trans("api.gateway.in"), '支付方式错误', ErrorCodeEnum::COMMON_ERROR);
            }

            // 商户开启系统生成 IP 时，用随机 IP 覆盖商户提交的 IP。
            if (isset($merchantInfo['system_create_ip']) && $merchantInfo['system_create_ip'] == 1) {
                $saveData['ip'] = app(RandomIpFromDbService::class)->excute($merchantInfo['currency_id']);
            }

            // 非 test 网关需要匹配商户代收费率，费率异常时释放未落库的防重复锁。
            if ($data['gateway'] != "test") {
                $rateResult = App::make(MerchantOrderRateService::class)->checkDepositRateConfigured($saveData);
                if (!$rateResult['success']) {
                    $duplicateLockService->releaseDeposit($data['mid'], $data['order_no']);
                    return $rateResult;
                }
            }

            // 创建订单后立即刷新订单缓存，保证商户查单能尽快读到新订单。
            $order = DepositOrder::create($saveData);
            $duplicateLockService->keepDeposit($data['mid'], $data['order_no'], $order->ordernumber);
            App::make(OrderCacheService::class)->putDeposit($order);

            // 创建订单日志，后续风控、通道请求、返回商户参数都会继续记录该订单处理过程。
            $logService = App::make(CreateDepositOrderLogService::class);
            $logService->excute($order->id, "创建订单: " . bob_ip(), $data);
        } catch (\Exception $e) {
            // 未落库异常需要释放防重复锁，允许商户使用同一订单号重试。
            if (!$order) {
                $duplicateLockService->releaseDeposit($data['mid'], $data['order_no']);
            }
            App::make(ReportExceptionService::class)->report('代收订单提交失败', $e, [
                'data' => $data,
                'mid' => $data['mid'] ?? null,
                'order_no' => $data['order_no'] ?? null,
            ]);
            if ($order && $logService) {
                $logService->excute($order->id, "返回商户参数", $this->logError(trans("api.submit_failed_contact_kefu")));
            }
            return $this->fail(trans("api.submit_failed_contact_kefu"), '订单提交失败，请联系客服!', ErrorCodeEnum::SUBMIT_CHANNEL_UNAVAILABLE);
        }

        try {
            // 构造商户接口默认返回参数，通道返回后会按实际结果覆盖支付地址和收款信息。
            $responseData = [
                'no' => $order->ordernumber,
                'order_no' => $order->order_no,
                'pay_name' => $order->pay_name,
                'pay_amount' => $order->pay_amount,
                'url' => App::make(GetCashierUrlService::class)->excute($merchantInfo, $order->ordernumber),
                'bankCode' => "",
                'bankBranch' => "",
                'bankName' => "",
                'cardNo' => "",
                'cardName' => "",
                'qrCodeUrl' => "",
                'appUrl' => "",
                'qrCodeData' => ""
            ];

            // test 网关不请求真实通道，直接返回测试收银台地址。
            if ($data['gateway'] == "test") {
                $responseData['url'] = route('test');
                $logService->excute($order->id, "测试订单跳过风控", [
                    '支付方式' => $data['gateway'],
                    '说明' => 'test订单不请求真实通道，直接返回测试地址',
                ], 'debug');
                $logService->excute($order->id, "返回商户参数", $this->logSuccess('OK', $responseData));
                return $this->success($responseData);
            }

            // 创建后风控：非 test 订单统一检查提交 IP、付款人姓名、刷单风险，命中时会更新状态并刷新缓存。
            $riskResult = App::make(DepositOrderRiskService::class)->checkCreatedOrder($order, $saveData, $logService);
            if ($riskResult) {
                return $riskResult;
            }

            // 请求可用代收通道，成功后保存通道返回字段并交给创建成功处理服务刷新缓存/后续处理。
            $getChannelService = App::make(GetDepositMerchantChannelService::class);
            $getChannelResult = $getChannelService->excute($order, $logService);
            if (!empty($getChannelResult)) {
                $responseData = $this->fillChannelResponseData($responseData, $getChannelResult);
                $updateData = $this->channelOrderUpdateData($getChannelResult);
                if (!empty($updateData)) {
                    $order->fill($updateData);
                    $order->save();
                }
                App::make(HandleDepositOrderCreatedSuccessService::class)->excute($order);
                $order->refresh();
                $responseData['pay_amount'] = $this->responsePayAmount($order);
                $logService->excute($order->id, "返回商户参数", $this->logSuccess('OK', $responseData));
                return $this->success($responseData);
            }

            // 通道提交失败时标记订单失败、刷新缓存并发送系统通知。
            $channelError = $this->formatErrorMessage($getChannelService->error, "提交到渠道失败");
            App::make(DepositOrderStatusService::class)->markFailed($order, $channelError);
            $this->sendSystemDepositNotice(['error_text' => $channelError . '，订单号：' . $order->ordernumber, 'voice_id' => 'deposit_6', 'id' => 6], [
                'ordernumber' => $order->ordernumber,
                'reason' => '代收通道提交失败通知',
            ]);
            $logService->excute($order->id, "返回商户参数", $this->logError(trans("api.submit_failed_contact_kefu")));

            return $this->fail(trans("api.submit_failed_contact_kefu"), '订单提交失败，请联系客服!', ErrorCodeEnum::COMMON_ERROR);
        } catch (\Throwable $e) {
            App::make(DepositOrderStatusService::class)->markFailed($order, '代收订单创建后处理异常');
            App::make(ReportExceptionService::class)->report('代收订单创建后处理异常', $e, [
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

    private function formatErrorMessage($error, string $default = '提交到渠道失败'): string
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

    private function normalizeDecimalAmount($amount): string
    {
        return bcadd((string)$amount, '0', 2);
    }

    private function fillChannelResponseData(array $responseData, array $channelResult): array
    {
        if (isset($channelResult['channel_pay_url'])) {
            $responseData['url'] = $channelResult['channel_pay_url'];
        }

        if (isset($channelResult['show_amount'])) {
            $responseData['pay_amount'] = (string)$channelResult['show_amount'];
        }

        return Arr::collapse([
            $responseData,
            Arr::only($channelResult, self::CHANNEL_RESPONSE_FIELDS),
        ]);
    }

    private function responsePayAmount(DepositOrder $order): string
    {
        $amount = $order->show_amount ?: $order->pay_amount;

        return $this->normalizeDecimalAmount($amount);
    }

    private function channelOrderUpdateData(array $channelResult): array
    {
        return Arr::only($channelResult, self::CHANNEL_ORDER_UPDATE_FIELDS);
    }

    private function paymentIdByGateway(string $gateway): ?int
    {
        $payment = collect(config('payment', []))->firstWhere('code', $gateway);

        return isset($payment['id']) ? (int)$payment['id'] : null;
    }

    private function sendSystemDepositNotice(array $payload, array $context = []): void
    {
        try {
            bob_send_system_deposit_notice($payload);
        } catch (\Throwable $e) {
            App::make(ReportExceptionService::class)->report('代收系统通知发送失败', $e, array_merge($context, [
                'payload' => $payload,
            ]));
        }
    }
}
