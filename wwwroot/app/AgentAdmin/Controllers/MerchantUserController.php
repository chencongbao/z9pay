<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\Filter;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Widgets\Card;
use App\Models\AgentUser;
use App\Models\MerchantInfo;
use App\Models\AgentUserRelation;
use App\Admin\Controllers\CommonController;
use App\Admin\Extensions\Layout\LeftTreeSide;
use Illuminate\Contracts\Support\Renderable;

class MerchantUserController extends CommonController
{
    public function title(): string
    {
        return __('menu.titles.information');
    }

    protected function grid(): Grid
    {
        return Grid::make(MerchantInfo::with(['merchant_user' => function ($query) {
            $query->select('id', 'created_at', 'status');
        }, 'agent_user' => function ($query) {
            $query->select('id', 'name');
        }]), function (Grid $grid) {
            $agentId = Admin::user()->id;
            $childAgentIds = AgentUserRelation::where('parent_id', $agentId)->pluck('child_id')->toArray();
            $agentResult = AgentUser::whereIn('id', $childAgentIds)->get(['pid', 'id', 'name', 'level', 'username']);

            // 代理端只展示当前代理下级商户，代理列表和筛选共用同一份数据。
            $grid->model()->whereIn('agent_user_id', $childAgentIds)->select([
                'merchant_user_id',
                'name',
                'coder',
                'agent_user_id',
            ])->orderBy('merchant_user_id', 'desc');
            $grid->column('merchant_user_id', __('global.fields.id'))->sortable()->center();
            $grid->column('name', __('home.labels.merchant_name'));
            $grid->column('coder', __('merchantuser.fields.merchant_coder'));
            $grid->column('agent_user.name', admin_trans_field('agent_belong'));
            $grid->column('merchant_user.status', admin_trans_label('status'))->status();
            $grid->column('merchant_user.created_at', trans('admin.created_at'))->center();
            $grid->disableDeleteButton();
            $grid->withBorder();
            $grid->disableViewButton();
            $grid->disableRowSelector();
            $grid->disableCreateButton();
            $grid->disableActions();
            $grid->filter(function (Filter $filter) use ($agentResult) {
                $filter->expand();
                $filter->panel();
                $filter->equal('merchant_user_id', __('global.fields.id'))->width(3);
                $filter->like('name', __('home.labels.merchant_name'))->width(3);
                $filter->like('coder', __('merchantuser.fields.merchant_coder'))->width(3);
                $filter->equal('agent_user_id', admin_trans_field('agent_belong'))->select(bob_build_select_options($agentResult->toArray()))->width(3);
            });
            $grid->wrap(function (Renderable $view) use ($agentResult) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($agentResult) {
                    $agentTree = $agentResult->map(function ($item) {
                        return [
                            'parentid' => $item->pid,
                            'text' => $item->bname,
                            'level' => $item->level,
                            'id' => $item->id,
                        ];
                    })->toArray();
                    $left = new LeftTreeSide();
                    $left->title(admin_trans_field('agent_list'))->field('agent_user_id')->default()->data($agentTree);
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
