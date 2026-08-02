<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\App;
use App\Admin\Extensions\Layout\LeftSide;
use App\Services\Order\OrderCacheService;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Actions\Grid\MerchantBalanceLog\LogCorre;
use App\Admin\Actions\Grid\MerchantBalanceLog\AddBalance;
use App\Admin\Actions\Grid\MerchantBalanceLog\ExportData;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Admin\Actions\Grid\MerchantBalanceLog\ReduceBalance;
use App\Services\Cache\AdminUser\GetAdminUserInfoByIdService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class MerchantBalanceLogController extends CommonController
{
    protected $disableEdit = true;

    protected $disableCreate = true;

    protected function grid(): Grid
    {
        $createdAt = request('created_at');
        $beginDate = $createdAt['start'] ?? date('Y-m-d') . ' 00:00:00';
        $endDate = $createdAt['end'] ?? date('Y-m-d') . ' 23:59:59';
        if ($createdAt === null) {
            request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
        }

        $merchantUserId = (int) request('mid', 0);
        $adminUser = Admin::user();
        $canAddBalance = $adminUser->can('merchant-balance-log-add');
        $canReduceBalance = $adminUser->can('merchant-balance-log-reduce');
        $canCorreBalanceLog = $adminUser->can('merchant-balance-log-corre');
        $isAdministrator = $adminUser?->isAdministrator() === true;
        $showUsdt = payment_app_name() === 'rdspay' || $isAdministrator;
        $merchantBalanceTypes = config('default.merchant_balance_type', []);
        $settlementModes = config('default.settlement_mode', []);
        $paymentMap = collect(config('payment', []))->keyBy('id');
        $currencyMap = collect(config('default.currency', []))->keyBy('id');
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $depositOrderInfoService = App::make(OrderCacheService::class);
        $transferOrderInfoService = App::make(OrderCacheService::class);
        $adminUserInfoService = App::make(GetAdminUserInfoByIdService::class);
        $merchantListInfoService = App::make(GetMerchantListInfoService::class);
        $self = $this;

        $query = MerchantBalanceLog::query()
            ->select([
                'id',
                'ordernumber',
                'mid',
                'type',
                'type_id',
                'currency_id',
                'payment_id',
                'amount',
                'fee',
                'balance_amount',
                'usdt_rate',
                'usdt_amount',
                'usdt_balance_amount',
                'created_at',
                'remark',
                'admin_id',
                'status',
                'settlement_mode',
                'settlement_time',
                'is_corre',
                'corre_log_id',
            ]);

        return Grid::make($query, function (Grid $grid) use (
            $self,
            $endDate,
            $showUsdt,
            $beginDate,
            $paymentMap,
            $currencyMap,
            $settlementModes,
            $isAdministrator,
            $merchantUserId,
            $adminUserInfoService,
            $merchantListInfoService,
            $merchantBaseInfoService,
            $merchantBalanceTypes,
            $depositOrderInfoService,
            $transferOrderInfoService,
            $canAddBalance,
            $canReduceBalance,
            $canCorreBalanceLog
        ) {
            if ($merchantUserId > 0) {
                $result = $merchantBaseInfoService->excute($merchantUserId);
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . optional($result)->offsetGet('bname') . '</button>');
            }

            $grid->column('id', '编号')->center();
            $grid->column('ordernumber', '交易单号')->display(function ($value) use ($self, $depositOrderInfoService, $transferOrderInfoService) {
                return $self->formatOrdernumberLink($value, (int) $this->type, (int) $this->type_id, $depositOrderInfoService, $transferOrderInfoService);
            })->center();
            $grid->column('merchant_info_bname', '所属商户')->display(function () use ($merchantBaseInfoService) {
                if ($this->mid > 0) {
                    $result = $merchantBaseInfoService->excute($this->mid);
                    if (!empty($result)) {
                        return optional($result)->offsetGet('bname');
                    }
                }

                return '';
            });
            $grid->column('type', '交易类型')->display(function () use ($merchantBalanceTypes) {
                return $merchantBalanceTypes[$this->type] ?? '';
            })->dot(bob_colors())->center();
            $grid->column('payment_id', '通道编码')->display(function ($value) use ($paymentMap) {
                if (intval($value) <= 0) {
                    return '';
                }

                $result = $paymentMap->get(intval($value));
                if (!empty($result)) {
                    return $result['name'] . '【' . $result['code'] . '】';
                }

                return '';
            })->center();
            $grid->column('currency_id')->display(function () use ($currencyMap) {
                return data_get($currencyMap->get($this->currency_id), 'name', '');
            })->center();
            $grid->column('amount', '交易金额')->amount()->center();
            $grid->column('fee', '手续费')->display(function ($value) {
                return $value > 0 ? -$value : abs($value);
            })->amount()->center();
            $grid->column('balance_amount', '账户余额')->amount()->center();
            if ($showUsdt) {
                $grid->column('usdt_rate', 'USDT费率')->display(function ($value) {
                    return floatval($value);
                });
                $grid->column('usdt_amount', 'USDT金额')->display(function ($value) {
                    return floatval($value);
                });
                $grid->column('usdt_balance_amount', 'USDT账户余额')->display(function ($value) {
                    return floatval($value);
                });
            }
            $grid->column('created_at', '交易时间')->center();
            $grid->column('remark');
            $grid->column('is_corre', '冲正状态')->display(function ($value) {
                return intval($value) === 1 ? '已冲正' : '';
            })->label([
                1 => 'danger',
            ])->center();
            $grid->column('corre_log_id', '冲正流水ID')->display(function ($value) {
                if (intval($value) <= 0) {
                    return '';
                }

                return bob_link((string) intval($value), Admin::app()->getRoute('merchant-balance-logs.index', [
                    'id' => intval($value),
                ]));
            })->center();
            $grid->column('admin_user_name', '操作人')->display(function () use ($adminUserInfoService) {
                if ($this->admin_id > 0) {
                    $result = $adminUserInfoService->excute($this->admin_id);
                    if (!empty($result)) {
                        return optional($result)->offsetGet('name');
                    }
                }

                return '';
            });
            if ($isAdministrator) {
                $grid->column('status', '处理状态')->using(['未处理', '已处理'])->label(['danger', 'success'])->center();
                $grid->column('settlement_mode', '结算模式')->using($settlementModes)->label(['default', 'danger', 'warning'])->center();
                $grid->column('settlement_time', '处理时间')->display(function ($value) {
                    return $value > 0 ? date('Y-m-d H:i:s', $value) : '';
                })->center();
            }
            $grid->actions(function (Grid\Displayers\Actions $actions) use ($canCorreBalanceLog) {
                $actions->disableView();
                $actions->disableEdit();
                $actions->disableDelete();

                if ($canCorreBalanceLog && in_array((int) $actions->row['type'], [11, 12], true) && (int) $actions->row['is_corre'] === 0) {
                    $actions->append(new LogCorre());
                }
            });
            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });
            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $merchantBaseInfoService, $merchantBalanceTypes) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(4);
                $filter->equal('type', '交易类型')->select($merchantBalanceTypes)->width(4);
                $filter->between('created_at', '创建时间')->datetime()->width(4)->default(['start' => $beginDate, 'end' => $endDate]);
                $filter->equal('ordernumber', '系统单号')->placeholder('请输入完整的交易单号')->width(4);
                $filter->equal('mid', '商户')->select(function ($mid) use ($merchantBaseInfoService) {
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);
                        if (!empty($result)) {
                            return [$result['merchant_user_id'] => $result['bname']];
                        }
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(4);
                $filter->like('remark', '备注')->width(4);
            });
            if ($merchantUserId > 0) {
                if ($canAddBalance) {
                    $grid->tools()->append(new AddBalance($merchantUserId));
                }
                if ($canReduceBalance) {
                    $grid->tools()->append(new ReduceBalance($merchantUserId));
                }
            }
            $grid->disableCreateButton();
            $grid->wrap(function (Renderable $view) use ($merchantUserId, $merchantListInfoService) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($merchantUserId, $merchantListInfoService) {
                    $merchantInfoResult = $merchantListInfoService->excute();
                    $merchantInfoResult = array_filter($merchantInfoResult, function ($item) {
                        return (int) $item['status'] === 1;
                    });
                    $left = new LeftSide();
                    $left->title('商户列表')->field('mid')->default($merchantUserId)->prependAll('全部商户')->data($merchantInfoResult);
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

    private function formatOrdernumberLink($value, int $type, int $typeId, OrderCacheService $depositOrderInfoService, OrderCacheService $transferOrderInfoService)
    {
        if ($value) {
            return $value;
        }

        if (in_array($type, [1, 9, 10], true) && $typeId > 0) {
            $depositOrder = $depositOrderInfoService->getDepositById($typeId);
            $ordernumber = optional($depositOrder)->offsetGet('ordernumber');
            if ($ordernumber) {
                return bob_link($ordernumber, Admin::app()->getRoute('deposit-orders.index', $this->buildOrderQuery($ordernumber, optional($depositOrder)->offsetGet('created_at'))));
            }
        }

        if (in_array($type, [2, 3, 4, 5, 6, 7, 8, 13], true) && $typeId > 0) {
            $transferOrder = $transferOrderInfoService->getTransferById($typeId);
            $ordernumber = optional($transferOrder)->offsetGet('ordernumber');
            if ($ordernumber) {
                $route = ((int) (optional($transferOrder)->offsetGet('type') ?: 0)) === 1 ? 'settlement-orders.index' : 'transfer-orders.index';

                return bob_link($ordernumber, Admin::app()->getRoute($route, $this->buildOrderQuery($ordernumber, optional($transferOrder)->offsetGet('created_at'))));
            }
        }

        return '';
    }

    private function buildOrderQuery(string $ordernumber, $createdAt): array
    {
        $date = date('Y-m-d', strtotime((string) $createdAt));

        return [
            'ordernumber' => $ordernumber,
            'created_at' => [
                'start' => $date . ' 00:00:00',
                'end' => $date . ' 23:59:59',
            ],
        ];
    }
}
