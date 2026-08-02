<?php

namespace App\Extendtions\Telegram;

use Throwable;
use App\Models\MerchantInfo;
use App\Traits\TelegramTrait;
use App\Models\MerchantPayment;
use App\Models\MerchantTelegramAdmin;
use App\Rules\DecimalTwoPlaces;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\Common\SystemLogService;
use App\Services\Telegram\TelegramOperatorService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Cache\MerchantPayment\RefreshMerchantPaymentRateCacheService;

class MerchantPaymentRateAction
{
    use TelegramTrait;

    public $telegram;

    public $keyboard = [
        'inline_keyboard' => []
    ];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $groupType = 0)
    {
        if ($groupType != 1) {
            return;
        }

        $items = $this->parseCommands((string) ($message['text'] ?? ''));
        if (empty($items)) {
            return;
        }

        $mid = intval($this->getMerchantUserId($message));
        if ($mid <= 0) {
            $this->reply($message, '群组未绑定商家,请输入<b>【<code>bd</code>+商户代码】</b>进行绑定', true);
            return;
        }

        if (!$this->checkIsManager($message)) {
            $this->reply($message, '您不是管理员，无权操作此命令', true);
            return;
        }

        $merchant = MerchantInfo::where('merchant_user_id', $mid)->first(['merchant_user_id', 'name', 'coder', 'currency_id']);
        if (!$merchant) {
            $this->reply($message, '商户不存在，无法修改费率');
            return;
        }

        $prepared = $this->prepareItems($mid, $items);
        if (empty($prepared['items'])) {
            $this->reply($message, $prepared['error'] ?: '命令格式错误，仅支持：alipay/2.1 alipay_uid/2.2');
            return;
        }

        $this->confirmUpdateRate($message, $merchant, $prepared['items']);
    }

    public function callbackUpdateRate($data = [], $message = [])
    {
        $cacheKey = $this->getCacheKey($message);
        $payload = Cache::get($cacheKey, []);
        if (empty($payload)) {
            $this->answerCallbackAlert($message, '您不是命令发起人或操作已过期，无权操作此按钮');
            return;
        }

        if (($data['action'] ?? '') === 'cancel') {
            Cache::forget($cacheKey);
            $this->clearKeyboard($message);
            $this->editText($message, '您已取消修改商户费率');
            return;
        }

        if (($data['action'] ?? '') !== 'confirm') {
            return;
        }

        $confirmKey = $cacheKey . '_confirm';
        if (!Cache::add($confirmKey, 1, now()->addSeconds(5))) {
            return;
        }

        try {
            [$updatedIds, $changes] = $this->updateRates($payload, $message);
            $this->refreshMerchantPaymentRateCache(intval($payload['merchant_user_id']), array_column($changes, 'payment_id'));
            Cache::forget($cacheKey);
            $this->clearKeyboard($message);
            $this->editText($message, $this->buildSuccessText($payload, $updatedIds));
            $this->replyMerchantTelegramAdminMention($payload, $message);
        } catch (Throwable $e) {
            $this->reply($message, $e->getMessage());
        } finally {
            Cache::forget($confirmKey);
        }
    }

    private function updateRates(array $payload, array $message): array
    {
        return DB::transaction(function () use ($payload, $message) {
            $updatedIds = [];
            $changes = [];
            $operator = app(TelegramOperatorService::class)->context($message);
            $operatorAdmin = app(TelegramOperatorService::class)->admin($message);
            foreach (($payload['items'] ?? []) as $item) {
                $records = MerchantPayment::where('merchant_user_id', intval($payload['merchant_user_id']))
                    ->where('payment_id', intval($item['payment_id']))
                    ->lockForUpdate()
                    ->get();

                if ($records->isEmpty()) {
                    throw new \Exception("当前商户未配置通道【{$item['payment_code']}】费率，无法修改");
                }

                foreach ($records as $record) {
                    $oldPayRate = $record->pay_rate;
                    $record->pay_rate = $item['rate'];
                    $record->save();
                    $updatedIds[] = $record->id;

                    $changes[] = [
                        'merchant_payment_id' => $record->id,
                        'payment_id' => $item['payment_id'],
                        'payment_code' => $item['payment_code'],
                        'payment_name' => $item['payment_name'],
                        'old_pay_rate' => $oldPayRate,
                        'new_pay_rate' => $item['rate'],
                    ];
                }
            }

            app(SystemLogService::class)->logAction(
                actionKey: 'merchant.payment.telegram_update_rate',
                text: 'Telegram批量修改商户通道费率',
                subject: null,
                properties: [
                    'merchant_user_id' => $payload['merchant_user_id'],
                    'changes' => $changes,
                    'merchant_payment_ids' => $updatedIds,
                    'telegram_chat_id' => $this->chatId($message),
                    'telegram_from_id' => $message['from']['id'] ?? 0,
                    'operator' => $operator,
                ],
                remark: 'Telegram批量修改商户通道费率',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $operatorAdmin
            );

            return [$updatedIds, $changes];
        });
    }

    private function refreshMerchantPaymentRateCache(int $merchantUserId, array $paymentIds): void
    {
        try {
            app(RefreshMerchantPaymentRateCacheService::class)->excute($merchantUserId, $paymentIds);
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('merchant_payment_rate_cache_refresh_failed', [
                'error' => 'Telegram修改商户支付费率后刷新商户支付费率缓存失败',
                'merchant_user_id' => $merchantUserId,
                'payment_ids' => $paymentIds,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function confirmUpdateRate($message, $merchant, array $items)
    {
        $merchantName = (string) (optional($merchant)->bname ?: ('商户#' . intval(optional($merchant)->merchant_user_id)));
        $merchantCode = (string) (optional($merchant)->coder ?: '');
        $cacheData = [
            'merchant_user_id' => intval(optional($merchant)->merchant_user_id),
            'merchant_name' => $merchantName,
            'merchant_code' => $merchantCode,
            'items' => $items,
        ];

        Cache::put($this->getCacheKey($message), $cacheData, now()->addMinutes(10));

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '确认修改', 'callback_data' => json_encode(['type' => 19, 'action' => 'confirm'])],
                    ['text' => '取消修改', 'callback_data' => json_encode(['type' => 19, 'action' => 'cancel'])],
                ]
            ],
        ];

        $text = "确认修改商户费率？\n";
        $text .= "商户：{$merchantName}";
        if ($merchantCode !== '') {
            $text .= "【{$merchantCode}】";
        }
        $text .= "\n批量修改明细：";
        foreach ($items as $index => $item) {
            $text .= "\n" . ($index + 1) . ". 【{$item['payment_code']}】{$item['payment_name']}：";
            $text .= ($item['old_rates'] !== '' ? $item['old_rates'] . '%' : '-') . " → " . bob_amount_format($item['rate']) . '%';
            $text .= "（{$item['record_count']}条）";
        }

        $this->telegram->sendMessage([
            'chat_id' => $this->chatId($message),
            'text' => $text,
            'reply_markup' => json_encode($keyboard),
        ]);
    }

    protected function parseCommands(string $text): array
    {
        $text = trim(strtolower((string) $text));
        if ($text === '' || str_contains($text, "\n")) {
            return [];
        }

        if (!str_starts_with($text, '修改费率 ')) {
            return [];
        }

        $text = trim(substr($text, strlen('修改费率')));
        if ($text === '') {
            return [];
        }

        $segments = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($segments)) {
            return [];
        }

        $decimalRule = new DecimalTwoPlaces();
        $result = [];
        $exists = [];
        foreach ($segments as $segment) {
            if (!preg_match('/^([a-z0-9_]+)\/([0-9]+(?:\.[0-9]{1,2})?)$/', $segment, $matches)) {
                return [];
            }

            $code = $matches[1];
            $rate = $matches[2] ?? '';
            if (!is_numeric($rate) || floatval($rate) < 0 || floatval($rate) > 100 || !$decimalRule->passes('pay_rate', $rate)) {
                return [];
            }

            if (isset($exists[$code])) {
                return [];
            }
            $exists[$code] = 1;

            $result[] = [
                'code' => $code,
                'rate' => floatval($rate),
            ];
        }

        return $result;
    }

    protected function prepareItems(int $mid, array $items): array
    {
        $prepared = [];
        $paymentMap = collect(config('payment', []))->keyBy(function ($config) {
            return strtolower((string) ($config['code'] ?? ''));
        });

        foreach ($items as $item) {
            $payment = $paymentMap->get($item['code']);

            if (empty($payment)) {
                return ['items' => [], 'error' => "通道编码【{$item['code']}】不存在"];
            }

            $records = MerchantPayment::where('merchant_user_id', $mid)
                ->where('payment_id', intval($payment['id']))
                ->get(['id', 'pay_rate']);

            if ($records->isEmpty()) {
                return ['items' => [], 'error' => "当前商户未配置通道【{$item['code']}】费率，无法修改"];
            }

            $prepared[] = [
                'payment_id' => intval($payment['id']),
                'payment_code' => (string) $payment['code'],
                'payment_name' => (string) $payment['name'],
                'rate' => $item['rate'],
                'record_count' => $records->count(),
                'old_rates' => $records->pluck('pay_rate')
                    ->map(fn ($value) => bob_amount_format($value))
                    ->unique()
                    ->values()
                    ->implode('%、'),
            ];
        }

        return ['items' => $prepared, 'error' => ''];
    }

    protected function buildSuccessText(array $payload, array $updatedIds): string
    {
        $text = "修改成功\n商户：{$payload['merchant_name']}";
        if (!empty($payload['merchant_code'])) {
            $text .= "【{$payload['merchant_code']}】";
        }
        $text .= "\n修改明细：";
        foreach (($payload['items'] ?? []) as $index => $item) {
            $text .= "\n" . ($index + 1) . ". 【{$item['payment_code']}】{$item['payment_name']} => " . bob_amount_format($item['rate']) . '%';
        }
        $text .= "\n更新记录数：" . count($updatedIds);

        return $text;
    }

    private function replyMerchantTelegramAdminMention(array $payload, array $message): void
    {
        $mentions = $this->merchantTelegramAdminMentions(intval($payload['merchant_user_id'] ?? 0), $this->chatId($message));
        if ($mentions === '') {
            return;
        }

        $this->telegram->sendMessage([
            'chat_id' => $this->chatId($message),
            'text' => $mentions,
            'parse_mode' => 'html',
            'reply_to_message_id' => $this->messageId($message),
        ]);
    }

    private function merchantTelegramAdminMentions(int $merchantUserId, int $telegramGroupId): string
    {
        if ($merchantUserId <= 0 || $telegramGroupId >= 0) {
            return '';
        }

        return MerchantTelegramAdmin::query()
            ->where('mid', $merchantUserId)
            ->where('telegram_group_id', $telegramGroupId)
            ->orderBy('id')
            ->get(['telegram_user_id', 'telegram_username', 'telegram_name'])
            ->map(function (MerchantTelegramAdmin $admin) {
                $username = trim((string)$admin->telegram_username);
                if ($username !== '') {
                    return '@' . ltrim($this->escape($username), '@');
                }

                $telegramUserId = intval($admin->telegram_user_id);
                if ($telegramUserId <= 0) {
                    return '';
                }

                $name = trim((string)$admin->telegram_name) ?: (string)$telegramUserId;
                return '<a href="tg://user?id=' . $telegramUserId . '">' . $this->escape($name) . '</a>';
            })
            ->filter()
            ->implode(' ');
    }

    protected function getCacheKey(array $message): string
    {
        $fromId = $message['from']['id'] ?? 0;
        $chatId = $message['chat']['id'] ?? ($message['message']['chat']['id'] ?? 0);

        return $fromId . '_' . $chatId . '_merchant_payment_rate';
    }

    private function clearKeyboard(array $message): void
    {
        $this->telegram->editMessageReplyMarkup(['chat_id' => $this->chatId($message), 'message_id' => $this->messageId($message), 'reply_markup' => json_encode($this->keyboard)]);
    }

    private function editText(array $message, string $text): void
    {
        $this->telegram->editMessageText(['chat_id' => $this->chatId($message), 'message_id' => $this->messageId($message), 'text' => $text]);
    }

    private function reply(array $message, string $text, bool $html = false): void
    {
        $data = ['chat_id' => $this->chatId($message), 'text' => $text, 'reply_to_message_id' => $this->messageId($message)];
        if ($html) {
            $data['parse_mode'] = 'html';
        }

        $this->telegram->sendMessage($data);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function chatId(array $message): int
    {
        return intval($message['chat']['id'] ?? ($message['message']['chat']['id'] ?? 0));
    }

    private function messageId(array $message): int
    {
        return intval($message['message_id'] ?? ($message['message']['message_id'] ?? 0));
    }
}
