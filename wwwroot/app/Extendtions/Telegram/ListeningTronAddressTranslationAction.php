<?php

namespace App\Extendtions\Telegram;

use App\Models\ListeningTronAddress;
use Illuminate\Support\Facades\App;
use App\Extendtions\Tron\TronAddressValidator;
use App\Services\Cache\ListeningTronAddress\GetListeningTronAddressService;

class ListeningTronAddressTranslationAction
{
    public $telegram;

    public $keyboard = [
        'inline_keyboard' => []
    ];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [])
    {
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = intval($message['chat']['id'] ?? 0);
        $messageId = intval($message['message_id'] ?? 0);
        $lowerText = strtolower($text);

        if (str_starts_with($lowerText, 'jia=')) {
            $this->addListeningAddress($this->parseAddress($text, 'jia='), $chatId, $messageId);
            return;
        }
        if (str_starts_with($lowerText, 'jian=')) {
            $this->removeListeningAddress($this->parseAddress($text, 'jian='), $chatId, $messageId);
        }
    }

    private function addListeningAddress(string $address, int $chatId, int $messageId): void
    {
        if (!$this->isValidAddress($address)) {
            $this->reply($chatId, '地址格式错误，添加监听失败', $messageId);
            return;
        }

        $result = ListeningTronAddress::firstOrCreate(['address' => $address, 'chat_id' => $chatId]);
        if (!$result->wasRecentlyCreated) {
            $this->reply($chatId, '已添加监听，请勿重复添加', $messageId);
            return;
        }

        $this->refreshListeningCache();
        $this->reply($chatId, '监听成功', $messageId);
    }

    private function removeListeningAddress(string $address, int $chatId, int $messageId): void
    {
        if (!$this->isValidAddress($address)) {
            $this->reply($chatId, '地址格式错误，移除监听失败', $messageId);
            return;
        }

        $result = ListeningTronAddress::where('address', $address)->where('chat_id', $chatId)->first(['id']);
        if (!$result) {
            $this->reply($chatId, '未监听此地址，无法移除', $messageId);
            return;
        }

        ListeningTronAddress::whereKey($result->id)->delete();
        $this->refreshListeningCache();
        $this->reply($chatId, '已移除监听', $messageId);
    }

    private function parseAddress(string $text, string $command): string
    {
        return trim(preg_replace('/^' . preg_quote($command, '/') . '/i', '', $text) ?? '');
    }

    private function isValidAddress(string $address): bool
    {
        return preg_match('/^T[a-zA-Z0-9]{33}$/', $address) === 1 && TronAddressValidator::isValid($address);
    }

    private function refreshListeningCache(): void
    {
        App::make(GetListeningTronAddressService::class)->excute(true);
    }

    private function reply(int $chatId, string $text, int $messageId = 0): void
    {
        $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'html'];
        if ($messageId > 0) {
            $data['reply_to_message_id'] = $messageId;
        }

        $this->telegram->sendMessage($data);
    }
}
