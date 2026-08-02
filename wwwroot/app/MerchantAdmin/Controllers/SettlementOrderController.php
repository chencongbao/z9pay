<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use App\Models\TransferOrder;
use Dcat\Admin\Layout\Content;
use Illuminate\Support\Facades\Storage;
use App\Admin\Controllers\CommonController;
use App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card1;
use App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card2;
use App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card3;
use App\Admin\Metrics\MerchantAdmin\SettlementOrder\Card4;
use App\MerchantAdmin\Actions\SettlementOrder\ExportData;
use App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm;

class SettlementOrderController extends CommonController
{
    protected $disableEdit = true;

    public $disableCreate = true;

    protected function grid(): Grid
    {
        return Grid::make(TransferOrder::with(['merchant' => function ($query) {
            $query->select('id', 'username');
        }, 'merchant_info' => function ($query) {
            $query->select('merchant_user_id', 'coder');
        }, 'bank' => function ($query) {
            $query->select('id', 'name');
        }]), function (Grid $grid) {
            $mid = bob_merchant_user_pid();
            $createdAt = request('created_at');
            $beginDate = $createdAt['start'] ?? date('Y-m-d') . ' 00:00:00';
            $endDate = $createdAt['end'] ?? date('Y-m-d') . ' 23:59:59';

            $grid->model()->where('mid', $mid)->where('type', 1)->select([
                'id', 'order_no', 'ordernumber', 'status',
                'callback_count', 'callback_time', 'callback_status',
                'amount', 'actual_amount', 'merchant_fee', 'merchant_extra_fee', 'merchant_rate',
                'pay_certificate_1', 'pay_certificate_2', 'pay_certificate_3',
                'bank_name', 'bank_code', 'holder_name', 'card_no', 'bank_province', 'bank_city', 'bank_branch',
                'success_time', 'created_at', 'mid', 'type', 'bank_id', 'merchant_action_id', 'remark',
            ]);
            $grid->model()->setConstraints(['mid' => $mid, 'type' => 1]);
            $grid->column('order_no', admin_trans_label('order_no'))->copyable();
            $grid->column('ordernumber', admin_trans_label('ordernumber'))->copyable();
            $grid->column('status', admin_trans_label('order_status'))->display(function () {
                return bob_show_label(admin_trans_option($this->status, 'transfer_status'), $this->status, 3);
            });
            $grid->column('amount', admin_trans_field('amount'))->display(function ($value) {
                return bob_unit_format($value);
            });
            $grid->column('actual_amount', admin_trans_field('actual_amount'))->display(function ($value) {
                return bob_unit_format($value);
            })->text(Admin::color()->green());
            $grid->column('merchant_fee', admin_trans_field('merchant_fee'))->display(function ($value) {
                return bob_unit_format($value);
            })->text(Admin::color()->green());
            $grid->column('merchant_extra_fee', admin_trans_field('merchant_extra_fee'))->display(function ($value) {
                return bob_unit_format($value);
            })->text(Admin::color()->danger());
            $grid->column('merchant_rate', admin_trans_field('merchant_rate'))->display(function ($value) {
                return floatval($value * 100) . '%';
            })->text(Admin::color()->green());
            $grid->column('certificate_info', admin_trans_field('certificate_info'))->display(function () {
                if ($this->status == 4) {
                    $data = [];
                    if (!empty($this->pay_certificate_1)) {
                        $data[] = ['<a href="' . Storage::disk('admin')->url($this->pay_certificate_1) . '" target="_blank">' . admin_trans_field('pay_certificate_1') . '</a>'];
                    }
                    if (!empty($this->pay_certificate_2)) {
                        $data[] = ['<a href="' . Storage::disk('admin')->url($this->pay_certificate_2) . '" target="_blank">' . admin_trans_field('pay_certificate_2') . '</a>'];
                    }
                    if (!empty($this->pay_certificate_3)) {
                        $data[] = ['<a href="' . Storage::disk('admin')->url($this->pay_certificate_3) . '" target="_blank">' . admin_trans_field('pay_certificate_3') . '</a>'];
                    }
                    if (!empty($data)) {
                        return bob_show_table_info($data);
                    }
                }

                return null;
            });
            $grid->column('collection_info', admin_trans_field('collection_info'))->display(function () {
                $bankName = $this->bank_name ?: data_get($this->bank, 'name');
                $data[] = [admin_trans_field('bank_name'), $bankName];
                $data[] = [admin_trans_field('bank_code'), $this->bank_code];
                $data[] = [admin_trans_field('holder_name'), $this->holder_name];
                $data[] = [admin_trans_field('card_no'), $this->card_no];
                $data[] = [admin_trans_field('bank_province'), $this->bank_province];
                $data[] = [admin_trans_field('bank_city'), $this->bank_city];
                $data[] = [admin_trans_field('bank_branch'), $this->bank_branch];

                return bob_show_table_info($data);
            });
            $grid->column('remark', admin_trans_field('remark'));
            $grid->column('merchant_action_id', admin_trans_field('merchant_action_id'))->display(function () {
                return data_get($this->merchant, 'username') . '@' . data_get($this->merchant_info, 'coder');
            });
            $grid->column('created_at', admin_trans_label('created_at'));
            $grid->column('success_time', admin_trans_label('success_time'))->display(function ($value) {
                if ($this->status == 4 && $value > 0) {
                    return date('Y-m-d H:i:s', $value);
                }

                return null;
            });
            $grid->disableCreateButton();
            $grid->disableActions();
            $grid->tools(function (Grid\Tools $tools) {
                if (Admin::user()->can('merchant-settlement-order-add')) {
                    $tools->append(view('merchant-admin.settlement-order.add'));
                }
                $tools->append(new ExportData());
            });

            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();
                $params = request()->all();

                $row->column(3, new Card1($params, $beginDate, $endDate));
                $row->column(3, new Card2($params, $beginDate, $endDate));
                $row->column(3, new Card3($params, $beginDate, $endDate));
                $row->column(3, new Card4($params, $beginDate, $endDate));

                return $row;
            });

            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate) {
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
                }
                $filter->expand();
                $filter->panel();
                $filter->equal('ordernumber', admin_trans_label('ordernumber'))->width(3);
                $filter->equal('order_no', admin_trans_label('order_no'))->width(3);
                $filter->equal('status', admin_trans_label('order_status'))->select(collect(config('default.transfer_status'))->transform(function ($item, $key) {
                    return admin_trans_option($key, 'transfer_status') ?: $item;
                })->toArray())->width(3);
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
                $filter->where('holder_name', function ($query) {
                    $query->where('card_no', 'like', "%{$this->input}%")
                        ->orWhere('holder_name', 'like', "%{$this->input}%")->orWhere('bank_code', 'like', "%{$this->input}%");
                }, __('settlement-order.fields.collection_info'))->placeholder(__('settlement-order.fields.collection_info_placeholder'))->width(3);
            });
        });
    }

    public function apply(Content $content): Content
    {
        if (!Admin::user()->can('merchant-settlement-order-add')) {
            abort(403, __('admin.deny'));
        }

        $card = new Card('', new ApplySettlementOrderForm());

        return $content->title(admin_trans_field('add_button'))->body(new Card(__('admin.create'), $card));
    }
}
