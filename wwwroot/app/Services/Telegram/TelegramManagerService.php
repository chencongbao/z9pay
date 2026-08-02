<?php

namespace App\Services\Telegram;

use App\Models\AdminAdministrator;

class TelegramManagerService
{
    public function fromId(array $message = []): int
    {
        return intval(data_get($message, 'from.id', 0));
    }

    public function isPrivateChat($chat): bool
    {
        $type = (string) data_get($chat, 'type', '');
        if ($type !== '') {
            return $type === 'private';
        }

        return intval(data_get($chat, 'id', 0)) > 0;
    }

    public function isManagerMessage(array $message = []): bool
    {
        return $this->isManager($this->fromId($message));
    }

    public function isSuperManagerMessage(array $message = []): bool
    {
        return $this->isSuperManager($this->fromId($message));
    }

    public function isDeveloperMessage(array $message = []): bool
    {
        return $this->isDeveloper($this->fromId($message));
    }

    public function isDeveloper(int $telegramUserId): bool
    {
        return $telegramUserId > 0 && $telegramUserId === intval(config('default.system_telegram_id'));
    }

    public function isManager(int $telegramUserId): bool
    {
        if ($telegramUserId <= 0) {
            return false;
        }

        if ($this->isSystemManager($telegramUserId)) {
            return true;
        }

        return AdminAdministrator::query()
            ->where('status', 1)
            ->where('telegram_user_id', $telegramUserId)
            ->where('telegram_role', '>=', AdminAdministrator::TELEGRAM_ROLE_MANAGER)
            ->exists();
    }

    public function isSuperManager(int $telegramUserId): bool
    {
        if ($telegramUserId <= 0) {
            return false;
        }

        return AdminAdministrator::query()
            ->where('status', 1)
            ->where('telegram_user_id', $telegramUserId)
            ->where('telegram_role', '>=', AdminAdministrator::TELEGRAM_ROLE_SUPER_MANAGER)
            ->exists();
    }

    public function managerIds(): array
    {
        return $this->roleIds(AdminAdministrator::TELEGRAM_ROLE_MANAGER);
    }

    public function superManagerIds(): array
    {
        return $this->roleIds(AdminAdministrator::TELEGRAM_ROLE_SUPER_MANAGER);
    }

    public function isSystemManager(int $telegramUserId): bool
    {
        return app(TelegramOperatorService::class)->isSystemManager($telegramUserId);
    }

    private function roleIds(int $role): array
    {
        return AdminAdministrator::query()
            ->where('status', 1)
            ->where('telegram_user_id', '>', 0)
            ->where('telegram_role', '>=', $role)
            ->pluck('telegram_user_id')
            ->map(fn ($id) => intval($id))
            ->unique()
            ->values()
            ->all();
    }
}
