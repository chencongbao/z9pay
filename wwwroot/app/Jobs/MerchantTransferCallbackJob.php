<?php

namespace App\Jobs;

use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Order\OrderCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\MerchantCallback\CreateMerchantCallbackLogService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class MerchantTransferCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    private int $transferOrderId;

    private string $queueName;

    private bool $force;

    public function __construct(int $transferOrderId, string $queueName = 'callback', bool $force = false)
    {
        $this->transferOrderId = $transferOrderId;
        $this->queueName = $queueName;
        $this->force = $force;
    }

    public function handle(): void
    {
        $lock = Cache::lock('transfer_callback_lock:' . $this->transferOrderId, 150);
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
        $order = $this->getCallbackTransferOrder($this->transferOrderId);
        if (!$order || (!$this->force && (int) $order->callback_status === 1)) {
            return;
        }

        if (empty($order->notify_url)) {
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", ['order_id' => $order->id, 'error' => "代付订单未获取到回调地址"]);
            return;
        }

        $data = $this->buildCallbackData($order);
        if (($data['code'] ?? 0) != 200) {
            $this->cacheTransferInfo($order);
            return;
        }

        $logService = App::make(CreateTransferOrderLogService::class);

        $callbackCount = ((int) $order->callback_count) + 1;
        $callbackTime = time();
        TransferOrder::where('id', $order->id)->update([
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
                TransferOrder::where('id', $order->id)->update(['callback_status' => 1]);
                $order->callback_status = 1;
                bob_send_system_transfer_notice(['success_text' => '代付订单回调成功，订单号：' . $order->ordernumber, 'voice_id' => 'transfer_7', 'id' => 7]);
                $logService->excute($order->id, "商户回调成功", $data, "debug");
                $this->cacheTransferInfo($order);
                return;
            }

            TransferOrder::where('id', $order->id)->update(['callback_status' => 2]);
            $order->callback_status = 2;
            if ($response->successful()) {
                $logService->excute($order->id, "商户回调返回非OK", ['data' => $data, '商家返回' => $result, "签名字符串" => bob_sign_string($data['data'])], "error");
                bob_send_system_transfer_notice(['success_text' => '代付订单回调返回非OK，订单号：' . $order->ordernumber, 'voice_id' => 'transfer_8', 'id' => 8]);
            } else {
                $logService->excute($order->id, "商户回调失败", ['data' => $data, '商家返回' => $response->status(), "签名字符串" => bob_sign_string($data['data'])], "error");
                bob_send_system_transfer_notice(['success_text' => '代付订单回调状态码非200，订单号：' . $order->ordernumber, 'voice_id' => 'transfer_8', 'id' => 8]);
            }

            $this->retryCallback($order);
        } catch (\Exception $e) {
            TransferOrder::where('id', $order->id)->update(['callback_status' => 2]);
            $order->callback_status = 2;
            $this->writeCallbackLog($order, $data, null, null, $startedAt, false, $e);
            $logService->excute($order->id, "商户回调失败", ['data' => $data, '商家返回' => $e->getMessage(), "签名字符串" => bob_sign_string($data['data'])], "error");
            bob_send_system_transfer_notice(['success_text' => '代付订单回调失败，订单号：' . $order->ordernumber, 'voice_id' => 'transfer_8', 'id' => 8]);
            $this->retryCallback($order);
        }

        $this->cacheTransferInfo($order);
    }

    private function buildCallbackData(TransferOrder $order): array
    {
        $data = [
            'code' => -999,
            'message' => "代付失败",
            'fail_reason' => '',
            'data' => []
        ];

        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute($order->mid);
        $appSecret = $merchant['appsecret'] ?? '';

        if ($order->status == 4) {
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
            $data['data']['transfer_time'] = $order->success_time;
            $data['data']['notify_time'] = time();
            $data['data']['status'] = "succeeded";
            $data['data']['extra'] = $order->extra;
            $data['data']['from_card_no'] = '';
            $data['data']['sign'] = bob_sign($data['data'], $appSecret);
        }

        if ($order->status == 5) {
            $data['code'] = 200;
            $data['message'] = "OK";
            $data['fail_reason'] = $order->remark ?? "请咨询客服";
            $data['data']['mid'] = $order->mid;
            $data['data']['no'] = $order->ordernumber;
            $data['data']['order_no'] = $order->order_no;
            $data['data']['amount'] = $order->amount;
            $data['data']['actual_amount'] = $order->actual_amount;
            $data['data']['fee'] = "0";
            $data['data']['created_time'] = strtotime($order->created_at);
            $data['data']['transfer_time'] = '';
            $data['data']['notify_time'] = time();
            $data['data']['status'] = "failed";
            $data['data']['extra'] = $order->extra;
            $data['data']['from_card_no'] = '';
            $data['data']['sign'] = bob_sign($data['data'], $appSecret);
        }

        return $data;
    }

    private function cacheTransferInfo(TransferOrder $order): void
    {
        App::make(OrderCacheService::class)->putTransfer($order);
    }

    private function getCallbackTransferOrder($transferOrderId): ?TransferOrder
    {
        return TransferOrder::where('id', $transferOrderId)->first($this->callbackFields());
    }

    private function callbackFields(): array
    {
        return array_values(array_unique(array_merge(
            CacheConstPrefixService::CACHE_TRANSFER_FILED,
            ['notify_url', 'callback_count', 'extra', 'remark']
        )));
    }

    private function retryCallback(?TransferOrder $transferOrder): void
    {
        if (!$transferOrder || $transferOrder->callback_count > 5) {
            return;
        }

        $delay = $this->getCallbackDelay((int)$transferOrder->callback_count);
        dispatch(new self($transferOrder->id, $this->queueName, $this->force))->delay(now()->addSeconds($delay))->onQueue($this->queueName);
    }

    private function isOkResponse(string $result): bool
    {
        return strtoupper(trim($result)) === 'OK';
    }

    private function writeCallbackLog(TransferOrder $order, array $data, ?int $status, ?string $body, float $startedAt, bool $isSuccess, ?\Throwable $exception = null): void
    {
        try {
            App::make(CreateMerchantCallbackLogService::class)->excute(
                2,
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
                'type' => 2,
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
