<?php

namespace App\Services\Merchant;

use Dcat\Admin\Admin;
use App\Jobs\TelegramQunSendJob;

class MerchantSecretAdminNoticeService
{
    public function send(string $action, int $merchantUserId, string $appkey): void
    {
        $telegramUserId = intval(config('default.system_telegram_id'));
        if ($telegramUserId <= 0) {
            throw new \RuntimeException('系统默认开发者 Telegram 用户未配置');
        }

        $payload = [
            'mid' => $merchantUserId,
            'message' => $action,
            'appkey' => bob_str_replace($appkey),
            'operator_id' => (int)optional(Admin::user())->id,
            'operator_username' => (string)optional(Admin::user())->username,
            'ip' => bob_ip(),
            'time' => date('Y-m-d H:i:s'),
        ];

        dispatch(new TelegramQunSendJob([
            'telegram_group_id' => $telegramUserId,
            'send_content' => $this->content($action, $payload),
            'parse_mode' => 'html',
            'is_telegram_failure_notice' => 1,
        ]))->onQueue('notice');
    }

    private function content(string $action, array $payload): string
    {
        return implode("\n", [
            '======🔐商户 API 密钥敏感操作提醒======',
            '操作类型：' . $this->safeText($action),
            '商户ID：' . intval($payload['mid'] ?? 0),
            '操作人：' . $this->safeText((string)($payload['operator_username'] ?? '')) . '（ID:' . intval($payload['operator_id'] ?? 0) . '）',
            'AppKey：' . $this->safeText((string)($payload['appkey'] ?? '')),
            'IP：' . $this->safeText((string)($payload['ip'] ?? '')),
            '时间：' . $this->safeText((string)($payload['time'] ?? '')),
        ]);
    }

    private function safeText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
