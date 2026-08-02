<?php

namespace App\Services\DepositOrder\ChannelMode;

interface ChannelModeInterface
{
    public function handle($order, array $channels, $logService): ?array;
}
