<?php

namespace App\Extendtions\Telegram;

use App\Models\User;
use App\Models\Channel;
use App\Models\DepositOrder;
use App\Models\TransferOrder;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Jobs\HandlePendingQueryDepositOrderResultJob;
use App\Jobs\HandlePendingQueryTransferOrderResultJob;
use App\Services\Cache\DepositOrder\CacheDepositOrderInfoService;

class QueryOrderInfoAction
{
    use TelegramTrait;

    protected $telegram;

    protected $telegram_group_id = 0;

    protected $keyboard = ['inline_keyboard' => []];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }


    public function parseConfig($index = -1)
    {
        $result = $this->bob_string_to_array(bob_admin_setting("base_query_ordernumber_status_text"));
        if ($index >= 0) {
            return $result[$index] ?? null;
        }
        return $result;
    }

    public function bob_string_to_array($value)
    {
        $value = str_replace("\n", "=", $value);
        $value = str_replace("\r", "=", $value);
        $value = str_replace("\r\n", "=", $value);
        $collection = collect(explode("=", $value));
        $flattened = $collection->map(function ($values) {
            return trim($values);
        })->filter()->values();
        return $flattened->all();
    }

    public function extractOrderNumbers($ordernumber)
    {
        return bob_replacement_empty($ordernumber);
    }

    public function excute($message = []): void
    {
        if ($this->checkIsCustomer($message)) return;
        if (isset($message['video'])) return;
        $message['caption'] = $this->extractOrderNumbers($message['caption'] ?? '');
        if (!empty($message['caption'])) {
            $mid = $this->getMerchantUserId($message);
            $result = App::make(CacheDepositOrderInfoService::class)->excute($message['caption'], $mid);
            if (!empty($result)) {
                $lang = $this->telegramLangService()->merchantLang(intval($result['mid'] ?? $mid));

                if ($result['status'] == 5) {
                    $html = $this->buildQueryResultMessage($message['caption'], $lang, $this->getCallbackText($result['callback_status']), $this->getCallbackTextByLang($result['callback_status'], $lang));
                    $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $html, 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
                    return;
                }
                $html = $this->buildQueryForwardingMessage($message['caption'], $lang);


                Cache::put(CacheConstPrefixService::TELEGRAM_DEPOSIT_ORDER_FORWARD . $result['id'], ['chat_id' => $message['chat']['id'], 'text' => $html, 'message_id' => $message['message_id']], now()->addDays(30));

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => "🚀 " . $this->buildButtonText('urgent_query', $lang), 'callback_data' => json_encode(['type' => 7, 'value' => $result['id']])]
                        ]
                    ],
                ];
                $telegram1 = $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $html, 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id'], 'reply_markup' => json_encode($keyboard)]);

                if (!isset($result['channel_id'])) return;

                $channel = Channel::whereKey($result['channel_id'])->first(['id', 'telegram_user_id', 'auto_query_status']);
                if ($result['channel_id'] == 1) {
                    if (isset($result['user_id']) && $result['user_id'] > 0) {
                        $user = User::whereKey($result['user_id'])->first(['telegram_group_id']);
                        if ($user && $user->telegram_group_id != 0) {
                            $this->telegram_group_id = $user->telegram_group_id;
                        }
                    } else {
                        $model = DepositOrder::whereKey($result['id'])->first(['user_id']);
                        if ($model && $model->user_id > 0) {
                            $user = User::whereKey($model->user_id)->first(['telegram_group_id']);
                            if ($user && $user->telegram_group_id != 0) {
                                $this->telegram_group_id = $user->telegram_group_id;
                            }
                        }
                    }
                } else {
                    if ($channel && $channel->telegram_user_id != 0) {
                        $this->telegram_group_id = $channel->telegram_user_id;
                    }
                }


                if (isset($result['channel_id']) && !empty($result['channel_id']) && isset($result['id']) && !empty($result['id'])) {
                    if ($channel && $channel->auto_query_status == 1) {
                        dispatch(new HandlePendingQueryDepositOrderResultJob($result['id']))->onQueue('query')->delay(now()->addSeconds(10));
                    }
                }

                if ($this->telegram_group_id != 0) {
                    $keyboard1 = [];
                    $i = 0;
                    $replyOptions = $this->parseConfig();
                    foreach ($replyOptions as $key => $val) {
                        $keyboard1['inline_keyboard'][$i][] = ['text' => $val, 'callback_data' => json_encode(['type' => 8, 'value' => $result['id'], 'index' => $key])];
                        $i += 1;
                    }
                    if (in_array(config('app.name'), ['sgpay', 'thuyphatpay'], true)) {
                        $keyboard1['inline_keyboard'][$i][] = ["text" => $this->buildRetractButtonText($lang), 'callback_data' => json_encode(['type' => 11])];
                    }
                    if (isset($message['photo'])) {
                        $telegram2 = $this->telegram->sendPhoto(['chat_id' => $this->telegram_group_id, 'photo' => $message['photo'][0]['file_id'], 'caption' => $result['ordernumber'], 'parse_mode' => 'html', 'reply_markup' => json_encode($keyboard1)]);
                        Cache::put(CacheConstPrefixService::TELEGRAM_MESSAGE_JIAJI_INFO . $telegram1->message_id, ['chat_id' => $this->telegram_group_id, 'message_id' => $telegram2->message_id], now()->addDays(7));
                        Cache::put(CacheConstPrefixService::TELEGRAM_DEPOSIT_ORDER_JIAJI_INFO . $result['id'], ['message_id' => $telegram1->message_id, 'chat_id' => $this->telegram_group_id], now()->addDays(7));
                    }
                }
                return;
            }

            $orderCacheService = App::make(OrderCacheService::class);
            $result = $orderCacheService->getTransferByOrdernumber($message['caption']);
            if (empty($result) && $mid > 0) {
                $result = $orderCacheService->getTransferByMerchantOrder($mid, $message['caption']);
            }
            if (!empty($result)) {
                $lang = $this->telegramLangService()->merchantLang(intval($result['mid'] ?? $mid));

                if ($result['status'] == 4) {
                    $html = $this->buildQueryResultMessage($message['caption'], $lang, $this->getCallbackText($result['callback_status']), $this->getCallbackTextByLang($result['callback_status'], $lang));
                    $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $html, 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id']]);
                    return;
                }

                $html = $this->buildQueryForwardingMessage($message['caption'], $lang);

                Cache::put(CacheConstPrefixService::TELEGRAM_TRANSFER_ORDER_FORWARD . $result['id'], ['chat_id' => $message['chat']['id'], 'text' => $html, 'message_id' => $message['message_id']], now()->addDays(30));

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => "🚀 " . $this->buildButtonText('urgent_query', $lang), 'callback_data' => json_encode(['type' => 7, 'value' => $result['id']])]
                        ]
                    ],
                ];
                $telegram1 = $this->telegram->sendMessage(['chat_id' => $message['chat']['id'], 'text' => $html, 'parse_mode' => 'html', 'reply_to_message_id' => $message['message_id'], 'reply_markup' => json_encode($keyboard)]);

                if (!isset($result['channel_id'])) return;

                $channel = Channel::whereKey($result['channel_id'])->first(['id', 'telegram_user_id', 'auto_query_status']);
                if ($result['channel_id'] == 1) {
                    if (isset($result['user_id']) && $result['user_id'] > 0) {
                        $user = User::whereKey($result['user_id'])->first(['telegram_group_id']);
                        if ($user && $user->telegram_group_id != 0) {
                            $this->telegram_group_id = $user->telegram_group_id;
                        }
                    } else {
                        $model = TransferOrder::whereKey($result['id'])->first(['user_id']);
                        if ($model && $model->user_id > 0) {
                            $user = User::whereKey($model->user_id)->first(['telegram_group_id']);
                            if ($user && $user->telegram_group_id != 0) {
                                $this->telegram_group_id = $user->telegram_group_id;
                            }
                        }
                    }
                } else {
                    if ($channel && $channel->telegram_user_id != 0) {
                        $this->telegram_group_id = $channel->telegram_user_id;
                    }
                }

                if (isset($result['channel_id']) && !empty($result['channel_id']) && isset($result['id']) && !empty($result['id'])) {
                    if ($channel && $channel->auto_query_status == 1) {
                        dispatch(new HandlePendingQueryTransferOrderResultJob($result['id']))->onQueue('query')->delay(now()->addSeconds(10));
                    }
                }

                if ($this->telegram_group_id != 0) {
                    $keyboard1 = [];
                    $i = 0;
                    $replyOptions = $this->parseConfig();
                    foreach ($replyOptions as $key => $val) {
                        $keyboard1['inline_keyboard'][$i][] = ['text' => $val, 'callback_data' => json_encode(['type' => 9, 'value' => $result['id'], 'index' => $key])];
                        $i += 1;
                    }
                    if (in_array(config('app.name'), ['sgpay', 'thuyphatpay'], true)) {
                        $keyboard1['inline_keyboard'][$i][] = ["text" => $this->buildRetractButtonText($lang), 'callback_data' => json_encode(['type' => 11])];
                    }
                    if (isset($message['photo'])) {
                        $telegram2 = $this->telegram->sendPhoto(['chat_id' => $this->telegram_group_id, 'photo' => $message['photo'][0]['file_id'], 'caption' => $result['ordernumber'], 'parse_mode' => 'html', 'reply_markup' => json_encode($keyboard1)]);
                        Cache::put(CacheConstPrefixService::TELEGRAM_MESSAGE_JIAJI_INFO . $telegram1->message_id, ['chat_id' => $this->telegram_group_id, 'message_id' => $telegram2->message_id], now()->addDays(7));
                        Cache::put(CacheConstPrefixService::TELEGRAM_TRANSFER_ORDER_JIAJI_INFO . $result['id'], ['message_id' => $telegram1->message_id, 'chat_id' => $this->telegram_group_id], now()->addDays(7));
                    }
                }
            }
        }
    }

    public function jiaji($message = []): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "已加急【" . ($message['from']['first_name'] ?? '') . ($message['from']['last_name'] ?? '') . "】", 'callback_data' => $message['message']['chat']['id']]
                ]
            ],
        ];
        $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'], 'message_id' => $message['message']['message_id'], 'reply_markup' => json_encode($keyboard)]);
        $info = Cache::get(CacheConstPrefixService::TELEGRAM_MESSAGE_JIAJI_INFO . $message['message']['message_id']);
        if (!empty($info)) {
            $chat_id = $info['chat_id'] ?? null;
            $message_id = $info['message_id'] ?? null;
            if (!is_null($chat_id) && !is_null($message_id)) {
                $lang = $this->telegramLangService()->merchantLang(intval($this->getMerchantUserId(['chat' => ['id' => $chat_id]])));
                $this->telegram->sendMessage(['chat_id' => $chat_id, 'text' => "‼️‼️‼️" . $this->telegramText('urgent_notice', 'zh_CN') . "\n‼️‼️‼️" . $this->telegramText('urgent_notice', $lang), 'parse_mode' => 'html', 'reply_to_message_id' => $message_id]);
            }
        }
    }

    public function callbackDepositReplay($data = [], $message = []): void
    {
        if (!$this->lockReplyAction('deposit', $data)) {
            return;
        }

        $result = App::make(OrderCacheService::class)->getDepositById($data['value']);
        if (!empty($result)) {
            $content = Cache::get(CacheConstPrefixService::TELEGRAM_DEPOSIT_ORDER_FORWARD . $result['id']);
            if (!empty($content)) {
                $lang = $this->telegramLangService()->merchantLang(intval($result['mid'] ?? 0));
                $replyText = $this->parseConfig($data['index']);
                $html = $this->buildQueryReplyMessage($result['order_no'] ?? '', $lang, $replyText);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => "已回复：" . $replyText . "【" . ($message['from']['first_name'] ?? '') . ($message['from']['last_name'] ?? '') . "】", 'callback_data' => $message['message']['chat']['id']]
                        ]
                    ],
                ];
                $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'] ?? '', 'message_id' => $message['message']['message_id'] ?? 0, 'reply_markup' => json_encode($keyboard)]);
                $this->telegram->sendMessage(['chat_id' => $content['chat_id'], 'text' => $html, 'parse_mode' => 'html', 'reply_to_message_id' => $content['message_id']]);

                $info = Cache::get(CacheConstPrefixService::TELEGRAM_DEPOSIT_ORDER_JIAJI_INFO . $result['id']);
                if (!empty($info)) {
                    Cache::delete(CacheConstPrefixService::TELEGRAM_MESSAGE_JIAJI_INFO . $info['message_id']);
                }
            } else {
                $this->reportReplyWarning($data, $result, '回复订单未找到回复信息');
            }
        } else {
            $this->reportReplyWarning($data, $result, '回复订单未找到订单信息');
        }
    }

    public function callbackTransferReplay($data = [], $message = []): void
    {
        if (!$this->lockReplyAction('transfer', $data)) {
            return;
        }

        $result = App::make(OrderCacheService::class)->getTransferById($data['value']);
        if (!empty($result)) {
            $content = Cache::get(CacheConstPrefixService::TELEGRAM_TRANSFER_ORDER_FORWARD . $result['id']);
            if (!empty($content)) {
                $lang = $this->telegramLangService()->merchantLang(intval($result['mid'] ?? 0));
                $replyText = $this->parseConfig($data['index']);
                $html = $this->buildQueryReplyMessage($result['order_no'], $lang, $replyText);
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => "已回复：" . $replyText . "【" . ($message['from']['first_name'] ?? '') . ($message['from']['last_name'] ?? '') . "】", 'callback_data' => $message['message']['chat']['id']]
                        ]
                    ],
                ];

                $this->telegram->editMessageReplyMarkup(['chat_id' => $message['message']['chat']['id'] ?? 0, 'message_id' => $message['message']['message_id'] ?? 0, 'reply_markup' => json_encode($keyboard)]);
                $this->telegram->sendMessage(['chat_id' => $content['chat_id'], 'text' => $html, 'parse_mode' => 'html', 'reply_to_message_id' => $content['message_id']]);

                $info = Cache::get(CacheConstPrefixService::TELEGRAM_TRANSFER_ORDER_JIAJI_INFO . $result['id']);
                if (!empty($info)) {
                    Cache::delete(CacheConstPrefixService::TELEGRAM_MESSAGE_JIAJI_INFO . $info['message_id']);
                }

            } else {
                $this->reportReplyWarning($data, $result, '回复订单未找到回复信息');
            }
        } else {
            $this->reportReplyWarning($data, $result, '回复订单未找到订单信息');
        }
    }

    private function lockReplyAction(string $type, array $data): bool
    {
        $orderId = intval($data['value'] ?? 0);
        $index = intval($data['index'] ?? -1);
        if ($orderId <= 0 || $index < 0) {
            return false;
        }

        return Cache::add("telegram_order_reply:{$type}:{$orderId}:{$index}", 1, now()->addSeconds(5));
    }

    private function buildQueryForwardingMessage(string $orderNo, string $lang): string
    {
        return $this->telegramText('querying', 'zh_CN')
            . "【" . $this->telegramText('querying', $lang) . "】"
            . "\n" . $this->telegramText('query_order_no', 'zh_CN') . "【" . $this->telegramText('query_order_no', $lang) . "】" . "：<code>" . $orderNo . "</code>";
    }

    private function buildQueryResultMessage(string $orderNo, string $lang, string $zhCallback, string $translatedCallback): string
    {
        $text = $this->telegramText('query_order_no', 'zh_CN') . "【" . $this->telegramText('query_order_no', $lang) . "】" . "：<code>" . $orderNo . "</code>";
        $text .= "\n" . $this->telegramText('query_result', 'zh_CN') . "【" . $this->telegramText('query_result', $lang) . "】" . "：✅ " . $this->telegramText('query_success', 'zh_CN') . $zhCallback;
        if ($translatedCallback !== $zhCallback) {
            $text .= " " . $translatedCallback;
        }

        return $text;
    }

    private function buildQueryReplyMessage(string $orderNo, string $lang, string $resultText): string
    {
        return $this->telegramText('query_order_no', 'zh_CN') . "【" . $this->telegramText('query_order_no', $lang) . "】" . "：<code>" . $orderNo . "</code>"
            . "\n" . $this->telegramText('query_result', 'zh_CN') . "【" . $this->telegramText('query_result', $lang) . "】" . "：" . $resultText;
    }

    private function buildRetractButtonText(string $lang): string
    {
        return $this->telegramText('retract_message', 'zh_CN') . "【" . $this->telegramText('retract_message', $lang) . "】";
    }

    private function buildButtonText(string $key, string $lang): string
    {
        $zhText = $this->telegramText($key, 'zh_CN');
        $langText = $this->telegramText($key, $lang ?: 'en-US');
        if ($langText === $zhText) {
            $langText = $this->telegramText($key, 'en-US');
        }

        return $zhText . "【" . $langText . "】";
    }

    private function reportReplyWarning(array $data, $result, string $error): void
    {
        App::make(SystemNoticeService::class)->warning('system_manual_notice', ['data' => $data, 'result' => $result, 'error' => $error]);
    }
}
