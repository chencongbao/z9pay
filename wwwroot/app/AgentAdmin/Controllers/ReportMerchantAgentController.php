<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\ReportMerchantAgent;
use App\Admin\Controllers\CommonController;

class ReportMerchantAgentController extends CommonController
{
    protected $disableEdit = true;
    protected $disableCreate = true;

    public $translation = 'reports-merchant-agents';

    protected function grid(): Grid
    {
        $agentId = Admin::user()->id;
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
        ]);

        return Grid::make($query, function (Grid $grid) use ($agentId) {
            $grid->model()->where('aid', $agentId)->orderByDesc('date_add')->orderByDesc('id');
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', admin_trans_field('date_add'))->center();
            $grid->column('deposit_order_number_total', admin_trans_field('deposit_order_number_total'));
            $grid->column('deposit_commission', admin_trans_field('deposit_commission'))->amount();
            $grid->column('deposit_order_total_amount', admin_trans_field('deposit_order_total_amount'))->amount();

            $grid->column('transfer_order_number_total', admin_trans_field('transfer_order_number_total'));
            $grid->column('transfer_order_total_amount', admin_trans_field('transfer_order_total_amount'))->amount();
            $grid->column('transfer_commission', admin_trans_field('transfer_commission'))->amount();

            $grid->column('settlement_order_number_total', admin_trans_field('settlement_order_number_total'));
            $grid->column('settlement_order_total_amount', admin_trans_field('settlement_order_total_amount'))->amount();
            $grid->column('settlement_commission', admin_trans_field('settlement_commission'))->amount();

            $grid->column('jian_total_amount', admin_trans_field('jian_total_amount'))->amount();
            $grid->column('add_total_amount', admin_trans_field('add_total_amount'))->amount();
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableBatchDelete();
            $grid->disableRowSelector();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->between('date_add', admin_trans_field('date_query'))->date()->width(6);
            });
        });
    }
}
