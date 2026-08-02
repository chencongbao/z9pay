<?php

namespace App\Admin\Controllers\Merchant;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\MerchantDayBalanceLog;
use App\Admin\Extensions\Layout\LeftSide;
use App\Admin\Controllers\CommonController;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class DayBalanceLogController extends CommonController
{

    public function title()
    {
        return "商户日切";
    }

    public function grid()
    {
        $mid = (int) request()->input('mid', 0);
        $merchantBaseInfoService = app(CacheMerchantBaseInfoService::class);
        $merchantNames = [];
        $merchantList = array_values(array_filter(app(GetMerchantListInfoService::class)->excute(), function ($item) {
            return (int) ($item['status'] ?? 0) === 1;
        }));

        $query = MerchantDayBalanceLog::query()
            ->select(['id', 'mid', 'date_add', 'balance_amount', 'usdt_balance_amount', 'created_at'])
            ->orderByDesc('date_add')
            ->orderByDesc('id');

        return Grid::make($query, function (Grid $grid) use ($mid, $merchantBaseInfoService, &$merchantNames, $merchantList) {
            $grid->model()->when($mid > 0, function ($query) use ($mid) {
                $query->where('mid', $mid);
            });

            if ($mid > 0) {
                $merchantInfo = $merchantBaseInfoService->excute($mid);
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . optional($merchantInfo)->offsetGet('bname') . '</button>');
            }

            $grid->column('id', '编号')->sortable();
            $grid->column('mid', '商户ID')->center();
            $grid->column('merchant_name', '所属商户')->display(function () use ($merchantBaseInfoService, &$merchantNames) {
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

            $grid->filter(function (Grid\Filter $filter) use ($merchantBaseInfoService) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(4);
                $filter->between('date_add', '日切日期')->date()->width(4);
                $filter->equal('mid', '商户')->select(function ($merchantId) use ($merchantBaseInfoService) {
                    if ($merchantId) {
                        $merchantInfo = $merchantBaseInfoService->excute($merchantId);
                        if (!empty($merchantInfo)) {
                            return [$merchantInfo['merchant_user_id'] => $merchantInfo['bname']];
                        }
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(4);
            });

            $grid->wrap(function (Renderable $view) use ($mid, $merchantList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($mid, $merchantList) {
                    $left = new LeftSide();
                    $left->title('商户列表')->field('mid')->default($mid)->prependAll('全部商户')->data($merchantList);
                    $column->row($left);
                });
                $row->column(10, function (Column $column) use ($view) {
                    $card = Card::make($view);
                    $card->padding('15px');
                    $column->row($card);
                });

                return $row->render();
            });
        });
    }
}
