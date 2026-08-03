<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Tab;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use Illuminate\Support\Facades\App;
use App\Models\ReportCurrencyMerchant;
use App\Admin\Metrics\Admin\CommonCard;
use App\Admin\Extensions\Layout\LeftSide;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ReportCurrencyMerchantController extends CommonController
{
    public $title = '商户报表';

    protected $disableCreate = true;

    protected $disableEdit = true;

    public $source_id = 1;

    protected function grid(): Grid
    {
        $mid = (int) request('mid', 0);
        if ($mid <= 0) {
            request()->query->remove('mid');
            request()->request->remove('mid');
        }
        $sourceId = (int) request('source_id', 1);
        $sourceId = in_array($sourceId, [1, 2, 3], true) ? $sourceId : 1;
        $this->source_id = $sourceId;
        $currencyList = collect(config('default.currency', []))->map(function ($item) {
            $item['bname'] = '【#' . $item['id'] . '】' . $item['name'];

            return $item;
        });
        $currencyOptions = $currencyList->pluck('bname', 'id');
        $currencyId = (int) request('cid', 0);
        if ($currencyId <= 0) {
            $firstCurrency = $currencyList->first();
            if (!empty($firstCurrency)) {
                $currencyId = (int) ($firstCurrency['id'] ?? 0);
            }
        }
        if (request('cid') === null && $currencyId > 0) {
            request()->merge(['cid' => $currencyId]);
        }

        $query = ReportCurrencyMerchant::query()
            ->select($this->reportColumns($sourceId))
            ->orderByDesc('date_add')
            ->orderByDesc('id')
            ->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'currency_id', 'name', 'coder']);
            }]);
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $currentCurrency = $currencyList->firstWhere('id', $currencyId);

        return Grid::make($query, function (Grid $grid) use ($mid, $sourceId, $currencyId, $currencyList, $currencyOptions, $currentCurrency, $merchantBaseInfoService) {
            $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . data_get($currentCurrency, 'bname', '') . '</button>');
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', '日期')->center();
            if ($sourceId === 1) {
                $grid->column('deposit_order_number_total', '代收单数');
                $grid->column('deposit_order_number_success', '代收成功单数');
                $grid->column('deposit_order_number_fail', '代收失败单数');
                $grid->column('deposit_order_number_overtime', '代收超时单数');
                $grid->column('deposit_order_number_swiping', '代收刷单单数');
                $grid->column('deposit_order_success_rate', '代收成功率');
                $grid->column('deposit_order_total_amount', '代收跑量')->amount();
                $grid->column('deposit_order_total_fee', '代收商户手续费')->amount();
                $grid->column('deposit_profit', '代收利润')->amount();
            }
            if ($sourceId === 2) {
                $grid->column('transfer_order_number_total', '代付单数');
                $grid->column('transfer_order_number_success', '代付成功单数');
                $grid->column('transfer_order_number_fail', '代付失败单数');
                $grid->column('transfer_order_success_rate', '代付成功率');
                $grid->column('transfer_order_total_amount', '代付跑量')->amount();
                $grid->column('transfer_order_total_fee', '代付商户手续费')->amount();
                $grid->column('transfer_profit', '代付利润')->amount();
            }
            if ($sourceId === 3) {
                $grid->column('settlement_order_number_total', '结算单数');
                $grid->column('settlement_order_number_success', '结算成功单数');
                $grid->column('settlement_order_number_fail', '结算失败单数');
                $grid->column('settlement_order_success_rate', '结算成功率');
                $grid->column('settlement_order_total_amount', '结算跑量')->amount();
                $grid->column('settlement_order_total_fee', '结算商户手续费')->amount();
                $grid->column('settlement_profit', '结算利润')->amount();
            }
            $grid->column('merchant_info.bname', '所属商户');
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->filter(function (Grid\Filter $filter) use ($currencyId, $currencyOptions, $merchantBaseInfoService) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
                $filter->equal('cid', '请选择币种')->select($currencyOptions)->width(3)->default($currencyId);
                $filter->equal('mid', '商户')->select(function ($mid) use ($merchantBaseInfoService) {
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);
                        if (!empty($result)) {
                            return [$result['merchant_user_id'] => $result['bname']];
                        }
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(3);
            });

            $grid->header(function () use ($mid, $sourceId, $currencyId) {
                $tab = Tab::make();
                $tab->addLink('代收', admin_route('report-currency-merchants.index', $this->tabQuery(1, $currencyId, $mid)), $sourceId === 1);
                $tab->addLink('代付', admin_route('report-currency-merchants.index', $this->tabQuery(2, $currencyId, $mid)), $sourceId === 2);
                $tab->addLink('结算', admin_route('report-currency-merchants.index', $this->tabQuery(3, $currencyId, $mid)), $sourceId === 3);
                $summary = $this->summaryAmount($sourceId, $currencyId);
                $row = new Row();
                $row->column(4, '');
                $row->column(4, new CommonCard($summary['title'], $summary['amount']));

                return '<div style="width: 100%">' . $tab . '<div style="flex:1;background: grey;">' . $row->render() . '</div></div>';
            });

            $grid->wrap(function (Renderable $view) use ($currencyId, $currencyList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($currencyId, $currencyList) {
                    $left = new LeftSide();
                    $left->title('币种列表')->field('cid')->default($currencyId)->data($currencyList->toArray());
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

    private function tabQuery(int $sourceId, int $currencyId, int $mid): array
    {
        $query = ['source_id' => $sourceId, 'cid' => $currencyId];
        if ($mid > 0) {
            $query['mid'] = $mid;
        }

        return $query;
    }

    private function reportColumns(int $sourceId): array
    {
        $columns = ['id', 'cid', 'mid', 'date_add'];
        if ($sourceId === 1) {
            return array_merge($columns, [
                'deposit_order_number_total',
                'deposit_order_number_success',
                'deposit_order_number_fail',
                'deposit_order_number_overtime',
                'deposit_order_number_swiping',
                'deposit_order_total_amount',
                'deposit_order_total_fee',
                'deposit_profit',
            ]);
        }

        if ($sourceId === 2) {
            return array_merge($columns, [
                'transfer_order_number_total',
                'transfer_order_number_success',
                'transfer_order_number_fail',
                'transfer_order_total_amount',
                'transfer_order_total_fee',
                'transfer_profit',
            ]);
        }

        return array_merge($columns, [
            'settlement_order_number_total',
            'settlement_order_number_success',
            'settlement_order_number_fail',
            'settlement_order_total_amount',
            'settlement_order_total_fee',
            'settlement_profit',
        ]);
    }

    private function summaryAmount(int $sourceId, int $currencyId): array
    {
        $field = match ($sourceId) {
            2 => 'transfer_order_total_amount',
            3 => 'settlement_order_total_amount',
            default => 'deposit_order_total_amount',
        };
        $title = match ($sourceId) {
            2 => '代付跑量',
            3 => '结算跑量',
            default => '代收跑量',
        };
        $query = ReportCurrencyMerchant::query();
        if ($currencyId > 0) {
            $query->where('cid', $currencyId);
        }

        $mid = (int) request('mid', 0);
        if ($mid > 0) {
            $query->where('mid', $mid);
        }

        $dateRange = request('date_add', []);
        $start = is_array($dateRange) ? ($dateRange['start'] ?? null) : null;
        $end = is_array($dateRange) ? ($dateRange['end'] ?? null) : null;
        if ($start) {
            $query->where('date_add', '>=', $start);
        }
        if ($end) {
            $query->where('date_add', '<=', $end);
        }

        return ['title' => $title, 'amount' => $query->sum($field)];
    }
}
