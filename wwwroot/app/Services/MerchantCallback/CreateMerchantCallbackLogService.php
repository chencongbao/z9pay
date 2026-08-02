<?php

namespace App\Services\MerchantCallback;

use App\Models\MerchantCallbackLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateMerchantCallbackLogService
{
    public function excute(
        int $type,
        Model $order,
        string $notifyUrl,
        array $requestData,
        ?int $responseStatus,
        ?string $responseBody,
        int $durationMs,
        bool $isSuccess,
        ?Throwable $exception = null
    ): void {
        MerchantCallbackLog::query()->create([
            'type' => $type,
            'order_id' => (int) $order->id,
            'notify_url' => $notifyUrl,
            'request_data' => $requestData,
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'duration_ms' => max(0, $durationMs),
            'is_success' => $isSuccess,
            'error_message' => $exception ? get_class($exception) . ': ' . $exception->getMessage() : null,
        ]);
    }
}
