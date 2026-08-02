<?php

namespace App\Admin\Renderable\Channel;

use Throwable;
use App\Models\Channel;
use Dcat\Admin\Support\LazyRenderable;
use App\Services\SystemNotice\SystemNoticeService;

class BankListJson extends LazyRenderable
{
    public function render()
    {
        $channelId = intval($this->payload['id'] ?? 0);
        $channel = Channel::query()->find($channelId, ['id', 'name', 'classname']);
        if (!$channel) {
            return $this->errorHtml('渠道不存在');
        }

        $classname = 'Richard\\Payment\\Channel\\' . $channel->classname;
        if (!class_exists($classname)) {
            return $this->errorHtml('渠道类不存在：' . $channel->classname);
        }

        try {
            $payment = new $classname('channel_bank_list_' . $channel->id);
            if (!method_exists($payment, 'getBanKList')) {
                return $this->errorHtml('渠道未实现获取银行列表方法');
            }

            $bankList = $payment->getBanKList();
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('system_manual_notice', [
                'error' => '获取渠道银行列表失败',
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'channel_classname' => $channel->classname,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return $this->errorHtml('获取渠道银行列表失败，请查看系统通知');
        }

        return $this->jsonHtml([
            '渠道ID' => $channel->id,
            '渠道名称' => $channel->name,
            '渠道类名' => $channel->classname,
            '银行数量' => is_countable($bankList) ? count($bankList) : 0,
            '银行列表' => $bankList,
        ]);
    }

    protected function jsonHtml($data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = var_export($data, true);
        }

        return '<pre style="max-height: 650px; overflow: auto; white-space: pre-wrap; word-break: break-all; background: #1f2933; color: #e6edf3; padding: 14px; border-radius: 4px;">' . e($json) . '</pre>';
    }

    protected function errorHtml(string $message): string
    {
        return '<div class="alert alert-danger" style="margin-bottom: 0;">' . e($message) . '</div>';
    }
}
