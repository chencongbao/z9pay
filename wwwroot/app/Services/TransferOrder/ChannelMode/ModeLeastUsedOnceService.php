<?php

namespace App\Services\TransferOrder\ChannelMode;

use Illuminate\Support\Facades\App;

class ModeLeastUsedOnceService extends AbstractChannelModeService
{
    protected function modeText(): string
    {
        return '按平均模式';
    }

    public function handle($order, array $channels, $logService): ?array
    {
        if (empty($channels)) {
            $this->error = '未匹配到可用平均渠道';
            $logService->excute($order->id, $this->modeText() . ' 候选渠道为空', '未匹配到可用渠道', 'error');
            return null;
        }

        [$channels, $result] = App::make(TransferChannelPriorityService::class)
            ->pickLeastUsed($channels, $order->mid, $logService, $order->id);

        $logService->excute($order->id, $this->modeText() . ' 排序结果', [
            '候选渠道数量' => count($channels),
            '排序规则' => '最近使用序号小的先尝试；序号相同按渠道ID排序',
            '候选渠道列表' => collect($channels)->filter(fn($channel) => is_array($channel))->map(fn($channel) => $this->channelLogData($channel, [
                '最近使用序号' => $channel['auto_priority'] ?? 0,
            ]))->values()->all(),
        ], 'debug');

        if (empty($result)) {
            $this->error = '未匹配到可用平均渠道';
            return null;
        }

        $logService->excute($order->id, $this->modeText() . ' 选中渠道', [
            '选中渠道' => $this->channelLogData($result, [
                '最近使用序号' => $result['auto_priority'] ?? 0,
            ]),
        ], 'debug');

        return $result;
    }
}
