<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Services\Telegram\TelegramInstanceService;

class FailedJobsCleanupCommand extends Command
{
    private const CALLBACK_TYPE = 27;

    private const QUEUE_CACHE_PREFIX = 'failed_jobs:queue:';

    protected $signature = 'failed-jobs:cleanup {--dry-run : 只统计不删除}';

    protected $description = '统计失败队列，并发送 Telegram 开发确认按钮';

    public function handle(): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            $this->warn('failed_jobs 表不存在，跳过清理。');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $queues = $this->queueCounts();
        $total = array_sum($queues);
        $this->info(($dryRun ? '失败队列统计试跑完成' : '失败队列统计完成') . '：总数 ' . $total . ' 条' . ($total > 0 ? '，' . $this->formatQueueCounts($queues) : '。'));

        if ($dryRun || $total <= 0) {
            return self::SUCCESS;
        }

        $this->sendQueueSummary($queues, $total);

        return self::SUCCESS;
    }

    protected function queueCounts(): array
    {
        return DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->map(fn($total) => (int) $total)
            ->all();
    }

    protected function formatQueueCounts(array $queues): string
    {
        return collect($queues)->map(fn($total, $queue) => "{$queue}:{$total}")->implode('，');
    }

    protected function sendQueueSummary(array $queues, int $total): void
    {
        $chatId = intval(config('default.system_telegram_id'));
        if ($chatId <= 0) {
            $this->warn('default.system_telegram_id 未配置，跳过 Telegram 通知。');
            return;
        }

        try {
            app(TelegramInstanceService::class)->excute()->sendMessage([
                'chat_id' => $chatId,
                'text' => $this->summaryText($queues, $total),
                'parse_mode' => 'html',
                'reply_markup' => json_encode($this->summaryKeyboard($queues), JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            $this->error('发送失败队列确认消息失败：' . $e->getMessage());
        }
    }

    private function summaryText(array $queues, int $total): string
    {
        $lines = [
            '<b>失败队列待处理</b>',
            '总数：<code>' . $total . '</code>',
            '',
        ];

        foreach ($queues as $queue => $count) {
            $lines[] = '<code>' . $this->html((string) $queue) . '</code>：<code>' . intval($count) . '</code>';
        }

        $lines[] = '';
        $lines[] = '请点击按钮查看详情、清空队列、重新执行或删除单个失败任务。';

        return implode("\n", $lines);
    }

    private function summaryKeyboard(array $queues): array
    {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '全部清空', 'callback_data' => $this->callbackData(['a' => 'ca'])],
                ],
            ],
        ];

        foreach ($queues as $queue => $count) {
            $token = $this->queueToken((string) $queue);
            $queueText = mb_substr((string) $queue, 0, 16);
            $keyboard['inline_keyboard'][] = [
                ['text' => '查看 ' . $queueText . '(' . intval($count) . ')', 'callback_data' => $this->callbackData(['a' => 'v', 'k' => $token])],
                ['text' => '清空 ' . $queueText, 'callback_data' => $this->callbackData(['a' => 'cq', 'k' => $token])],
            ];
        }

        return $keyboard;
    }

    private function queueToken(string $queue): string
    {
        $token = md5($queue);
        Cache::put(self::QUEUE_CACHE_PREFIX . $token, $queue, now()->addDay());

        return $token;
    }

    private function callbackData(array $data): string
    {
        return json_encode(array_merge(['t' => self::CALLBACK_TYPE], $data), JSON_UNESCAPED_UNICODE);
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
