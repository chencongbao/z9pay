<?php

namespace App\Services\DepositOrder;

use App\Models\DepositOrder;
use App\Services\BlackContent\CheckIpRuleService;
use App\Services\BlackContent\CheckPayNameService;
use App\Services\Common\ReportExceptionService;
use App\Services\Enums\ErrorCodeEnum;
use App\Traits\ServiceResponseTrait;
use Illuminate\Support\Facades\App;

class DepositOrderRiskService
{
    use ServiceResponseTrait;

    private const SCENE_API = 'api';
    private const SCENE_CASHIER = 'cashier';

    public function checkCreatedOrder(DepositOrder $order, array $saveData, $logService = null): ?array
    {
        return $this->check($order, $saveData, $logService, self::SCENE_API);
    }

    public function checkCashierSubmit(DepositOrder $order, array $submitData, $logService = null): ?array
    {
        return $this->check($order, $submitData, $logService, self::SCENE_CASHIER);
    }

    private function check(DepositOrder $order, array $data, $logService, string $scene): ?array
    {
        $riskData = $this->normalizeRiskData($order, $data, $scene);

        $result = $this->checkSubmitIp($order, $riskData, $logService, $scene);
        if ($result) {
            return $result;
        }

        $result = $this->checkPayName($order, $riskData, $logService, $scene);
        if ($result) {
            return $result;
        }

        return $this->checkRefreshOrder($order, $riskData, $logService, $scene);
    }

    private function normalizeRiskData(DepositOrder $order, array $data, string $scene): array
    {
        if ($scene === self::SCENE_CASHIER) {
            foreach ($data as $field => $value) {
                $order->{$field} = $value;
            }
        }

        $riskData = array_merge([
            'ip' => $order->ip ?: bob_ip(),
            'mid' => $order->mid,
            'amount' => $order->amount,
            'pay_name' => $order->pay_name,
            'card_no' => $order->card_no,
        ], $data);

        if ($scene === self::SCENE_CASHIER) {
            $riskData['ip'] = bob_ip();
            $riskData['pay_name'] = $data['pay_name'] ?? '';
            $riskData['refresh_pay_name'] = $data['pay_name'] ?? ($data['card_no'] ?? '');
        }

        return $riskData;
    }

    public function checkSubmitIp(DepositOrder $order, array $saveData, $logService = null, string $scene = self::SCENE_API): ?array
    {
        if (!App::make(CheckIpRuleService::class)->excute($saveData['ip'] ?? bob_ip(), $saveData['mid'])) {
            return null;
        }

        App::make(DepositOrderStatusService::class)->markFailed($order, '提交IP已触发黑名单机制');
        $this->sendSystemDepositNotice(['error_text' => '提交IP已触发黑名单机制，订单号：' . $order->ordernumber, 'voice_id' => 'deposit_6', 'id' => 6], $order, '提交IP黑名单通知');
        $this->writeRiskLog($logService, $order->id, $scene, "提交IP已触发黑名单机制", "提交IP已触发黑名单机制");

        return $this->fail($this->message($scene, 'ip_black'), '提交IP已触发黑名单机制', ErrorCodeEnum::SUBMIT_BLACKLIST_IP);
    }

    public function checkPayName(DepositOrder $order, array $saveData, $logService = null, string $scene = self::SCENE_API): ?array
    {
        if (empty($saveData['pay_name'])) {
            return null;
        }

        if (!App::make(CheckPayNameService::class)->excute($saveData['pay_name'], $saveData['mid'])) {
            return null;
        }

        App::make(DepositOrderStatusService::class)->markFailed($order, '付款人姓名限制支付');
        $this->sendSystemDepositNotice(['error_text' => '付款人姓名限制支付，订单号：' . $order->ordernumber, 'voice_id' => 'deposit_6', 'id' => 6], $order, '付款人姓名黑名单通知');
        $this->writeRiskLog($logService, $order->id, $scene, "付款人姓名限制支付", "付款人姓名限制支付", true);

        return $this->fail($this->message($scene, 'pay_name_black'), '付款人姓名限制支付', ErrorCodeEnum::SUBMIT_BLACKLIST_PAYER_NAME);
    }

