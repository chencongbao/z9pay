<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Widgets\Lazy;
use App\Models\ReportPayment;
use Dcat\Admin\Layout\Column;
use Illuminate\Support\Facades\App;
use App\Models\ReportPaymentMerchant;
use App\Admin\Extensions\Layout\LeftSide;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Renderable\ReportPayment\SummaryCard;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ReportPaymentController extends CommonController
{
    public $title = '通道报表';

    protected $disableCreate = true;

    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $merchantUserId = (int) request('mid', 0);
        if ($merchantUserId <= 0) {
            request()->query->remove('mid');
            request()->request->remove('mid');
        }
        $paymentOptions = $this->paymentOptions();
        $paymentSelectOptions = collect($paymentOptions)->pluck('select_name', 'id');
        $isAllPayment = request()->has('pid') && (int) request('pid') <= 0;
        if ($isAllPayment) {
            request()->query->remove('pid');
            request()->request->remove('pid');
        }
        $paymentId = (int) request('pid', 0);
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);

        $columns = ['id', 'pid', 'date_add', 'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping', 'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_profit'];
        $model = $merchantUserId > 0 ? ReportPaymentMerchant::query()->select(array_merge($columns, ['mid'])) : ReportPayment::query()->select($columns);
        $model->orderByDesc('date_add')->orderByDesc('id');

        return Grid::make($model, function (Grid $grid) use ($paymentId, $paymentOptions, $paymentSelectOptions, $merchantBaseInfoService) {
            $payment = collect($paymentOptions)->firstWhere('id', $paymentId);
            $paymentName = $paymentId > 0 ? ($payment['bname'] ?? '') : '全部通道';

            $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> '.$paymentName.'</button>');
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', '日期')->center();
            if ($paymentId <= 0) {
                $grid->column('payment_info_name', '所属通道')->display(function () use ($paymentOptions) {
                    $payment = collect($paymentOptions)->firstWhere('id', (int) $this->pid);

                    return data_get($payment, 'bname', '【#'.(int) $this->pid.'】通道信息缺失');
                });
            }
            $grid->column('deposit_order_number_total', '代收单数');
            $grid->column('deposit_order_number_success', '代收成功单数');
            $grid->column('deposit_order_number_fail', '代收失败单数');
            $grid->column('deposit_order_number_overtime', '代收超时单数');
            $grid->column('deposit_order_number_swiping', '代收刷单单数');
            $grid->column('deposit_order_success_rate', '代收成功率')->display(function () {
                return bob_percent($this->deposit_order_number_success, $this->deposit_order_number_total);
            });
            $grid->column('deposit_order_total_amount', '代收跑量')->amount();
            $grid->column('deposit_order_total_fee', '代收商户手续费')->amount();
            $grid->column('deposit_profit', '代收利润')->amount();

            $grid->disableActions();
            $grid->disableCreateButton();

            $grid->header(function () {
                $row = new Row();
                $row->column(4, '');
                $row->column(4, Lazy::make(SummaryCard::make(request()->all())));

                return '<div style="width: 100%"><div style="flex:1;background: grey;">'.$row->render().'</div></div>';
            });

            $grid->filter(function (Grid\Filter $filter) use ($paymentId, $paymentSelectOptions, $merchantBaseInfoService) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
                $filter->equal('pid', '通道类型')->select($paymentSelectOptions)->width(3)->default($paymentId);
                $filter->equal('mid', '商户')->select(function ($mid) use ($merchantBaseInfoService) {
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);

                        return empty($result) ? [] : [$result['merchant_user_id'] => $result['bname']];
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(3);
            });

            $grid->wrap(function (Renderable $view) use ($paymentId, $paymentOptions) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($paymentId, $paymentOptions) {
                    $left = new LeftSide();
                    $left->title('通道列表')->field('pid')->default($paymentId)->prependAll('全部通道', true)->data($paymentOptions);
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

    private function paymentOptions(): array
    {
        return collect(config('payment', []))->filter(function ($item) {
            return (int) ($item['id'] ?? 0) > 0;
        })->map(function ($item) {
            $item['bname'] = '【#'.$item['id'].'】'.$item['name'];
            $item['select_name'] = $item['name'].'【'.($item['code'] ?? '').'】';

            return $item;
        })->values()->toArray();
    }
}
