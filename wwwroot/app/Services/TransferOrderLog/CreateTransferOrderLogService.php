<?php

namespace App\Services\TransferOrderLog;

use App\Traits\ServiceTraits;
use App\Models\TransferOrderLog;

class CreateTransferOrderLogService
{
    use ServiceTraits;

    public function excute($order_id = 0, $message = '', $content = '', $type = 'info'): ?TransferOrderLog
    {
        $orderId = intval($order_id);
        if ($orderId <= 0) {
            return null;
        }

        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return TransferOrderLog::create([
            'order_id' => $orderId,
            'message' => (string) $message,
            'content' => (string) $content,
            'type' => strtoupper((string) $type),
        ]);
    }
}
