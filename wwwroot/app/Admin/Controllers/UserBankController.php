<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\BankCode;
use App\Models\UserBank;
use App\Traits\ResponseTraits;
use Dcat\Admin\Layout\Content;
use App\Rules\DecimalTwoPlaces;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Http\Auth\Permission;
use App\Admin\Actions\Grid\UserBank\Copy;
use App\Admin\Actions\Grid\UserBank\Logs;
use App\Admin\Actions\Grid\UserBank\Delete;
use App\Admin\Actions\Grid\UserBank\Recover;
use App\Admin\Actions\Grid\UserBank\AddBalance;
use App\Admin\Actions\Grid\UserBank\ReduceBalance;
use App\Services\UserBank\UserBankActionLogService;
use App\Services\UserBank\UserBankTodayStatsService;
use App\Admin\Actions\Grid\UserBank\BatchCopyUserBank;
use App\Admin\Actions\Grid\UserBank\BatchOpenUserBank;
use App\Admin\Actions\Grid\UserBank\BatchCloseUserBank;
use App\Admin\Actions\Grid\UserBank\BatchUpdateLimitMinMaxAmount;

class UserBankController extends CommonController
{
    use ResponseTraits;

    protected $disableDestroy = false;

    protected function grid(): Grid
    {
        Admin::script(
            <<<JS
            $(document).off('click', '.preview-image').on('click', '.preview-image', function () {
                layer.photos({
                    photos: {title:$(this).data('name'),data:[{src:$(this).attr('src')}]}
                    ,anim: 5 //0-6的选择，指定弹出图片动画类型，默认随机（请注意，3.0之前的版本用shift参数）
               });
            });
JS

        );

        $userId = (int) request('user_id');
        $paymentId = (int) request('payment_id');
        $accountType = (int) request('account_type');
        $isTrashed = request('_scope_') == 'trashed';
        $adminUser = Admin::user();
        $canCreate = $adminUser->can('user-bank-create');
        $canEdit = $adminUser->can('user-bank-edit');
        $canDelete = $adminUser->can('user-bank-delete');
        $canRestore = $adminUser->can('user-bank-restore');
        $canStatus = $adminUser->can('user-bank-status');
        $canCopy = $adminUser->can('user-bank-copy');
        $canBatchCopy = $adminUser->can('user-bank-batch-copy');
        $canBatchOpen = $adminUser->can('user-bank-batch-open');
        $canBatchClose = $adminUser->can('user-bank-batch-close');
        $canBatchLimit = $adminUser->can('user-bank-batch-limit');
        $canBalanceAdd = $adminUser->can('user-bank-balance-add');
        $canBalanceReduce = $adminUser->can('user-bank-balance-reduce');
        $paymentOptions = collect(config('payment'))->pluck('name', 'id');
        $paymentFilterOptions = collect(config('payment'))->mapWithKeys(function ($item) {
            return [$item['id'] => "【#" . $item['id'] . "】" . $item['name']];
        });
        $bankOptions = BankCode::query()->orderBy('id')->pluck('name', 'id');
        $userOptions = User::query()->where('is_agent', 0)->select(['id', 'name', 'username'])->get()->pluck('bname', 'id');
        $todayStatsService = App::make(UserBankTodayStatsService::class);

        $query = UserBank::with([
            'user' => function ($query) {
                $query->select(['id', 'name', 'username', 'status', 'acquisition_status', 'deleted_at'])->withTrashed();
            },
            'bank_code' => function ($query) {
                $query->select(['id', 'name']);
            },
        ])->select($this->listColumns())->orderByDesc("collection_status")->orderByDesc("id");
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }
        if ($paymentId > 0) {
            $query->where('payment_id', $paymentId);
        }
        if ($accountType > 0) {
            $query->where('account_type', $accountType);
        }

