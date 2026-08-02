<?php

namespace App\Admin\Renderable\MerchantCallback;

use App\Models\MerchantCallbackLog;
use Dcat\Admin\Support\LazyRenderable;
use Dcat\Admin\Widgets\Table;

class Records extends LazyRenderable
{
    public function render()
    {
        $type = (int) ($this->payload['type'] ?? 0);
        $orderId = (int) ($this->payload['order_id'] ?? 0);
        $logs = MerchantCallbackLog::query()
            ->where('type', $type)
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $table = new Table([
            '时间',
            '结果',
            '耗时',
            '回调地址',
            'HTTP',
            '请求参数',
            '响应/异常',
        ], $logs->map(function (MerchantCallbackLog $log) {
            return [
                $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                $log->is_success ? '<span class="label bg-success">成功</span>' : '<span class="label bg-red">失败</span>',
                $log->duration_ms . 'ms',
                '<div style="max-width:260px;word-break:break-all;">' . e($log->notify_url) . '</div>',
                $log->response_status ?: '-',
                $this->formatJson($log->request_data),
                $this->formatResponse($log),
            ];
        })->all());
        $table->withBorder();
        $table->setStyle(['custom-data-table data-table table-bordered complex-headers']);

        return $table;
    }

    private function formatJson($data): string
    {
        $json = json_encode($data ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return '<pre style="max-width:360px;max-height:220px;overflow:auto;white-space:pre-wrap;word-break:break-all;margin:0;">' . e($json ?: '') . '</pre>';
    }

    private function formatResponse(MerchantCallbackLog $log): string
    {
        $content = $log->error_message ?: (string) $log->response_body;
        if ($content === '') {
            $content = '-';
        }

        return '<pre style="max-width:360px;max-height:220px;overflow:auto;white-space:pre-wrap;word-break:break-all;margin:0;">' . e($content) . '</pre>';
    }
}
