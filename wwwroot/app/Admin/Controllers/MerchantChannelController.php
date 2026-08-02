<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\Channel;
use Dcat\Admin\Layout\Row;
use App\Models\ChannelRate;
use Dcat\Admin\Widgets\Box;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\MerchantChannel;
use App\Models\MerchantPayment;
use App\Rules\DecimalTwoPlaces;
use App\Models\AgentUserRelation;
use Dcat\Admin\Http\Auth\Permission;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Enums\DepositChannelModeEnum;
use App\Services\Merchant\GetMerchantListService;
use App\Admin\Actions\Grid\MerchantChannel\BatchAdd;
use App\Services\Cache\Channel\GetChannelListService;
use App\Admin\Actions\Grid\MerchantChannel\BatchRestore;
use App\Admin\Actions\Grid\MerchantChannel\BatchOpenFloat;
use App\Admin\Actions\Grid\MerchantChannel\BatchCloseFloat;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Admin\Actions\Grid\MerchantChannel\BatchOpenChannel;
use App\Admin\Actions\Grid\MerchantChannel\BatchCloseChannel;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Admin\Actions\Grid\MerchantChannel\BatchUpdatePayRate;
use App\Admin\Actions\Grid\MerchantChannel\UpdatePayMinMaxAmount;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Admin\Actions\Grid\MerchantChannel\UpdateCollectionMinMaxAmount;
use App\Services\MerchantChannel\MerchantChannelDispatchDescriptionService;

class MerchantChannelController extends CommonController
{
    protected $disableDestroy = false;

