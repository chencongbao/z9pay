<?php

namespace App\Services\Telegram;

use App\Models\Channel;
use App\Services\Channel\QueryChannelBalanceService;

class TelegramChannelBalanceService
{
    public function findChannel(int $channelId): ?Channel
    {
        if ($channelId <= 0) {
            return null;
        }

        return Channel::whereKey($channelId)->first(['id', 'classname', 'name', 'balance_amount', 'balance_update_time']);
    }

    public function parseChannelId(string $text): int
    {
        if (preg_match('/^渠道余额\s*\+?\s*(\d+)$/u', trim($text), $matches) !== 1) {
            return 0;
        }

        return intval($matches[1] ?? 0);
    }

    public function refresh(Channel $channel): void
    {
        app(QueryChannelBalanceService::class)->execute($channel, true);
    }

    public function buildBalanceText(Channel $channel): string
    {
        $html = "💰 渠道余额\n";
        $html .= "Channel Balance\n\n";
        $html .= "渠道名称：<code>" . $this->html($channel->name) . "</code>\n";
        $html .= "渠道ID：<code>" . $channel->id . "</code>\n\n";
        $html .= "账户余额：<code>" . bob_unit_format($channel->balance_amount) . "</code>\n";
        $html .= "Balance：<code>" . bob_unit_format($channel->balance_amount) . "</code>\n\n";
        $html .= "更新时间：<code>" . ($channel->balance_update_time ?: '-') . "</code>";

        return $html;
    }

    public function buildKeyboard(int $channelId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '更新余额', 'callback_data' => json_encode(['type' => 17, 'cid' => $channelId])],
                ],
            ],
        ];
    }

    private function html($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
