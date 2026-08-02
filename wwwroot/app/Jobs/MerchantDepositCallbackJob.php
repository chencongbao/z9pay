<?php

namespace App\Jobs;

use App\Models\DepositOrder;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\DepositOrderLog\CreateDepositOrderLogService;
use App\Services\Order\OrderCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\MerchantCallback\CreateMerchantCallbackLogService;

class MerchantDepositCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    private int $depositOrderId;

    private string $queueName;

    private bool $force;

    public function __construct(int $depositOrderId, string $queueName = 'callback', bool $force = false)
    {
        $this->depositOrderId = $depositOrderId;
        $this->queueName = $queueName;
        $this->force = $force;
    }

    public function handle(): void
    {
        $lock = Cache::lock('deposit_callback_lock:' . $this->depositOrderId, 150);
        if (!$lock->get()) {
            return;
        }

        try {
            $this->handleCallback();
        } finally {
            optional($lock)->release();
        }
    }

    private function handleCallback(): void
    {
        $order = $this->getCallbackDepositOrder($this->depositOrderId);
        if (!$order || (!$this->force && (int) $order->callback_status === 1)) {
            return;
        }

        if (empty($order->notify_url)) {
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", ['order_id' => $order->id, 'error' => "代收订单未获取到回调地址"]);
            return;
        }

        $data = $this->buildCallbackData($order);
        if (($data['code'] ?? 0) != 200) {
            $this->cacheDepositInfo($order);
            return;
        }

        $logService = App::make(CreateDepositOrderLogService::class);

        $callbackCount = ((int) $order->callback_count) + 1;
        $callbackTime = time();
        DepositOrder::where('id', $order->id)->update([
            'callback_count' => $callbackCount,
            'callback_time' => $callbackTime,
        ]);
        $order->callback_count = $callbackCount;
        $order->callback_time = $callbackTime;

        $headers = ['Content-Type' => 'application/json'];
        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders($headers)->connectTimeout(5)->timeout(30)->withoutVerifying()->post($order->notify_url, $data);
            $result = $response->body();
            $isSuccess = $response->successful() && $this->isOkResponse($result);
            $this->writeCallbackLog($order, $data, $response->status(), $result, $startedAt, $isSuccess);

            if ($isSuccess) {
                DepositOrder::where('id', $order->id)->update(['callback_status' => 1]);
                $order->callback_status = 1;
                $logService->excute($order->id, "商户回调成功", $data, "debug");
                $this->cacheDepositInfo($order);
                return;
            }

            DepositOrder::where('id', $order->id)->update(['callback_status' => 2]);
            $order->callback_status = 2;
            if ($response->successful()) {
                $logService->excute($order->id, "商户回调返回非OK", ['data' => $data, '商家返回' => $result, "签名字符串" => bob_sign_string($data['data'])], "error");
                bob_send_system_deposit_notice(['error_text' => '代收订单回调返回非OK，订单号：' . $order->ordernumber, 'voice_id' => 'deposit_9', 'id' => 9]);
            } else {
                $logService->excute($order->id, "商户回调失败", ['data' => $data, '商家返回' => $response->status(), "签名字符串" => bob_sign_string($data['data'])], "error");
                bob_send_system_deposit_notice(['error_text' => '代收订单回调状态码非200，订单号：' . $order->ordernumber, 'voice_id' => 'deposit_9', 'id' => 9]);
            }
            $this->retryCallback($order);
        } catch (\Exception $e) {
            DepositOrder::where('id', $order->id)->update(['callback_status' => 2]);
            $order->callback_status = 2;
            bob_send_system_deposit_notice(['error_text' => '代收订单回调失败，订单号：' . $order->ordernumber, 'voice_id' => 'deposit_9', 'id' => 9]);
            $this->writeCallbackLog($order, $data, null, null, $startedAt, false, $e);
            $logService->excute($order->id, "商户回调失败", ['data' => $data, "商家返回" => $e->getMessage(), "签名字符串" => bob_sign_string($data['data'])], "error");
            $this->retryCallback($order);
        }

        $this->cacheDepositInfo($order);
    }

    private function buildCallbackData(DepositOrder $order): array
    {
        $data = [
            'code' => -999,
            'message' => "充值失败",
            'data' => []
        ];

        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute($order->mid);
        $appSecret = $merchant['appsecret'] ?? '';

        if ($order->status == 5) {
            $data['code'] = 200;
            $data['message'] = "OK";
            if (!is_null($order->utr) && $order->utr !== '') {
                $data['utr'] = $order->utr;
            }
            $data['data']['mid'] = $order->mid;
            $data['data']['no'] = $order->ordernumber;
            $data['data']['order_no'] = $order->order_no;
            $data['data']['amount'] = $order->amount;
            $data['data']['actual_amount'] = $order->actual_amount;
            $data['data']['fee'] = (string)bob_amount_format($order->merchant_fee + $order->merchant_extra_fee);
            $data['data']['created_time'] = strtotime($order->created_at);
            $data['data']['deposit_time'] = $order->success_time;
            $data['data']['notify_time'] = time();
            $data['data']['status'] = "succeeded";
            $data['data']['extra'] = $order->extra;
            $data['data']['orig_amount'] = $order->amount;
            $data['data']['payer_name'] = $order->pay_name;
            $data['data']['sign'] = bob_sign($data['data'], $appSecret);
        }

        if ($order->status == 6) {
            $data['code'] = 200;
            $data['message'] = "OK";
            $data['data']['mid'] = $order->mid;
            $data['data']['no'] = $order->ordernumber;
            $data['data']['order_no'] = $order->order_no;
            $data['data']['amount'] = $order->amount;
            $data['data']['actual_amount'] = $order->actual_amount;
            $data['data']['fee'] = "0";
            $data['data']['created_time'] = strtotime($order->created_at);
            $data['data']['deposit_time'] = '';
            $data['data']['notify_time'] = time();
            $data['data']['status'] = "failed";
            $data['data']['extra'] = $order->extra;
            $data['data']['orig_amount'] = $order->amount;
            $data['data']['payer_name'] = $order->pay_name;
            $data['data']['sign'] = bob_sign($data['data'], $appSecret);
        }

        return $data;
    }

    private function cacheDepositInfo(DepositOrder $order): void
    {
        App::make(OrderCacheService::class)->putDeposit($order);
    }

    private function getCallbackDepositOrder($depositOrderId): ?DepositOrder
    {
        return DepositOrder::where('id', $depositOrderId)->first($this->callbackFields());
    }

    private function callbackFields(): array
    {
        return array_values(array_unique(array_merge(
            CacheConstPrefixService::CACHE_DEPOSIT_FILED,
            ['notify_url', 'callback_count', 'extra']
        )));
    }

    private function retryCallback(?DepositOrder $depositOrder): void
    {
        if (!$depositOrder || $depositOrder->callback_count > 5) {
            return;
        }

        $delay = $this->getCallbackDelay((int)$depositOrder->callback_count);
        dispatch(new self($depositOrder->id, $this->queueName, $this->force))->delay(now()->addSeconds($delay))->onQueue($this->queueName);
    }

    private function isOkResponse(string $result): bool
    {
        return strtoupper(trim($result)) === 'OK';
    }

    private function writeCallbackLog(DepositOrder $order, array $data, ?int $status, ?string $body, float $startedAt, bool $isSuccess, ?\Throwable $exception = null): void
    {
        try {
            App::make(CreateMerchantCallbackLogService::class)->excute(
                1,
                $order,
                (string) $order->notify_url,
                $data,
                $status,
                $body,
                (int) round((microtime(true) - $startedAt) * 1000),
                $isSuccess,
                $exception
            );
        } catch (\Throwable $e) {
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning('merchant_callback_log_write_failed', [
                'type' => 1,
                'order_id' => $order->id,
                'ordernumber' => $order->ordernumber,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function getCallbackDelay(int $callbackCount): int
    {
        $delays = [
            0 => 0,
            1 => 5,
            2 => 30,
            3 => 60,
            4 => 180,
            5 => 300,
        ];

        return $delays[$callbackCount] ?? 300;
    }
}
