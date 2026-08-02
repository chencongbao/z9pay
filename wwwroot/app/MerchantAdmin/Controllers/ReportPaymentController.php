<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Box;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\ReportPaymentMerchant;
use App\Admin\Controllers\CommonController;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Merchant\GetMerchantPaymentListService;

class ReportPaymentController extends CommonController
{
    private const TRANSFER_PAYMENT_ID = 7;

    protected $disableCreate = true;

    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $mid = bob_merchant_user_pid();
        $payments = collect(app(GetMerchantPaymentListService::class)->excute($mid, true))
            ->reject(fn ($item) => (int) ($item['id'] ?? 0) === self::TRANSFER_PAYMENT_ID)
            ->values()
            ->toArray();
        $paymentId = (int) request('payment_id', 0);
        $paymentId = $paymentId > 0 ? $paymentId : $this->defaultPaymentId($payments);
        $model = ReportPaymentMerchant::query()->select([
            'id', 'mid', 'pid', 'date_add', 'deposit_order_number_total',
            'deposit_order_number_success', 'deposit_order_number_fail',
            'deposit_order_number_overtime', 'deposit_order_number_swiping',
            'deposit_order_total_amount', 'deposit_order_total_fee',
        ]);

        return Grid::make($model, function (Grid $grid) use ($mid, $paymentId, $payments) {
            $grid->model()->where('mid', $mid)->where('pid', $paymentId)->orderByDesc('date_add')->orderByDesc('id');
            $grid->model()->setConstraints(['mid' => $mid, 'pid' => $paymentId]);
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', admin_trans_field('date_add'))->center();
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

            $grid->disableActions();
            $grid->disableCreateButton();

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', admin_trans_label('query_date'))->date()->width(6);
            });

            $grid->wrap(function (Renderable $view) use ($paymentId, $payments) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($paymentId, $payments) {
                    $box = Box::make(admin_trans_label('payment_list'), view('admin.ReportPayment.payment-list', ['result' => $payments, 'payment_id' => $paymentId]));
                    $box->padding('15px 0px');
                    $column->row($box);
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

    private function defaultPaymentId(array $payments): int
    {
        return (int) data_get($payments, '0.id', 0);
    }
}