    protected function grid(): Grid
    {
        session()->put('merchantchannelpreviousUrl', url()->full());
        $this->registerFloatStatusScript();

        $merchantUserId = (int) request('merchant_user_id', 0);
        $isTrashed = request('_scope_') === 'trashed';
        $adminUser = Admin::user();
        $canCreate = $adminUser->can('merchant-channel-create');
        $canEdit = $adminUser->can('merchant-channel-edit');
        $canDelete = $adminUser->can('merchant-channel-delete');
        $canRestore = $adminUser->can('merchant-channel-restore');
        $canBatchAdd = $adminUser->can('merchant-channel-batch-add');
        $canBatchPayLimit = $adminUser->can('merchant-channel-batch-pay-limit');
        $canBatchCollectionLimit = $adminUser->can('merchant-channel-batch-collection-limit');
        $canBatchRate = $adminUser->can('merchant-channel-batch-rate');
        $canStatus = $adminUser->can('merchant-channel-status');
        $canFloatStatus = $adminUser->can('merchant-channel-float-status');
        $appName = config('app.name');
        $paymentOptions = collect(config('payment', []))->mapWithKeys(function ($item) {
            return [$item['id'] => ($item['name'] ?? '') . '【' . ($item['code'] ?? '') . '】'];
        });
        $currencyOptions = collect(config('default.currency', []))->pluck('name', 'id');
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $merchantListInfoService = App::make(GetMerchantListInfoService::class);
        $channelOptions = collect(App::make(GetChannelListService::class)->excute())
            ->filter(fn ($item) => (int) $item['status'] === 1)
            ->pluck('bname', 'id')
            ->toArray();
        $merchantOptions = App::make(GetMerchantListService::class)->excute(['currency_id'], true);
        $merchantAgentOptions = bob_build_select_options(App::make(GetMerchantAgentListService::class)->excute());
        $dispatchDescription = $merchantUserId > 0 ? App::make(MerchantChannelDispatchDescriptionService::class)->excute($merchantUserId) : [];

        $query = MerchantChannel::query()
            ->select([
                'id',
                'merchant_user_id',
                'channel_id',
                'payment_id',
                'priority',
                'weight',
                'status',
                'float_status',
                'pay_min_amount',
                'pay_max_amount',
                'collection_min_amount',
                'collection_max_amount',
                'fee',
                'deposit_fee',
                'settlement_mode',
            ])
            ->with([
                'channel' => function ($query) {
                    $query->select(['id', 'name', 'code', 'status', 'classname']);
                },
                'merchant_info' => function ($query) {
                    $query->select(['merchant_user_id', 'currency_id', 'name', 'coder', 'auto_transfer', 'amount_float_type', 'float_amount']);
                },
            ]);
        $this->applyMerchantChannelListSort($query, $dispatchDescription);

        return Grid::make($query, function (Grid $grid) use (
            $appName,
            $isTrashed,
            $channelOptions,
            $currencyOptions,
            $paymentOptions,
            $merchantOptions,
            $merchantUserId,
            $merchantAgentOptions,
            $merchantListInfoService,
            $merchantBaseInfoService,
            $dispatchDescription,
            $canCreate,
            $canEdit,
            $canDelete,
            $canRestore,
            $canBatchAdd,
            $canBatchPayLimit,
            $canBatchCollectionLimit,
            $canBatchRate,
            $canStatus,
            $canFloatStatus
        ) {
            if ($merchantUserId > 0) {
                $grid->model()->where('merchant_user_id', $merchantUserId);
                $grid->model()->setConstraints(['merchant_user_id' => $merchantUserId]);
                $result = $merchantBaseInfoService->excute($merchantUserId);
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . optional($result)->offsetGet('bname') . '</button>');
            } else {
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" />全部商户</button>');
            }
            $grid->column('id')->sortable();
            $grid->column('channel.name', '渠道名称')->display(function ($value) {
                if ((int) $this->payment_id === 7) {
                    return $value;
                }

                return $value . ([1 => '<span style="display:inline-block;margin-left:6px;padding:2px 7px;border-radius:10px;background:#fff1f0;color:#d4380d;font-size:12px;font-weight:600;white-space:nowrap;">T1</span>', 2 => '<span style="display:inline-block;margin-left:6px;padding:2px 7px;border-radius:10px;background:#fff1f0;color:#d4380d;font-size:12px;font-weight:600;white-space:nowrap;">T2</span>'][$this->settlement_mode] ?? '');
            });
            $grid->column('channel.status', '渠道状态')->status();
            $grid->column('payment_name', '通道信息')->display(function () use ($appName) {
                if ($this->channel && $this->channel->classname && File::exists(base_path("vendor/richard/payment/src/Channel/" . $this->channel->classname . ".php"))) {
                    $classname = 'Richard\\Payment\\Channel\\' . $this->channel->classname;
                    $pay = new $classname();
                    $data = $pay->getChanelCoderTable($this->payment_id);
                    if (!empty($data)) {
                        return bob_show_table_info($data, [], ['tr-1', 'tr-2', 'tr-3']);
                    }
                }

                return '';
            });
            $grid->column('merchant_info.bname', '所属商户')->append(function () {
                return $this->transferModeLabel();
            });

            if (!$isTrashed) {
                $priorityColumn = $grid->column('priority', '优先级(数小优先)');
                $weightColumn = $grid->column('weight', '权重')->help('仅按权重模式生效，数值越大分配比例越高');
                if ($canEdit) {
                    $priorityColumn->editable(['mask' => 1, 'refresh' => 1, 'alias' => 'integer', 'min' => 1, 'max' => 999]);
                    $weightColumn->editable(['mask' => 1, 'refresh' => 1, 'alias' => 'integer', 'min' => 1, 'max' => 9999]);
                }

                $statusColumn = $grid->column('status', '状态');
                $canStatus ? $statusColumn->switch(Admin::color()->green()) : $statusColumn->status();

                $grid->column('float_status', '浮动配置')->display(function () use ($canFloatStatus) {
                    return $this->floatConfigInfo($canFloatStatus);
                })->help('先看商户是否开启金额浮动；商户开启后，当前通道浮动开关才生效');
            }
            if ($appName === 'sgpay') {
                $grid->column('amount_fee_info', '限额与手续费')->display(function () {
                    return $this->amountFeeInfo();
                });
            } else {
                $grid->column('amount_fee_info', '限额与手续费')->display(function () {
                    return $this->amountFeeInfo();
                });
            }
            $grid->showRowSelector();
            $canDelete ? $grid->showBatchDelete() : $grid->disableBatchDelete();
            $grid->paginate(50);
            if (!$canEdit) {
                $grid->disableEditButton();
            }
            if (!$canDelete) {
                $grid->disableDeleteButton();
            }

            if ($isTrashed) {
                $grid->disableActions();
                $grid->disableBatchDelete();
                if ($canRestore) {
                    $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                        $batch->add(new BatchRestore(MerchantChannel::class));
                    });
                }
            } else {
                $grid->tools(function ($tools) use ($canBatchAdd, $canBatchPayLimit, $canBatchCollectionLimit, $canBatchRate, $canStatus, $canFloatStatus) {
                    if ($canBatchAdd) {
                        $tools->append(new BatchAdd());
                    }
                    if ($canBatchCollectionLimit) {
                        $tools->append(new UpdateCollectionMinMaxAmount());
                    }
                    if ($canBatchPayLimit) {
                        $tools->append(new UpdatePayMinMaxAmount());
                    }
                    if ($canBatchRate) {
                        $tools->append(new BatchUpdatePayRate());
                    }

                    $tools->batch(function ($batch) use ($canStatus, $canFloatStatus) {
                        if ($canStatus) {
                            $batch->add(new BatchCloseChannel());
                            $batch->add(new BatchOpenChannel());
                        }
                        if ($canFloatStatus) {
                            $batch->add(new BatchCloseFloat());
                            $batch->add(new BatchOpenFloat());
                        }
                    });
                });
            }

