<?php

namespace App\Services\DepositOrderLog;

use App\Models\DepositeOrderLog;

class CreateDepositOrderLogService
{
    public function excute($orderId = 0, $message = '', $content = '', $type = 'info'): void
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        DepositeOrderLog::create([
            'order_id' => (int) $orderId,
            'message' => (string) $message,
            'content' => $content,
            'type' => strtoupper(trim((string) $type)) ?: 'INFO',
        ]);
    }
}
