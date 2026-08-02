<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use App\Models\ReportMerchant;
use Dcat\Admin\Widgets\Modal;
use App\Models\MerchantPayment;
use App\Extendtions\Dcat\Widgets\BobTable;
use App\MerchantAdmin\Form\LookSecrectDetail;
use Dcat\Admin\Http\Controllers\AdminController;
use App\Admin\Metrics\MerchantAdmin\DepositOrders;
use App\Admin\Metrics\MerchantAdmin\TransferOrders;

class HomeController extends AdminController
{
    public function index(Content $content): Content
    {
        return $content->title(admin_trans('menu.titles.index'))
            ->body(function (Row $row) {
                $mid = bob_merchant_user_pid();
                $merchant = MerchantInfo::where('merchant_user_id', $mid)->first([
                    'balance_amount', 'available_balance', 'settlement_amount', 'freeze_amount',
                    'available_usdt_balance', 'is_usdt_ava_rate', 'usdt_ava_rate',
                ]);
                $result = ReportMerchant::where('mid', $mid)->where('date_add', date('Y-m-d', strtotime('-1 day')))->first([
                    'deposit_order_number_success', 'deposit_order_total_fee', 'deposit_order_total_amount', 'deposit_order_number_total',
                    'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_total_amount', 'transfer_order_total_fee',
                ]);

                $row->column(12, function (Column $column) use ($merchant) {
                    $column->row(view('merchant-admin.home.block3', [
                        'balance_amount' => floatval(data_get($merchant, 'balance_amount', 0)),
                        'available_balance' => floatval(data_get($merchant, 'available_balance', 0)),
                        'settlementing_amount' => floatval(data_get($merchant, 'settlement_amount', 0)),
                        'freeze_amount' => floatval(data_get($merchant, 'freeze_amount', 0)),
                        'available_usdt_balance' => floatval(data_get($merchant, 'available_usdt_balance', 0)),
                        'is_usdt_ava_rate' => data_get($merchant, 'is_usdt_ava_rate', 0),
                        'usdt_ava_rate' => floatval(data_get($merchant, 'usdt_ava_rate', 0)),
                    ]));
                });

                $defaultData = [
                    'order_number_total' => 0,
                    'order_total_amount' => 0,
                    'order_total_fee' => 0,
                    'order_success_rate' => 0,
                ];

                $row->column(12, function (Column $column) use ($row, $defaultData, $result) {
                    $data = $defaultData;
                    if ($result) {
                        $data['order_number_total'] = $result->deposit_order_number_total;
                        $data['order_total_amount'] = $result->deposit_order_total_amount;
                        $data['order_total_fee'] = $result->deposit_order_total_fee;
                        $data['order_success_rate'] = bob_percent($result->deposit_order_number_success, $result->deposit_order_number_total);
                    }
                    $row->column(6, new DepositOrders());
                    $row->column(6, new Card(admin_trans_label('yestoday_deposit_count_title'), view('merchant-admin.home.block', $data)));
                });

                $row->column(12, function (Column $column) use ($row, $defaultData, $result) {
                    $data = $defaultData;
                    if ($result) {
                        $data['order_number_total'] = $result->transfer_order_number_total;
                        $data['order_total_amount'] = $result->transfer_order_total_amount;
                        $data['order_total_fee'] = $result->transfer_order_total_fee;
                        $data['order_success_rate'] = bob_percent($result->transfer_order_number_success, $result->transfer_order_number_total);
                    }
                    $row->column(6, new TransferOrders());
                    $row->column(6, new Card(admin_trans_label('yestoday_transfer_count_title'), view('merchant-admin.home.block', $data)));
                });
            });
    }


