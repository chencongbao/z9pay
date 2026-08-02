<?php

namespace App\Extendtions\Telegram;

use App\Models\User;
use App\Models\MerchantInfo;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use App\Services\Telegram\MerchantBalanceTextService;
use App\Services\User\GetUserRemainingDepositService;

class QueryBalanceAction
{
    use TelegramTrait;

    protected $telegram;

    protected array $emptyKeyboard = ['inline_keyboard' => []];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0): void
    {
        if (!$this->isBalanceCommand($message) || intval($group_type) === 0) {
            return;
        }

        if (intval($group_type) === 2 && $this->getUserId($message) <= 0) {
            $this->sendMessage($message, '您的账号未绑定金主，请先绑定');
            return;
        }

        $this->userBalance($message, intval($group_type));
    }

    public function balance($message = []): void
    {
        $data = json_decode($message['data'] ?? '', true);
        if (is_array($data) && intval($data['type'] ?? 0) === 18 && intval($data['cid'] ?? 0) > 0) {
            $this->showChannelBalanceByCallback($message, intval($data['cid']));
            return;
        }

        $this->clearCallbackKeyboard($message);

        if (isset($message['message']['reply_to_message'])) {
            $replyMessage = $message['message']['reply_to_message'];
            $this->userBalance($replyMessage, $this->getGroup($replyMessage), 1);
            return;
        }

        $message['chat'] = $message['message']['chat'] ?? [];
        $this->userBalance($message, $this->getGroup($message), 1);
    }

    private function isBalanceCommand(array $message): bool
    {
        $text = trim((string)($message['text'] ?? ''));

        return $text === '余额' || strtolower($text) === 'yu';
    }

    private function showChannelBalanceByCallback(array $message, int $channelId): void
    {
        App::makeWith(QueryChannelBalanceAction::class, ['telegram' => $this->telegram])->cancel($message, $channelId);
    }

    private function clearCallbackKeyboard(array $message): void
    {
        $chatId = $message['message']['chat']['id'] ?? 0;
        $messageId = $message['message']['message_id'] ?? 0;
        if (!$chatId || !$messageId) {
            return;
        }

        $this->telegram->editMessageReplyMarkup(['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode($this->emptyKeyboard)]);
    }

    private function userBalance($message = [], $group_type = 0, $callback = 0): void
    {
        $groupType = intval($group_type);
        if ($groupType === 1) {
            $this->sendMerchantBalance($message, intval($callback));
            return;
        }

        if ($groupType === 2) {
            $this->sendUserBalance($message, intval($callback));
            return;
        }

        if ($groupType === 3) {
            $this->sendChannelBalance($message, intval($callback));
        }
    }

    private function sendMerchantBalance(array $message, int $callback = 0): void
    {
        $merchantUserId = $this->getMerchantUserId($message);
        $merchant = MerchantInfo::where('merchant_user_id', $merchantUserId)->first(['merchant_user_id', 'coder', 'name', 'currency_id', 'balance_amount', 'available_balance', 'freeze_amount']);
        if (!$merchant) {
            return;
        }

        $lang = $this->merchantTelegramLangByMessage($message, 1);
        $text = App::make(MerchantBalanceTextService::class)->excute($merchant, $lang);

        $this->sendMessage($message, $text, [], $callback);
    }

    private function sendUserBalance(array $message, int $callback = 0): void
    {
        $userId = $this->getUserId($message);
        if ($userId <= 0) {
            return;
        }

        $user = User::whereKey($userId)->first(['id', 'name', 'balance_amount', 'deposit_balance_amount', 'transfer_balance_amount', 'commission_balance_amount', 'deposit_amount']);
        if (!$user) {
            return;
        }

        $lang = 'en-US';
        $text = "== ** " . $this->translatedLabel('user_balance_title', $lang) . " ** ==\n";
        $text .= $this->translatedLabel('user_name', $lang) . "：<code>" . e((string)$user->name) . "</code>\n";
        $text .= $this->translatedLabel('user_id', $lang) . "：<code>" . intval($user->id) . "</code>\n";
        $text .= $this->translatedLabel('user_total_balance', $lang) . "：<code>" . floatval($user->balance_amount) . "</code>\n";
        $text .= $this->translatedLabel('user_deposit_balance', $lang) . "：<code>" . floatval($user->deposit_balance_amount) . "</code>\n";
        $text .= $this->translatedLabel('user_transfer_balance', $lang) . "：<code>" . floatval($user->transfer_balance_amount) . "</code>\n";
        $text .= $this->translatedLabel('user_commission_balance', $lang) . "：<code>" . floatval($user->commission_balance_amount) . "</code>\n";
        $text .= $this->translatedLabel('user_deposit_amount', $lang) . "：<code>" . $this->formatUserDepositAmount($user, $lang) . "</code>\n";

        $this->sendMessage($message, $text, [], $callback);
    }

    private function formatUserDepositAmount(User $user, string $lang = 'en-US'): string
    {
        if ($user->deposit_amount <= 0) {
            return $this->translatedLabel('unlimited', $lang);
        }

        $remainingDeposit = App::make(GetUserRemainingDepositService::class)->excute($user->id);

        return (string)floatval(max((float)($remainingDeposit['remaining_deposit'] ?? 0), 0));
    }

    private function translatedLabel(string $key, string $lang = 'en-US'): string
    {
        $zhText = $this->telegramText($key, 'zh_CN');
        $langText = $this->telegramText($key, $lang ?: 'en-US');
        if ($langText === $zhText) {
            $langText = $this->telegramText($key, 'en-US');
        }

        return $zhText . "【" . $langText . "】";
    }

    private function sendChannelBalance(array $message, int $callback = 0): void
    {
        $channels = $this->getChannels($message, ['id', 'balance_amount', 'name', 'balance_update_time']);
        $channelCount = $channels->count();
        if ($channelCount === 1) {
            App::makeWith(QueryChannelBalanceAction::class, ['telegram' => $this->telegram])->sendBalance($message, $channels->first(), $callback);
            return;
        }

        if ($channelCount <= 1) {
            return;
        }

        $text = "💰 渠道余额查询\n";
        $text .= "Channel Balance Query\n\n";
        $text .= "当前群已绑定多个渠道，请选择要查看余额的渠道：";
        $keyboard = [
            'inline_keyboard' => $channels->map(function ($channel) {
                return [[
                    'text' => '💳 ' . $channel->name,
                    'callback_data' => json_encode(['type' => 18, 'cid' => $channel->id]),
                ]];
            })->values()->all(),
        ];

        $this->sendMessage($message, $text, $keyboard, $callback);
    }

    private function sendMessage(array $message, string $text, array $keyboard = [], int $callback = 0): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'html'];
        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode($keyboard);
        }
        if ($callback !== 1 && !empty($message['message_id'])) {
            $payload['reply_to_message_id'] = $message['message_id'];
        }

        $this->telegram->sendMessage($payload);
    }
}
