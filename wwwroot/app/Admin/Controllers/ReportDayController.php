<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\ReportDay;
use Dcat\Admin\Widgets\Tab;

class ReportDayController extends CommonController
{
    public $title = '日总报表';

    protected $disableCreate = true;

    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $sourceId = (int) request('source_id', 1);
        $sourceId = in_array($sourceId, [1, 2, 3], true) ? $sourceId : 1;
        $model = ReportDay::query()->select($this->reportColumns($sourceId))->orderByDesc('date_add')->orderByDesc('id');

        return Grid::make($model, function (Grid $grid) use ($sourceId) {
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', '日期')->center();

            if ($sourceId === 1) {
                $grid->column('deposit_order_number_total', '代收单数')->center();
                $grid->column('deposit_order_number_success', '代收成功单数')->center();
                $grid->column('deposit_order_number_fail', '代收失败单数')->center();
                $grid->column('deposit_order_number_overtime', '代收超时单数')->center();
                $grid->column('deposit_order_number_swiping', '代收刷单单数')->center();
                $grid->column('deposit_order_success_rate', '代收成功率')->center();
            }

            if ($sourceId === 2) {
                $grid->column('transfer_order_number_total', '代付单数')->center();
                $grid->column('transfer_order_number_success', '代付成功单数')->center();
                $grid->column('transfer_order_number_fail', '代付失败单数')->center();
                $grid->column('transfer_order_success_rate', '代付成功率')->center();
            }

            if ($sourceId === 3) {
                $grid->column('settlement_order_number_total', '结算单数')->center();
                $grid->column('settlement_order_number_success', '结算成功单数')->center();
                $grid->column('settlement_order_number_fail', '结算失败单数')->center();
                $grid->column('settlement_order_success_rate', '结算成功率')->center();
            }

            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->export();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
            });

            $grid->header(function () use ($sourceId) {
                $tab = Tab::make();
                $tab->addLink('代收', admin_route('report-days.index', ['source_id' => 1]), $sourceId === 1);
                $tab->addLink('代付', admin_route('report-days.index', ['source_id' => 2]), $sourceId === 2);
                $tab->addLink('结算', admin_route('report-days.index', ['source_id' => 3]), $sourceId === 3);

                return '<div style="width: 100%">'.$tab.'</div>';
            });
        });
    }

    private function reportColumns(int $sourceId): array
    {
        $columns = ['id', 'date_add'];

        if ($sourceId === 1) {
            return array_merge($columns, [
                'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
            ]);
        }

        if ($sourceId === 2) {
            return array_merge($columns, [
                'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail',
            ]);
        }

        return array_merge($columns, [
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail',
        ]);
    }

}