            if ($merchantUserId === 0 || !$canCreate) {
                $grid->disableCreateButton();
            }

            $grid->filter(function (Grid\Filter $filter) use ($channelOptions, $paymentOptions, $merchantOptions, $currencyOptions, $merchantAgentOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->equal('channel_id', '支付渠道')->select($channelOptions)->width(3);
                $filter->equal('payment_id', '通道类型')->select($paymentOptions)->width(3);
                $filter->where('business_type', function ($query) {
                    if ((int) $this->input === 1) {
                        $query->where('payment_id', '<>', 7);
                        return;
                    }
                    if ((int) $this->input === 2) {
                        $query->where('payment_id', 7);
                    }
                }, '业务类型')->select([1 => '代收', 2 => '代付'])->width(3);
                $filter->where('merchant_user_id', function ($query) {
                    if ((int) $this->input <= 0) {
                        return;
                    }

                    $query->where('merchant_user_id', $this->input);
                }, '选择商户')->select($merchantOptions)->width(3);
                $filter->where('currency_id', function ($query) {
                    $query->whereIn('merchant_user_id', MerchantInfo::query()->select('merchant_user_id')->where('currency_id', $this->input));
                }, '选择币种')->select($currencyOptions)->width(3);
                $filter->where('merchant_agent_id', function ($query) {
                    $agentIds = AgentUserRelation::where('parent_id', $this->input)
                        ->pluck('child_id')
                        ->push((int) $this->input)
                        ->unique()
                        ->toArray();

                    $merchantUserIds = MerchantInfo::whereIn('agent_user_id', $agentIds)
                        ->pluck('merchant_user_id')
                        ->toArray();

                    $query->whereIn('merchant_user_id', $merchantUserIds);
                }, '商户代理')->select($merchantAgentOptions)->width(3);

                $filter->scope('trashed', '回收站')->onlyTrashed();
            });

