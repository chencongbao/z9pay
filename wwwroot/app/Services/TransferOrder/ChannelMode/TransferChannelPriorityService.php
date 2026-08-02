<?php

namespace App\Services\TransferOrder\ChannelMode;

use Illuminate\Support\Facades\Cache;

class TransferChannelPriorityService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function pickLeastUsed(array $channels, $mid, $logService = null, $orderId = null): array
    {
        if (empty($channels) || empty($mid)) {
            return [[], []];
        }

        $seqKey = $this->seqKey($mid);
        $mapKey = $this->mapKey($mid);
        $lock = Cache::lock("lock:{$mapKey}", 3);

        try {
            $lock->block(2);

            $map = Cache::get($mapKey, []);
            $channels = $this->applyPriorityMap($channels, $map);
            $channels = $this->sortChannels($channels);
            $picked = $channels[0] ?? [];

            if (!empty($picked['channel_id'])) {
                $seq = (int)Cache::get($seqKey, 0) + 1;
                Cache::put($seqKey, $seq, now()->addSeconds(self::CACHE_TTL_SECONDS));

                $map[$picked['channel_id']] = $seq;
                Cache::put($mapKey, $map, now()->addSeconds(self::CACHE_TTL_SECONDS));
            }

            return [$channels, $picked];
        } catch (\Throwable $e) {
            $this->log($logService, $orderId, '代付平均模式 priority 选中异常', [
                '异常信息' => $e->getMessage(),
                'mid' => $mid,
            ], 'error');

            $channels = $this->sortChannels($this->applyPriorityMap($channels, []));

            return [$channels, $channels[0] ?? []];
        } finally {
            optional($lock)->release();
        }
    }

    public function touch(array $channel, $logService = null, $orderId = null): void
    {
        $mid = $channel['mid'] ?? null;
        $channelId = $channel['channel_id'] ?? null;

        if (!$mid || !$channelId) {
            $this->log($logService, $orderId, '代付平均模式 priority 参数缺失', [
                'mid' => $mid,
                'channel_id' => $channelId,
            ], 'error');
            return;
        }

        $seqKey = $this->seqKey($mid);
        $mapKey = $this->mapKey($mid);
        $lock = Cache::lock("lock:{$mapKey}", 3);

        try {
            $lock->block(2);

            $seq = (int)Cache::get($seqKey, 0) + 1;
            Cache::put($seqKey, $seq, now()->addSeconds(self::CACHE_TTL_SECONDS));

            $map = Cache::get($mapKey, []);
            $map[$channelId] = $seq;
            Cache::put($mapKey, $map, now()->addSeconds(self::CACHE_TTL_SECONDS));
        } catch (\Throwable $e) {
            $this->log($logService, $orderId, '代付平均模式 priority 更新异常', [
                '异常信息' => $e->getMessage(),
                'mid' => $mid,
                'channel_id' => $channelId,
            ], 'error');
        } finally {
            optional($lock)->release();
        }
    }

    public function attachPriority(array $channels, $mid, $logService = null, $orderId = null): array
    {
        if (empty($channels) || empty($mid)) {
            return $channels;
        }

        return $this->applyPriorityMap($channels, Cache::get($this->mapKey($mid), []));
    }

    private function applyPriorityMap(array $channels, array $map): array
    {
        foreach ($channels as &$channel) {
            $channelId = $channel['channel_id'] ?? 0;
            $channel['auto_priority'] = $map[$channelId] ?? 0;
        }

        unset($channel);

        return $channels;
    }

    private function sortChannels(array $channels): array
    {
        usort($channels, function ($left, $right) {
            $priorityCompare = (int)($left['auto_priority'] ?? 0) <=> (int)($right['auto_priority'] ?? 0);
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return (int)($left['channel_id'] ?? 0) <=> (int)($right['channel_id'] ?? 0);
        });

        return $channels;
    }

    private function seqKey($mid): string
    {
        return "transfer_channel_priority_seq:{$mid}";
    }

    private function mapKey($mid): string
    {
        return "transfer_channel_priority_map:{$mid}";
    }

    private function log($logService, $orderId, string $title, $content = [], string $type = 'debug'): void
    {
        if ($logService && $orderId && method_exists($logService, 'excute')) {
            $logService->excute($orderId, $title, $content, $type);
        }
    }
}
