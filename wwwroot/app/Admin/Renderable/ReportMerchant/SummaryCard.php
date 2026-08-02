<?php

namespace App\Admin\Renderable\ReportMerchant;

use App\Admin\Metrics\Admin\CommonCard;
use App\Models\MerchantInfo;
use App\Models\ReportMerchant;
use Dcat\Admin\Support\LazyRenderable;

class SummaryCard extends LazyRenderable
{
    public function render()
    {
        $sourceId = intval($this->source_id ?? 1);

        $title = match ($sourceId) {
            2 => '代付成功出款金额',
            3 => '成功结算金额',
            default => '代收成功入账金额',
        };

        $field = match ($sourceId) {
            2 => 'transfer_order_total_amount',
            3 => 'settlement_order_total_amount',
            default => 'deposit_order_total_amount',
        };

        return new CommonCard($title, $this->buildQuery()->sum($field));
    }

    protected function buildQuery()
    {
        $query = ReportMerchant::query();

        $mid = intval($this->mid ?? 0);
        if ($mid > 0) {
            $query->where('mid', $mid);
        }

        $dateRange = $this->date_add ?? [];
        $start = is_array($dateRange) ? ($dateRange['start'] ?? null) : null;
        $end = is_array($dateRange) ? ($dateRange['end'] ?? null) : null;

        if ($start) {
            $query->where('date_add', '>=', $start);
        }

        if ($end) {
            $query->where('date_add', '<=', $end);
        }

        $currencyId = intval($this->cid ?? 0);
        if ($currencyId > 0) {
            $query->whereIn('mid', MerchantInfo::where('currency_id', $currencyId)->pluck('merchant_user_id'));
        }

        return $query;
    }
}
