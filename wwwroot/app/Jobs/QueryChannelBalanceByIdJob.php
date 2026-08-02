<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Services\Channel\QueryChannelBalanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueryChannelBalanceByIdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 5;

    protected int $channelId = 0;

    public function __construct(int $channelId = 0)
    {
        $this->channelId = $channelId;
    }

    public function handle(): void
    {
        if ($this->channelId <= 1) {
            return;
        }

        $channel = Channel::where('id', $this->channelId)->first(['id', 'classname', 'name', 'status', 'telegram_user_id']);
        if (! $channel || intval($channel->status) !== 1) {
            return;
        }

        $service = app(QueryChannelBalanceService::class);
        if (! $service->supportsBalanceQuery($channel)) {
            return;
        }

        if (! $service->acquireOrderQueryThrottle((int) $channel->id)) {
            return;
        }

        try {
            $service->execute($channel, true);
        } catch (\Throwable $e) {
            if ($service->isUnsupportedBalanceQueryException($e) || $service->isBalanceQueryCooldownException($e)) {
                return;
            }
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'action' => '订单后触发渠道余额查询失败',
                'channel_id' => $this->channelId,
            ]);
        }
    }
}
