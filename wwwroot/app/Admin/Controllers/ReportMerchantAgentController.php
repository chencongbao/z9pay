<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\ReportMerchantAgent;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Extensions\Layout\LeftTreeSide;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class ReportMerchantAgentController extends CommonController
{
    protected $title = '商户代理报表';

    protected $disableEdit = true;

    protected $disableCreate = true;

    protected function grid(): Grid
    {
        $agentId = (int) request('aid', 0);
        if ($agentId <= 0) {
            request()->query->remove('aid');
            request()->request->remove('aid');
        }
        $agentListService = App::make(GetMerchantAgentListService::class);
        $agentList = $agentListService->excute();
        $agentOptions = bob_build_select_options(collect($agentList)->toArray());
        $agentTreeData = collect($agentList)->map(function ($item) {
            return [
                'parentid' => $item['pid'],
                'text' => '【' . $item['id'] . '】' . $item['name'],
                'level' => $item['level'],
                'id' => $item['id'],
            ];
        });
        $query = ReportMerchantAgent::query()->select([
            'id',
            'aid',
            'date_add',
            'deposit_order_number_total',
            'deposit_commission',
            'deposit_order_total_amount',
            'transfer_order_number_total',
            'transfer_order_total_amount',
            'transfer_commission',
            'settlement_order_number_total',
            'settlement_order_total_amount',
            'settlement_commission',
            'jian_total_amount',
            'add_total_amount',
        ])->orderByDesc('date_add')->orderByDesc('id');

        return Grid::make($query, function (Grid $grid) use ($agentId, $agentOptions, $agentTreeData) {
            if ($agentId > 0) {
                $agent = App::make(GetMerchantAgentDetailService::class)->excute($agentId);
                $grid->tools()->prepend('<button class="btn btn-info"><i class="fa fa-fw fa-users" /> ' . optional($agent)->offsetGet('bname') . '</button>');
            }
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', '日期')->center();
            $grid->column('deposit_order_number_total', '代收成功订单数量');
            $grid->column('deposit_commission', '代收佣金')->amount();
            $grid->column('deposit_order_total_amount', '代收跑量')->amount();

            $grid->column('transfer_order_number_total', '代付成功订单数量');
            $grid->column('transfer_order_total_amount', '代付跑量')->amount();
            $grid->column('transfer_commission', '代付佣金')->amount();

            $grid->column('settlement_order_number_total', '结算成功订单数量');
            $grid->column('settlement_order_total_amount', '结算跑量')->amount();
            $grid->column('settlement_commission', '结算佣金')->amount();

            $grid->column('jian_total_amount', '资金减项')->amount();
            $grid->column('add_total_amount', '资金加项')->amount();
            $grid->disableActions();
            $grid->disableCreateButton();

            $grid->filter(function (Grid\Filter $filter) use ($agentOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
                $filter->equal('aid', '所属代理')->select($agentOptions)->width(3);
            });

            $grid->wrap(function (Renderable $view) use ($agentId, $agentTreeData) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($agentId, $agentTreeData) {
                    $left = new LeftTreeSide();
                    $left->title('代理列表')->field('aid')->default($agentId)->prependAll('全部代理')->data($agentTreeData);
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
