<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DepositOrder\ChannelMode\AbstractChannelModeService;

class SelfChannelDispatchLockTest extends TestCase
{
    public function test_self_channel_uses_one_lock_across_payment_types(): void
    {
        $service = new class extends AbstractChannelModeService {
            public function handle($order, array $channels, $logService): ?array
            {
                return null;
            }

            protected function candidates($order, array $channels, $logService): array
            {
                return [];
            }

            protected function modeText(): string
            {
                return 'test';
            }

            public function lockKey(): string
            {
                return $this->selfChannelDispatchLockKey();
            }
        };

        $this->assertSame('self_channel_deposit_dispatch', $service->lockKey());
    }
}
