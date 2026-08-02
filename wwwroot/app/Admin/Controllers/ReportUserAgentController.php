<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\ReportUserAgent;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Extensions\Layout\LeftTreeSide;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\User\GetUserAgentListService;

class ReportUserAgentController extends CommonController
{
    protected $title = '金主代理报表';

    protected $disableEdit = true;

    protected $disableCreate = true;

    protected function grid(): Grid
    {
        $requestedAgentId = (int) request('aid', 0);
        $agentList = App::make(GetUserAgentListService::class)->excute();
        $activeAgent = $requestedAgentId > 0 ? collect($agentList)->firstWhere('id', $requestedAgentId) : null;
        $agentId = $activeAgent ? $requestedAgentId : 0;
        if ($requestedAgentId > 0 && !$activeAgent) {
            request()->query->remove('aid');
            request()->request->remove('aid');
        }

        $agentSelectOptions = bob_build_select_options(collect($agentList)->toArray());
        $agentTreeList = collect($agentList)->map(function ($item) {
            return [
                'id' => $item['id'],
                'parentid' => $item['pid'],
                'level' => $item['level'],
                'text' => '【'.$item['id'].'】'.$item['name'],
            ];
        });

        $model = ReportUserAgent::query()->select([
            'id', 'aid', 'date_add', 'deposit_commission', 'deposit_order_number_total', 'deposit_order_total_amount',
            'transfer_order_number_total', 'transfer_order_total_amount', 'transfer_commission', 'jian_total_amount', 'add_total_amount',
        ])->orderByDesc('date_add')->orderByDesc('id');

        return Grid::make($model, function (Grid $grid) use ($agentId, $agentSelectOptions, $agentTreeList) {
            if ($agentId > 0) {
                $agent = App::make(GetUserDetailService::class)->excute($agentId);
                $bname = optional($agent)->offsetGet('bname');
                if ($bname) {
                    $grid->tools()->prepend('<button class="btn btn-info"><i class="fa fa-fw fa-users" /> '.$bname.'</button>');
                }
            }

            $grid->column('id')->sortable()->center();
            $grid->column('date_add', '日期')->center();
            $grid->column('deposit_commission', '代收佣金');
            $grid->column('deposit_order_number_total', '代收成功订单数量');
            $grid->column('deposit_order_total_amount', '代收跑量')->amount();
            $grid->column('transfer_order_number_total', '代付成功订单数量');
            $grid->column('transfer_order_total_amount', '代付跑量')->amount();
            $grid->column('transfer_commission', '代付佣金')->amount();
            $grid->column('jian_total_amount', '资金减项')->amount();
            $grid->column('add_total_amount', '资金加项')->amount();
            $grid->disableActions();
            $grid->disableCreateButton();

            $grid->filter(function (Grid\Filter $filter) use ($agentSelectOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
                $filter->equal('aid', '代理')->select($agentSelectOptions)->width(3);
            });

            $grid->wrap(function (Renderable $view) use ($agentId, $agentTreeList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($agentId, $agentTreeList) {
                    $left = new LeftTreeSide();
                    $left->title('代理列表')->field('aid')->default($agentId)->prependAll('全部代理')->data($agentTreeList);
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
