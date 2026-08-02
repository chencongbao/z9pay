<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Tab;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\ReportMerchant;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use App\Admin\Extensions\Layout\LeftSide;
use App\Admin\Controllers\CommonController;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ReportMerchantController extends CommonController
{
    public $translation = 'report-merchant';

    public $source_id = 1;

    protected $disableCreate = true;
    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $agentId = Admin::user()->id;
        $sourceId = (int) request('source_id', 1);
        $sourceId = in_array($sourceId, [1, 2], true) ? $sourceId : 1;
        $this->source_id = $sourceId;

        $childAgentIds = AgentUserRelation::where('parent_id', $agentId)->pluck('child_id')->toArray();
        $merchantList = array_filter(App::make(GetMerchantListInfoService::class)->excute(), function ($item) use ($childAgentIds) {
            return in_array($item['agent_user_id'], $childAgentIds);
        });
        $visibleMerchantIds = collect($merchantList)->pluck('merchant_user_id')->map(fn ($id) => (int) $id)->toArray();
        $merchantUserId = (int) request('mid', 0);
        if (!in_array($merchantUserId, $visibleMerchantIds, true)) {
            $merchantUserId = (int) (data_get(current($merchantList), 'merchant_user_id', 0));
            if ($merchantUserId > 0) {
                request()->merge(['mid' => $merchantUserId]);
            }
        }

        $query = ReportMerchant::query()->select($this->reportColumns($sourceId));

        return Grid::make($query, function (Grid $grid) use ($sourceId, $merchantUserId, $merchantList) {
            // 代理端报表必须绑定当前代理可见商户，避免无商户时误展示全站数据。
            if ($merchantUserId > 0) {
                $grid->model()->where('mid', $merchantUserId);
            } else {
                $grid->model()->whereRaw('1 = 0');
            }
            $grid->model()->orderByDesc('date_add')->orderByDesc('id');

            $merchantInfo = App::make(CacheMerchantBaseInfoService::class)->excute($merchantUserId);
            $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . optional($merchantInfo)->offsetGet('bname') . '</button>');
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', __('reports-merchant-agents.fields.date_add'))->center();
            if ($sourceId === 1) {
                $grid->column('deposit_order_number_total', admin_trans_field('deposit_order_number_total'));
                $grid->column('deposit_order_number_success', admin_trans_field('deposit_order_number_success'));
                $grid->column('deposit_order_number_fail', admin_trans_field('deposit_order_number_fail'));
                $grid->column('deposit_order_number_overtime', admin_trans_field('deposit_order_number_overtime'));
                $grid->column('deposit_order_number_swiping', admin_trans_field('deposit_order_number_swiping'));
                $grid->column('deposit_order_success_rate', admin_trans_field('deposit_order_success_rate'))->display(function () {
                    return bob_percent($this->deposit_order_number_success, $this->deposit_order_number_total);
                });
                $grid->column('deposit_order_total_amount', admin_trans_field('deposit_order_total_amount'))->amount();
            }
            if ($sourceId === 2) {
                $grid->column('transfer_order_number_total', admin_trans_field('transfer_order_number_total'));
                $grid->column('transfer_order_number_success', admin_trans_field('transfer_order_number_success'));
                $grid->column('transfer_order_number_fail', admin_trans_field('transfer_order_number_fail'));
                $grid->column('transfer_order_success_rate', admin_trans_field('transfer_order_success_rate'))->display(function () {
                    return bob_percent($this->transfer_order_number_success, $this->transfer_order_number_total);
                });
                $grid->column('transfer_order_total_amount', admin_trans_field('transfer_order_total_amount'))->amount();
            }
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableBatchDelete();
            $grid->disableRowSelector();
            $grid->export();

            $grid->filter(function (Grid\Filter $filter) use ($merchantUserId, $merchantList) {
                request()->merge(['mid' => $merchantUserId]);
                $filter->expand();
                $filter->panel();
                $filter->between('date_add', __('reports-merchant-agents.fields.date_query'))->date()->width(4);
                $filter->equal('mid', __('merchant-user.labels.merchant'))->select(collect($merchantList)->pluck('bname', 'merchant_user_id'))->width(4)->default($merchantUserId);
            });

            $grid->header(function () use ($sourceId, $merchantUserId) {
                $tab = Tab::make();
                $tab->addLink(admin_trans_field('deposit_menu'), '?source_id=1&mid=' . $merchantUserId, $sourceId === 1);
                $tab->addLink(admin_trans_field('transfer_menu'), '?source_id=2&mid=' . $merchantUserId, $sourceId === 2);

                return '<div style="width: 100%">' . $tab . '</div>';
            });

            $grid->wrap(function (Renderable $view) use ($merchantUserId, $merchantList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($merchantUserId, $merchantList) {
                    $left = new LeftSide();
                    $left->title(__('rates-agent-payments.fields.merchant_list'))->field('mid')->default($merchantUserId)->data($merchantList);
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
                'deposit_order_number_total',
                'deposit_order_number_success',
                'deposit_order_total_amount',
                'deposit_order_number_fail',
                'deposit_order_number_overtime',
                'deposit_order_number_swiping',
            ]);
        }

        return array_merge($columns, [
            'transfer_order_number_total',
            'transfer_order_number_success',
            'transfer_order_number_fail',
            'transfer_order_total_amount',
        ]);
    }
}
