<?php

namespace App\Services\DepositOrder\ChannelMode;

class ModePriorityService extends AbstractChannelModeService
{
    protected function modeText(): string
    {
        return '按优先级模式';
    }

    protected function candidates($order, array $channels, $logService): array
    {
        usort($channels, function ($left, $right) {
            $priorityCompare = (int)($left['priority'] ?? 0) <=> (int)($right['priority'] ?? 0);
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            $merchantChannelCompare = (int)($right['merchant_channel_id'] ?? 0) <=> (int)($left['merchant_channel_id'] ?? 0);
            if ($merchantChannelCompare !== 0) {
                return $merchantChannelCompare;
            }

            return (int)($left['channel_id'] ?? 0) <=> (int)($right['channel_id'] ?? 0);
        });

        $logService->excute($order->id, $this->modeText() . ' 候选渠道', [
            '候选渠道数量' => count($channels),
            '排序规则' => '优先级小的先尝试；优先级相同按商户通道ID降序、渠道ID升序排序',
            '候选渠道列表' => $this->channelListLogData($channels, $order),
        ], 'debug');

        return $channels;
    }

    protected function needRealName($order, array $channels): bool
    {
        if ($this->hasPayName($order)) {
            return false;
        }

        return $this->anyChannelNeedRealName($channels);
    }

    public function handle($order, array $channels, $logService): ?array
    {

        $list = $this->candidates($order, $channels, $logService);

        if ($this->needRealName($order, $list)) {
            $logService->excute($order->id, $this->modeText() . '， 要求实名但未填写', "返回系统收营台，填写付款人姓名", 'debug');

            return [
                'channel_info' => $channels,
                'status' => 1,
            ];
        }

        $total = count($list);
        foreach ($list as $idx => $ch) {

            $logService->excute($order->id, $this->modeText() . ' 准备尝试渠道', $this->channelLogData($ch, $order, [
                '尝试顺序' => ($idx + 1) . '/' . $total,
            ]), 'debug');

            $row = $this->callChannel($order, $ch, $logService);

            if (!empty($row)) {
                return $row;
            }
        }

        $logService->excute($order->id, $this->modeText() . ' 全部渠道尝试完毕', "未匹配到合适的渠道", 'error');
        return null;
    }


}
