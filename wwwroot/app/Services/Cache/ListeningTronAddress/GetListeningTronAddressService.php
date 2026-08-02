<?php

namespace App\Services\Cache\ListeningTronAddress;

use App\Traits\ServiceTraits;
use App\Models\ListeningTronAddress;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class GetListeningTronAddressService
{
    use ServiceTraits;

    public function excute($force = false): array
    {
        if ($force) {
            return $this->update();
        }

        return Cache::rememberForever($this->cacheKey(), fn () => $this->queryListeningAddresses());
    }

    public function addressMap($force = false): array
    {
        return collect($this->excute($force))->keyBy('address')->all();
    }

    public function chatIdsByAddress(string $address, $force = false): array
    {
        $item = $this->addressMap($force)[$address] ?? null;
        if (empty($item)) {
            return [];
        }

        return array_values(array_filter([(string) ($item['chat_id'] ?? '')]));
    }

    private function update(): array
    {
        Cache::forget($this->cacheKey());
        $data = $this->queryListeningAddresses();
        Cache::forever($this->cacheKey(), $data);

        return $data;
    }

    private function queryListeningAddresses(): array
    {
        return ListeningTronAddress::query()
            ->select(['address', 'chat_id'])
            ->get()
            ->map(fn ($item) => ['address' => $item->address, 'chat_id' => $item->chat_id])
            ->all();
    }

    private function cacheKey(): string
    {
        return CacheConstPrefixService::LISTENING_TRON_ADDRESS_LIST;
    }
}
