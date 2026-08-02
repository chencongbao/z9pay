<?php

namespace App\Extendtions\Telegram;

use Throwable;
use App\Models\GroupAddress;
use App\Traits\TelegramTrait;
use App\Services\Common\ReportExceptionService;

class CountGroupAddressAction
{
    use TelegramTrait;

    public $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [])
    {
        $text = $message['text'] ?? '';
        $chatId = $message['chat']['id'];
        if (empty($text)) {
            return;
        }

        $addresses = array_slice($this->extractAddresses($text), 0, 20);
        if (empty($addresses)) {
            return;
        }

        $lines = [];
        foreach ($addresses as $address) {
            try {
                $record = GroupAddress::firstOrCreate(['chat_id' => $chatId, 'address' => $address], ['count' => 0]);
                $count = intval($record->count) + 1;
                $record->increment('count');
                $lines[] = "地址：`{$address}`\n在本群一共出现：*{$count}* 次";
            } catch (Throwable $e) {
                app(ReportExceptionService::class)->report('统计地址错误', $e, [
                    'chat_id' => $chatId,
                    'address' => $address,
                ]);
            }
        }

        if (!empty($lines)) {
            $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => implode("\n\n", $lines), 'parse_mode' => 'Markdown']);
        }
    }

    protected function extractAddresses(string $text): array
    {
        $matches = [];
        preg_match_all('/(?<!T20)T[a-zA-Z0-9]{33}(?![a-zA-Z0-9])|0x[a-fA-F0-9]{40}(?![a-fA-F0-9])/', $text, $matches);
        return array_unique($matches[0] ?? []);
    }
}
