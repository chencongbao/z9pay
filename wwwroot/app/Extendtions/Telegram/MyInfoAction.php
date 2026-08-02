<?php

namespace App\Extendtions\Telegram;

use App\Traits\TelegramTrait;

class MyInfoAction
{
    use TelegramTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0): void
    {
        if (!$this->isMyInfoCommand($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? 0;
        $messageId = $message['message_id'] ?? 0;
        if (!$chatId || empty($message['from']['id'])) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $this->buildInfoText($message, $group_type), 'parse_mode' => 'html'];
        if ($messageId) {
            $payload['reply_to_message_id'] = $messageId;
        }

        $this->telegram->sendMessage($payload);
    }

    private function isMyInfoCommand(array $message): bool
    {
        return in_array($message['text'] ?? '', ['我的信息', '个人信息'], true);
    }

    private function buildInfoText(array $message, int $groupType): string
    {
        $text = "个人ID：<code><b>" . intval($message['from']['id']) . "</b></code>\n";
        $text .= "个人性质：" . ($this->checkIsManager($message) ? "<b>群组管理员</b>\n" : "<b>群组成员</b>\n");

        if ($groupType === 1) {
            $text .= "商户群：<b>" . $this->html($this->getUserInfo($message, $groupType)) . "</b>\n";
        }

        if ($groupType === 2) {
            $userId = $this->getUserId($message);
            $text .= "金主群：" . (!$userId ? "<b>未绑定</b>\n" : "<b>" . $this->html($this->getUserInfo($message, $groupType)) . "</b>\n");
        }

        if ($groupType === 3) {
            $text .= $this->buildChannelGroupText($message);
        }

        return $text . "群组ID：<code><b>" . intval($message['chat']['id']) . "</b></code>\n";
    }

    private function buildChannelGroupText(array $message): string
    {
        $channels = $this->getChannels($message, ['id', 'name']);
        if ($channels->isEmpty()) {
            return '';
        }

        $items = $channels->map(function ($channel) {
            return "【（#" . intval($channel->id) . "）" . $this->html($channel->name) . "】";
        })->implode('');

        return "渠道群：<b>" . $items . "</b>\n";
    }

    private function html($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
