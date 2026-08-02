<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SelfChannelRefreshEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new Channel('system');
    }

    public function broadcastAs()
    {
        return 'self-channel-refresh';
    }

    public function broadcastWith()
    {
        return $this->data;
    }
}
