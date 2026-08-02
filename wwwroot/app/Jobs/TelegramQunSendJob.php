<?php

namespace App\Jobs;

use Throwable;
use RuntimeException;
use Illuminate\Bus\Queueable;
use App\Models\MerchantTelegramAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\FileUpload\InputFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Telegram\TelegramInstanceService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Telegram\Bot\Exceptions\TelegramResponseException;

class TelegramQunSendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SEND_BLOCKED_UNTIL_CACHE_KEY_PREFIX = 'telegram:send:blocked_until:';
    private const PHOTO_FILE_ID_CACHE_KEY_PREFIX = 'telegram:photo:file_id:';
    private const PHOTO_UPLOAD_LOCK_KEY_PREFIX = 'telegram:photo:upload_lock:';

    public $tries = 1;

    public $timeout = 10;

    public $data = [];

    public $is_telegram_failure_notice = 0;

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function handle()
    {
        try {
            $chatId = $this->data['telegram_group_id'] ?? 0;
            $sendContent = $this->data['send_content'] ?? '';
            $sendImage = $this->data['send_image'] ?? '';
            $parseMode = $this->data['parse_mode'] ?? 'html';
            $this->is_telegram_failure_notice = $this->data['is_telegram_failure_notice'] ?? 0;

            if (empty($chatId)) {
                return;
            }

            if ($this->isSendBlocked($chatId)) {
                return;
            }

            $telegram = app(TelegramInstanceService::class)->excute();
            $sendContent = $this->appendMerchantTelegramAdminMentions((string) $sendContent, intval($chatId));

            if (!empty($sendImage)) {
                $this->sendPhoto($telegram, $chatId, (string)$sendImage, (string)$sendContent, (string)$parseMode);
                return;
            }

            if (!empty($sendContent)) {
                $params = $this->appendOptionalParams([
                    'chat_id' => $chatId,
                    'text' => $sendContent,
                    'parse_mode' => $parseMode,
                ]);

                $telegram->sendMessage($params);
            }
        } catch (Throwable $e) {
            if ($this->blockSendIfTooManyRequests($e, $this->data['telegram_group_id'] ?? 0)) {
                return;
            }

            if ($this->is_telegram_failure_notice == 1) {
                return;
            }

            app(SystemNoticeService::class)->warning("system_manual_notice", [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'data' => $this->data,
            ]);
        }
    }

    private function sendPhoto($telegram, $chatId, string $sendImage, string $sendContent, string $parseMode): void
    {
        $imageHash = trim((string)($this->data['send_image_hash'] ?? ''));
        $imagePath = $this->resolveSendImagePath($sendImage);
        if ($imageHash === '' && $imagePath !== null) {
            $imageHash = (string)hash_file('sha256', $imagePath);
        }

        $cacheKey = $this->photoFileIdCacheKey($imageHash);
        $fileId = $cacheKey === '' ? '' : (string)Cache::get($cacheKey, '');
        if ($fileId !== '') {
            $telegram->sendPhoto($this->photoParams($chatId, $fileId, $sendContent, $parseMode));
            return;
        }

        if ($imagePath === null) {
            if ($sendContent !== '') {
                $telegram->sendMessage($this->appendOptionalParams([
                    'chat_id' => $chatId,
                    'text' => $sendContent,
                    'parse_mode' => $parseMode,
                ]));
                return;
            }

            throw new RuntimeException('群发图片不存在或无法读取');
        }

        if ($cacheKey === '') {
            $this->uploadPhotoAndCache($telegram, $chatId, $imagePath, $sendContent, $parseMode, '');
            return;
        }

        $lock = Cache::lock(self::PHOTO_UPLOAD_LOCK_KEY_PREFIX . md5($cacheKey), 30);
        try {
            $lock->block(10, function () use ($telegram, $chatId, $imagePath, $sendContent, $parseMode, $cacheKey) {
                $cachedFileId = (string)Cache::get($cacheKey, '');
                if ($cachedFileId !== '') {
                    $telegram->sendPhoto($this->photoParams($chatId, $cachedFileId, $sendContent, $parseMode));
                    return;
                }

                $this->uploadPhotoAndCache($telegram, $chatId, $imagePath, $sendContent, $parseMode, $cacheKey);
            });
        } catch (LockTimeoutException $e) {
            $fileId = (string)Cache::get($cacheKey, '');
            $this->uploadPhotoAndCache($telegram, $chatId, $imagePath, $sendContent, $parseMode, $fileId === '' ? $cacheKey : '', $fileId);
        }
    }

    private function uploadPhotoAndCache($telegram, $chatId, string $imagePath, string $sendContent, string $parseMode, string $cacheKey, string $fileId = ''): void
    {
        $response = $telegram->sendPhoto($this->photoParams(
            $chatId,
            $fileId !== '' ? $fileId : new InputFile($imagePath),
            $sendContent,
            $parseMode
        ));

        if ($cacheKey === '' || $fileId !== '') {
            return;
        }

        $telegramFileId = $this->extractPhotoFileId($response);
        if ($telegramFileId !== '') {
            Cache::forever($cacheKey, $telegramFileId);
        }
    }

    private function photoParams($chatId, $photo, string $sendContent, string $parseMode): array
    {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'parse_mode' => $parseMode,
        ];

        if ($sendContent !== '') {
            $params['caption'] = $sendContent;
        }

        return $this->appendOptionalParams($params);
    }

    private function extractPhotoFileId($response): string
    {
        if (!is_object($response) || !method_exists($response, 'get')) {
            return '';
        }

        $photos = $response->get('photo', []);
        $photo = is_object($photos) && method_exists($photos, 'last') ? $photos->last() : collect((array)$photos)->last();

        if (is_object($photo) && method_exists($photo, 'get')) {
            return (string)$photo->get('file_id', '');
        }

        return (string)data_get($photo, 'file_id', '');
    }

    private function resolveSendImagePath(string $sendImage): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $sendImage), '/');
        if (!str_starts_with($relativePath, 'images/') || str_contains($relativePath, '../')) {
            return null;
        }

        $imageRoot = realpath(storage_path('app/public/images'));
        $imagePath = realpath(storage_path('app/public/' . $relativePath));
        if ($imageRoot === false || $imagePath === false || !str_starts_with($imagePath, $imageRoot . DIRECTORY_SEPARATOR) || !is_file($imagePath) || !is_readable($imagePath)) {
            return null;
        }

        return $imagePath;
    }

    private function photoFileIdCacheKey(string $imageHash): string
    {
        if ($imageHash === '') {
            return '';
        }

        $token = (string)(bob_admin_setting('telegram_bot_token') ?: config('telegram.telegram_bot_token', ''));
        return self::PHOTO_FILE_ID_CACHE_KEY_PREFIX . hash('sha256', $token) . ':' . $imageHash;
    }

    private function appendOptionalParams(array $params): array
    {
        if (!empty($this->data['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $this->data['reply_to_message_id'];
        }

        if (!empty($this->data['reply_markup'])) {
            $params['reply_markup'] = is_array($this->data['reply_markup'])
                ? json_encode($this->data['reply_markup'], JSON_UNESCAPED_UNICODE)
                : $this->data['reply_markup'];
        }

        return $params;
    }

    private function appendMerchantTelegramAdminMentions(string $sendContent, int $chatId): string
    {
        if (empty($this->data['mention_merchant_admins'])) {
            return $sendContent;
        }

        $mentions = $this->merchantTelegramAdminMentions(intval($this->data['merchant_id'] ?? 0), $chatId);
        if ($mentions === '') {
            return $sendContent;
        }

        return trim($sendContent) === '' ? $mentions : rtrim($sendContent) . "\n\n" . $mentions;
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
                $username = trim((string) $admin->telegram_username);
                if ($username !== '') {
                    return '@' . ltrim(e($username), '@');
                }

                $telegramUserId = intval($admin->telegram_user_id);
                if ($telegramUserId <= 0) {
                    return '';
                }

                $name = trim((string) $admin->telegram_name) ?: (string) $telegramUserId;
                return '<a href="tg://user?id=' . $telegramUserId . '">' . e($name) . '</a>';
            })
            ->filter()
            ->implode(' ');
    }

    private function isSendBlocked($chatId): bool
    {
        $blockedUntil = intval(Cache::get($this->getSendBlockedUntilCacheKey($chatId), 0));

        if ($blockedUntil <= time()) {
            return false;
        }

        return true;
    }

    private function blockSendIfTooManyRequests(Throwable $e, $chatId): bool
    {
        $retryAfter = $this->resolveRetryAfter($e);

        if ($retryAfter === null || empty($chatId)) {
            return false;
        }

        $retryAfter = max(1, $retryAfter);
        $blockedUntil = time() + $retryAfter;

        Cache::put($this->getSendBlockedUntilCacheKey($chatId), $blockedUntil, now()->addSeconds($retryAfter));

        return true;
    }

    private function getSendBlockedUntilCacheKey($chatId): string
    {
        return self::SEND_BLOCKED_UNTIL_CACHE_KEY_PREFIX . md5((string) $chatId);
    }

    private function resolveRetryAfter(Throwable $e): ?int
    {
        if ($e instanceof TelegramResponseException) {
            if ((int) $e->getCode() === 429 || (int) $e->getHttpStatusCode() === 429) {
                $parameters = $e->get('parameters', []);
                return intval($parameters['retry_after'] ?? 60);
            }
        }

        if (preg_match('/retry after\s+(\d+)/i', $e->getMessage(), $matches)) {
            return intval($matches[1]);
        }

        if (stripos($e->getMessage(), 'Too Many Requests') !== false) {
            return 60;
        }

        return null;
    }
}
