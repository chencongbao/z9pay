<?php

namespace App\Services\Channel;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Channel\ChannelInfoByChannelIdService;

class CheckChannelPaymentService
{
    use ServiceTraits;

    public function excute($cid = 0, $payment_id = 0): bool
    {
        $channel = App::make(ChannelInfoByChannelIdService::class)->excute($cid);
        if (empty($channel['payment_ids'])) {
            return false;
        }

        $ids = array_map('intval', explode(',', $channel['payment_ids']));

        return in_array(intval($payment_id), $ids, true);
    }
}
