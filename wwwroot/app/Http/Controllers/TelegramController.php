<?php

namespace App\Http\Controllers;

use Throwable;
use Illuminate\Http\Request;
use App\Extendtions\Telegram\Message;
use Illuminate\Support\Facades\Cache;
use App\Services\Telegram\TelegramManagerService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Telegram\TelegramInstanceService;

class TelegramController extends Controller
{
    public function webhook(Request $request)
    {
        $updates = [];
        try {
            $updates = $request->getContent();
            $payload = json_decode($updates, true);

            if (!is_array($payload) || empty($payload)) {
                return 'ok';
            }

            if (isset($payload['update_id']) && !Cache::add('telegram_update:' . $payload['update_id'], 1, now()->addMinutes(10))) {
                return 'ok';
            }

            $isCommandMessage = !empty($payload['message']['text']) && str_starts_with((string) $payload['message']['text'], '/');
            $telegram = app(TelegramInstanceService::class)->excute(false, $isCommandMessage);

            if ($isCommandMessage) {
                $managerService = app(TelegramManagerService::class);
                $chat = $payload['message']['chat'] ?? [];
                $chatId = intval($chat['id'] ?? 0);
                $text = trim((string) $payload['message']['text']);
                $lowerText = strtolower($text);

                if ($managerService->isPrivateChat($chat) && !$managerService->isManagerMessage($payload['message'])) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => '您暂无权限使用私聊机器人命令，请联系管理员开通权限。',
                    ]);
                    return 'ok';
                }

                if (str_starts_with($lowerText, '/success_rate')) {
                    $message = new Message($telegram);
                    $message->init($payload);
                    return 'ok';
                }

                if ($chatId < 0 && (str_starts_with($lowerText, '/channel_rate') || str_starts_with($lowerText, '/channel_fixed_rate'))) {
                    return 'ok';
                }

                $telegram->commandsHandler(true);
                return 'ok';
            }

            if ($payload) {
                $message = new Message($telegram);
                $message->init($payload);
            }
            return 'ok';
        } catch (Throwable $e) {
            $telegramBotToken = (string)config("telegram.telegram_bot_token");
            app(SystemNoticeService::class)->warning("system_manual_notice", [
                'message' => $this->sanitizeTelegramError($e->getMessage(), $telegramBotToken),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'updates' => json_decode($updates, true),
                'telegram_bot_token_configured' => $telegramBotToken !== '',
                'telegram_bot_token_masked' => $this->maskTelegramToken($telegramBotToken),
            ]);
            return 'ok';
        }
    }

    private function sanitizeTelegramError(string $message, string $token): string
    {
        if ($token !== '') {
            $message = str_replace($token, $this->maskTelegramToken($token), $message);
        }

        $message = preg_replace('/bot\d+:[A-Za-z0-9_-]+/', 'bot******', $message) ?? $message;
        $message = preg_replace('/\d{6,}:[A-Za-z0-9_-]{20,}/', '******', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }

    private function maskTelegramToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        if (strlen($token) <= 10) {
            return str_repeat('*', strlen($token));
        }

        return substr($token, 0, 6) . str_repeat('*', 8) . substr($token, -4);
    }
}
