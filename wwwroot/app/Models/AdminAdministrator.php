<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use Dcat\Admin\Models\Administrator;

class AdminAdministrator extends Administrator
{
    use ActivityLogTrait;

    public const TELEGRAM_ROLE_NONE = 0;
    public const TELEGRAM_ROLE_MANAGER = 1;
    public const TELEGRAM_ROLE_SUPER_MANAGER = 2;

    protected $casts = [
        'telegram_user_id' => 'integer',
        'telegram_role' => 'integer',
    ];

    public function isProtectedAdmin(?int $id = null): bool
    {
        return in_array($id ?: (int)$this->getKey(), [self::DEFAULT_ID], true);
    }
}
