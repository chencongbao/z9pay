<?php

namespace App\Services\Channel;

use App\Traits\ServiceTraits;

class GetChannelNoticeBalanceService
{
    use ServiceTraits;

    public function enabled($cid = 0): bool
    {
        $amount = $this->excute($cid);
        $chatIds = bob_format_muti_data_to_array((string) bob_admin_setting('telegram_channel_balance_notice_telegram_group_ids'));

        return $amount > 0 && !empty($chatIds);
    }

    public function excute($cid = 0)
    {
        $noticeSettings = bob_admin_setting('telegram_channel_balance_notice_single');
        if (!empty($noticeSettings)) {
            $noticeSettings = json_decode($noticeSettings, true);
            foreach ($noticeSettings ?: [] as $item) {
                if (intval($item['cid'] ?? 0) === intval($cid)) {
                    return floatval($item['value'] ?? 0);
                }
            }
        }

        return 0;
    }
}
