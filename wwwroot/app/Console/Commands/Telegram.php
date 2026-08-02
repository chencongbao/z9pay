<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Console\Command;
use App\Services\Telegram\TelegramInstanceService;

class Telegram extends Command
{
    protected $signature = 'telegram {--info : 查看Webhook信息} {--remove : 删除Webhook} {--force : 确认执行删除Webhook} {--test : 发送测试消息} {--chat-id= : 测试消息接收群/用户ID} {--text=test : 测试消息内容}';

    protected $description = '获取或测试飞机Webhook信息';

    public function handle(): int
    {
        if (!$this->option('info') && !$this->option('remove') && !$this->option('test')) {
            $this->warn('请指定 --info、--remove 或 --test 参数。');
            return self::SUCCESS;
        }

        if (!$this->validateRemoveOption()) {
            return self::FAILURE;
        }

        try {
            $testChatId = null;
            if ($this->option('test')) {
                $testChatId = $this->testChatId();
                if ($testChatId === null) {
                    return self::FAILURE;
                }
            }

            $telegram = app(TelegramInstanceService::class)->excute(true);

            if ($this->option('remove')) {
                $telegram->removeWebhook();
                $this->info('Webhook已删除。');
            }

            if ($this->option('test')) {
                $this->sendTestMessage($telegram, $testChatId);
            }

            if ($this->option('info')) {
                $this->showWebhookInfo($telegram);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('飞机命令执行失败：' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function validateRemoveOption(): bool
    {
        if (!$this->option('remove')) {
            return true;
        }

        if ($this->option('info') || $this->option('test')) {
            $this->error('--remove 必须单独执行，不能和 --info 或 --test 同时使用。');
            return false;
        }

        if (!$this->option('force')) {
            $this->error('删除 Telegram Webhook 属于破坏性操作，请显式指定 --force。');
            return false;
        }

        return true;
    }

    private function sendTestMessage($telegram, string $chatId): void
    {
        $text = (string)$this->option('text');

        $telegram->sendMessage(['chat_id' => $chatId, 'text' => $text]);
        $this->info('测试消息已发送：' . $chatId);
    }

    private function showWebhookInfo($telegram): void
    {
        $response = $telegram->getWebhookInfo()->all();
        $this->line('Telegram Bot Token：' . (blank(config('telegram.telegram_bot_token')) ? '未配置' : '已配置'));

        $fields = [
            'url',
            'has_custom_certificate',
            'pending_update_count',
            'ip_address',
            'last_error_date',
            'last_error_message',
            'last_synchronization_error_date',
            'max_connections',
            'allowed_updates',
        ];
        $rows = collect($fields)->map(fn (string $field) => [$field, $this->formatWebhookInfoValue($response[$field] ?? null)]);

        collect($response)
            ->except($fields)
            ->each(fn ($value, $key) => $rows->push([$key, $this->formatWebhookInfoValue($value)]));

        $this->table(['字段', '值'], $rows->values()->all());
    }

    private function formatWebhookInfoValue($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function testChatId(): ?string
    {
        $chatId = $this->option('chat-id') ?: config('default.system_telegram_id');
        $chatId = trim((string)$chatId);

        if ($chatId === '') {
            $this->error('请配置 default.system_telegram_id 或使用 --chat-id 指定接收ID。');
            return null;
        }

        return $chatId;
    }
}