    public function information(Content $content): Content
    {
        $script = <<<'JS'
$('.copy-appkey, .copy-appsecret').off('click').on('click', function () {
    var content = $(this).data('content');
    var $temp = $('<input>');
    $("body").append($temp);
    $temp.val(content).select();
    document.execCommand("copy");
    $temp.remove();
    Dcat.success('复制成功');
});
JS;
        Admin::script($script);

        $info = MerchantInfo::find(bob_merchant_user_pid());
        $paymentDisplayName = fn (array $payment): string => $this->paymentDisplayName($payment);

        return $content->translation($this->translation())->title(admin_trans_label('Information'))
            ->body(function (Row $row) use ($info, $paymentDisplayName) {
                $row->column(6, function (Column $column) use ($info) {
                    $form = new Form();
                    $form->disableResetButton();
                    $form->disableSubmitButton();
                    $form->display('merchant_coder', admin_trans_label('merchant_coder'))->default(data_get($info, 'coder'))->width(6, 3);
                    $form->display('merchant_name', admin_trans_label('merchant_name'))->default(data_get($info, 'name'))->width(6, 3);
                    $form->display('currency_name', admin_trans_label('currency_name'))->default(data_get(collect(config('default.currency'))->where('id', data_get($info, 'currency_id'))->first(), 'name'))->width(6, 3);
                    $form->display('merchant_google', admin_trans_label('merchant_google'))->default(view('merchant-admin.home.google-status', ['google_two_fa_enable' => Admin::user()->google_two_fa_enable, 'google_two_fa_bind' => Admin::user()->google_two_fa_bind]))->width(6, 3);
                    $card = new Card(admin_trans_label('merchant_base_info'), $form);
                    $card->withHeaderBorder();
                    $card->style('height:400px');
                    $column->row($card);
                });
                $row->column(6, function (Column $column) use ($info) {
                    $form = new Form();
                    $form->disableResetButton();
                    $form->disableSubmitButton();
                    $form->display('merchant_id', admin_trans_label('merchant_id'))->default(bob_merchant_user_pid())->width(3, 3);

                    if (request()->session()->has('look_secret_detail')) {
                        $form->text('merchant_app_key', admin_trans_label('merchant_app_key'))->default(data_get($info, 'appkey'))->disable()->width(6, 3)->prepend('<span style="cursor: pointer;" class="copy-appkey" data-content="' . data_get($info, 'appkey') . '">' . admin_trans_label('copy') . '</span>');
                        $form->text('merchant_app_secect', admin_trans_label('merchant_app_secect'))->default(data_get($info, 'appsecret'))->disable()->width(6, 3)->prepend('<span style="cursor: pointer;" class="copy-appsecret" data-content="' . data_get($info, 'appsecret') . '">' . admin_trans_label('copy') . '</span>');
                        request()->session()->forget('look_secret_detail');
                    } else {
                        $lookSecrectDetailModal = Modal::make()->lg()->title(admin_trans_label('look_merchant_secret'))->body(LookSecrectDetail::make())->button('<button class="btn btn-custom">' . __('home.labels.look_reset_merchant_secret') . '</button>');
                        $form->display('merchant_app_key', admin_trans_label('merchant_app_key'))->default(bob_str_replace(data_get($info, 'appkey')))->width(6, 3);
                        $form->text('merchant_app_secect', admin_trans_label('merchant_app_secect'))->default(bob_str_replace(data_get($info, 'appsecret')))->disable()->width(6, 3)->append(Admin::user()->can('merchant_reset_secret') ? $lookSecrectDetailModal : '');
                    }
                    $card = new Card(admin_trans_label('api_info'), $form);
                    $card->withHeaderBorder();
                    $card->style('height:400px');
                    $column->row($card);
                });
                $row->column(12, function (Column $column) use ($paymentDisplayName) {
                    $headers = [admin_trans_label('payment_name'), admin_trans_label('payment_code'), admin_trans_label('status'), admin_trans_label('payment_rate'), admin_trans_label('min_limit_amount'), admin_trans_label('max_limit_amount')];
                    $payments = collect(config('payment'))->keyBy('id');
                    $rows = MerchantPayment::where('merchant_user_id', bob_merchant_user_pid())->get(['payment_id', 'status', 'pay_rate', 'min_limit_amount', 'max_limit_amount'])->map(function ($item) use ($payments, $paymentDisplayName) {
                        $payment = $payments->get($item->payment_id, []);

                        return [
                            $paymentDisplayName((array)$payment),
                            data_get($payment, 'code'),
                            optional(['<span class="label bg-red">' . admin_trans_option(0, 'status_text') . '</span>', '<span class="label bg-green">' . admin_trans_option(1, 'status_text') . '</span>'])[$item->status],
                            floatval($item->pay_rate) . '%',
                            $item->min_limit_amount,
                            $item->max_limit_amount,
                        ];
                    });
                    $table = new BobTable($headers, $rows, 'custom-data-table data-table');
                    $table->withBorder();
                    $card = new Card(admin_trans_label('payment_list'), $table);
                    $card->withHeaderBorder();
                    $column->row($card);
                });
            });
    }

    private function paymentDisplayName(array $payment): string
    {
        $name = (string)data_get($payment, 'name', '');
        $code = (string)data_get($payment, 'code', '');
        if ($code === '') {
            return $name;
        }

        $key = 'payment.options.names.' . $code;
        $translated = __($key);

        return $translated === $key ? $name : $translated;
    }
}
