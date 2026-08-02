<?php

namespace App\Admin\Forms\TransferOrder;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Models\TransferOrder;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;

class OrderInfo extends Form implements LazyRenderable
{
    use LazyWidget;

    protected function authorize($user): bool
    {
        return Admin::user()->can('transfer-orders');
    }

    public function form()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $order = TransferOrder::query()->whereKey($id)->first([
            'id',
            'currency_id',
            'bank_name',
            'bank_id',
            'status',
            'success_time',
            'created_at',
            'channel_ordernumber',
            'ordernumber',
            'utr',
            'order_no',
            'holder_name',
            'card_no',
            'actual_amount',
            'amount',
        ]);

        $this->html(view('admin.transfer-order.order-info', ['order' => $order]))->width(12, 0);

        $this->disableResetButton();
        $this->disableSubmitButton();
    }

    public function default()
    {
        return [];
    }
}
