<?php

namespace App\Admin\Controllers\Merchant;

use Throwable;
use Dcat\Admin\Form;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use App\Models\Channel;
use App\Models\BankCode;
use Dcat\Admin\Layout\Row;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Widgets\Table;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Widgets\Modal;
use App\Models\MerchantPayment;
use App\Rules\DecimalTwoPlaces;
use App\Models\AgentUserRelation;
use Illuminate\Support\HtmlString;
use Dcat\Admin\Http\Auth\Permission;
use App\Admin\Extensions\Layout\LeftSide;
use App\Admin\Controllers\CommonController;
use Illuminate\Contracts\Support\Renderable;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Admin\Actions\Grid\MerchantPayment\BatchUpdateSetting;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Admin\Actions\Grid\MerchantPayment\BatchUpdateRateSetting;
use App\Services\Cache\MerchantPayment\RefreshMerchantPaymentRateCacheService;

class PaymentController extends CommonController
{

    protected $disableDestroy = false;

    protected function grid()
    {
        $canCreate = Admin::user()->can('merchant-payment-create');
        $canEdit = Admin::user()->can('merchant-payment-edit');
        $canDelete = Admin::user()->can('merchant-payment-delete');
        $canStatus = Admin::user()->can('merchant-payment-status');
        $canBatchLimit = Admin::user()->can('merchant-payment-batch-limit');
        $canBatchRate = Admin::user()->can('merchant-payment-batch-rate');
        $merchantUserId = (int) request()->input('merchant_user_id', 0);
        $paymentOptions = $this->paymentOptions();
        $paymentMap = collect(config('payment'))->keyBy('id');
        $merchantBaseInfoService = app(CacheMerchantBaseInfoService::class);
        $merchantList = array_values(array_filter(app(GetMerchantListInfoService::class)->excute(), fn ($item) => (int) ($item['status'] ?? 0) === 1));
        $agentOptions = bob_build_select_options(collect(app(GetMerchantAgentListService::class)->excute())->toArray());
        $bankMap = BankCode::query()->get(['id', 'name', 'code'])->keyBy('id');
        $channelMap = Channel::query()->get(['id', 'code', 'name'])->keyBy('id');
        $controller = $this;

        return Grid::make(MerchantPayment::with(['merchant_info']), function (Grid $grid) use ($canCreate, $canEdit, $canDelete, $canStatus, $canBatchLimit, $canBatchRate, $merchantUserId, $paymentOptions, $paymentMap, $merchantBaseInfoService, $merchantList, $agentOptions, $bankMap, $channelMap, $controller) {
            if ($merchantUserId > 0) {
                $grid->model()->where('merchant_user_id', $merchantUserId);
                $merchantInfo = $merchantBaseInfoService->excute($merchantUserId);
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . optional($merchantInfo)->offsetGet('bname') . '</button>');
            }
            $grid->model()->setConstraints(['merchant_user_id' => $merchantUserId]);
            $grid->column('id')->sortable()->center();
            $grid->column('payment_name', '通道编码')->display(function () use ($paymentMap) {
                $payment = $paymentMap->get($this->payment_id);
                if (!empty($payment)) {
                    return $payment['name'] . "【" . $payment['code'] . "】";
                }
            });
            $grid->column('merchant_info.bname', "所属商户");
            if ($canStatus) {
                $grid->column('status', '启用状态')->switch()->center();
            } else {
                $grid->column('status', '启用状态')->display(fn ($value) => config('default.status_text')[$value] ?? $value)->center();
            }
            $grid->column('pay_rate', '支付费率')->display(function () use ($bankMap, $channelMap, $controller) {
                if ((int) $this->payment_id !== 7) {
                    $channelRateButton = $controller->renderDepositChannelRates($this->transfer_rates, $channelMap);

                    return new HtmlString('<div style="line-height:1.8;">代收费率：<span style="color:#21b978;font-weight:700;">' . bob_amount_format($this->pay_rate) . '%</span> ' . $channelRateButton . '</div>');
                }

                $bankRateButton = $controller->renderTransferBankRates($this->transfer_rates, $bankMap, $channelMap);

                return new HtmlString('<div style="line-height:1.8;">代付费率：<span style="color:#21b978;font-weight:700;">' . bob_amount_format($this->pay_rate) . '%</span> ' . $bankRateButton . '</div>');
            })->center();
            $grid->column('agent1_rate', "一级代理费率")->display(function ($value) {
                return bob_amount_format($value) . "%";
            })->center();
            $grid->column('agent2_rate', "二级代理费率")->display(function ($value) {
                return bob_amount_format($value) . "%";
            })->center();
            $grid->column('agent3_rate', "三级代理费率")->display(function ($value) {
                return bob_amount_format($value) . "%";
            })->center();
            $grid->column('min-max_limit_amount', '单笔限额')->display(function ($value) {
                return bob_unit_format($this->min_limit_amount) . " - " . bob_unit_format($this->max_limit_amount);
            })->center();
            $grid->filter(function (Grid\Filter $filter) use ($merchantUserId, $paymentOptions, $merchantBaseInfoService, $agentOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(2);
                $filter->equal('payment_id', "通道类型")->select($paymentOptions)->width(3);
                $filter->equal('status', "启用状态")->select(config('default.status_text'))->width(2);
                $filter->equal('merchant_user_id', "商户")->select(function ($mid) use ($merchantUserId, $merchantBaseInfoService) {
                    $mid = $mid ?: $merchantUserId;
                    if ($mid) {
                        $merchantInfo = $merchantBaseInfoService->excute($mid);
                        if (!empty($merchantInfo)) {
                            return [$merchantInfo['merchant_user_id'] => $merchantInfo['bname']];
                        }
                    }

                    return [];
                })->ajax("/ajax/getMerchantList", "merchant_user_id", "bname")->width(3)->default($merchantUserId);
                $filter->where('agent_user_id', function ($q) {
                    $q->whereIn('agent_user_id', AgentUserRelation::where('parent_id', $this->input)->pluck('child_id'));
                }, "所属代理")->select($agentOptions)->width(3);
            });

            $grid->showRowSelector();
            if ($canDelete) {
                $grid->showBatchDelete();
            } else {
                $grid->disableDeleteButton();
                $grid->disableBatchDelete();
            }
            $grid->withBorder();
            if ($merchantUserId == 0 || !$canCreate) {
                $grid->disableCreateButton();
            }
            $grid->actions(function ($actions) use ($canEdit, $canDelete) {
                if (!$canEdit) {
                    $actions->disableEdit();
                }
                if (!$canDelete) {
                    $actions->disableDelete();
                }
            });
            $grid->tools(function ($tools) use ($canBatchLimit, $canBatchRate) {
                if ($canBatchLimit) {
                    $tools->append(new BatchUpdateSetting());
                }
                if ($canBatchRate) {
                    $tools->append(new BatchUpdateRateSetting());
                }
            });

            $grid->wrap(function (Renderable $view) use ($merchantUserId, $merchantList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($merchantUserId, $merchantList) {
                    $left = new LeftSide();
                    $left->title("商户列表")->field("merchant_user_id")->default($merchantUserId)->prependAll('全部商户')->data($merchantList);
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

    public function store()
    {
        Permission::check('merchant-payment-create');

        return parent::store();
    }

    public function update($id)
    {
        Permission::check($this->isStatusSwitchRequest() ? 'merchant-payment-status' : 'merchant-payment-edit');

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('merchant-payment-delete');

        return parent::destroy($id);
    }

    protected function form()
    {
        $paymentOptions = $this->paymentOptions();
        $bankOptions = BankCode::query()->get(['id', 'name', 'code'])->pluck('bname', 'id');
        $channelOptions = Channel::query()->where('status', 1)->orderBy('id', 'desc')->get(['id', 'code', 'name'])->mapWithKeys(fn ($channel) => [$channel->id => '【#' . $channel->id . '】【' . $channel->code . '】' . $channel->name]);
        $rateRules = ['numeric', 'between:0,100', new DecimalTwoPlaces()];
        $amountRules = ['numeric', 'between:0,999999999', new DecimalTwoPlaces()];
        $requiredRateRules = array_merge(['required'], $rateRules);
        $requiredAmountRules = array_merge(['required'], $amountRules);
        $controller = $this;
        $addTransferRateTable = function ($form) use ($bankOptions, $channelOptions, $rateRules, $amountRules) {
            $form->table("transfer_rates", "商户渠道银行代付费率", function ($table) use ($bankOptions, $channelOptions, $rateRules, $amountRules) {
                $table->select('channel_id', "所属渠道")->options($channelOptions)->help('不选择渠道表示所有渠道使用同一套银行费率');
                $table->select('bank_id', "银行编码")->options($bankOptions);
                $table->rate('pay_rate', "代付费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->rate('agent1_rate', "一级代理费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->rate('agent2_rate', "二级代理费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->rate('agent3_rate', "三级代理费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->number('min_limit_amount', '单笔最低限额')->rules($amountRules, ['numeric' => '数值不合法', 'between' => '单笔最低限额0-999999999'])->default(0);
                $table->number('max_limit_amount', '单笔最高限额')->rules($amountRules, ['numeric' => '数值不合法', 'between' => '单笔最高限额0-999999999'])->default(0);
            });
        };
        $addDepositChannelRateTable = function ($form) use ($channelOptions, $rateRules, $amountRules) {
            $form->table("transfer_rates", "商户渠道代收费率", function ($table) use ($channelOptions, $rateRules, $amountRules) {
                $table->select('channel_id', "所属渠道")->options($channelOptions)->help('不选择渠道表示所有渠道使用同一套代收费率');
                $table->rate('pay_rate', "代收费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->rate('agent1_rate', "一级代理费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->rate('agent2_rate', "二级代理费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->rate('agent3_rate', "三级代理费率")->rules($rateRules, ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
                $table->number('min_limit_amount', '单笔最低限额')->rules($amountRules, ['numeric' => '数值不合法', 'between' => '单笔最低限额0-999999999'])->default(0);
                $table->number('max_limit_amount', '单笔最高限额')->rules($amountRules, ['numeric' => '数值不合法', 'between' => '单笔最高限额0-999999999'])->default(0);
            });
        };

        return Form::make(MerchantPayment::with(['merchant_info']), function (Form $form) use ($paymentOptions, $rateRules, $amountRules, $requiredRateRules, $requiredAmountRules, $addTransferRateTable, $addDepositChannelRateTable, $controller) {
            $form->hidden('status')->default(1);
            $id = $form->getKey();
            if ($id) {
                $form->display('merchant_info.name', '商户名称');
            }
            if ($id) {
                $form->display('payment_name', '通道类型');
                if ($form->model() && $form->model()->payment_id == 7) {
                    $addTransferRateTable($form);
                } elseif ($form->model()) {
                    $addDepositChannelRateTable($form);
                }
            } else {
                $paymentField = $form->select('payment_id', '通道类型')->options($paymentOptions)->rules(['required', 'numeric', 'min:0'], ['required' => '请选择通道类型', 'numeric' => '请选择通道类型', 'min' => "请选择通道类型"])->default(1)->disableClearButton();
                $paymentField->when(7, function ($form) use ($addTransferRateTable) {
                    $addTransferRateTable($form);
                });
                foreach ($paymentOptions->keys()->filter(fn ($paymentId) => (int) $paymentId !== 7) as $paymentId) {
                    $paymentField->when($paymentId, function ($form) use ($addDepositChannelRateTable) {
                        $addDepositChannelRateTable($form);
                    });
                }
                $form->hidden('agent_user_id')->default(0);
                $form->hidden('merchant_user_id')->default(request()->input('merchant_user_id', 0));
            }
            $form->rate('pay_rate', '支付费率')->rules($requiredRateRules, ['required' => '请填写支付费率', 'numeric' => '支付费率数值不合法', 'between' => '支付费率0-100'])->default(0);
            $form->rate('agent1_rate', '一级代理费率')->rules($requiredRateRules, ['required' => '请填写一级代理费率', 'numeric' => '一级代理费率数值不合法', 'between' => '一级代理费率0-100'])->default(0);
            $form->rate('agent2_rate', '二级代理费率')->rules($requiredRateRules, ['required' => '请填写二级代理费率', 'numeric' => '二级代理费率数值不合法', 'between' => '二级代理费率0-100'])->default(0);
            $form->rate('agent3_rate', '三级代理费率')->rules($requiredRateRules, ['required' => '请填写三级代理费率', 'numeric' => '三级代理费率数值不合法', 'between' => '三级代理费率0-100'])->default(0);
            $form->number('min_limit_amount', '单笔最低限额')->rules($requiredAmountRules, ['required' => '请填写单笔最低限额', 'numeric' => '单笔最低限额数值不合法', 'between' => '单笔最低限额0-999999999'])->default(0);
            $form->number('max_limit_amount', '单笔最高限额')->rules(array_merge($requiredAmountRules, ['gte:min_limit_amount']), ['required' => '请填写单笔最高限额', 'numeric' => '单笔最高限额数值不合法', 'between' => '单笔最高限额0-999999999', 'gte' => '单笔最高限额必须大于等于单笔最低限额'])->default(0);
            $form->saving(function (Form $form) use ($controller) {
                if ($form->isCreating()) {
                    $merchantInfo = MerchantInfo::query()->find($form->merchant_user_id, ['merchant_user_id', 'agent_user_id']);
                    if (!$merchantInfo) {
                        return $form->response()->error('商户不存在，非法操作');
                    }

                    $form->merchant_user_id = $merchantInfo->merchant_user_id;
                    $form->agent_user_id = $merchantInfo->agent_user_id;
                }

                $paymentId = (int) ($form->payment_id ?: optional($form->model())->payment_id);
                if ($paymentId === 7 && $message = $controller->validateTransferBankRates($form->transfer_rates, $form->pay_rate, $form->min_limit_amount, $form->max_limit_amount)) {
                    return $form->response()->error($message);
                }
                if ($paymentId !== 7 && $message = $controller->validateDepositChannelRates($form->transfer_rates, $form->pay_rate, $form->min_limit_amount, $form->max_limit_amount)) {
                    return $form->response()->error($message);
                }
            });
            $form->saved(function (Form $form) use ($controller) {
                $payment = $form->model();
                $controller->refreshMerchantPaymentRateCache((int) $payment->merchant_user_id, [(int) $payment->payment_id]);
            });
        });
    }

    private function paymentOptions()
    {
        return collect(config('payment'))->mapWithKeys(function ($item) {
            return [$item['id'] => $item['name'] . "【" . $item['code'] . "】"];
        });
    }

    private function isStatusSwitchRequest(): bool
    {
        $keys = collect(request()->except(['_token', '_method', '_previous_', '_editable']))->keys();

        return $keys->count() === 1 && $keys->first() === 'status';
    }

    public function refreshMerchantPaymentRateCache(int $merchantUserId, array $paymentIds): void
    {
        try {
            app(RefreshMerchantPaymentRateCacheService::class)->excute($merchantUserId, $paymentIds);
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('merchant_payment_rate_cache_refresh_failed', [
                'error' => '单条修改商户支付配置后刷新商户支付费率缓存失败',
                'merchant_user_id' => $merchantUserId,
                'payment_ids' => $paymentIds,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function renderTransferBankRates($rates, $bankMap, $channelMap)
    {
        $rates = $this->normalizeTransferRates($rates);
        if (empty($rates)) {
            return new HtmlString('');
        }

        $rows = [];
        foreach ($rates as $rate) {
            $bankId = (int) ($rate['bank_id'] ?? 0);
            if ($bankId <= 0) {
                continue;
            }

            $bank = $bankMap->get($bankId);
            $bankName = $bank ? $bank->name . '【' . $bank->code . '】' : '银行ID：' . $bankId;
            $channelId = (int) ($rate['channel_id'] ?? 0);
            $channel = $channelId > 0 ? $channelMap->get($channelId) : null;
            $channelName = $channelId > 0 ? ($channel ? '【#' . $channel->id . '】' . $channel->name : '渠道ID：' . $channelId) : '所有渠道';
            $rows[] = [
                e($channelName),
                e($bankName),
                '<span style="color:#21b978;font-weight:700;">' . bob_amount_format($rate['pay_rate'] ?? 0) . '%</span>',
                bob_amount_format($rate['agent1_rate'] ?? 0) . '%',
                bob_amount_format($rate['agent2_rate'] ?? 0) . '%',
                bob_amount_format($rate['agent3_rate'] ?? 0) . '%',
                bob_unit_format($rate['min_limit_amount'] ?? 0) . ' - ' . bob_unit_format($rate['max_limit_amount'] ?? 0),
            ];
        }

        if (empty($rows)) {
            return new HtmlString('');
        }

        $table = (new Table(['所属渠道', '银行编码', '代付费率', '一级代理', '二级代理', '三级代理', '单笔限额'], $rows))->class('merchant-rate-detail-table', true)->withBorder();
        $body = $this->renderRateDetailTableBody($table);

        return Modal::make()->lg()->title('渠道银行代付费率明细')->body(new HtmlString($body))->button('<a href="javascript:void(0);" style="display:inline-flex;align-items:center;height:22px;padding:0 8px;border-radius:11px;background:#eef5ff;color:#586cb1;font-size:12px;font-weight:600;text-decoration:none;">更多费率 ' . count($rows) . ' 条</a>');
    }

    public function renderDepositChannelRates($rates, $channelMap)
    {
        $rates = $this->normalizeTransferRates($rates);
        if (empty($rates)) {
            return new HtmlString('');
        }

        $rows = [];
        foreach ($rates as $rate) {
            $channelId = (int) ($rate['channel_id'] ?? 0);
            $channel = $channelId > 0 ? $channelMap->get($channelId) : null;
            $channelName = $channelId > 0 ? ($channel ? '【#' . $channel->id . '】' . $channel->name : '渠道ID：' . $channelId) : '所有渠道';
            $rows[] = [
                e($channelName),
                '<span style="color:#21b978;font-weight:700;">' . bob_amount_format($rate['pay_rate'] ?? 0) . '%</span>',
                bob_amount_format($rate['agent1_rate'] ?? 0) . '%',
                bob_amount_format($rate['agent2_rate'] ?? 0) . '%',
                bob_amount_format($rate['agent3_rate'] ?? 0) . '%',
                bob_unit_format($rate['min_limit_amount'] ?? 0) . ' - ' . bob_unit_format($rate['max_limit_amount'] ?? 0),
            ];
        }

        if (empty($rows)) {
            return new HtmlString('');
        }

        $table = (new Table(['所属渠道', '代收费率', '一级代理', '二级代理', '三级代理', '单笔限额'], $rows))->class('merchant-rate-detail-table', true)->withBorder();
        $body = $this->renderRateDetailTableBody($table);

        return Modal::make()->lg()->title('渠道代收费率明细')->body(new HtmlString($body))->button('<a href="javascript:void(0);" style="display:inline-flex;align-items:center;height:22px;padding:0 8px;border-radius:11px;background:#eef5ff;color:#586cb1;font-size:12px;font-weight:600;text-decoration:none;">更多费率 ' . count($rows) . ' 条</a>');
    }

    private function renderRateDetailTableBody(Table $table): string
    {
        return '<style>.merchant-rate-detail-table th,.merchant-rate-detail-table td{vertical-align:middle!important;}</style>'
            . '<div style="max-height:520px;overflow:auto;">' . $table->render() . '</div>';
    }

    public function normalizeTransferRates($rates): array
    {
        if (empty($rates)) {
            return [];
        }

        if (is_string($rates)) {
            $rates = json_decode($rates, true);
        }

        if (!is_array($rates)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($rate) {
            if (is_object($rate)) {
                $rate = (array) $rate;
            }

            return is_array($rate) ? $rate : null;
        }, $rates)));
    }

    public function validateTransferBankRates($rates, $mainPayRate = 0, $mainMinAmount = 0, $mainMaxAmount = 0): string
    {
        $rates = $this->normalizeTransferRates($rates);
        if (empty($rates)) {
            return '';
        }

        $bankIds = [];
        $allChannelBanks = [];
        $specificChannelBanks = [];
        foreach ($rates as $index => $rate) {
            if (!$this->hasTransferBankRateValue($rate)) {
                continue;
            }

            $rowNumber = $index + 1;
            $bankId = (int) ($rate['bank_id'] ?? 0);
            $channelId = (int) ($rate['channel_id'] ?? 0);
            if ($bankId <= 0) {
                return '商户渠道银行代付费率第' . $rowNumber . '行已填写，请选择银行编码';
            }
            if ($this->isSameAmount($rate['pay_rate'] ?? 0, $mainPayRate)) {
                return '商户渠道银行代付费率第' . $rowNumber . '行代付费率不能与主费率相同';
            }

            $uniqueKey = $channelId . ':' . $bankId;
            if (isset($bankIds[$uniqueKey])) {
                return '商户渠道银行代付费率第' . $rowNumber . '行渠道和银行编码重复，请勿重复配置';
            }
            $bankIds[$uniqueKey] = true;

            if ($channelId === 0) {
                if (isset($specificChannelBanks[$bankId])) {
                    return '商户渠道银行代付费率第' . $rowNumber . '行已配置所有渠道，同一银行不能再配置指定渠道费率';
                }
                $allChannelBanks[$bankId] = true;
            } else {
                if (isset($allChannelBanks[$bankId])) {
                    return '商户渠道银行代付费率第' . $rowNumber . '行已存在所有渠道费率，同一银行不能再配置指定渠道费率';
                }
                $specificChannelBanks[$bankId] = true;
            }

            $minAmount = (float) ($rate['min_limit_amount'] ?? 0);
            $maxAmount = (float) ($rate['max_limit_amount'] ?? 0);
            if ($maxAmount > 0 && $minAmount > $maxAmount) {
                return '商户渠道银行代付费率第' . $rowNumber . '行单笔最高限额必须大于等于单笔最低限额';
            }
        }

        return '';
    }

    public function validateDepositChannelRates($rates, $mainPayRate = 0, $mainMinAmount = 0, $mainMaxAmount = 0): string
    {
        $rates = $this->normalizeTransferRates($rates);
        if (empty($rates)) {
            return '';
        }

        $channelIds = [];
        foreach ($rates as $index => $rate) {
            if (!$this->hasDepositChannelRateValue($rate)) {
                continue;
            }

            $rowNumber = $index + 1;
            $channelId = (int) ($rate['channel_id'] ?? 0);

            if (isset($channelIds[$channelId])) {
                return '商户渠道代收费率第' . $rowNumber . '行渠道重复，请勿重复配置同一个渠道';
            }
            $channelIds[$channelId] = true;

            if ($this->isSameAmount($rate['pay_rate'] ?? 0, $mainPayRate)) {
                return '商户渠道代收费率第' . $rowNumber . '行代收费率不能与主费率相同';
            }

            $minAmount = (float) ($rate['min_limit_amount'] ?? 0);
            $maxAmount = (float) ($rate['max_limit_amount'] ?? 0);
            if ($maxAmount > 0 && $minAmount > $maxAmount) {
                return '商户渠道代收费率第' . $rowNumber . '行单笔最高限额必须大于等于单笔最低限额';
            }
            if ($channelId === 0 && $this->isSameAmount($minAmount, $mainMinAmount) && $this->isSameAmount($maxAmount, $mainMaxAmount)) {
                return '商户渠道代收费率第' . $rowNumber . '行选择所有渠道时，单笔限额不能与主费率完全相同';
            }
        }

        return '';
    }

    private function hasTransferBankRateValue(array $rate): bool
    {
        if (!empty($rate['bank_id'])) {
            return true;
        }

        foreach (['channel_id', 'pay_rate', 'agent1_rate', 'agent2_rate', 'agent3_rate', 'min_limit_amount', 'max_limit_amount'] as $field) {
            if (isset($rate[$field]) && (float) $rate[$field] > 0) {
                return true;
            }
        }

        return false;
    }

    private function hasDepositChannelRateValue(array $rate): bool
    {
        foreach (['channel_id', 'pay_rate', 'agent1_rate', 'agent2_rate', 'agent3_rate', 'min_limit_amount', 'max_limit_amount'] as $field) {
            if (isset($rate[$field]) && (float) $rate[$field] > 0) {
                return true;
            }
        }

        return false;
    }

    private function isSameAmount($left, $right): bool
    {
        return bccomp((string) $left, (string) $right, 2) === 0;
    }
}
