<?php

namespace App\Services\DepositOrder\ChannelMode;

use Illuminate\Support\Facades\App;

class ModeLeastUsedOnceService extends AbstractChannelModeService
{
    protected function modeText(): string
    {
        return '按平均模式';
    }

    protected function needTouchAfter(): bool
    {
        return false;
    }

    protected function needRealName($order, array $channels): bool
    {
        if ($this->hasPayName($order)) {
            return false;
        }

        return $this->channelNeedRealName($channels);
    }

    protected function candidates($order, array $channels, $logService): array
    {
        if (empty($channels)) {
            $logService->excute($order->id, $this->modeText() . ' 候选渠道为空', '未匹配到可用渠道', 'error');
            return [];
        }

        [$channels, $picked] = App::make(DepositChannelPriorityService::class)
            ->pickLeastUsed($channels, $order->mid, $order->payment_id, $logService, $order->id);

        $logService->excute($order->id, $this->modeText() . ' 排序结果', [
            '候选渠道数量' => count($channels),
            '排序规则' => '最近使用序号小的先尝试；序号相同按商户通道ID、渠道ID排序',
            '候选渠道列表' => collect($channels)->map(fn($channel) => $this->channelLogData($channel, $order, [
                '最近使用序号' => $channel['auto_priority'] ?? 0,
            ]))->values()->all(),
        ], 'debug');

        if (empty($picked)) {
            return [];
        }

        $logService->excute($order->id, $this->modeText() . ' 选中渠道', [
            '选中渠道' => $this->channelLogData($picked, $order, [
                '最近使用序号' => $picked['auto_priority'] ?? 0,
            ]),
        ], 'debug');

        return $picked;
    }

    public function handle($order, array $channels, $logService): ?array
    {

        $channel = $this->candidates($order, $channels, $logService);

        if (empty($channel)) {
            $this->error = '未匹配到可用平均渠道';
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
