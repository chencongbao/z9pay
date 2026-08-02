<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use App\Services\Telegram\TelegramInstanceService;

class TelegramCommandTest extends TestCase
{
    public function test_info_does_not_output_raw_bot_token(): void
    {
        $token = '123456789:codex-secret-token';
        config(['telegram.telegram_bot_token' => $token]);
        $service = $this->fakeTelegramService();

        $exitCode = Artisan::call('telegram', ['--info' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $service->calls);
        $this->assertStringContainsString('Telegram Bot Token：已配置', $output);
        $this->assertStringContainsString('https://example.test/webhook', $output);
        $this->assertStringNotContainsString($token, $output);
        $this->assertStringNotContainsString('codex-secret-token', $output);
    }

    public function test_test_message_without_chat_id_fails_before_telegram_initialization(): void
    {
        config(['default.system_telegram_id' => null]);
        $service = $this->fakeTelegramService();

        $exitCode = Artisan::call('telegram', ['--test' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, $service->calls);
        $this->assertStringContainsString('请配置 default.system_telegram_id 或使用 --chat-id 指定接收ID。', $output);
    }

    public function test_test_message_with_chat_id_keeps_existing_send_behavior(): void
    {
        config(['default.system_telegram_id' => null]);
        $service = $this->fakeTelegramService();

        $exitCode = Artisan::call('telegram', [
            '--test' => true,
            '--chat-id' => '10001',
            '--text' => 'hello',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $service->calls);
        $this->assertSame([['chat_id' => '10001', 'text' => 'hello']], $service->telegram->messages);
        $this->assertStringContainsString('测试消息已发送：10001', Artisan::output());
    }

    public function test_remove_without_force_fails_before_telegram_initialization(): void
    {
        $service = $this->fakeTelegramService();

        $exitCode = Artisan::call('telegram', ['--remove' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, $service->calls);
        $this->assertSame(0, $service->telegram->removeCalls);
        $this->assertStringContainsString('删除 Telegram Webhook 属于破坏性操作，请显式指定 --force。', Artisan::output());
    }

    public function test_remove_with_other_actions_fails_even_when_force_is_present(): void
    {
        $service = $this->fakeTelegramService();

        $exitCode = Artisan::call('telegram', ['--remove' => true, '--info' => true, '--force' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, $service->calls);
        $this->assertSame(0, $service->telegram->removeCalls);
        $this->assertStringContainsString('--remove 必须单独执行，不能和 --info 或 --test 同时使用。', Artisan::output());
    }

    public function test_remove_with_force_removes_webhook_once(): void
    {
        $service = $this->fakeTelegramService();

        $exitCode = Artisan::call('telegram', ['--remove' => true, '--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $service->calls);
        $this->assertSame(1, $service->telegram->removeCalls);
        $this->assertStringContainsString('Webhook已删除。', Artisan::output());
    }

    public function test_remove_exception_returns_failure(): void
    {
        $service = $this->fakeTelegramService();
        $service->telegram->removeException = new \RuntimeException('remove failed');

        $exitCode = Artisan::call('telegram', ['--remove' => true, '--force' => true]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(1, $service->calls);
        $this->assertSame(1, $service->telegram->removeCalls);
        $this->assertStringContainsString('飞机命令执行失败：remove failed', Artisan::output());
    }

    private function fakeTelegramService(): object
    {
        $service = new class {
            public int $calls = 0;
            public object $telegram;

            public function __construct()
            {
                $this->telegram = new class {
                    public array $messages = [];
                    public int $removeCalls = 0;
                    public ?\Throwable $removeException = null;

                    public function sendMessage(array $payload): void
                    {
                        $this->messages[] = $payload;
                    }

                    public function removeWebhook(): void
                    {
                        $this->removeCalls++;

                        if ($this->removeException) {
                            throw $this->removeException;
                        }
                    }

                    public function getWebhookInfo(): object
                    {
                        return new class {
                            public function all(): array
                            {
                                return [
                                    'url' => 'https://example.test/webhook',
                                    'pending_update_count' => 0,
                                ];
                            }
                        };
                    }
                };
            }

            public function excute($debug = false, bool $withCommands = false, ?string $telegramBotToken = null): object
            {
                $this->calls++;

                return $this->telegram;
            }
        };

        $this->app->instance(TelegramInstanceService::class, $service);

        return $service;
    }
}
