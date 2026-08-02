<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use App\Models\FreezeOrder;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\FreezeOrder\Card1;
use App\Admin\Metrics\Admin\FreezeOrder\Card2;
use App\Admin\Actions\Grid\FreezeOrder\ExportData;
use App\Admin\Actions\Grid\FreezeOrder\UnFreezeOrder;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class FreezeOrderController extends CommonController
{
    protected $disableEdit = true;
    protected $disableCreate = true;

    protected function grid(): Grid
    {
        $createdAt = request('created_at');
        $beginDate = $createdAt['start'] ?? '';
        $endDate = $createdAt['end'] ?? '';

        $currencyMap = collect(config('default.currency'))->keyBy('id');
        $freezeStatus = config('default.freeze_status', []);
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $query = FreezeOrder::query()
            ->select(['id', 'mid', 'user_id', 'status', 'amount', 'remark', 'created_at', 'unfreeze_time', 'deposit_order_id'])
            ->with([
                'merchant_info' => function ($query) {
                    $query->select('merchant_user_id', 'currency_id', 'name', 'coder');
                },
                'user' => function ($query) {
                    $query->select('id', 'name');
                },
                'deposit_order' => function ($query) {
                    $query->select('id', 'currency_id', 'order_no', 'ordernumber', 'actual_amount');
                },
            ]);

        return Grid::make($query, function (Grid $grid) use ($beginDate, $endDate, $currencyMap, $freezeStatus, $merchantBaseInfoService) {

            $canUnfreeze = Admin::user()->can('freeze-order-unfreeze');

            $grid->column('merchant_info.bname', '商户');
            $grid->column('currency_id', '币种')->display(function () use ($currencyMap) {
                return optional($currencyMap->get($this->deposit_order->currency_id ?? null))->offsetGet('name');
            });
            $grid->column('deposit_order.order_no', '商户订单号');
            $grid->column('deposit_order.ordernumber', '平台订单号');
            $grid->column('status', '冻结状态')->display(function () use ($freezeStatus) {
                return $freezeStatus[$this->status] ?? '';
            })->dot([Admin::color()->orange(), Admin::color()->green()]);
            $grid->column('deposit_order.actual_amount', '充值金额')->display(function ($value) {
                return bob_unit_format($value);
            });
            $grid->column('amount', '冻结金额')->display(function ($value) {
                return bob_unit_format($value);
            });
            $grid->column('userinfo', '金主')->display(function () {
                if ($this->user_id > 0) {
                    return optional($this->user)->name . '(#' . $this->user_id . ')';
                }

                return '';
            });
            $grid->column('created_at', '冻结时间');
            $grid->column('unfreeze_time')->display(function () {
                if ($this->unfreeze_time > 0) {
                    return date('Y-m-d H:i:s', $this->unfreeze_time);
                }

                return '';
            });
            $grid->column('remark');
            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });
            $grid->actions(function ($actions) use ($canUnfreeze) {
                if ($canUnfreeze && (int) $actions->row['status'] === 1) {
                    $actions->append(new UnFreezeOrder());
                }
            });
            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();

                $row->column(6, new Card1(request()->all(), $beginDate, $endDate));
                $row->column(6, new Card2(request()->all(), $beginDate, $endDate));

                return $row;
            });
            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $freezeStatus, $merchantBaseInfoService) {
                $filter->expand();
                $filter->panel();
                $filter->equal('mid', '商户')->select(function ($mid) use ($merchantBaseInfoService) {
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);
                        if (!empty($result)) {
                            return [$result['merchant_user_id'] => $result['bname']];
                        }
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(3);
                $filter->where('freeze_ordernumber', function ($query) {
                    $query->whereHas('deposit_order', function ($query) {
                        $query->where('ordernumber', $this->input);
                    });
                }, '平台订单号')->width(3);
                $filter->where('freeze_order_no', function ($query) {
                    $query->whereHas('deposit_order', function ($query) {
                        $query->where('order_no', $this->input);
                    });
                }, '商户订单号')->width(3);
                $filter->between('created_at', '冻结时间')->datetime()->width(3);
                $filter->equal('status', '订单状态')->select($freezeStatus)->width(3);
                $filter->whereBetween('unfreeze_time', function ($query) {
                    $start = $this->input['start'] ?? null;
                    $end = $this->input['end'] ?? null;
                    if ($start !== null) {
                        $query->where('unfreeze_time', '>=', strtotime($start));
                    }
                    if ($end !== null) {
                        $query->where('unfreeze_time', '<=', strtotime($end));
                    }
                }, '解冻时间')->datetime()->width(3);
            });
        });
    }
}
