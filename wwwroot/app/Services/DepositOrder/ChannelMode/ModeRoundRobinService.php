<?php

namespace App\Services\DepositOrder\ChannelMode;

use Illuminate\Support\Facades\App;

class ModeRoundRobinService extends AbstractChannelModeService
{
    protected function modeText(): string
    {
        return '按平均轮询模式';
    }

    protected function needRealName($order, array $channels): bool
    {
        if ($this->hasPayName($order)) {
            return false;
        }

        return $this->anyChannelNeedRealName($channels);
    }

    protected function needTouchAfter(): bool
    {
        return true;
    }

    protected function candidates($order, array $channels, $logService): array
    {
        if (empty($channels)) {
            $logService->excute($order->id, $this->modeText() . ' 候选渠道为空', '未匹配到可用渠道', 'error');
            return [];
        }

        $channels = App::make(DepositChannelPriorityService::class)->attachPriority($channels, $order->mid, $order->payment_id, $logService, $order->id);

        usort($channels, function ($left, $right) {
            $priorityCompare = (int)($left['auto_priority'] ?? 0) <=> (int)($right['auto_priority'] ?? 0);
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            $merchantChannelCompare = (int)($left['merchant_channel_id'] ?? 0) <=> (int)($right['merchant_channel_id'] ?? 0);
            if ($merchantChannelCompare !== 0) {
                return $merchantChannelCompare;
            }

            return (int)($left['channel_id'] ?? 0) <=> (int)($right['channel_id'] ?? 0);
        });

        $logService->excute($order->id, $this->modeText() . ' 排序结果', [
            '候选渠道数量' => count($channels),
            '排序规则' => '最近使用序号小的先尝试；序号相同按商户通道ID、渠道ID排序',
            '候选渠道列表' => collect($channels)->map(fn($channel) => $this->channelLogData($channel, $order, [
                '最近使用序号' => $channel['auto_priority'] ?? 0,
            ]))->values()->all(),
        ], 'debug');

        return $channels;
    }

    public function handle($order, array $channels, $logService): ?array
    {

        $list = $this->candidates($order, $channels, $logService);

        if (empty($list)) {
            $this->error = '未匹配到可用轮询渠道';
            return null;
        }

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
                '最近使用序号' => $ch['auto_priority'] ?? 0,
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
