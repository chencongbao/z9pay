<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\MerchantInfo;
use App\Models\MerchantChannel;

class MerchantInfoMerchantChannelTable extends Grid\LazyRenderable
{
    public function grid(): Grid
    {
        $currencyOptions = collect(config('default.currency', []))->pluck('name', 'id');
        $paymentOptions = collect(config('payment', []))->mapWithKeys(function ($item) {
            return [$item['id'] => ($item['name'] ?? '') . '【' . ($item['code'] ?? '') . '】'];
        });
        $query = MerchantInfo::query()->select(['merchant_user_id', 'name', 'coder', 'currency_id', 'telegram_group_id']);

        return Grid::make($query, function (Grid $grid) use ($currencyOptions, $paymentOptions) {
            $grid->column('id')->display(function () {
                return $this->merchant_user_id;
            });
            $grid->column('name', '商户名称');
            $grid->column('coder', '商户代码');
            $grid->disableActions();
            $grid->showBatchActions();
            $grid->showRowSelector();
            $grid->disablePagination();
            $grid->filter(function (Grid\Filter $filter) use ($currencyOptions, $paymentOptions) {
                $filter->panel();
                $filter->expand();
                $filter->like('name', '商户名称')->width(6);
                $filter->like('coder', '商户代码')->width(6);
                $filter->equal('currency_id', '选择币种')->select($currencyOptions)->width(6);
                $filter->where('payment_id', function ($query) {
                    $query->whereIn('merchant_user_id', MerchantChannel::query()->select('merchant_user_id')->where('payment_id', $this->input));
                }, '支付编码')->select($paymentOptions)->width(6);
            });
        });
    }
}
