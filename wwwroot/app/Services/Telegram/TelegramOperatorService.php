<?php

namespace App\Services\Telegram;

use App\Models\AdminAdministrator;

class TelegramOperatorService
{
    public const SYSTEM_ADMIN_ID = 1;

    public function telegramUserId(array $message = []): int
    {
        return intval(data_get($message, 'from.id', 0));
    }

    public function telegramName(array $message = []): string
    {
        $name = trim((string)data_get($message, 'from.first_name', '') . ' ' . (string)data_get($message, 'from.last_name', ''));
        if ($name !== '') {
            return $name;
        }

        return (string)(data_get($message, 'from.username') ?: data_get($message, 'from.id', 'Telegram用户'));
    }

    public function adminId(array $message = [], int $role = AdminAdministrator::TELEGRAM_ROLE_MANAGER): int
    {
        $admin = $this->admin($message, $role);
        return $admin ? (int)$admin->id : 0;
    }

    public function admin(array $message = [], int $role = AdminAdministrator::TELEGRAM_ROLE_MANAGER): ?AdminAdministrator
    {
        return $this->adminByTelegramUserId($this->telegramUserId($message), $role);
    }

    public function adminByTelegramUserId(int $telegramUserId, int $role = AdminAdministrator::TELEGRAM_ROLE_MANAGER): ?AdminAdministrator
    {
        if ($telegramUserId <= 0) {
            return null;
        }

        if ($this->isSystemManager($telegramUserId)) {
            return AdminAdministrator::query()->find(self::SYSTEM_ADMIN_ID, ['id', 'name', 'username', 'status', 'telegram_user_id', 'telegram_role']);
        }

        return AdminAdministrator::query()
            ->where('status', 1)
            ->where('telegram_user_id', $telegramUserId)
            ->where('telegram_role', '>=', $role)
            ->first(['id', 'name', 'username', 'status', 'telegram_user_id', 'telegram_role']);
    }

    public function context(array $message = [], int $role = AdminAdministrator::TELEGRAM_ROLE_MANAGER): array
    {
        $admin = $this->admin($message, $role);

        return [
            'admin_id' => $admin ? (int)$admin->id : 0,
            'admin_name' => $admin ? (string)$admin->name : '',
            'admin_username' => $admin ? (string)$admin->username : '',
            'telegram_user_id' => $this->telegramUserId($message),
            'telegram_name' => $this->telegramName($message),
        ];
    }

    public function isSystemManager(int $telegramUserId): bool
    {
        return intval(config('default.system_telegram_manager_on', 0)) === 1
            && $telegramUserId > 0
            && $telegramUserId === intval(config('default.system_telegram_id'));
    }
}
