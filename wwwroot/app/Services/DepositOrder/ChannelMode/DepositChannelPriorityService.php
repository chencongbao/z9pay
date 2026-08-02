<?php

namespace App\Services\DepositOrder\ChannelMode;

use Illuminate\Support\Facades\Cache;

class DepositChannelPriorityService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function pickLeastUsed(array $channels, $mid, $paymentId, $logService = null, $orderId = null): array
    {
        if (empty($channels) || empty($mid) || empty($paymentId)) {
            return [[], []];
        }

        $signature = $this->channelSignature($channels);
        $seqKey = $this->signedSeqKey($mid, $paymentId, $signature);
        $mapKey = $this->signedMapKey($mid, $paymentId, $signature);
        $lock = Cache::lock("lock:{$mapKey}", 3);

        try {
            $lock->block(2);

            $map = Cache::get($mapKey, []);
            $channels = $this->attachMapPriority($channels, $map);
            $channels = $this->sortChannels($channels);
            $picked = $channels[0] ?? [];

            if (!empty($picked)) {
                $seq = (int)Cache::get($seqKey, 0) + 1;
                Cache::put($seqKey, $seq, now()->addSeconds(self::CACHE_TTL_SECONDS));

                $map[$this->channelKey($picked)] = $seq;
                Cache::put($mapKey, $map, now()->addSeconds(self::CACHE_TTL_SECONDS));
            }

            return [$channels, $picked];
        } catch (\Throwable $e) {
            $this->log($logService, $orderId, '代收平均模式 priority 选中异常', [
                '异常信息' => $e->getMessage(),
                'mid' => $mid,
                'payment_id' => $paymentId,
            ], 'error');

            $channels = $this->sortChannels($this->attachMapPriority($channels, []));

            return [$channels, $channels[0] ?? []];
        } finally {
            optional($lock)->release();
        }
    }

    public function touch(array $channel, $logService = null, $orderId = null): void
    {
        $mid       = $channel['mid'] ?? null;
        $paymentId = $channel['payment_id'] ?? null;
        $channelKey = $this->channelKey($channel);

        if (!$mid || !$paymentId || !$channelKey) {
            $this->log($logService, $orderId, 'LRU touch 参数缺失', [
                'mid' => $mid, 'payment_id' => $paymentId, 'channel_key' => $channelKey
            ], 'error');
            return;
        }

        $seqKey   = $this->seqKey($mid, $paymentId);
        $mapKey   = $this->mapKey($mid, $paymentId);
        $lockKey  = "lock:{$mapKey}";

        $lock = Cache::lock($lockKey, 3);

        try {
            $lock->block(2);

            $oldSeq = (int)Cache::get($seqKey, 0);
            $seq    = $oldSeq + 1;
            Cache::put($seqKey, $seq, now()->addSeconds(self::CACHE_TTL_SECONDS));

            $map = Cache::get($mapKey, []);

            $map[$channelKey] = $seq;
            Cache::put($mapKey, $map, now()->addSeconds(self::CACHE_TTL_SECONDS));

        } catch (\Throwable $e) {
            $this->log($logService, $orderId, 'LRU touch 异常', [
                'error' => $e->getMessage(),
                'mid' => $mid,
                'payment_id' => $paymentId,
                'channel_key' => $channelKey,
            ], 'error');
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * 给渠道数组批量注入 auto_priority（用于排序：小=久未用）
     */
    public function attachPriority(array $channels, $mid, $paymentId, $logService = null, $orderId = null): array
    {
        $mapKey = $this->mapKey($mid, $paymentId);
        $map    = Cache::get($mapKey, []);

        return $this->attachMapPriority($channels, $map);
    }

    protected function seqKey($mid, $paymentId): string
    {
        return "deposit_channel_priority_seq:{$mid}:{$paymentId}";
    }

    protected function mapKey($mid, $paymentId): string
    {
        return "deposit_channel_priority_map:{$mid}:{$paymentId}";
    }

    private function signedSeqKey($mid, $paymentId, string $signature): string
    {
        return "deposit_channel_priority_seq:{$mid}:{$paymentId}:{$signature}";
    }

    private function signedMapKey($mid, $paymentId, string $signature): string
    {
        return "deposit_channel_priority_map:{$mid}:{$paymentId}:{$signature}";
    }

    private function attachMapPriority(array $channels, array $map): array
    {
        foreach ($channels as &$ch) {
            $ch['auto_priority'] = $map[$this->channelKey($ch)] ?? 0;
        }

        unset($ch);

        return $channels;
    }

    private function sortChannels(array $channels): array
    {
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

        return $channels;
    }

    private function channelKey(array $channel): int
    {
        return (int)($channel['merchant_channel_id'] ?? $channel['channel_id'] ?? 0);
    }

    private function channelSignature(array $channels): string
    {
        $keys = collect($channels)
            ->filter(fn($channel) => is_array($channel))
            ->map(fn($channel) => $this->channelKey($channel))
            ->filter(fn($channelKey) => $channelKey > 0)
            ->sort()
            ->values()
            ->implode(',');

        return md5($keys);
    }

    protected function log($logService, $orderId, string $title, $data = [], string $level = 'debug'): void
    {
        if (!empty($logService) && !empty($orderId)) {
            $logService->excute($orderId, $title, $data, $level);
        }
    }
}
