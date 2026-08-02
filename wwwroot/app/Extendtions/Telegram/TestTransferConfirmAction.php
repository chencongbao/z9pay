<?php

namespace App\Extendtions\Telegram;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Telegram\TelegramManagerService;
use App\Services\TransferOrder\AdminTestTransferService;

class TestTransferConfirmAction
{
    protected $telegram;

    protected array $emptyKeyboard = ['inline_keyboard' => []];

    public function __construct($telegram = null)
    {
        $this->telegram = $telegram ?: app(TelegramInstanceService::class)->excute();
    }

    public function callback(array $data = [], array $message = []): void
    {
        $token = (string)($data['k'] ?? '');
        if ($token === '') {
            return;
        }

        $cacheKey = $this->getCacheKey($token);
        $payload = Cache::get($cacheKey, []);
        if (empty($payload)) {
            $this->clearKeyboard($message);
            $this->replyText($message, '该代付测试确认已过时，请重新发起');
            return;
        }

        if ($this->isExpired($payload)) {
            $payload['status'] = 'expired';
            $payload['expired_at'] = now()->toDateTimeString();
            Cache::put($cacheKey, $payload, now()->addMinutes(30));
            $this->clearKeyboard($message);
            $this->replyText($message, $this->buildBaseText($payload) . "\n\n状态：已过时\n过期时间：" . ($payload['expires_at'] ?? '-'));
            return;
        }

        if (!app(TelegramManagerService::class)->isSuperManagerMessage($message)) {
            $operatorName = $this->buildOperatorName($message);
            $operatorId = intval($message['from']['id'] ?? 0);
            $this->answerCallback($message, '你没有代付测试确认权限');
            $this->sendMessage($message, "无权限：{$operatorName}(TG#{$operatorId}) 尝试确认/拒绝该代付测试，但其不在飞机超级管理员名单内");
            return;
        }

        if (($payload['status'] ?? '') !== 'pending') {
            $this->replyText($message, $this->handledStatusText((string) ($payload['status'] ?? '')));
            return;
        }

        $action = (string) ($data['a'] ?? '');
        if ($action === 'x') {
            $payload['status'] = 'rejected';
            $payload['rejected_by'] = $this->buildOperatorName($message);
            $payload['rejected_at'] = now()->toDateTimeString();
            Cache::put($cacheKey, $payload, now()->addMinutes(30));
            $this->clearKeyboard($message);
            $this->replyText($message, $this->buildBaseText($payload) . "\n\n状态：已拒绝\n操作人：" . $payload['rejected_by']);
            return;
        }

        if ($action !== 'c') {
            return;
        }

        $lockKey = $cacheKey . ':lock';
        if (!Cache::add($lockKey, 1, now()->addSeconds(10))) {
            return;
        }

        try {
            $payload['status'] = 'confirmed';
            $payload['confirmed_by'] = $this->buildOperatorName($message);
            $payload['confirmed_at'] = now()->toDateTimeString();
            Cache::put($cacheKey, $payload, now()->addMinutes(30));

            $this->clearKeyboard($message);
            $this->replyText($message, $this->buildBaseText($payload) . "\n\n状态：已确认，正在执行代付测试\n确认人：" . $payload['confirmed_by']);

            $result = app(AdminTestTransferService::class)->execute($payload['input'] ?? [], [
                'id' => $message['from']['id'] ?? 0,
                'name' => $payload['confirmed_by'],
            ]);

            $payload['status'] = 'executed';
            $payload['executed_at'] = now()->toDateTimeString();
            $payload['order_no'] = $result['order_no'] ?? '';
            Cache::put($cacheKey, $payload, now()->addMinutes(30));

            $this->sendMessage($message, $this->buildBaseText($payload) . "\n\n状态：执行成功\n确认人：" . ($payload['confirmed_by'] ?? '') . "\n测试单号：" . ($result['order_no'] ?? '') . "\n结果：" . ($result['message'] ?? '下单成功'));
        } catch (Throwable $e) {
            $payload['status'] = 'failed';
            $payload['failed_at'] = now()->toDateTimeString();
            $payload['failed_message'] = $e->getMessage();
            Cache::put($cacheKey, $payload, now()->addMinutes(30));

            $this->sendMessage($message, $this->buildBaseText($payload) . "\n\n状态：执行失败\n确认人：" . ($payload['confirmed_by'] ?? '') . "\n失败原因：" . $e->getMessage());
        } finally {
            Cache::forget($lockKey);
        }
    }

    protected function getCacheKey(string $token): string
    {
        return 'admin:test_transfer:confirm:' . $token;
    }

    protected function clearKeyboard(array $message = []): void
    {
        $chatId = $this->chatId($message);
        $messageId = $this->messageId($message);
        if (!$chatId || !$messageId) {
            return;
        }

        $this->telegram->editMessageReplyMarkup(['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode($this->emptyKeyboard)]);
    }

    protected function replyText(array $message = [], string $text = ''): void
    {
        $chatId = $this->chatId($message);
        $messageId = $this->messageId($message);
        if (!$chatId || !$messageId) {
            return;
        }

        $this->telegram->editMessageText(['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text]);
    }

    protected function buildOperatorName(array $message = []): string
    {
        $from = $message['from'] ?? [];
        $name = trim((string)(($from['username'] ?? '') !== '' ? '@' . $from['username'] : trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''))));

        return $name !== '' ? $name : ('TG#' . intval($from['id'] ?? 0));
    }

    protected function buildBaseText(array $payload = []): string
    {
        return implode("\n", [
            '代付测试商户：' . ($payload['merchant_name'] ?? '-'),
            '代付测试币种：' . ($payload['currency_name'] ?? '-'),
            '代付测试银行：' . ($payload['bank_name'] ?? '-'),
            '代付测试金额：' . ($payload['amount'] ?? '-'),
            '代付测试账号：' . ($payload['account_no'] ?? '-'),
            '代付账号名称：' . ($payload['holder_name'] ?? '-'),
            '发起人：' . ($payload['admin_name'] ?? '-'),
            '发起时间：' . ($payload['created_at'] ?? '-'),
        ]);
    }

    protected function handledStatusText(string $status): string
    {
        return match ($status) {
            'executed' => '该代付测试已执行，请勿重复确认',
            'failed' => '该代付测试已执行失败，请勿重复确认',
            'rejected' => '该代付测试已被拒绝',
            'expired' => '该代付测试已过时，请重新发起',
            default => '该代付测试已处理',
        };
    }

    protected function isExpired(array $payload = []): bool
    {
        $expiresAt = trim((string)($payload['expires_at'] ?? ''));
        if ($expiresAt === '') {
            return false;
        }

        return now()->greaterThanOrEqualTo(Carbon::parse($expiresAt));
    }

    protected function answerCallback(array $message, string $text): void
    {
        if (empty($message['id'])) {
            return;
        }

        $this->telegram->answerCallbackQuery(['callback_query_id' => $message['id'], 'text' => $text, 'show_alert' => true]);
    }

    protected function sendMessage(array $message, string $text): void
    {
        $chatId = $this->chatId($message);
        if (!$chatId) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text];
        $messageId = $this->messageId($message);
        if ($messageId) {
            $payload['reply_to_message_id'] = $messageId;
        }

        $this->telegram->sendMessage($payload);
    }

    protected function chatId(array $message): int
    {
        return intval($message['message']['chat']['id'] ?? 0);
    }

    protected function messageId(array $message): int
    {
        return intval($message['message']['message_id'] ?? 0);
    }
}
