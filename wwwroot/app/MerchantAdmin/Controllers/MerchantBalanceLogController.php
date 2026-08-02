<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\MerchantBalanceLog;
use App\Services\Order\OrderCacheService;
use App\Admin\Controllers\CommonController;
use App\MerchantAdmin\Actions\MerchantBalanceLog\ExportData;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\DepositOrder\CacheDepositOrderInfoService;

class MerchantBalanceLogController extends CommonController
{
    private const MERCHANT_BALANCE_TYPE_FALLBACK = [
        13 => '结算手续费',
        15 => '结算冲正',
    ];

    protected $translation = 'merchant-admin-merchant-balance-log';

    protected $disableEdit = true;

    protected $disableCreate = true;

    protected function grid(): Grid
    {
        $balanceTypeOptions = $this->merchantBalanceTypeOptions();

        return Grid::make(MerchantBalanceLog::query()->orderBy('created_at', 'desc'), function (Grid $grid) use ($balanceTypeOptions) {
            $createdAt = request('created_at');
            $beginDate = $createdAt['start'] ?? date('Y-m-d') . ' 00:00:00';
            $endDate = $createdAt['end'] ?? date('Y-m-d') . ' 23:59:59';
            $mid = bob_merchant_user_pid();
            $paymentMap = collect(config('payment'))->keyBy('id');
            $currencyMap = collect(config('default.currency'))->keyBy('id');
            $depositOrders = [];
            $transferOrders = [];
            $getDepositOrder = function ($id) use (&$depositOrders) {
                if (!isset($depositOrders[$id])) {
                    $depositOrders[$id] = app(OrderCacheService::class)->getDepositById($id) ?: [];
                }

                return $depositOrders[$id];
            };
            $getTransferOrder = function ($id) use (&$transferOrders) {
                if (!isset($transferOrders[$id])) {
                    $transferOrders[$id] = app(OrderCacheService::class)->getTransferById($id) ?: [];
                }

                return $transferOrders[$id];
            };

            $grid->model()->where('mid', $mid)->select([
                'id', 'mid', 'ordernumber', 'order_no', 'type', 'type_id', 'payment_id',
                'amount', 'fee', 'currency_id', 'balance_amount', 'usdt_rate',
                'usdt_amount', 'usdt_balance_amount', 'remark', 'is_corre', 'created_at',
            ]);
            $grid->model()->setConstraints(['mid' => $mid]);
            $grid->column('id', 'ID');
            $grid->column('ordernumber', admin_trans_label('ordernumber'))->display(function ($value) use ($getDepositOrder, $getTransferOrder) {
                if ($value) {
                    return $value;
                }

                if (in_array((int) $this->type, [1, 9, 10], true) && $this->type_id > 0) {
                    $depositOrder = $getDepositOrder($this->type_id);
                    $ordernumber = data_get($depositOrder, 'ordernumber');
                    if ($ordernumber) {
                        return bob_link($ordernumber, Admin::app()->getRoute('deposit-orders.index', ['ordernumber' => $ordernumber]));
                    }
                }

                if ($this->type >= 2 && $this->type <= 8 && $this->type_id > 0) {
                    $transferOrder = $getTransferOrder($this->type_id);
                    $type = data_get($transferOrder, 'type', 0);
                    $ordernumber = data_get($transferOrder, 'ordernumber');
                    if ($type == 0) {
                        return bob_link($ordernumber, Admin::app()->getRoute('transfer-orders.index', ['ordernumber' => $ordernumber]));
                    }
                    if ($type == 1) {
                        return bob_link($ordernumber, Admin::app()->getRoute('settlement-orders.index', ['ordernumber' => $ordernumber]));
                    }
                }
            });
            $grid->column('order_no', admin_trans_label('order_no'))->display(function ($value) use ($getDepositOrder, $getTransferOrder) {
                if ($value) {
                    return $value;
                }

                if (in_array((int) $this->type, [1, 9, 10], true) && $this->type_id > 0) {
                    $depositOrder = $getDepositOrder($this->type_id);
                    $orderNo = data_get($depositOrder, 'order_no');
                    if ($orderNo) {
                        return bob_link($orderNo, Admin::app()->getRoute('deposit-orders.index', ['order_no' => $orderNo]));
                    }
                }

                if ($this->type >= 2 && $this->type <= 8 && $this->type_id > 0) {
                    $transferOrder = $getTransferOrder($this->type_id);
                    $type = data_get($transferOrder, 'type', 0);
                    $orderNo = data_get($transferOrder, 'order_no');
                    if ($type == 0) {
                        return bob_link($orderNo, Admin::app()->getRoute('transfer-orders.index', ['order_no' => $orderNo]));
                    }
                    if ($type == 1) {
                        return bob_link($orderNo, Admin::app()->getRoute('settlement-orders.index', ['order_no' => $orderNo]));
                    }
                }
            });
            $grid->column('type', admin_trans_field('type'))->display(function () use ($balanceTypeOptions) {
                return $balanceTypeOptions[(int) $this->type] ?? (string) $this->type;
            })->dot(bob_colors());
            $grid->column('payment_id', '通道编码')->display(function ($value) use ($paymentMap) {
                if (intval($value) <= 0) {
                    return '';
                }

                $result = $paymentMap->get(intval($value));
                if (!empty($result)) {
                    return $result['name'] . '【' . $result['code'] . '】';
                }
            });
            $grid->column('amount', admin_trans_field('amount'));
            $grid->column('fee', admin_trans_field('fee'));
            $grid->column('currency_id', admin_trans_field('currency'))->display(function () use ($currencyMap) {
                return data_get($currencyMap->get($this->currency_id), 'name');
            });
            $grid->column('balance_amount', admin_trans_field('balance_amount'));
            $merchant = app(CacheMerchantBaseInfoService::class)->excute($mid);
            if (data_get($merchant, 'is_usdt_ava_rate') == 1) {
                $grid->column('usdt_rate', admin_trans_field('usdt_rate'))->display(function ($value) {
                    return floatval($value);
                });
                $grid->column('usdt_amount', admin_trans_field('usdt_amount'))->display(function ($value) {
                    return floatval($value);
                });
                $grid->column('usdt_balance_amount', admin_trans_field('usdt_balance_amount'))->display(function ($value) {
                    return floatval($value);
                });
            }
            $grid->column('remark', admin_trans_field('remark'));
            $grid->column('is_corre', '冲正状态')->display(function ($value) {
                return intval($value) === 1 ? '已冲正' : '';
            })->label([
                1 => 'danger',
            ]);
            $grid->column('created_at', admin_trans_label('created_at'));
            $grid->disableActions();
            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });
            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $mid, $balanceTypeOptions) {
                $filter->equal('id')->width(3);
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
                }
                $filter->expand();
                $filter->panel();
                $filter->where('ordernumber', function ($query) {
                    if (mb_strpos($this->input, 'T') === 0) {
                        $result = app(OrderCacheService::class)->getTransferByOrdernumber($this->input);
                        if (!empty($result) && isset($result['id'])) {
                            $query->where('type_id', $result['id'])->where('type', '>=', 2)->where('type', '<', 6);
                        }
                    }
                    if (mb_strpos($this->input, 'D') === 0) {
                        $result = app(CacheDepositOrderInfoService::class)->excute($this->input);
                        if (!empty($result) && isset($result['id'])) {
                            $query->where('type_id', $result['id'])->where('type', 1);
                        }
                    }
                    if (mb_strpos($this->input, 'S') === 0) {
                        $result = app(OrderCacheService::class)->getTransferByOrdernumber($this->input);
                        if (!empty($result) && isset($result['id'])) {
                            $query->where('type_id', $result['id'])->whereIn('type', [6, 7, 8, 5]);
                        }
                    }
                }, admin_trans_label('ordernumber'))->width(3);
                $filter->where('order_no', function ($query) use ($mid) {
                    $result = app(OrderCacheService::class)->getTransferByMerchantOrder($mid, $this->input);
                    if (!empty($result) && isset($result['id'])) {
                        $query->where('type_id', $result['id'])->where('type', '>=', 2)->where('type', '<', 9);
                    } else {
                        $result = app(CacheDepositOrderInfoService::class)->excute($this->input, $mid);
                        if (!empty($result) && isset($result['id'])) {
                            $query->where('type_id', $result['id'])->where('type', 1);
                        }
                    }
                }, admin_trans_label('order_no'))->width(3);
                $filter->equal('type', admin_trans_field('type'))->select($balanceTypeOptions)->width(3);
                $filter->between('created_at', admin_trans_label('created_at'))->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
            });
            $grid->disableCreateButton();
        });
    }

    private function merchantBalanceTypeOptions(): array
    {
        $types = config('default.merchant_balance_type', []);
        foreach (self::MERCHANT_BALANCE_TYPE_FALLBACK as $type => $label) {
            $types[$type] = $types[$type] ?? $label;
        }

        return collect($types)->mapWithKeys(function ($item, $key) {
            $translated = admin_trans_option($key, 'merchant_balance_type');
            $label = $translated && (string) $translated !== (string) $key ? $translated : $item;

            return [(int) $key => $label ?: (string) $key];
        })->toArray();
    }
}
