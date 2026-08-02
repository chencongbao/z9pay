<?php

namespace App\Services\Common;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportExceptionService
{
    public function report(string $title, Throwable $e, array $context = [], int $noticeTtlSeconds = 60): void
    {
        $context = $this->sanitizeContext($context);

        Log::channel('exception_report')->error($title, [
            '异常标题' => $title,
            '异常类型' => get_class($e),
            '异常信息' => $e->getMessage(),
            '异常文件' => $e->getFile(),
            '异常行号' => $e->getLine(),
            '上下文' => $context,
            '发生时间' => now()->format('Y-m-d H:i:s'),
        ]);

        $noticeKey = 'exception_notice:' . md5($title . '|' . get_class($e) . '|' . $e->getMessage());
        if (Cache::add($noticeKey, 1, now()->addSeconds($noticeTtlSeconds))) {
            bob_send_exception_message($e, array_merge(['error' => $title], $context));
        }
    }

    private function sanitizeContext(array $context): array
    {
        if (isset($context['data']) && is_array($context['data'])) {
            $context['data'] = $this->sanitizeData($context['data']);
        }

        return $context;
    }

    private function sanitizeData(array $data): array
    {
        $data = Arr::except($data, ['sign']);

        foreach (['card_no', 'cardNo'] as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->maskCardNo($data[$field]);
            }
        }

        return $data;
    }

    private function maskCardNo($cardNo): string
    {
        $cardNo = (string)$cardNo;
        $length = strlen($cardNo);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($cardNo, 0, 4) . str_repeat('*', $length - 8) . substr($cardNo, -4);
    }
}
