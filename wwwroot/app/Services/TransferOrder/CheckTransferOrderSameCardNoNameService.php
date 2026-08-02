<?php

namespace App\Services\TransferOrder;

use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\Cache;

class CheckTransferOrderSameCardNoNameService
{
    use ServiceTraits;

    public function excute($data = [])
    {
        if (!intval(bob_admin_setting("base_transfer_same_card_name_switch"))) {
            return false;
        }

        $limit = intval(bob_admin_setting("base_transfer_same_card_name_number"));
        if ($limit <= 0) {
            return false;
        }

        if (!$this->hasRequiredData($data)) {
            return false;
        }

        $ttlMinutes = max(1, floatval(bob_admin_setting("base_transfer_same_card_name_time")));
        $key = $this->cacheKey($data);

        Cache::add($key, 0, now()->addMinutes($ttlMinutes));
        $number = Cache::increment($key);

        return $number > $limit;
    }

    private function hasRequiredData(array $data): bool
    {
        return !empty($data['mid']) && !empty($data['holder_name']) && !empty($data['card_no']);
    }

    private function cacheKey(array $data): string
    {
        $mid = $data['mid'] ?? 0;
        $holderName = $this->normalizeHolderName($data['holder_name'] ?? '');
        $cardNo = trim((string)($data['card_no'] ?? ''));

        return 'transfer:same_card_name:' . $mid . ':' . md5($holderName . '|' . $cardNo);
    }

    private function normalizeHolderName($holderName): string
    {
        $holderName = strtolower(trim((string)$holderName));

        return preg_replace('/\s+/', ' ', $holderName);
    }
}
