<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\TelegramQunSendJob;
use Telegram\Bot\Objects\Message;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\FileUpload\InputFile;
use App\Services\Telegram\TelegramInstanceService;

class TelegramQunSendJobImageReuseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'telegram.telegram_bot_token' => 'codex-test-token',
        ]);
        app('cache')->setDefaultDriver('array');
        Cache::flush();
    }

    public function test_same_image_is_uploaded_once_and_then_reused_by_file_id(): void
    {
        $imageDirectory = storage_path('app/public/images');
        if (!is_dir($imageDirectory)) {
            mkdir($imageDirectory, 0755, true);
        }

        $filename = 'codex-telegram-reuse-' . uniqid('', true) . '.png';
        $relativePath = 'images/' . $filename;
        $imagePath = $imageDirectory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($imagePath, 'codex-image-content');
        $imageHash = hash_file('sha256', $imagePath);

        $service = $this->fakeTelegramService();

        try {
            (new TelegramQunSendJob([
                'telegram_group_id' => -10001,
                'send_image' => $relativePath,
                'send_image_hash' => $imageHash,
                'send_content' => 'first',
            ]))->handle();

            unlink($imagePath);

            (new TelegramQunSendJob([
                'telegram_group_id' => -10002,
                'send_image' => $relativePath,
                'send_image_hash' => $imageHash,
                'send_content' => 'second',
            ]))->handle();
        } finally {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }

        $this->assertCount(2, $service->telegram->photos);
        $this->assertInstanceOf(InputFile::class, $service->telegram->photos[0]['photo']);
        $this->assertSame('telegram-file-id', $service->telegram->photos[1]['photo']);
    }

    public function test_missing_image_falls_back_to_text_message(): void
    {
        $service = $this->fakeTelegramService();

        (new TelegramQunSendJob([
            'telegram_group_id' => -10003,
            'send_image' => 'images/missing.png',
            'send_content' => 'fallback text',
        ]))->handle();

        $this->assertCount(0, $service->telegram->photos);
        $this->assertSame([[
            'chat_id' => -10003,
            'text' => 'fallback text',
            'parse_mode' => 'html',
        ]], $service->telegram->messages);
    }

    private function fakeTelegramService(): object
    {
        $service = new class {
            public object $telegram;

            public function __construct()
            {
                $this->telegram = new class {
                    public array $photos = [];
                    public array $messages = [];

                    public function sendPhoto(array $payload): Message
                    {
                        $this->photos[] = $payload;

                        return new Message([
                            'photo' => [[
                                'file_id' => 'telegram-file-id',
                                'file_unique_id' => 'telegram-file-unique-id',
                                'width' => 100,
                                'height' => 100,
                            ]],
                        ]);
                    }

                    public function sendMessage(array $payload): void
                    {
                        $this->messages[] = $payload;
                    }
                };
            }

            public function excute(): object
            {
                return $this->telegram;
            }
        };

        $this->app->instance(TelegramInstanceService::class, $service);

        return $service;
    }
}
