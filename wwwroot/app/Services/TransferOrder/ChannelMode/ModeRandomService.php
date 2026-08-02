<?php

namespace App\Services\TransferOrder\ChannelMode;

use Illuminate\Support\Arr;

class ModeRandomService extends AbstractChannelModeService
{
    protected function modeText(): string
    {
        return '按随机模式';
    }

    public function handle($order, array $channels, $logService): ?array
    {
        if (empty($channels)) {
            $this->error = '未匹配到可用随机渠道';
            $logService->excute($order->id, $this->modeText() . ' 候选渠道为空', '未匹配到可用渠道', 'error');
            return null;
        }

        $result = Arr::random($channels);
        $logService->excute($order->id, $this->modeText() . ' 选中渠道', [
            '候选渠道数量' => count($channels),
            '候选渠道列表' => $this->channelListLogData($channels),
            '选中渠道' => $this->channelLogData($result),
        ], 'debug');

        return $result;
    }
}
