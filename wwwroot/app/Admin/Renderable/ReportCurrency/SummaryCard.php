<?php

namespace App\Admin\Renderable\ReportCurrency;

use App\Admin\Metrics\Admin\CommonCard;
use App\Models\ReportCurrency;
use App\Models\ReportCurrencyMerchant;
use Dcat\Admin\Support\LazyRenderable;

class SummaryCard extends LazyRenderable
{
    public function render()
    {
        $sourceId = intval($this->source_id ?? 1);

        $title = match ($sourceId) {
            2 => '代付跑量',
            3 => '结算跑量',
            default => '代收跑量',
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
        $mid = intval($this->mid ?? 0);
        $query = $mid > 0 ? ReportCurrencyMerchant::query() : ReportCurrency::query();

        $currencyId = intval($this->cid ?? 0);
        if ($currencyId > 0) {
            $query->where('cid', $currencyId);
        }

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

        return $query;
    }
}
