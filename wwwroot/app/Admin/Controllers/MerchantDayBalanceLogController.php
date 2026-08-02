<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Grid\LazyRenderable;
use App\Models\MerchantDayBalanceLog;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class MerchantDayBalanceLogController extends LazyRenderable
{
    public function grid(): Grid
    {
        $mid = (int) request('mid', 0);
        $merchantBaseInfoService = app(CacheMerchantBaseInfoService::class);
        $query = MerchantDayBalanceLog::query()
            ->select(['id', 'mid', 'date_add', 'balance_amount', 'usdt_balance_amount', 'created_at'])
            ->orderByDesc('date_add')
            ->orderByDesc('id');

        return Grid::make($query, function (Grid $grid) use ($mid, $merchantBaseInfoService) {
            if ($mid > 0) {
                $grid->model()->where('mid', $mid);
            }

            $grid->column('id', '编号')->sortable();
            $grid->column('mid', '商户ID')->center();
            $grid->column('merchant_name', '所属商户')->display(function ()use($merchantBaseInfoService) {
                static $merchantNames = [];
                $mid = (int) $this->mid;
                if ($mid <= 0) {
                    return '';
                }

                if (!array_key_exists($mid, $merchantNames)) {
                    $merchantInfo = $merchantBaseInfoService->excute($mid);
                    $merchantNames[$mid] = $merchantInfo['bname'] ?? '';
                }

                return $merchantNames[$mid];
            });
            $grid->column('date_add', '日切日期')->center();
            $grid->column('balance_amount', '日切余额')->amount()->center();
            $grid->column('usdt_balance_amount', 'USDT日切余额')->display(function ($value) {
                return floatval($value);
            })->center();
            $grid->column('created_at', '创建时间')->center();
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableRowSelector();
            $grid->disableFilterButton();
        });
    }
}
