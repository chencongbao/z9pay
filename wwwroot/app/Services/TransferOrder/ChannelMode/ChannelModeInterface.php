<?php

namespace App\Services\TransferOrder\ChannelMode;

interface ChannelModeInterface
{
    public function handle($order, array $channels, $logService): ?array;
}
