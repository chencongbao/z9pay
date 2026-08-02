<?php

namespace App\Observers;

use App\Models\ChannelRate;
use Illuminate\Support\Facades\App;
use App\Services\Cache\ChannelRate\GetChannelRateDetailService;

class ChannelRateObserver
{
    public $afterCommit = true;

    public function saved(ChannelRate $model)
    {
        App::make(GetChannelRateDetailService::class)->excute($model->channel_id, $model->payment_id, true);
    }

    public function deleted(ChannelRate $model)
    {
        App::make(GetChannelRateDetailService::class)->excute($model->channel_id, $model->payment_id, true);
    }
}
