<?php

namespace App\Admin\Renderable\ReportPayment;

use App\Admin\Metrics\Admin\CommonCard;
use App\Models\ReportPayment;
use App\Models\ReportPaymentMerchant;
use Dcat\Admin\Support\LazyRenderable;

class SummaryCard extends LazyRenderable
{
    public function render()
    {
        return new CommonCard('代收跑量', $this->buildQuery()->sum('deposit_order_total_amount'));
    }

    protected function buildQuery()
    {
        $mid = intval($this->mid ?? 0);
        $query = $mid > 0 ? ReportPaymentMerchant::query() : ReportPayment::query();

        $paymentId = intval($this->pid ?? 0);
        if ($paymentId > 0) {
            $query->where('pid', $paymentId);
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
