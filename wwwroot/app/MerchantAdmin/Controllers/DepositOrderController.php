<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use App\Models\DepositOrder;
use App\Admin\Controllers\CommonController;
use App\MerchantAdmin\Actions\DepositOrder\ExportData;
use App\Admin\Metrics\MerchantAdmin\DepositOrder\Card1;
use App\Admin\Metrics\MerchantAdmin\DepositOrder\Card2;
use App\Admin\Metrics\MerchantAdmin\DepositOrder\Card3;
use App\Admin\Metrics\MerchantAdmin\DepositOrder\Card4;
use App\Admin\Metrics\MerchantAdmin\DepositOrder\Card5;
use App\Services\Merchant\GetMerchantPaymentListService;

class DepositOrderController extends CommonController
{
    private const TRANSFER_PAYMENT_ID = 7;

    public $disableCreate = true;

    public $disableEdit = true;

    protected function grid(): Grid
    {
        return Grid::make(new DepositOrder(), function (Grid $grid) {
            $createdAt = request('created_at');
            $beginDate = $createdAt['start'] ?? date('Y-m-d') . ' 00:00:00';
            $endDate = $createdAt['end'] ?? date('Y-m-d') . ' 23:59:59';
            $mid = bob_merchant_user_pid();
            $merchantPayments = collect(app(GetMerchantPaymentListService::class)->excute($mid, true))
                ->reject(fn ($item) => (int) ($item['id'] ?? 0) === self::TRANSFER_PAYMENT_ID)
                ->values()
                ->toArray();
            $paymentOptions = collect($merchantPayments)->pluck('bname', 'id')->toArray();
            $paymentNames = collect(config('payment'))->mapWithKeys(function ($item) {
                return [$item['id'] => $item['name'] . '【' . $item['code'] . '】'];
            })->toArray();

            $grid->model()->where('mid', $mid)->select([
                'id', 'order_no', 'ordernumber', 'pay_name', 'status',
                'callback_count', 'callback_time', 'callback_status',
                'amount', 'pay_amount', 'actual_amount',
                'merchant_fee', 'merchant_extra_fee', 'merchant_rate',
                'payment_id', 'success_time', 'created_at', 'mid', 'usdt_rate',
            ]);
            $grid->model()->setConstraints(['mid' => $mid]);

            $grid->column('order_no', admin_trans_label('order_no'))->copyable();
            $grid->column('ordernumber', admin_trans_label('ordernumber'))->copyable();
            $grid->column('pay_name', admin_trans_field('pay_name'))->copyable();
            $grid->column('status', admin_trans_label('order_status'))->display(function () {
                return bob_show_label(admin_trans_option($this->status, 'deposit_status'), $this->status, 2);
            });
            $grid->column('callback_info', admin_trans_field('callback_info'))->display(function () {
                if ($this->status == 5 || $this->status == 6) {
                    $data[] = [admin_trans_field('callback_count'), $this->callback_count];
                    if ($this->callback_time > 0) {
                        $data[] = [admin_trans_field('callback_time'), date('Y-m-d H:i:s', $this->callback_time)];
                    }
                    if ($this->callback_status == 1) {
                        $data[] = [admin_trans_field('callback_status'), '<span class="label bg-success margin-r-5">' . admin_trans_field('callback_success') . '</span>'];
                    }
                    if ($this->callback_status == 2) {
                        $data[] = [admin_trans_field('callback_status'), '<span class="label bg-red margin-r-5">' . admin_trans_field('callback_failed') . '</span>'];
                    }
                    return bob_show_table_info($data);
                }

                return null;
            });
            $grid->column('amount', admin_trans_field('amount'))->display(function ($value) {
                return bob_unit_format($value);
            });
            $grid->column('pay_amount', admin_trans_field('pay_amount'))->display(function ($value) {
                return bob_unit_format($value);
            });
            $grid->column('actual_amount', admin_trans_field('actual_amount'))->display(function ($value) {
                if ($value > 0) {
                    $data[] = [admin_trans_field('actual_amount'), bob_unit_format($this->actual_amount)];
                    if ($this->usdt_rate > 0 && $this->actual_amount > 0) {
                        $amount = $this->actual_amount - $this->merchant_fee - $this->merchant_extra_fee;
                        $data[] = [admin_trans_field('actual_amount'), floatval($amount)];
                        $data[] = [admin_trans_field('usdt_avg_rate'), floatval($this->usdt_rate)];
                        $data[] = [admin_trans_field('usdt_account_amount'), floatval(bcdiv($amount, $this->usdt_rate, 2))];
                    }
                    return bob_show_table_info($data, [], ['tr-9', 'tr-8', 'tr-7', 'tr-6', 'tr-5', 'tr-4'], 4);
                }

                return null;
            });
            $grid->column('merchant_fee', admin_trans_field('merchant_fee'))->display(function ($value) {
                return bob_unit_format($value);
            })->text(Admin::color()->green());
            $grid->column('merchant_extra_fee', admin_trans_field('merchant_extra_fee'))->display(function ($value) {
                return bob_unit_format($value);
            })->text(Admin::color()->danger());
            $grid->column('merchant_rate', admin_trans_field('merchant_rate'))->display(function ($value) {
                return floatval($value * 100) . '%';
            })->text(Admin::color()->green());
            $grid->column('payment_name', admin_trans_label('payment_type'))->display(function () use ($paymentNames) {
                return $paymentNames[$this->payment_id] ?? null;
            });
            $grid->column('success_time', admin_trans_label('success_time'))->display(function ($value) {
                if ($this->status == 5 && $value > 0) {
                    return date('Y-m-d H:i:s', $value);
                }

                return null;
            });
            $grid->column('created_at', admin_trans_label('created_at'));
            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->disableActions();

            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();
                $params = request()->all();

                $row->column(4, new Card1($params, $beginDate, $endDate));
                $row->column(4, new Card2($params, $beginDate, $endDate));
                $row->column(4, new Card3($params, $beginDate, $endDate));
                $row->column(4, new Card4($params, $beginDate, $endDate));
                $row->column(4, new Card5($params, $beginDate, $endDate));

                return $row;
            });

            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $paymentOptions) {
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
                }
                $filter->expand();
                $filter->panel();
                $filter->equal('ordernumber', admin_trans_label('ordernumber'))->width(3);
                $filter->equal('order_no', admin_trans_label('order_no'))->width(3);
                $filter->equal('status', admin_trans_label('order_status'))->select(collect(config('default.deposite_status'))->transform(function ($item, $key) {
                    return admin_trans_option($key, 'deposit_status') ?: $item;
                })->toArray())->width(3);
                $filter->equal('payment_id', admin_trans_label('payment_type'))->select($paymentOptions)->width(3);
                $filter->between('created_at', admin_trans_label('created_at'))->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
                $filter->whereBetween('success_time', function ($q) {
                    $start = $this->input['start'] ?? null;
                    $end = $this->input['end'] ?? null;
                    if ($start !== null) {
                        $q->where('success_time', '>=', strtotime($start));
                    }
                    if ($end !== null) {
                        $q->where('success_time', '<=', strtotime($end));
                    }
                }, admin_trans_label('success_time'))->datetime()->width(3);
                if (config('app.name') == 'shpay') {
                    $filter->like('pay_name', '付款人名称')->width(3);
                }
            });
        });
    }
}
