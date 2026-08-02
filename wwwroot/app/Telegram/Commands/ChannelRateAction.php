<?php

namespace App\Telegram\Commands;

use App\Models\Channel;
use App\Models\ChannelRate;
use App\Rules\DecimalTwoPlaces;
use App\Services\Common\SystemLogService;
use App\Services\Telegram\TelegramInstanceService;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChannelRateAction
{
    use TelegramTrait;

    public $telegram;

    public $keyboard = [
        'inline_keyboard' => []
    ];

    public function __construct($telegram = null)
    {
        $this->telegram = $telegram ?: app(TelegramInstanceService::class)->excute();
    }

    public function excute($message = [])
    {
        $parsed = $this->parseCommands((string) ($message['text'] ?? ''));
        if (empty($parsed['channel_id']) || empty($parsed['items'])) {
            return;
        }

        if (($message['chat']['id'] ?? 0) < 0) {
            $this->telegram->sendMessage([
                'chat_id' => $message['chat']['id'],
                'text' => '该命令仅允许私聊机器人操作',
                'reply_to_message_id' => $message['message_id'] ?? 0,
            ]);
            return;
        }

        if (!$this->checkIsManager($message)) {
            $this->telegram->sendMessage([
                'chat_id' => $message['chat']['id'],
                'text' => '您不是管理员，无权操作此命令',
                'reply_to_message_id' => $message['message_id'] ?? 0,
            ]);
            return;
        }

        $channel = Channel::query()->find($parsed['channel_id'], ['id', 'name', 'code']);
        if (!$channel) {
            $this->telegram->sendMessage([
                'chat_id' => $message['chat']['id'],
                'text' => '渠道不存在，请检查 channel_id',
                'reply_to_message_id' => $message['message_id'] ?? 0,
            ]);
            return;
        }

        $prepared = $this->prepareItems((int) $channel->id, $parsed['items']);
        if (empty($prepared['items'])) {
            $this->telegram->sendMessage([
                'chat_id' => $message['chat']['id'],
                'text' => $prepared['error'] ?: '命令格式错误，仅支持：' . $this->commandExample(),
                'reply_to_message_id' => $message['message_id'] ?? 0,
            ]);
            return;
        }

        $this->confirmUpdateRate($message, $channel, $prepared['items']);
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
            $this->telegram->editMessageReplyMarkup([
                'chat_id' => $message['message']['chat']['id'],
                'message_id' => $message['message']['message_id'],
                'reply_markup' => json_encode($this->keyboard),
            ]);
            $this->telegram->editMessageText([
                'chat_id' => $message['message']['chat']['id'],
                'message_id' => $message['message']['message_id'],
                'text' => '您已取消修改渠道费率',
            ]);
            return;
        }

        if (($data['action'] ?? '') !== 'confirm') {
            return;
        }

        $confirmKey = $cacheKey . '_confirm';
        if (!Cache::add($confirmKey, 1, now()->addSeconds(5))) {
            return;
        }

        DB::beginTransaction();
        try {
            $updatedIds = [];
            $changes = [];
            foreach (($payload['items'] ?? []) as $item) {
                $records = ChannelRate::where('channel_id', intval($payload['channel_id']))
                    ->where('payment_id', intval($item['payment_id']))
                    ->where('type', $this->rateType())
                    ->lockForUpdate()
                    ->get();

                if ($records->isEmpty()) {
                    throw new \Exception("渠道【{$payload['channel_name']}】未配置通道【{$item['payment_code']}】的{$this->rateLabel()}");
                }

                foreach ($records as $record) {
                    $record->{$this->rateField()} = $item['rate'];
                    $record->save();
                    $updatedIds[] = $record->id;
                }

                $changes[] = [
                    'payment_id' => $item['payment_id'],
                    'payment_code' => $item['payment_code'],
                    'payment_name' => $item['payment_name'],
                    'rate' => $item['rate'],
                ];
            }

            app(SystemLogService::class)->logAction(
                actionKey: $this->systemActionKey(),
                text: $this->systemLogText(),
                subject: null,
                properties: [
                    'channel_id' => $payload['channel_id'],
                    'channel_code' => $payload['channel_code'],
                    'channel_name' => $payload['channel_name'],
                    'changes' => $changes,
                    'channel_rate_ids' => $updatedIds,
                    'telegram_chat_id' => $message['message']['chat']['id'] ?? 0,
                    'telegram_from_id' => $message['from']['id'] ?? 0,
                ],
                remark: $this->systemLogText(),
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin'
            );

            DB::commit();
            Cache::forget($cacheKey);

            $this->telegram->editMessageReplyMarkup([
                'chat_id' => $message['message']['chat']['id'],
                'message_id' => $message['message']['message_id'],
                'reply_markup' => json_encode($this->keyboard),
            ]);
            $this->telegram->editMessageText([
                'chat_id' => $message['message']['chat']['id'],
                'message_id' => $message['message']['message_id'],
                'text' => $this->buildSuccessText($payload, $updatedIds),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->telegram->sendMessage([
                'chat_id' => $message['message']['chat']['id'],
                'text' => $e->getMessage(),
                'reply_to_message_id' => $message['message']['message_id'] ?? 0,
            ]);
        }
    }

    protected function confirmUpdateRate($message, Channel $channel, array $items)
    {
        $cacheData = [
            'channel_id' => intval($channel->id),
            'channel_name' => (string) $channel->name,
            'channel_code' => (string) $channel->code,
            'items' => $items,
        ];

        Cache::put($this->getCacheKey($message), $cacheData, now()->addMinutes(10));

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '确认修改', 'callback_data' => json_encode(['type' => $this->callbackType(), 'action' => 'confirm'])],
                    ['text' => '取消修改', 'callback_data' => json_encode(['type' => $this->callbackType(), 'action' => 'cancel'])],
                ]
            ],
        ];

        $text = "确认修改{$this->rateLabel()}？\n";
        $text .= "渠道：{$channel->bname}\n";
        $text .= "批量修改明细：";
        foreach ($items as $index => $item) {
            $text .= "\n" . ($index + 1) . ". 【{$item['payment_code']}】{$item['payment_name']}：";
            $text .= $this->formatRateValue($item['old_rates'], true) . " → " . $this->formatRateValue($item['rate']);
            $text .= "（{$item['record_count']}条）";
        }

        $this->telegram->sendMessage([
            'chat_id' => $message['chat']['id'],
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

        if (!preg_match('/^' . preg_quote($this->parsePrefix(), '/') . '\/(\d+)\s+(.+)$/', $text, $prefixMatches)) {
            return [];
        }

        $channelId = intval($prefixMatches[1] ?? 0);
        $rest = trim((string) ($prefixMatches[2] ?? ''));
        if ($channelId <= 0 || $rest === '') {
            return [];
        }

        $segments = preg_split('/\s+/', $rest, -1, PREG_SPLIT_NO_EMPTY);
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
            if (!is_numeric($rate) || floatval($rate) < 0 || floatval($rate) > 100 || !$decimalRule->passes('rate', $rate)) {
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

        return [
            'channel_id' => $channelId,
            'items' => $result,
        ];
    }

    protected function prepareItems(int $channelId, array $items): array
    {
        $prepared = [];
        foreach ($items as $item) {
            $payment = collect(config('payment', []))->first(function ($config) use ($item) {
                return strtolower((string) ($config['code'] ?? '')) === $item['code'];
            });

            if (empty($payment)) {
                return ['items' => [], 'error' => "通道编码【{$item['code']}】不存在"];
            }

            $records = ChannelRate::where('channel_id', $channelId)
                ->where('payment_id', intval($payment['id']))
                ->where('type', $this->rateType())
                ->get(['id', $this->rateField()]);

            if ($records->isEmpty()) {
                return ['items' => [], 'error' => "当前渠道未配置通道【{$item['code']}】的{$this->rateLabel()}"];
            }

            $prepared[] = [
                'payment_id' => intval($payment['id']),
                'payment_code' => (string) $payment['code'],
                'payment_name' => (string) $payment['name'],
                'rate' => $item['rate'],
                'record_count' => $records->count(),
                'old_rates' => $records->pluck($this->rateField())
                    ->map(fn ($value) => bob_amount_format($value))
                    ->unique()
                    ->values()
                    ->implode('、'),
            ];
        }

        return ['items' => $prepared, 'error' => ''];
    }

    protected function buildSuccessText(array $payload, array $updatedIds): string
    {
        $text = "修改成功\n渠道：【#{$payload['channel_id']}】【{$payload['channel_code']}】{$payload['channel_name']}";
        $text .= "\n修改明细：";
        foreach (($payload['items'] ?? []) as $index => $item) {
            $text .= "\n" . ($index + 1) . ". 【{$item['payment_code']}】{$item['payment_name']} => " . $this->formatRateValue($item['rate']);
        }
        $text .= "\n更新记录数：" . count($updatedIds);

        return $text;
    }

    protected function getCacheKey(array $message): string
    {
        $fromId = $message['from']['id'] ?? 0;
        $chatId = $message['chat']['id'] ?? ($message['message']['chat']['id'] ?? 0);

        return $fromId . '_' . $chatId . '_' . $this->cacheKeySuffix();
    }

    protected function rateType(): int
    {
        return 0;
    }

    protected function rateField(): string
    {
        return 'rate';
    }

    protected function rateLabel(): string
    {
        return '百分比成本费率';
    }

    protected function callbackType(): int
    {
        return 20;
    }

    protected function parsePrefix(): string
    {
        return '修改渠道费率';
    }

    protected function commandExample(): string
    {
        return '/channel_rate 1 alipay/2.1 alipay_uid/2.2';
    }

    protected function cacheKeySuffix(): string
    {
        return 'channel_rate';
    }

    protected function systemActionKey(): string
    {
        return 'channel.rate.telegram_update_rate';
    }

    protected function systemLogText(): string
    {
        return 'Telegram批量修改渠道百分比成本费率';
    }

    protected function formatRateValue($value, bool $allowEmptyPlaceholder = false): string
    {
        if ($allowEmptyPlaceholder && ($value === '' || $value === null)) {
            return '-';
        }

        return bob_amount_format($value) . '%';
    }
}
