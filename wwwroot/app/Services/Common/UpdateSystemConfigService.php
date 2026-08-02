<?php

namespace App\Services\Common;

use App\Traits\ServiceTraits;

class UpdateSystemConfigService
{
    use ServiceTraits;

    public function excute()
    {
        $this->setCatchaConfig();
        $this->setAdminConfig();
    }

    protected function setCatchaConfig()
    {
        config(['behavior.watermark.text' => config('admin.name')]);
    }

    public function setAdminConfig()
    {
        config(['other.transfer_pending_status' => intval(bob_admin_setting('other_transfer_pending_status'))]);
        config(['telegram.turn_on' => intval(bob_admin_setting('telegram_turn_on'))]);
        config(['telegram.telegram_bot_token' => bob_admin_setting('telegram_bot_token')]);
    }
}