            $grid->wrap(function (Renderable $view) use ($merchantUserId, $merchantListInfoService, $dispatchDescription) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($merchantUserId, $merchantListInfoService) {
                    $merchantInfoResult = $merchantListInfoService->excute();
                    $merchantInfoResult = !empty($merchantInfoResult) ? array_filter($merchantInfoResult, function ($item) {
                        return (int) $item['status'] === 1;
                    }) : [];
                    $box = Box::make('商户列表', view('admin.merchant-channel.merchantList', ['result' => $merchantInfoResult, 'merchant_user_id' => $merchantUserId, 'title' => '商户列表']));
                    $box->padding('15px 0px');
                    $column->row($box);
                });
                $row->column(10, function (Column $column) use ($view, $merchantUserId, $dispatchDescription) {
                    if ($merchantUserId > 0) {
                        $column->row(view('admin.merchant-channel.dispatch-description', ['description' => $dispatchDescription])->render());
                    }
                    $card = Card::make($view);
                    $card->padding('15px');
                    $column->row($card);
                });
                return $row->render();
            });
        });
    }

    private function applyMerchantChannelListSort($query, array $dispatchDescription): void
    {
        $query->orderBy('status', 'desc');

        $mode = $this->currentListDispatchMode($dispatchDescription);
        if ($mode === DepositChannelModeEnum::PRIORITY) {
            $query->orderBy('priority', 'asc')->orderBy('id', 'desc')->orderBy('channel_id', 'asc');
            return;
        }

        if ($mode === DepositChannelModeEnum::WEIGHT) {
            $query->orderBy('weight', 'desc')->orderBy('priority', 'asc')->orderBy('id', 'desc');
            return;
        }

        $query->orderBy('priority', 'asc')->orderBy('id', 'desc');
    }

    private function currentListDispatchMode(array $dispatchDescription): int
    {
        if (empty($dispatchDescription)) {
            return 0;
        }

        $paymentId = (int) request('payment_id', 0);
        if ($paymentId > 0) {
            return $paymentId === 7 ? (int)($dispatchDescription['transfer']['mode_value'] ?? 0) : (int)($dispatchDescription['deposit']['mode_value'] ?? 0);
        }
        return 0;
    }

    private function registerFloatStatusScript(): void
    {
        Admin::style(<<<'CSS'
.merchant-channel-float-info{min-width:150px}
.merchant-channel-float-info .float-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:3px 0;color:#606266;font-size:12px}
.merchant-channel-float-info .float-key{color:#909399;white-space:nowrap}
.merchant-channel-float-info .float-value{color:#303133;font-weight:600;text-align:right}
.merchant-channel-float-info .float-tag{display:inline-block;padding:1px 7px;border-radius:10px;font-size:12px;font-weight:700;line-height:18px}
.merchant-channel-float-info .float-tag-open{background:#f6ffed;color:#389e0d}
.merchant-channel-float-info .float-tag-close{background:#fff1f0;color:#d4380d}
.merchant-channel-float-switch{position:relative;width:38px;height:20px;display:inline-block;vertical-align:middle}
.merchant-channel-float-switch input{display:none}
.merchant-channel-float-switch span{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#dcdfe6;border-radius:12px;transition:.2s}
.merchant-channel-float-switch span:before{position:absolute;content:"";height:16px;width:16px;left:2px;bottom:2px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}
.merchant-channel-float-switch input:checked+span{background:#21b978}
.merchant-channel-float-switch input:checked+span:before{transform:translateX(18px)}
CSS);

        Admin::script(<<<'JS'
(function () {
    $(document).off('change', '.merchant-channel-float-switch input').on('change', '.merchant-channel-float-switch input', function () {
        var $input = $(this);
        var checked = $input.is(':checked');
        var url = $input.data('url');
        $input.prop('disabled', true);

        Dcat.NP.start();
        $.put({
            url: url,
            data: {float_status: checked ? 1 : 0},
            success: function (d) {
                Dcat.NP.done();
                var msg = d.data && d.data.message ? d.data.message : d.message;
                if (d.status) {
                    Dcat.success(msg || '更新成功');
                    Dcat.reload();
                    return;
                }

                $input.prop('checked', !checked);
                Dcat.error(msg || '更新失败');
            },
            error: function () {
                Dcat.NP.done();
                $input.prop('checked', !checked);
                Dcat.error('更新失败');
            },
            complete: function () {
                $input.prop('disabled', false);
            }
        });
    });
})();
JS);
    }

    protected function form(): Form
    {
        $updatePermissionSlug = fn (): string => $this->updatePermissionSlug();
        $maxChannelPercentRate = fn (?ChannelRate $channelRate): float => $this->maxChannelPercentRate($channelRate);

        return Form::make(MerchantChannel::with('merchant_info'), function (Form $form) use ($updatePermissionSlug, $maxChannelPercentRate) {

            $id = $form->getKey();

            if ($id) {
                $form->display('merchant_info.name', '商家');
            } else {
                $merchantUserId = (int) request('merchant_user_id', 0);
                $form->hidden('merchant_user_id')->default($merchantUserId);
                $merchantInfoResult = MerchantInfo::query()->find($merchantUserId, ['merchant_user_id', 'name']);
                $form->display('merchant_name', '商家')->default(optional($merchantInfoResult)->name);
            }
            $form->select('channel_id', '渠道类型')->disableClearButton()->options(Channel::query()->where('status', 1)->orderBy('id', 'desc')->get(['id', 'code', 'name'])->mapWithKeys(fn ($channel) => [$channel->id => '【#' . $channel->id . '】【' . $channel->code . '】' . $channel->name]))->load('payment_id', 'ajax/merchantChannelPaymentField')->rules(['numeric', 'min:1'], ['numeric' => '请选择渠道类型', 'min' => '请选择渠道类型'])->required();
            $form->select('payment_id', '通道类型')->rules(['numeric', 'min:1'], ['numeric' => '请选择通道类型', 'min' => '请选择通道类型'])->required()->disableClearButton();
            $form->select('settlement_mode', '代收结算模式')->options(config('default.settlement_mode'))->default(0)->when('>', 0, function ($form) {
                $form->time('settlement_time', '结算时间')->default('17:00:00')->format('HH:mm:ss')->required();
            })->disableClearButton()->help('代付设置无效');
            $form->number('priority', '优先级(数小优先)')->rules(['numeric', 'integer', 'between:0,999999'], ['numeric' => '请输入合法的数值', 'integer' => '请输入整数', 'between' => '优先级0-999999'])->min(0)->required();
            $form->number('weight', '权重')->rules(['numeric', 'integer', 'between:1,9999'], ['numeric' => '请输入合法的数值', 'integer' => '请输入整数', 'between' => '权重1-9999'])->default(1)->required()->help('仅按权重模式生效，数值越大分配比例越高');
            $form->number('pay_min_amount', '代收单笔下限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代收单笔下限0-999999999'])->default(0)->required();
            $form->number('pay_max_amount', '代收单笔上限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:pay_min_amount'], ['numeric' => '数值不合法', 'between' => '代收单笔上限0-999999999', 'gte' => '代收单笔上限必须大于等于代收单笔下限'])->default(0)->required();
            $form->number('collection_min_amount', '代付单笔下限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代付单笔下限0-999999999'])->default(0)->required();
            $form->number('collection_max_amount', '代付单笔上限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:collection_min_amount'], ['numeric' => '数值不合法', 'between' => '代付单笔上限0-999999999', 'gte' => '代付单笔上限必须大于等于代付单笔下限'])->default(0)->required();
            $form->text('fee', '代付额外手续费')->default(0)->required()->help('最多保留2位小数')->rules(['numeric', 'between:0,999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代付额外手续费0-999999'])->width(3);
            $form->text('deposit_fee', '代收额外手续费')->default(0)->required()->help('最多保留2位小数')->rules(['numeric', 'between:0,999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代收额外手续费0-999999'])->width(3);
            $form->radio('status', '状态')->options([0 => '禁用', 1 => '启用'])->default(1);
            $form->radio('float_status', '是否浮动')->options([0 => '否', 1 => '是'])->default(0);
            $form->radio('use_cashier', '系统收营台')->options([0 => '默认', 1 => '开启', 2 => '关闭'])->default(0)->help('默认代表，按照渠道设置，否则按照当前设置');
            $form->saving(function (Form $form) use ($updatePermissionSlug, $maxChannelPercentRate) {
                if ($form->isCreating() && !Admin::user()->can('merchant-channel-create')) {
                    return $form->response()->error('无新增商户渠道权限');
                }
                if ($form->isEditing() && !Admin::user()->can($updatePermissionSlug())) {
                    return $form->response()->error('无商户渠道操作权限');
                }

                if ($form->isCreating()) {
                    $merchantInfo = MerchantInfo::query()->find($form->merchant_user_id, ['merchant_user_id']);
                    if (!$merchantInfo) {
                        return $form->response()->error('商户不存在，非法操作');
                    }

                    $form->merchant_user_id = $merchantInfo->merchant_user_id;
                    $merchantChannelExists = MerchantChannel::query()->where('merchant_user_id', $form->merchant_user_id)->where('channel_id', $form->channel_id)->where('payment_id', $form->payment_id)->exists();
                    if ($merchantChannelExists) {
                        return $form->response()->error('通道类型已经存在，请勿重复添加');
                    }

                    $merchantPayment = MerchantPayment::query()->where('merchant_user_id', $form->merchant_user_id)->where('status', 1)->where('payment_id', $form->payment_id)->orderBy('id', 'desc')->first(['pay_rate']);
                    if (!$merchantPayment) {
                        return $form->response()->error('请设置通道费率');
                    }

                    $channelPayment = ChannelRate::query()->where('channel_id', $form->channel_id)->where('payment_id', $form->payment_id)->first(['type', 'rate', 'rate_ranges']);
                    $maxChannelRate = $maxChannelPercentRate($channelPayment);
                    if ($channelPayment && $maxChannelRate > floatval($merchantPayment->pay_rate)) {
                        return $form->response()->error('渠道的通道成本费率【' . bob_amount_format($maxChannelRate) . '】不能大于设置的通道费率【' . bob_amount_format($merchantPayment->pay_rate) . '】');
                    }

                }
                if ($form->isEditing()) {
                    $merchantUserId = $form->model()->merchant_user_id;
                    $channelId = $form->channel_id ?: $form->model()->channel_id;
                    $paymentId = $form->payment_id ?: $form->model()->payment_id;
                    $merchantChannelExists = MerchantChannel::query()->where('id', '<>', $form->model()->id)->where('merchant_user_id', $merchantUserId)->where('channel_id', $channelId)->where('payment_id', $paymentId)->exists();
                    if ($merchantChannelExists) {
                        return $form->response()->error('通道类型已经存在，请勿重复添加');
                    }
                    $merchantPayment = MerchantPayment::query()->where('merchant_user_id', $merchantUserId)->where('status', 1)->where('payment_id', $paymentId)->orderBy('id', 'desc')->first(['pay_rate', 'payment_id']);
                    $channelPayment = ChannelRate::query()->where('channel_id', $channelId)->where('payment_id', $paymentId)->first(['type', 'rate', 'rate_ranges', 'payment_id']);
                    $maxChannelRate = $maxChannelPercentRate($channelPayment);
                    if ($merchantPayment && $channelPayment && $maxChannelRate > floatval($merchantPayment->pay_rate)) {
                        return $form->response()->error('渠道的通道成本费率【' . bob_amount_format($maxChannelRate) . '】不能大于设置的通道费率【' . bob_amount_format($merchantPayment->pay_rate) . '】');
                    }
                }
            });
            $form->saved(function (Form $form) {
                return $form->response()->success('保存成功')->redirect(session()->get('merchantchannelpreviousUrl'));
            });
        });
    }

    public function store()
    {
        Permission::check('merchant-channel-create');

        return parent::store();
    }

    public function update($id)
    {
        Permission::check($this->updatePermissionSlug());

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('merchant-channel-delete');

        return parent::destroy($id);
    }

    private function updatePermissionSlug(): string
    {
        $keys = collect(request()->except(['_token', '_method', '_previous_', '_editable']))->keys();
        if ($keys->count() === 1 && $keys->first() === 'status') {
            return 'merchant-channel-status';
        }
        if ($keys->count() === 1 && $keys->first() === 'float_status') {
            return 'merchant-channel-float-status';
        }

        return 'merchant-channel-edit';
    }

    private function maxChannelPercentRate(?ChannelRate $channelRate): float
    {
        if (!$channelRate) {
            return 0;
        }

        if ((int)$channelRate->type !== 0) {
            return 0;
        }

        $rates = [(float)$channelRate->rate];
        foreach (($channelRate->rate_ranges ?: []) as $range) {
            if (is_array($range)) {
                $rates[] = (float)($range['rate'] ?? 0);
            }
        }

        return empty($rates) ? 0 : max($rates);
    }
}
