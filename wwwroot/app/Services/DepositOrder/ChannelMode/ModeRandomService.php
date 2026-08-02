<?php

namespace App\Services\DepositOrder\ChannelMode;

use Illuminate\Support\Arr;

class ModeRandomService extends AbstractChannelModeService
{
    protected function modeText(): string
    {
        return '按随机模式';
    }

    protected function candidates($order, array $channels, $logService): array
    {
        if (empty($channels)) {
            $logService->excute($order->id, $this->modeText() . ' 候选渠道为空', '未匹配到可用渠道', 'error');
            return [];
        }

        $picked = Arr::random($channels);

        $logService->excute($order->id, $this->modeText() . ' 选中渠道', [
            '候选渠道数量' => count($channels),
            '选中渠道' => $this->channelLogData($picked, $order),
        ], 'debug');

        return $picked;
    }

    protected function needRealName($order, array $channels): bool
    {
        if ($this->hasPayName($order)) {
            return false;
        }

        return $this->channelNeedRealName($channels);
    }

    public function handle($order, array $channels, $logService): ?array
    {

        $channel = $this->candidates($order, $channels, $logService);

        if (empty($channel)) {
            $this->error = '未匹配到可用随机渠道';
            return null;
        }

        if ($this->needRealName($order, $channel)) {
            $logService->excute($order->id, $this->modeText() . '， 要求实名但未填写', "返回系统收营台，填写付款人姓名", 'debug');

            return [
                'channel_info' => $channel,
                'status' => 1,
            ];
        }


        $logService->excute($order->id, $this->modeText() . ' 准备尝试渠道', $this->channelLogData($channel, $order), 'debug');

        $row = $this->callChannel($order, $channel, $logService);

        if (!empty($row)) {
            return $row;
        }

        $logService->excute($order->id, $this->modeText() . ' 全部渠道尝试完毕', "未匹配到合适的渠道", 'error');

        return null;
    }
}