        return Grid::make($query, function (Grid $grid) use ($isTrashed, $paymentOptions, $paymentFilterOptions, $bankOptions, $userOptions, $todayStatsService, $canCreate, $canEdit, $canDelete, $canRestore, $canStatus, $canCopy, $canBatchCopy, $canBatchOpen, $canBatchClose, $canBatchLimit, $canBalanceAdd, $canBalanceReduce) {
            $grid->column('id')->sortable();
            $grid->column('user.bname', "所属金主")->display(function ($value) {
                return bob_link($value, Admin::app()->getRoute('tusers.index', ['id' => $this->user_id]));
            });
            $grid->column('user.status', "账号状态")->status();
            $grid->column('user.acquisition_status', "金主收款状态")->using([0 => '收单关闭', 1 => "收单开启"])->label([0 => "red", 1 => "#21b978"]);
            if ($isTrashed) {
                $grid->column('collection_status', '收款卡收款状态')->using([0 => '收单关闭', 1 => "收单开启"])->dot([0 => "red", 1 => "#586cb1"])->center();
            } else {
                $statusColumn = $grid->column('collection_status', '收款卡收款状态')->center();
                $canStatus ? $statusColumn->switch() : $statusColumn->using([0 => '收单关闭', 1 => "收单开启"])->dot([0 => "red", 1 => "#586cb1"]);
            }
            $grid->column('payment_id', "通道类型")->display(function () use ($paymentOptions) {
                return optional($paymentOptions)->offsetGet($this->payment_id);
            })->dot(['success', 'danger', 'warning', 'info', 'primary', 'default']);
            $grid->column('account_type', "账号类型")->display(function () {
                return optional(config('default.user_bank_type'))[$this->account_type];
            })->dot(['success', 'danger', 'warning', 'info', 'primary', 'default']);
            $grid->column('name', '收款人姓名');
            $grid->column('bank', '账号')->display(function () {
                if (in_array((int) $this->account_type, [3, 5, 14, 28], true)) {
                    $url = $this->paymentQrcodeUrl();
                    if (!$url) {
                        return '<span class="label bg-warning">二维码文件缺失</span>';
                    }

                    return '<img src="' . e($url) . '" width="100" class="preview-image" data-name="' . e($this->payment_qrcode_format) . '"/>';
                }

                return ($this->bank_code ? $this->bank_code->name : '') . "：" . $this->card_no;
            });
            $grid->column('balance_amount', '实际收款金额')->modal(function ($modal) {
                $modal->title('收款卡交易明细');
                $modal->xl();
                return UserBankBalanceLogController::make(['user_bank_id' => $this->id]);
            });
            $grid->column('limint_info', '收款限额')->display(function () use ($todayStatsService) {
                $data[] = ["单笔限制：" . bob_unit_format($this->limint_min_amount) . " - " . bob_unit_format($this->limint_max_amount), ''];
                $data[] = ["全天总额：" . bob_unit_format($this->limint_day_amount), "今日跑量：" . '<span style="color:red">' . bob_unit_format($todayStatsService->amountFor((int) $this->id)) . '</span>'];
                $data[] = ["全天总单数：" . $this->limit_day_order_number, "今日成功单数：" . '<span style="color:red">' . $todayStatsService->numberFor((int) $this->id) . '</span>'];
                return bob_show_table_info($data, [], ['tr-1', 'tr-2', 'tr-3']);
            });
            $grid->column('last_collection_time', '最近一笔入款时间');
            if ($isTrashed) {
                $grid->column("deleted_at", "删除时间");
            } else {
                $grid->column('created_at');
            }
            $grid->column('remark', '备注');
            $grid->showRowSelector();
            $grid->showBatchDelete();
            $grid->tools(function ($tools) use ($canBatchClose, $canBatchOpen, $canBatchLimit, $canBatchCopy) {
                if ($canBatchClose) {
                    $tools->append(new BatchCloseUserBank());
                }
                if ($canBatchOpen) {
                    $tools->append(new BatchOpenUserBank());
                }
                if ($canBatchLimit) {
                    $tools->append(new BatchUpdateLimitMinMaxAmount());
                }
                if ($canBatchCopy) {
                    $tools->append(new BatchCopyUserBank());
                }
            });

            if ($isTrashed) {
                $grid->actions(function ($action) use ($canRestore) {
                    $action->disableDelete();
                    $action->disableEdit();
                    $action->append(new Logs());
                    if ($canRestore) {
                        $action->append(new Recover());
                    }
                });
            } else {
                $grid->actions(function ($action) use ($canDelete, $canCopy, $canBalanceAdd, $canBalanceReduce) {
                    $action->disableDelete();
                    $action->append(new Logs());
                    if ($canDelete) {
                        $action->append(new Delete());
                    }
                    if ($canCopy) {
                        $action->append(new Copy());
                    }
                    if ($canBalanceAdd) {
                        $action->append(new AddBalance());
                    }
                    if ($canBalanceReduce) {
                        $action->append(new ReduceBalance());
                    }
                });
            }

            $grid->filter(function (Grid\Filter $filter) use ($userOptions, $paymentFilterOptions, $bankOptions) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->equal('user_id', "金主")->select($userOptions)->width(3);
                $filter->equal('collection_status', "收款状态")->select([0 => '收单关闭', 1 => "收单开启"])->width(3);
                $filter->equal('payment_id', "通道类型")->select($paymentFilterOptions)->width(3);
                $filter->equal('account_type', "账号类型")->select(config('default.user_bank_type'))->width(3);
                $filter->like('card_no', '收款卡号')->width(3);
                $filter->like('name', '收款人姓名')->width(3);
                $filter->equal('bank_id', '所属银行')->select($bankOptions)->width(3);
                $filter->scope('trashed', '回收站')->onlyTrashed();
            });

            if (!$canCreate) {
                $grid->disableCreateButton();
            }
            if (!$canEdit) {
                $grid->disableEditButton();
            }
            $grid->disableBatchDelete();
        });
    }

    protected function form(): Form
    {
        Admin::js('https://unpkg.com/wechat-qrcode-ocr-wasm/index.js');
        $link = asset('storage/');
        Admin::script(
            <<<JS
                function getCode(url) {
                  return getImgQRCodeInfo({
                    wasmBinaryFile: "https://unpkg.com/wechat-qrcode-ocr-wasm/static/wasm/onlyWechatWasmFile.data",
                    wechatQRcodeFile: "https://unpkg.com/wechat-qrcode-ocr-wasm/static/wasm/wechatQRcodeFile.data",
                    url
                  });
                }
                $(document).off('click', '.getQrcodeUrl').on('click', '.getQrcodeUrl', function () {
                    let path = $("input[name=payment_qrcode]").val();
                    if(path == ''){
                        Dcat.error('请上传二维码');
                        return;
                    }
                    if($(".field_account_type").select2('val') != 2 && $(".field_account_type").select2('val') != 3){
                        Dcat.error('仅支持支付宝二维码');
                        return;
                    }
                    Dcat.loading({background:"rgba(0,0,0,1)"});
                    getCode("{$link}/"+path).then((res) => {
                        Dcat.loading(false);
                        if(res.data[0]){
                            $("input[name=payment_qrcode_url]").val(res.data[0]);
                        }else{
                            Dcat.error("无法识别二维码内容");
                        }
                    });
                });
                 $(document).off('change', '.field_account_type').on('change', '.field_account_type', function () {
                        if($(this).val() == 2){
                            $('.field_bank_id').val(93).trigger("change");
                        }
                        if($(this).val() == 4){
                            $('.field_bank_id').val(84).trigger("change");
                        }
                        if($(this).val() == 6){
                            $('.field_bank_id').val(175).trigger("change");
                        }
                    });
JS
        );
        $bankOptions = BankCode::query()->orderBy('id')->pluck('name', 'id');
        $userOptions = User::query()->where('is_agent', 0)->where('status', 1)->select(['id', 'name', 'username'])->get()->pluck('bname', 'id');
        $controller = $this;

        return Form::make(new UserBank(), function (Form $form) use ($userOptions, $bankOptions, $controller) {
            $this->clearNumberValidateErrorScript();
            $form->hidden('status')->default(1);
            $form->select('user_id', "所属金主")->options($userOptions)->disableClearButton()->rules(['numeric', 'min:1'], ['numeric' => '请选择金主', 'min' => "请选择金主"])->required();
            $form->text('name', '收款人姓名')->required()->rules(['required', 'max:50'], ['required' => '收款卡姓名必填', 'max' => '收款卡姓名字符长度不能大于50']);
            $form->select('payment_id', '通道类型')->options(collect(config('payment'))->pluck('name', 'id'))->default(1)->disableClearButton()->required();
            $form->select('account_type', '账号类型')->options(config('default.user_bank_type'))->default(1)->disableClearButton()->required()->when([1, 2, 4, 6], function (Form $form) use ($bankOptions) {
                $form->select("bank_id", "银行名称")->options($bankOptions->prepend("请选择银行卡", 0))->default(0)->disableClearButton();
                $form->text('card_no', '收款账号')->help('收款账号或卡号或手机号或钱包编号')->rules(['nullable', 'max:100'], ['max' => '收款号字符长度不能大于100']);
            })->when([2, 3, 4, 5, 28, 14], function (Form $form) {
                $form->image("payment_qrcode", "收款二维码")->uniqueName()->autoUpload()->autoSave(false)->accept('jpg,png,gif,jpeg')->on('uploadFinished', <<<JS
        function(event, file, response, previewId, index){
            if($(".field_account_type").select2('val') == 2 || $(".field_account_type").select2('val') == 3){
                $(".getQrcodeUrl").trigger('click');
            }else{
                $("input[name=payment_qrcode_url]").val("");
            }
        }
JS
                );
                $form->text("payment_qrcode_url", "收款二维码链接")->help("如果需要H5唤醒支付宝APP,请填写此项")->append('<span class="btn btn-danger getQrcodeUrl" style="margin-right: 10px">手动获取</span>')->readOnly();
            })->when(2, function (Form $form) {
                $form->text("alipay_uid", "支付宝UID")->help("开启支付宝黄金支付");
            });
            $form->number('limint_min_amount', '单笔最低限额')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '数值不合法'])->default(0)->required()->help("0为不限制");
            $form->number('limint_max_amount', '单笔最高限额')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces(), 'gte:limint_min_amount'], ['numeric' => '数值不合法', 'between' => '数值不合法', 'gte' => '单笔最高限额必须大于等于单笔最低限额'])->default(0)->required()->help("0为不限制");
            $form->number('limint_day_amount', '全天总额')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '数值不合法'])->default(0)->required()->help("0为不限制");
            $form->number('limit_day_order_number', '全天总单数')->rules(['integer', 'between:0,9999999', new DecimalTwoPlaces()], ['integer' => '数值不合法', 'between' => '数值不合法'])->default(0)->required()->help("0为不限制");
            $form->number('same_amount_interval_time', '同金额接单时间间隔(分钟)')->rules(['integer', 'between:0,100'], ['integer' => '数值不合法', 'between' => '数值不合法'])->default(0)->required()->help("0为不限制");
            $form->radio('collection_status', "收款状态")->options([0 => "收单停止", 1 => "收单启动"])->default(1);
            $form->textarea('remark', "备注");
            $form->saving(function (Form $form) use ($controller) {
                $permissionSlug = $controller->savePermissionSlug($form);
                if (Admin::user()->cannot($permissionSlug)) {
                    return $form->response()->error('无操作权限');
                }

                $accountType = (int) $form->account_type;
                if (in_array($accountType, [3, 5, 14, 28], true) && !$form->payment_qrcode) {
                    return $form->response()->error("请上传收款二维码");
                }

                if (in_array($accountType, [1, 2, 4, 6], true)) {
                    if ((int) $form->bank_id === 0) {
                        return $form->response()->error("请选择银行名称");
                    }
                    if (!$form->card_no) {
                        return $form->response()->error("请填写收款账号");
                    }
                    if (!$form->payment_qrcode) {
                        $form->payment_qrcode_url = '';
                    }
                    $existsQuery = UserBank::query()->where('card_no', $form->card_no)->where('payment_id', $form->payment_id);
                    if ($form->isEditing()) {
                        $existsQuery->where('id', '<>', $form->getKey());
                    }
                    if ($existsQuery->exists()) {
                        return $form->response()->error("当前收款账号已存在");
                    }
                }
            });
            $form->saved(function (Form $form) {
                $adminId = Admin::user()->id;
                $model = $form->repository()->model();
                $logService = App::make(UserBankActionLogService::class);
                if ($form->isCreating()) {
                    $logService->excute(['type' => 2, 'type_id' => $adminId, 'action' => 1, 'user_bank_id' => $model->id, 'remark' => json_encode($model->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
                if ($form->isEditing()) {
                    if (count(request()->all()) == 3) {
                        $logService->excute(['type' => 2, 'type_id' => $adminId, 'action' => request()->input('collection_status') == 0 ? 6 : 5, 'user_bank_id' => $model->id]);
                    } else {
                        $logService->excute(['type' => 2, 'type_id' => $adminId, 'action' => 2, 'user_bank_id' => $model->id, 'remark' => json_encode($model->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    }
                }
                if ($form->isDeleting()) {
                    $logService->excute(['type' => 2, 'type_id' => $adminId, 'action' => 3, 'user_bank_id' => $model->id]);
                }
            });
        });
    }

    public function create(Content $content)
    {
        Permission::check('user-bank-create');

        return parent::create($content);
    }

    public function store()
    {
        Permission::check('user-bank-create');

        return parent::store();
    }

    public function edit($id, Content $content)
    {
        Permission::check('user-bank-edit');

        return parent::edit($id, $content);
    }

    public function update($id)
    {
        if ($this->isCollectionStatusSwitch()) {
            return $this->updateCollectionStatusSwitch((int)$id);
        }

        Permission::check($this->updatePermissionSlug());

        return parent::update($id);
    }

    private function savePermissionSlug(Form $form): string
    {
        if ($form->isCreating()) {
            return 'user-bank-create';
        }

        return $this->updatePermissionSlug();
    }

    private function updatePermissionSlug(): string
    {
        $keys = collect($this->requestBodyInput())->keys();
        if ($keys->count() === 1 && $keys->first() === 'collection_status') {
            return 'user-bank-status';
        }

        return 'user-bank-edit';
    }

    private function isCollectionStatusSwitch(): bool
    {
        $input = $this->requestBodyInput();

        return count($input) === 1 && array_key_first($input) === 'collection_status';
    }

    private function requestBodyInput(): array
    {
        $input = request()->request->all();
        if (empty($input) && request()->isJson()) {
            $input = request()->json()->all();
        }

        return collect($input)->except(['_token', '_method', '_previous_', '_editable'])->all();
    }

    private function updateCollectionStatusSwitch(int $id)
    {
        Permission::check('user-bank-status');

        $value = request()->input('collection_status');
        if (!in_array((string)$value, ['0', '1'], true)) {
            return response()->json(['status' => false, 'message' => '状态值不合法', 'data' => ['message' => '状态值不合法']]);
        }

        $userBank = UserBank::query()->whereKey($id)->first();
        if (!$userBank) {
            return response()->json(['status' => false, 'message' => '收款卡不存在', 'data' => ['message' => '收款卡不存在']]);
        }

        $userBank->collection_status = (int)$value;
        $userBank->save();

        return response()->json(['status' => true, 'message' => '更新成功', 'data' => ['message' => '更新成功']]);
    }

    private function clearNumberValidateErrorScript(): void
    {
        Admin::script(<<<'JS'
(function () {
    var formSelector = 'form[action*="/bank-users"]';
    var inputSelector = [
        formSelector + ' input[name="limint_min_amount"]',
        formSelector + ' input[name="limint_max_amount"]',
        formSelector + ' input[name="limint_day_amount"]',
        formSelector + ' input[name="limit_day_order_number"]',
        formSelector + ' input[name="same_amount_interval_time"]'
    ].join(',');

    $(document)
        .off('input.user-bank-error-clear change.user-bank-error-clear keyup.user-bank-error-clear', inputSelector)
        .on('input.user-bank-error-clear change.user-bank-error-clear keyup.user-bank-error-clear', inputSelector, function () {
            var $input = $(this);
            var $form = $input.closest('form');
            var $group = $input.closest('.form-group,.form-label-group,.form-field');

            $group.removeClass('has-error');
            $group.find('.with-errors').empty();
            $input.removeAttr('aria-invalid').removeClass('is-invalid');
            $form.find('[type="submit"],.submit').buttonLoading(false);
        });
})();
JS);
    }

    private function listColumns(): array
    {
        return [
            'id', 'user_id', 'bank_id', 'payment_id', 'account_type', 'name', 'card_no', 'payment_qrcode',
            'balance_amount', 'limint_min_amount', 'limint_max_amount', 'limint_day_amount', 'limit_day_order_number',
            'collection_status', 'last_collection_time', 'today_stat_date', 'today_total_amount', 'today_total_number',
            'today_total_income', 'created_at', 'deleted_at', 'remark',
        ];
    }
}
