<?php

namespace App\Services\TransferOrder\ChannelMode;

abstract class AbstractChannelModeService implements ChannelModeInterface
{
    protected $error = null;

    public function getError()
    {
        if (is_array($this->error)) {
            return json_encode($this->error, JSON_UNESCAPED_UNICODE);
        }

        return $this->error;
    }

    protected function channelLogData(array $channel, array $extra = []): array
    {
        return array_merge([
            '渠道ID' => $channel['channel_id'] ?? 0,
            '商户通道ID' => $channel['merchant_channel_id'] ?? 0,
            '渠道名称' => $channel['channel_name'] ?? $channel['name'] ?? '',
            '权重' => $channel['weight'] ?? 0,
            '代付额外手续费' => $channel['merchant_extra_fee'] ?? 0,
            '代付单笔下限' => $channel['collection_min_amount'] ?? 0,
            '代付单笔上限' => $channel['collection_max_amount'] ?? 0,
        ], $extra);
    }

    protected function channelListLogData(array $channels): array
    {
        return collect($channels)
            ->filter(fn($channel) => is_array($channel))
            ->map(fn($channel) => $this->channelLogData($channel))
            ->values()
            ->all();
    }

    abstract protected function modeText(): string;
}
