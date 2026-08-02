<?php

namespace App\Services\TransferOrder\ChannelMode;

use Illuminate\Support\Facades\Cache;

class ModeWeightService extends AbstractChannelModeService
{
    protected function modeText(): string
    {
        return '按权重模式';
    }

    public function handle($order, array $channels, $logService): ?array
    {
        if (empty($channels)) {
            $this->error = '未匹配到可用权重渠道';
            $logService->excute($order->id, $this->modeText() . ' 候选渠道为空', '未匹配到可用渠道', 'error');
            return null;
        }

        $list = array_values(array_filter($channels, function ($item) {
            return (int)($item['weight'] ?? 0) > 0;
        }));

        if (empty($list)) {
            $logService->excute($order->id, $this->modeText() . ' 权重配置异常', [
                '说明' => '所有候选渠道权重都小于等于0，系统将按平均模式选择渠道',
                '候选渠道数量' => count($channels),
                '候选渠道列表' => $this->channelListLogData($channels),
            ], 'error');

            return app(ModeLeastUsedOnceService::class)->handle($order, $channels, $logService);
        }

        $date = date('Y-m-d');
        $channelSignature = $this->channelSignature($list);
        $cacheKey = "transfer_channel_weight_day_{$order->mid}_{$date}_{$channelSignature}";
        $lockKey = "transfer_channel_weight_day_lock_{$order->mid}_{$date}_{$channelSignature}";
        $lock = Cache::lock($lockKey, 5);

        try {
            $lock->block(3);

            $stats = Cache::get($cacheKey, []);

            foreach ($list as $channel) {
                $channelKey = $this->channelKey($channel);
                if ($channelKey <= 0) {
                    continue;
                }

                if (!isset($stats[$channelKey])) {
                    $stats[$channelKey] = [
                        'count' => 0,
                        'weight' => (int)($channel['weight'] ?? 0),
                    ];
                } else {
                    $stats[$channelKey]['weight'] = (int)($channel['weight'] ?? 0);
                }
            }

            $channelKeys = array_map(fn($item) => $this->channelKey($item), $list);
            foreach ($stats as $channelKey => $item) {
                if (!in_array((int)$channelKey, $channelKeys, true)) {
                    unset($stats[$channelKey]);
                }
            }

            $selectedChannelKey = $this->pickByWeightGap($stats);

            $result = collect($list)->first(function ($item) use ($selectedChannelKey) {
                return $this->channelKey($item) === (int)$selectedChannelKey;
            });

            if (empty($result)) {
                $result = $list[0] ?? null;
                $selectedChannelKey = $this->channelKey($result ?: []);
            }

            if (empty($result)) {
                $this->error = '按权重模式未选中渠道';
                return null;
            }

            $stats[$selectedChannelKey]['count'] = (int)($stats[$selectedChannelKey]['count'] ?? 0) + 1;
            Cache::put($cacheKey, $stats, now()->endOfDay());

            $logService->excute($order->id, $this->modeText() . ' 分配结果', [
                '候选渠道数量' => count($list),
                '候选渠道签名' => $channelSignature,
                '选中渠道' => $this->channelLogData($result, [
                    '当日已分配次数' => $stats[$selectedChannelKey]['count'] ?? 0,
                ]),
                '当日权重统计' => $this->weightStatsLogData($stats, $list),
            ], 'debug');

            return $result;
        } finally {
            optional($lock)->release();
        }
    }

    protected function pickByWeightGap(array $stats): ?int
    {
        $stats = array_filter($stats, function ($item) {
            return (int)($item['weight'] ?? 0) > 0;
        });

        if (empty($stats)) {
            return null;
        }

        $totalWeight = array_sum(array_column($stats, 'weight'));
        $totalCount = array_sum(array_column($stats, 'count'));
        if ($totalWeight <= 0) {
            return null;
        }

        $selectedChannelId = null;
        $maxGap = null;

        foreach ($stats as $channelId => $item) {
            $weight = (int)($item['weight'] ?? 0);
            $currentCount = (int)($item['count'] ?? 0);

            if ($totalCount === 0) {
                $gap = $weight;
            } else {
                $targetCount = ($totalCount + 1) * ($weight / $totalWeight);
                $gap = $targetCount - $currentCount;
            }

            if ($selectedChannelId === null || $gap > $maxGap) {
                $selectedChannelId = (int)$channelId;
                $maxGap = $gap;
            }
        }

        return $selectedChannelId;
    }

    private function channelSignature(array $channels): string
    {
        $channelKeys = collect($channels)
            ->map(fn($channel) => $this->channelKey($channel))
            ->filter(fn($channelKey) => $channelKey > 0)
            ->sort()
            ->values()
            ->implode(',');

        return md5($channelKeys);
    }

    private function weightStatsLogData(array $stats, array $channels): array
    {
        $channelMap = collect($channels)->filter(fn($channel) => is_array($channel))->keyBy(fn($channel) => $this->channelKey($channel));

        return collect($stats)->map(function ($stat, $channelKey) use ($channelMap) {
            $channel = $channelMap->get((int)$channelKey, []);

            return [
                '统计ID' => (int)$channelKey,
                '渠道ID' => $channel['channel_id'] ?? 0,
                '商户通道ID' => $channel['merchant_channel_id'] ?? 0,
                '渠道名称' => $channel['channel_name'] ?? $channel['name'] ?? '-',
                '权重' => $stat['weight'] ?? 0,
                '当日已分配次数' => $stat['count'] ?? 0,
            ];
        })->values()->all();
    }

    private function channelKey(array $channel): int
    {
        return (int)($channel['merchant_channel_id'] ?? $channel['channel_id'] ?? 0);
    }
}
