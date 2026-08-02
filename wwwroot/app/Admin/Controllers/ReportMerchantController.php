<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Tab;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Widgets\Lazy;
use Dcat\Admin\Layout\Column;
use App\Models\ReportMerchant;
use Illuminate\Support\Facades\App;
use App\Admin\Extensions\Layout\LeftSide;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Renderable\ReportMerchant\SummaryCard;
use App\Admin\Actions\Grid\ReportMerchant\ExportData;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ReportMerchantController extends CommonController
{
    public $title = '商户报表';

    public $source_id = 1;

    protected $disableCreate = true;

    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $merchantUserId = (int) request('mid', 0);
        $sourceId = (int) request('source_id', 1);
        $sourceId = in_array($sourceId, [1, 2, 3], true) ? $sourceId : 1;
        $this->source_id = $sourceId;

        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $merchantListInfoService = App::make(GetMerchantListInfoService::class);
        $currencyOptions = collect(config('default.currency', []))->pluck('name', 'id');
        $merchantList = array_filter($merchantListInfoService->excute(), function ($item) {
            return (int) ($item['status'] ?? 0) === 1;
        });

        $model = ReportMerchant::query()
            ->select($this->reportColumns($sourceId))
            ->orderByDesc('date_add')
            ->orderByDesc('id');
        if ($merchantUserId <= 0) {
            $model->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'currency_id', 'agent_user_id', 'name', 'coder']);
            }]);
        }

        return Grid::make($model, function (Grid $grid) use ($merchantUserId, $sourceId, $merchantBaseInfoService, $merchantList, $currencyOptions) {
            $merchantName = '所有商户';
            if ($merchantUserId > 0) {
                $merchantInfo = $merchantBaseInfoService->excute($merchantUserId);
                $merchantName = optional($merchantInfo)->offsetGet('bname') ?: $merchantName;
            }

            $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> '.$merchantName.'</button>');
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', '日期')->center();

            if ($sourceId === 1) {
                $grid->column('deposit_order_number_total', '代收提单数');
                $grid->column('deposit_order_number_success', '代收提单成功数');
                $grid->column('deposit_order_total_amount', '代收提单成功金额')->amount();
                $grid->column('deposit_order_number_fail', '代收提单失败数');
                $grid->column('deposit_order_number_overtime', '代收提单超时数');
                $grid->column('deposit_order_number_swiping', '代收提单刷单数');
                $grid->column('deposit_order_success_rate', '代收提单成功率')->display(function () {
                    return bob_percent($this->deposit_order_number_success, $this->deposit_order_number_total);
                });
                $grid->column('deposit_created_success_number', '代收成功入账单数');
                $grid->column('deposit_created_success_amount', '代收成功入账金额')->amount();
                $grid->column('deposit_freeze_number', '代收冻结笔数');
                $grid->column('deposit_freeze_amount', '代收冻结金额')->amount();
                $grid->column('deposit_unfreeze_number', '代收解冻笔数');
                $grid->column('deposit_unfreeze_amount', '代收解冻金额')->amount();
                $grid->column('deposit_order_total_fee', '代收商户手续费')->amount();
                $grid->column('deposit_one_agent_commission', '代收一级代理佣金')->amount();
                $grid->column('deposit_two_agent_commission', '代收二级代理佣金')->amount();
                $grid->column('deposit_three_agent_commission', '代收三级代理佣金')->amount();
                $grid->column('deposit_profit', '代收总利润')->amount();
                $grid->column('jian_total_amount', '商户资金减项')->amount();
                $grid->column('add_total_amount', '商户资金加项')->amount();
            }

            if ($sourceId === 2) {
                $grid->column('transfer_order_number_total', '代付提单数');
                $grid->column('transfer_order_number_success', '代付提单成功数');
                $grid->column('transfer_order_total_amount', '代付提单成功金额')->amount();
                $grid->column('transfer_order_number_fail', '代付提单失败数');
                $grid->column('transfer_order_success_rate', '代付提单成功率')->display(function () {
                    return bob_percent($this->transfer_order_number_success, $this->transfer_order_number_total);
                });
                $grid->column('transfer_created_success_number', '代付成功出款单数');
                $grid->column('transfer_created_success_amount', '代付成功出款金额')->amount();
                $grid->column('transfer_deduct_number', '代付扣款笔数');
                $grid->column('transfer_deduct_amount', '代付扣款金额')->amount();
                $grid->column('transfer_corre_number', '代付冲正笔数');
                $grid->column('transfer_corre_amount', '代付冲正金额')->amount();
                $grid->column('transfer_order_total_fee', '代付商户手续费')->amount();
                $grid->column('transfer_one_agent_commission', '代付一级代理佣金')->amount();
                $grid->column('transfer_two_agent_commission', '代付二级代理佣金')->amount();
                $grid->column('transfer_three_agent_commission', '代付三级代理佣金')->amount();
                $grid->column('transfer_profit', '代付总利润')->amount();
            }

            if ($sourceId === 3) {
                $grid->column('settlement_order_number_total', '结算提单数');
                $grid->column('settlement_order_number_success', '结算提单成功数');
                $grid->column('settlement_order_total_amount', '结算提单成功金额')->amount();
                $grid->column('settlement_order_number_fail', '结算提单失败数');
                $grid->column('settlement_order_success_rate', '结算提单成功率')->display(function () {
                    return bob_percent($this->settlement_order_number_success, $this->settlement_order_number_total);
                });
                $grid->column('settlement_created_success_number', '成功结算单数');
                $grid->column('settlement_created_success_amount', '成功结算金额')->amount();
                $grid->column('settlement_deduct_number', '结算扣款笔数');
                $grid->column('settlement_deduct_amount', '结算扣款金额')->amount();
                $grid->column('settlement_corre_number', '结算冲正笔数');
                $grid->column('settlement_corre_amount', '结算冲正金额')->amount();
                $grid->column('settlement_order_total_fee', '结算商户手续费')->amount();
                $grid->column('settlement_one_agent_commission', '结算一级代理佣金')->amount();
                $grid->column('settlement_two_agent_commission', '结算二级代理佣金')->amount();
                $grid->column('settlement_three_agent_commission', '结算三级代理佣金')->amount();
                $grid->column('settlement_profit', '结算利润')->amount();
            }

            if ($merchantUserId <= 0) {
                $grid->column('merchant_info.bname', '所属商户');
            }

            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->filter(function (Grid\Filter $filter) use ($merchantUserId, $merchantBaseInfoService, $currencyOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
                $filter->equal('mid', '商户')->select(function ($mid) use ($merchantUserId, $merchantBaseInfoService) {
                    $mid = $mid ?: $merchantUserId;
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);

                        return empty($result) ? [] : [$result['merchant_user_id'] => $result['bname']];
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(4)->default($merchantUserId);

                $filter->where('cid', function ($query) {
                    $query->whereIn('mid', MerchantInfo::query()->select('merchant_user_id')->where('currency_id', $this->input));
                }, '选择币种')->select($currencyOptions)->width(3);
            });

            $grid->header(function () use ($merchantUserId, $sourceId) {
                $tab = Tab::make();
                $tab->addLink('代收', admin_route('report-merchants.index', ['source_id' => 1, 'mid' => $merchantUserId]), $sourceId === 1);
                $tab->addLink('代付', admin_route('report-merchants.index', ['source_id' => 2, 'mid' => $merchantUserId]), $sourceId === 2);
                $tab->addLink('结算', admin_route('report-merchants.index', ['source_id' => 3, 'mid' => $merchantUserId]), $sourceId === 3);

                $row = new Row();
                $row->column(4, '');
                $row->column(4, Lazy::make(SummaryCard::make(request()->all())));

                return '<div style="width: 100%">'.$tab.'<div style="flex:1;background: grey;">'.$row->render().'</div></div>';
            });

            $grid->wrap(function (Renderable $view) use ($merchantUserId, $merchantList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($merchantUserId, $merchantList) {
                    $left = new LeftSide();
                    $left->title('商户列表')->field('mid')->default($merchantUserId)->prependAll('全部商户')->data($merchantList);
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

    private function reportColumns(int $sourceId): array
    {
        $columns = ['id', 'mid', 'date_add'];

        if ($sourceId === 1) {
            return array_merge($columns, [
                'deposit_order_number_total', 'deposit_created_success_number', 'deposit_created_success_amount', 'deposit_order_number_success',
                'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping', 'deposit_freeze_number', 'deposit_freeze_amount',
                'deposit_unfreeze_number', 'deposit_unfreeze_amount',
                'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_one_agent_commission', 'deposit_two_agent_commission', 'deposit_three_agent_commission',
                'deposit_profit', 'jian_total_amount', 'add_total_amount',
            ]);
        }

        if ($sourceId === 2) {
            return array_merge($columns, [
                'transfer_order_number_total', 'transfer_created_success_number', 'transfer_created_success_amount', 'transfer_order_number_success',
                'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_deduct_number', 'transfer_deduct_amount', 'transfer_corre_number',
                'transfer_corre_amount', 'transfer_order_total_fee',
                'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission', 'transfer_profit',
            ]);
        }

        return array_merge($columns, [
            'settlement_order_number_total', 'settlement_created_success_number', 'settlement_created_success_amount', 'settlement_order_number_success',
            'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_deduct_number', 'settlement_deduct_amount',
            'settlement_corre_number', 'settlement_corre_amount', 'settlement_order_total_fee',
            'settlement_one_agent_commission', 'settlement_two_agent_commission', 'settlement_three_agent_commission', 'settlement_profit',
        ]);
    }

}
