<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Widgets\Tab;
use App\Models\ReportMerchant;
use App\Admin\Controllers\CommonController;

class ReportMerchantController extends CommonController
{
    protected $disableCreate = true;

    protected $disableEdit = true;

    public $source_id = 1;

    protected function grid(): Grid
    {
        $sourceId = (int) request('source_id', 1);
        $sourceId = in_array($sourceId, [1, 2, 3], true) ? $sourceId : 1;
        $this->source_id = $sourceId;
        $mid = bob_merchant_user_pid();

        $model = ReportMerchant::query()->select($this->reportColumns($sourceId));

        return Grid::make($model, function (Grid $grid) use ($sourceId, $mid) {
            $grid->model()->where('mid', $mid)->orderByDesc('date_add')->orderByDesc('id');
            $grid->model()->setConstraints(['mid' => $mid]);
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', admin_trans_label('query_date'))->center();
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
                $grid->column('deposit_order_total_fee', admin_trans_field('deposit_order_total_fee'))->amount();
                $grid->column('jian_total_amount', admin_trans_field('jian_total_amount'))->amount();
                $grid->column('add_total_amount', admin_trans_field('add_total_amount'))->amount();
            }
            if ($sourceId === 2) {
                $grid->column('transfer_order_number_total', admin_trans_field('transfer_order_number_total'));
                $grid->column('transfer_order_number_success', admin_trans_field('transfer_order_number_success'));
                $grid->column('transfer_order_number_fail', admin_trans_field('transfer_order_number_fail'));
                $grid->column('transfer_order_success_rate', admin_trans_field('transfer_order_success_rate'))->display(function () {
                    return bob_percent($this->transfer_order_number_success, $this->transfer_order_number_total);
                });
                $grid->column('transfer_order_total_amount', admin_trans_field('transfer_order_total_amount'))->amount();
                $grid->column('transfer_order_total_fee', admin_trans_field('transfer_order_total_fee'))->amount();
            }
            if ($sourceId === 3) {
                $grid->column('settlement_order_number_total', admin_trans_field('settlement_order_number_total'));
                $grid->column('settlement_order_number_success', admin_trans_field('settlement_order_number_success'));
                $grid->column('settlement_order_number_fail', admin_trans_field('settlement_order_number_fail'));
                $grid->column('settlement_order_success_rate', admin_trans_field('settlement_order_success_rate'))->display(function () {
                    return bob_percent($this->settlement_order_number_success, $this->settlement_order_number_total);
                });
                $grid->column('settlement_order_total_amount', admin_trans_field('settlement_order_total_amount'))->amount();
                $grid->column('settlement_order_total_fee', admin_trans_field('settlement_order_total_fee'))->amount();
            }
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->export();
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', admin_trans_label('query_date'))->date()->width(6);
            });
            $grid->header(function () use ($sourceId) {
                $tab = Tab::make();
                $tab->addLink(admin_trans_field('deposit_menu'), '?source_id=1', $sourceId === 1);
                $tab->addLink(admin_trans_field('transfer_menu'), '?source_id=2', $sourceId === 2);
                $tab->addLink(admin_trans_field('sellement_menu'), '?source_id=3', $sourceId === 3);

                return '<div style="width: 100%">' . $tab . '</div>';
            });
        });
    }

    private function reportColumns(int $sourceId): array
    {
        $columns = ['id', 'mid', 'date_add'];

        if ($sourceId === 1) {
            return array_merge($columns, [
                'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_total_amount',
                'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
                'deposit_order_total_fee', 'jian_total_amount', 'add_total_amount',
            ]);
        }

        if ($sourceId === 2) {
            return array_merge($columns, [
                'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail',
                'transfer_order_total_amount', 'transfer_order_total_fee',
            ]);
        }

        return array_merge($columns, [
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail',
            'settlement_order_total_amount', 'settlement_order_total_fee',
        ]);
    }
}