    public function checkRefreshOrder(DepositOrder $order, array $saveData, $logService = null, string $scene = self::SCENE_API): ?array
    {
        $riskData = $saveData;
        if (array_key_exists('refresh_pay_name', $riskData)) {
            $riskData['pay_name'] = $riskData['refresh_pay_name'];
            unset($riskData['refresh_pay_name']);
        }

        $refreshOrderService = App::make(CheckDepositOrderRefreshOrderService::class);
        $riskResult = $refreshOrderService->checkWithReason($riskData, (int)$order->id);
        $isRefreshOrderRisk = (bool)($riskResult['triggered'] ?? false);

        if (!$isRefreshOrderRisk) {
            return null;
        }

        $reason = (string)($riskResult['reason'] ?? '刷单');
        $context = is_array($riskResult['context'] ?? null) ? $riskResult['context'] : [];
        $logContent = $this->formatRefreshRiskLogContent($reason, $context);
        App::make(DepositOrderStatusService::class)->markRisk($order, '刷单');
        $this->sendSystemDepositNotice(['error_text' => '代收订单刷单风控，订单号：' . $order->ordernumber . '，原因：' . $reason, 'voice_id' => 'deposit_2', 'id' => 2], $order, '代收刷单风控通知');
        $this->writeRiskLog($logService, $order->id, $scene, $logContent, $logContent);

        return $this->fail($this->message($scene, 'system_risk_control'), '订单提交失败，请联系客服!', ErrorCodeEnum::SUBMIT_RISK_BRUSHING);
    }

    private function formatRefreshRiskLogContent(string $reason, array $context): string
    {
        $conditions = $this->formatMatchedConditions((array)($context['matched_conditions'] ?? []));
        $windowMinutes = $context['window_minutes'] ?? null;
        $triggerCount = $context['trigger_count'] ?? null;
        $currentCount = $context['current_count'] ?? null;
        $lines = [
            '触发原因：' . $reason,
        ];

        if ($conditions !== '') {
            $lines[] = '命中条件：' . $conditions;
        }
        if ($windowMinutes !== null && $triggerCount !== null) {
            $lines[] = '风控规则：' . $windowMinutes . '分钟内达到' . $triggerCount . '单即拦截';
        }
        if ($currentCount !== null && $triggerCount !== null) {
            $lines[] = '当前计数：' . $currentCount . '/' . $triggerCount . '单';
        }

        return implode("\n", $lines);
    }

    private function formatMatchedConditions(array $conditions): string
    {
        $labels = [
            '同商户' => '商户ID',
            '同IP' => 'IP',
            '同姓名' => '付款人',
            '同金额' => '金额',
        ];
        $parts = [];

        foreach ($conditions as $name => $value) {
            $label = $labels[$name] ?? $name;
            $parts[] = $label . ' ' . $value;
        }

        return implode('；', $parts);
    }

    private function writeRiskLog($logService, int $orderId, string $scene, string $content, $apiLogContent, bool $payNameRisk = false): void
    {
        if ($scene === self::SCENE_CASHIER) {
            $title = $payNameRisk ? "会员设置付款人姓名错误" : "会员提交收银台信息错误";
            $this->writeLog($logService, $orderId, $title, $content, "error");
            return;
        }

        $this->writeLog($logService, $orderId, "充值请求验证失败", $apiLogContent, "error");
        $this->writeLog($logService, $orderId, "返回商户参数", $this->logError($this->message(self::SCENE_API, $this->apiLogMessageKey($content))));
    }

    private function message(string $scene, string $key): string
    {
        if ($scene === self::SCENE_CASHIER) {
            return match ($key) {
                'ip_black' => __("cashier.ip_black"),
                'pay_name_black' => __("cashier.pay_name_black"),
                'system_risk_control' => __("cashier.system_risk_control"),
                default => __("cashier.system_risk_control"),
            };
        }

        return match ($key) {
            'ip_black' => trans("api.ip_black"),
            'pay_name_black' => trans("api.pay_name_black"),
            'system_risk_control' => trans("api.submit_failed_contact_kefu"),
            default => trans("api.submit_failed_contact_kefu"),
        };
    }

    private function apiLogMessageKey(string $content): string
    {
        return match ($content) {
            "提交IP已触发黑名单机制" => 'ip_black',
            "付款人姓名限制支付" => 'pay_name_black',
            default => 'system_risk_control',
        };
    }

    private function writeLog($logService, int $orderId, string $title, $content, string $type = 'info'): void
    {
        if ($logService) {
            $logService->excute($orderId, $title, $content, $type);
        }
    }

    private function sendSystemDepositNotice(array $payload, DepositOrder $order, string $reason): void
    {
        try {
            bob_send_system_deposit_notice($payload);
        } catch (\Throwable $e) {
            app(ReportExceptionService::class)->report('代收风控通知发送失败', $e, [
                'reason' => $reason,
                'ordernumber' => $order->ordernumber,
                'payload' => $payload,
            ]);
        }
    }
}
