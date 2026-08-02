<?php

namespace App\Extendtions\Telegram;

use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Services\Telegram\TelegramManagerService;

class FailedJobConfirmAction
{
    private const CALLBACK_TYPE = 27;

    private const QUEUE_CACHE_PREFIX = 'failed_jobs:queue:';

    private array $emptyKeyboard = ['inline_keyboard' => []];

    public function __construct(private $telegram)
    {
    }

    public function callback(array $data, array $message): void
    {
        if (!app(TelegramManagerService::class)->isDeveloperMessage($message)) {
            $this->reply($message, '您没有开发权限，无权操作失败队列。');
            return;
        }

        match ((string) ($data['a'] ?? '')) {
            'ca' => $this->clearAll($message),
            'cq' => $this->clearQueue((string) ($data['k'] ?? ''), $message),
            'v' => $this->showQueue((string) ($data['k'] ?? ''), $message),
            'r' => $this->retryJob((int) ($data['i'] ?? 0), $message),
            'd' => $this->deleteJob((int) ($data['i'] ?? 0), $message),
            default => $this->reply($message, '未知失败队列操作。'),
        };
    }

    private function clearAll(array $message): void
    {
        $count = DB::table('failed_jobs')->count();
        if ($count <= 0) {
            $this->reply($message, '当前没有失败任务。');
            return;
        }

        DB::table('failed_jobs')->delete();
        $this->clearKeyboard($message);
        $this->reply($message, '已清空全部失败任务：' . $count . ' 条。');
    }

    private function clearQueue(string $token, array $message): void
    {
        $queue = $this->queueByToken($token);
        if ($queue === null) {
            $this->reply($message, '队列确认信息已过期，请重新执行统计命令。');
            return;
        }

        $count = DB::table('failed_jobs')->where('queue', $queue)->count();
        if ($count <= 0) {
            $this->reply($message, '队列 ' . $queue . ' 当前没有失败任务。');
            return;
        }

        DB::table('failed_jobs')->where('queue', $queue)->delete();
        $this->clearKeyboard($message);
        $this->reply($message, '已清空队列 ' . $queue . '：' . $count . ' 条。');
    }

    private function showQueue(string $token, array $message): void
    {
        $queue = $this->queueByToken($token);
        if ($queue === null) {
            $this->reply($message, '队列确认信息已过期，请重新执行统计命令。');
            return;
        }

        $total = DB::table('failed_jobs')->where('queue', $queue)->count();
        if ($total <= 0) {
            $this->reply($message, '队列 ' . $queue . ' 当前没有失败任务。');
            return;
        }

        $jobs = DB::table('failed_jobs')
            ->where('queue', $queue)
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'queue', 'payload', 'exception', 'failed_at']);

        $this->sendMessage($message, $this->queueDetailText($queue, $total, $jobs), $this->queueDetailKeyboard($token, $queue, $jobs));
    }

    private function retryJob(int $id, array $message): void
    {
        if ($id <= 0) {
            $this->reply($message, '失败任务 ID 无效。');
            return;
        }

        $job = DB::table('failed_jobs')->where('id', $id)->first(['id', 'queue']);
        if (!$job) {
            $this->reply($message, '失败任务 #' . $id . ' 不存在或已被处理。');
            return;
        }

        try {
            Artisan::call('queue:retry', ['id' => [$id]]);
            $this->clearKeyboard($message);
            $this->reply($message, '已重新执行失败任务 #' . $id . '，队列：' . $job->queue . '。');
        } catch (Throwable $e) {
            $this->reply($message, '重新执行失败任务 #' . $id . ' 失败：' . $e->getMessage());
        }
    }

    private function deleteJob(int $id, array $message): void
    {
        if ($id <= 0) {
            $this->reply($message, '失败任务 ID 无效。');
            return;
        }

        $job = DB::table('failed_jobs')->where('id', $id)->first(['id', 'queue']);
        if (!$job) {
            $this->reply($message, '失败任务 #' . $id . ' 不存在或已被处理。');
            return;
        }

        try {
            Artisan::call('queue:forget', ['id' => $id]);
            $this->clearKeyboard($message);
            $this->reply($message, '已删除失败任务 #' . $id . '，队列：' . $job->queue . '。');
        } catch (Throwable $e) {
            $this->reply($message, '删除失败任务 #' . $id . ' 失败：' . $e->getMessage());
        }
    }

    private function queueDetailText(string $queue, int $total, $jobs): string
    {
        $lines = [
            '<b>失败队列详情</b>',
            '队列：<code>' . $this->html($queue) . '</code>',
            '总数：<code>' . $total . '</code>',
            '',
        ];

        foreach ($jobs as $job) {
            $lines[] = '#<code>' . (int) $job->id . '</code> ' . $this->html($this->jobName((string) $job->payload));
            $lines[] = '失败时间：<code>' . $this->html((string) $job->failed_at) . '</code>';
            $lines[] = '异常：' . $this->html(mb_substr($this->firstExceptionLine((string) $job->exception), 0, 200));
            $lines[] = '';
        }

        if ($total > $jobs->count()) {
            $lines[] = '仅显示前 ' . $jobs->count() . ' 条，请处理后刷新查看后续任务。';
        }

        return implode("\n", $lines);
    }

    private function queueDetailKeyboard(string $token, string $queue, $jobs): array
    {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '清空 ' . mb_substr($queue, 0, 16), 'callback_data' => $this->callbackData(['a' => 'cq', 'k' => $token])],
                    ['text' => '刷新 ' . mb_substr($queue, 0, 16), 'callback_data' => $this->callbackData(['a' => 'v', 'k' => $token])],
                ],
            ],
        ];

        foreach ($jobs as $job) {
            $id = (int) $job->id;
            $keyboard['inline_keyboard'][] = [
                ['text' => '执行#' . $id, 'callback_data' => $this->callbackData(['a' => 'r', 'i' => $id])],
                ['text' => '删除#' . $id, 'callback_data' => $this->callbackData(['a' => 'd', 'i' => $id])],
            ];
        }

        return $keyboard;
    }

    private function queueByToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $queue = Cache::get(self::QUEUE_CACHE_PREFIX . $token);

        return is_string($queue) && $queue !== '' ? $queue : null;
    }

    private function callbackData(array $data): string
    {
        return json_encode(array_merge(['t' => self::CALLBACK_TYPE], $data), JSON_UNESCAPED_UNICODE);
    }

    private function sendMessage(array $message, string $text, array $keyboard = []): void
    {
        $chatId = intval($message['message']['chat']['id'] ?? 0);
        $messageId = intval($message['message']['message_id'] ?? 0);
        if ($chatId === 0) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'html'];
        if ($messageId > 0) {
            $payload['reply_to_message_id'] = $messageId;
        }
        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE);
        }

        $this->telegram->sendMessage($payload);
    }

    private function reply(array $message, string $text): void
    {
        $this->sendMessage($message, $this->html($text));
    }

    private function clearKeyboard(array $message): void
    {
        $chatId = intval($message['message']['chat']['id'] ?? 0);
        $messageId = intval($message['message']['message_id'] ?? 0);
        if ($chatId === 0 || $messageId <= 0) {
            return;
        }

        try {
            $this->telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => json_encode($this->emptyKeyboard),
            ]);
        } catch (Throwable $e) {
        }
    }

    private function jobName(string $payload): string
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return '-';
        }

        return (string) ($data['displayName'] ?? $data['job'] ?? '-');
    }

    private function firstExceptionLine(string $exception): string
    {
        $line = strtok($exception, "\n");

        return $line !== false ? $line : '';
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
