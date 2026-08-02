<?php

namespace App\Admin\Controllers;

use App\Models\User;
use App\Models\UserBank;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\AdminUser;
use App\Models\UserGroup;
use App\Models\UserModel as Administrator;
use Dcat\Admin\Layout\Row;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Rules\DecimalTwoPlaces;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Http\Auth\Permission;
use App\Admin\Actions\Grid\User\Delete;
use App\Admin\Actions\Grid\User\CacheStats;
use App\Admin\Actions\Grid\User\UnlockUser;
use App\Admin\Actions\Grid\User\ForceLogout;
use App\Admin\Actions\Grid\User\UpdateAgent;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Extensions\Layout\LeftTreeSide;
use App\Admin\Actions\Grid\User\ResetPassword;
use App\Admin\Actions\Grid\User\ResetTelegram;
use App\Admin\Actions\Grid\User\AddYajinBalance;
use App\Admin\Actions\Grid\User\UserDepostDetail;
use App\Services\Cache\User\GetUserDetailService;
use App\Admin\Actions\Grid\User\AddDepositBalance;
use App\Admin\Actions\Grid\User\UserDayBalanceLog;
use App\Admin\Actions\Grid\User\AddTransferBalance;
use App\Admin\Actions\Grid\User\ReduceYajinBalance;
use App\Admin\Actions\Grid\User\ResetGooglePassword;
use App\Services\Cache\User\GetUserAgentListService;
use App\Admin\Actions\Grid\User\AddCommissionBalance;
use App\Admin\Actions\Grid\User\ReduceDepositBalance;
use App\Services\UserBank\GetSelfUserBankTypeService;
use App\Services\User\GetUserRemainingDepositService;
use App\Admin\Actions\Grid\User\ReduceTransferBalance;
use App\Admin\Actions\Grid\User\ReduceCommissionBalance;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class UserController extends CommonController
{
    protected $translation = "user";

    protected function grid(): Grid
    {
        $agentId = (int) request('agent_id', 0);
        $isTrashed = request('_scope_') == 'trashed';
        $adminUser = Admin::user();
        $canEdit = $adminUser->can('user-edit');
        $canDelete = $adminUser->can('user-delete');
        $canStatus = $adminUser->can('user-status');
        $canAcquisitionStatus = $adminUser->can('user-acquisition-status');
        $canUnlockLogin = $adminUser->can('user-unlock-login');
        $canForceLogout = $adminUser->can('user-force-logout');
        $canResetPassword = $adminUser->can('user-reset-password');
        $canUpdateAgent = $adminUser->can('user-update-agent');
        $canDepositBalanceAdd = $adminUser->can('user-deposit-balance-add');
        $canDepositBalanceReduce = $adminUser->can('user-deposit-balance-reduce');
        $canCollectionBalanceAdd = $adminUser->can('user-collection-balance-add');
        $canCollectionBalanceReduce = $adminUser->can('user-collection-balance-reduce');
        $canTransferBalanceAdd = $adminUser->can('user-transfer-balance-add');
        $canTransferBalanceReduce = $adminUser->can('user-transfer-balance-reduce');
        $canCommissionBalanceAdd = $adminUser->can('user-commission-balance-add');
        $canCommissionBalanceReduce = $adminUser->can('user-commission-balance-reduce');
        $canUnbindTelegram = $adminUser->can('user-unbind-telegram');
        $canResetGoogle = $adminUser->can('user-reset-googlecode');
        $agentList = collect(App::make(GetUserAgentListService::class)->excute());
        $agentOptions = collect(bob_build_select_options($agentList->toArray()))->prepend('全部代理', 0)->toArray();
        $merchantOptions = collect(App::make(GetMerchantListInfoService::class)->excute())->pluck('bname', 'id');
        $merchantNameMap = $merchantOptions->all();
        $userDetailService = App::make(GetUserDetailService::class);
        $remainingDepositService = App::make(GetUserRemainingDepositService::class);
        $controller = $this;

        $query = Administrator::query()->select($this->listColumns())->where('is_agent', 0)->orderByDesc('acquisition_status')->orderByDesc('status')->orderByDesc('id');
        if ($agentId > 0) {
            $query->whereHas('user_relation', function ($query) use ($agentId) {
                $query->where('parent_id', $agentId);
            });
        }

        return Grid::make($query, function (Grid $grid) use ($agentId, $isTrashed, $adminUser, $agentList, $agentOptions, $merchantOptions, $merchantNameMap, $userDetailService, $remainingDepositService, $controller, $canEdit, $canDelete, $canStatus, $canAcquisitionStatus, $canUnlockLogin, $canForceLogout, $canResetPassword, $canUpdateAgent, $canDepositBalanceAdd, $canDepositBalanceReduce, $canCollectionBalanceAdd, $canCollectionBalanceReduce, $canTransferBalanceAdd, $canTransferBalanceReduce, $canCommissionBalanceAdd, $canCommissionBalanceReduce, $canUnbindTelegram, $canResetGoogle) {
            if ($agentId > 0) {
                $agent = $agentList->firstWhere('id', $agentId);
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" />' . e($agent['name'] ?? '') . '</button>');
            } else {
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> 全部代理</button>');
            }
            $grid->column('id', "编号")->sortable()->center();
            $grid->column('user_info', "金主信息")->display(function () {
                $data[] = ["金主名称", $this->name];
                $data[] = ["金主账号", $this->username];
                $data[] = ["手机号", $this->mobile];
                return bob_show_table_info($data, [], ['tr-1', 'tr-2', 'tr-3', 'tr-4'], 3);
            })->top();
            $grid->column('user_agent', "金主代理")->display(function () use ($userDetailService, $controller) {
                $user = $userDetailService->excute($this->id);
                $data = $controller->agentRows($user);
                if (!empty($data)) {
                    return bob_show_table_info($data, [], ['tr-1', 'tr-2', 'tr-3', 'tr-4'], 5);
                }
            })->top();
            if ($isTrashed) {
                $grid->column('status', "金主状态")->status()->center();
            } else {
                $statusColumn = $grid->column('status', "金主状态")->center();
                $canStatus ? $statusColumn->switch(Admin::color()->green()) : $statusColumn->status();
            }
            if ($isTrashed) {
                $grid->column('acquisition_status', "收款状态")->using([0 => "收款关闭", 1 => '收款开启'])->dot([0 => "red", 1 => "#586cb1"])->center();
            } else {
                $acquisitionStatusColumn = $grid->column('acquisition_status', "收款状态")->center();
                $canAcquisitionStatus ? $acquisitionStatusColumn->switch(Admin::color()->green()) : $acquisitionStatusColumn->using([0 => "收款关闭", 1 => '收款开启'])->dot([0 => "red", 1 => "#586cb1"]);
            }

            $grid->column('deposit_user_rate', "代收费率")->display(function ($value) use ($controller) {
                return bob_show_table_info($controller->rateRows($this, 'deposit'), [], [], 3);
            })->top();
            $grid->column('transfer_user_rate', "代付费率")->display(function ($value) use ($controller) {
                return bob_show_table_info($controller->rateRows($this, 'transfer'), [], [], 3);
            })->top();
            $grid->column('settlement_user_rate', "结算费率")->display(function ($value) use ($controller) {
                return bob_show_table_info($controller->rateRows($this, 'settlement'), [], [], 3);
            })->top();
            $grid->column('user_account', "金主账户")->display(function ($value) use ($remainingDepositService, $controller) {
                $remainingDeposit = $remainingDepositService->excute((int) $this->id);
                return $controller->userAccountRows($this, $remainingDeposit);
            })->top();
            $grid->column('trade_limit', "金主限额")->display(function ($value) {
                $data[] = ["代收单笔限额", bob_unit_format($this->collection_limit_min) . " - " . bob_unit_format($this->collection_limit_max)];
                $data[] = ["代付单笔限额", bob_unit_format($this->pay_limit_min) . " - " . bob_unit_format($this->pay_limit_max)];
                return bob_show_table_info($data);
            })->top();
            $grid->column('pay_group_merchant_user_ids', "商户代付分组标示")->display(function () use ($merchantNameMap) {
                if (!empty($this->pay_group_merchant_user_ids)) {
                    $result = collect(explode(",", $this->pay_group_merchant_user_ids))->map(function ($id) use ($merchantNameMap) {
                        return isset($merchantNameMap[$id]) ? [$merchantNameMap[$id]] : null;
                    })->filter()->values()->toArray();
                    if (!empty($result)) {
                        return bob_show_table_info($result);
                    }
                }
            })->top();
            $grid->column('balance5', "商户代收分组标示")->display(function () use ($merchantNameMap) {
                if (!empty($this->collection_group_merchant_ids)) {
                    $result = collect(explode(",", $this->collection_group_merchant_ids))->map(function ($id) use ($merchantNameMap) {
                        return isset($merchantNameMap[$id]) ? [$merchantNameMap[$id]] : null;
                    })->filter()->values()->toArray();
                    if (!empty($result)) {
                        return bob_show_table_info($result);
                    }
                }
            })->top();
            $grid->column('user_bank_info', "收款卡")->display(function () use ($controller) {
                return $controller->userBankStatsRows((int)$this->id, (string)$this->account_types, (string)$this->name);
            })->top();
            $grid->column('limit_amount', "限额")->display(function () {
                $data[] = ["代收限额", bob_unit_format($this->collection_limit_min) . "-" . bob_unit_format($this->collection_limit_max)];
                $data[] = ["代付限额", bob_unit_format($this->pay_limit_min) . "-" . bob_unit_format($this->pay_limit_max)];
                return bob_show_table_info($data);
            })->top();
            $grid->column('other_info', "相关信息")->display(function () use ($isTrashed) {
                $data[] = ["创建时间", $this->created_at];
                if ($isTrashed) {
                    $data[] = ["删除时间", $this->deleted_at];
                    $data[] = ["操作人", optional(AdminUser::where('id', $this->admin_user_id)->first(['username']))->offsetGet('username')];
                } else {
                    if($this->google_two_fa_enable ==  2){
                        if(empty($this->google_two_fa_secret)){
                            $data[] = ['谷歌验证码', '<span class="label" style="background:#21b978;">已开启</span><br/><span class="label" style="background:#ef5228;">已重置</span>'];
                        }else{
                            $data[] = ['谷歌验证码', '<span class="label" style="background:#21b978;">已开启</span>'];
                        }
                    }else{
                        $data[] = ['谷歌验证码', '<span class="label" style="background:#ef5228">未开启</span>'];
                    }
                    $data[] = ['锁定状态', $this->lock_user ? '<span class="label" style="background:#ef5228">是</span>' : '<span class="label" style="background:#21b978">否</span>'];
                    $data[] = ['收益日结至保证金', $this->income_settlement_to_deposit_on ? '<span class="label" style="background:#21b978;">开启</span>' : '<span class="label" style="background:#ef5228;">关闭</span>'];
                    $data[] = ['登陆IP', $this->last_login_ip];
                    $data[] = ['登陆时间', $this->last_login_time];
                }
                return bob_show_table_info($data, [], [], 3);
            })->top();
            if ($isTrashed) {
                $grid->actions(function (Grid\Displayers\Actions $actions) {
                    $actions->disableView();
                    $actions->disableDelete();
                    $actions->disableEdit();
                    $actions->append(new UserDepostDetail());
                });
            } else {
                $grid->actions(function (Grid\Displayers\Actions $actions) use ($adminUser, $canDelete, $canUnlockLogin, $canForceLogout, $canResetPassword, $canUpdateAgent, $canDepositBalanceAdd, $canDepositBalanceReduce, $canCollectionBalanceAdd, $canCollectionBalanceReduce, $canTransferBalanceAdd, $canTransferBalanceReduce, $canCommissionBalanceAdd, $canCommissionBalanceReduce, $canUnbindTelegram, $canResetGoogle) {
                    $actions->disableDelete();
                    if ($canDelete) {
                        $actions->append(new Delete());
                    }
                    if ($canUnlockLogin) {
                        $actions->append(new UnlockUser('user-unlock-login'));
                    }
                    if ($canForceLogout) {
                        $actions->append(new ForceLogout('user-force-logout'));
                    }
                    if ($canResetPassword) {
                        $actions->append(new ResetPassword());
                    }
                    if ($canUpdateAgent) {
                        $actions->append(new UpdateAgent());
                    }

                    if ($adminUser->isAdministrator()) {
                        $actions->append(new CacheStats());
                    }

                    if ($canDepositBalanceAdd) {
                        $actions->append(new AddYajinBalance());
                    }
                    if ($canDepositBalanceReduce) {
                        $actions->append(new ReduceYajinBalance());
                    }
                    $actions->append(new UserDepostDetail());
                    $actions->append(new UserDayBalanceLog());


                    if ($canCommissionBalanceAdd) {
                        $actions->append(new AddCommissionBalance());
                    }
                    if ($canCommissionBalanceReduce) {
                        $actions->append(new ReduceCommissionBalance());
                    }
                    if ($canTransferBalanceAdd) {
                        $actions->append(new AddTransferBalance());
                    }
                    if ($canTransferBalanceReduce) {
                        $actions->append(new ReduceTransferBalance());
                    }
                    if ($canCollectionBalanceAdd) {
                        $actions->append(new AddDepositBalance());
                    }
                    if ($canCollectionBalanceReduce) {
                        $actions->append(new ReduceDepositBalance());
                    }
                    if ($canUnbindTelegram && $actions->row['telegram_group_id'] !== 0) {
                        $actions->append(new ResetTelegram());
                    }
                    if ($canResetGoogle) {
                        $actions->append(new ResetGooglePassword());
                    }
                });
            }

            $grid->filter(function (Grid\Filter $filter) use ($agentOptions, $merchantOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like('last_login_ip', '登陆IP')->width(3);
                $filter->like('username', '金主账号')->width(3);
                $filter->like('name', '金主名称')->width(3);
                $filter->like('mobile', '金主手机号')->width(3);
                $filter->where('agent_id', function ($query) {
                    if ((int) $this->input > 0) {
                        $query->whereHas('user_relation', function ($q) {
                            $q->where('parent_id', (int) $this->input);
                        });
                    }
                }, '代理')->select($agentOptions)->width(3);
                $filter->between('created_at', "创建时间")->date()->width(3);
                $filter->equal('acquisition_status', "收款状态")->select([0 => '收款关闭', 1 => "收款开启"])->width(3);
                $filter->where('pay_merchant_id', function ($query) {
                    $query->whereRaw('FIND_IN_SET(?,pay_group_merchant_user_ids)', [$this->input]);
                }, "代付分组商户")->select($merchantOptions)->width(3);
                $filter->where('collection_merchant_id', function ($query) {
                    $query->whereRaw('FIND_IN_SET(?,collection_group_merchant_ids)', [$this->input]);
                }, "代收分组商户")->select($merchantOptions)->width(3);
                $filter->scope('trashed', '回收站')->onlyTrashed();
            });

            $grid->disableCreateButton();
            if (!$canEdit) {
                $grid->disableEditButton();
            }

            $grid->wrap(function (Renderable $view) use ($agentList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($agentList) {
                    $agent_user_result = $agentList->map(function ($item) {
                        $value['parentid'] = $item['pid'];
                        $value['text'] = "【" . $item['id'] . "】" . $item['name'];
                        $value['level'] = $item['level'];
                        $value['id'] = $item['id'];
                        return $value;
                    });
                    $left = new LeftTreeSide();
                    $left->title("代理列表")->field("agent_id")->default()->prependAll('全部代理')->data($agent_user_result);
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

    public function update($id)
    {
        if ($field = $this->statusSwitchField()) {
            return $this->updateStatusSwitch((int)$id, $field);
        }

        Permission::check($this->updatePermissionSlug());

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('user-delete');

        return parent::destroy($id);
    }

    private function updatePermissionSlug(): string
    {
        $keys = collect($this->requestBodyInput())->keys();
        if ($keys->count() === 1 && $keys->first() === 'status') {
            return 'user-status';
        }
        if ($keys->count() === 1 && $keys->first() === 'acquisition_status') {
            return 'user-acquisition-status';
        }

        return 'user-edit';
    }

    private function statusSwitchField(): ?string
    {
        $input = $this->requestBodyInput();
        if (count($input) !== 1) {
            return null;
        }

        $field = array_key_first($input);

        return in_array($field, ['status', 'acquisition_status'], true) ? $field : null;
    }

    private function requestBodyInput(): array
    {
        $input = request()->request->all();
        if (empty($input) && request()->isJson()) {
            $input = request()->json()->all();
        }

        return collect($input)->except(['_token', '_method', '_previous_', '_editable'])->all();
    }

    private function updateStatusSwitch(int $id, string $field)
    {
        $permission = $field === 'status' ? 'user-status' : 'user-acquisition-status';
        Permission::check($permission);

        $value = request()->input($field);
        if (!in_array((string)$value, ['0', '1'], true)) {
            return response()->json(['status' => false, 'message' => '状态值不合法', 'data' => ['message' => '状态值不合法']]);
        }

        $user = User::query()->whereKey($id)->where('is_agent', 0)->first();
        if (!$user) {
            return response()->json(['status' => false, 'message' => '金主不存在', 'data' => ['message' => '金主不存在']]);
        }

        $user->{$field} = (int)$value;
        $user->save();

        return response()->json(['status' => true, 'message' => '更新成功', 'data' => ['message' => '更新成功']]);
    }

    public function form(): Form
    {
        $pid = (int) request('pid', 0);
        $paymentOptions = $this->paymentCodeOptions();
        $userBankTypeOptions = $this->userBankTypeOptions();
        $merchantOptions = $this->merchantGroupOptions();
        $userGroupOptions = UserGroup::query()->pluck('name', 'id')->prepend("无分组", 0);

        $updatePermissionSlug = fn (): string => $this->updatePermissionSlug();

        return Form::make(new User(), function (Form $form) use ($pid, $paymentOptions, $userBankTypeOptions, $merchantOptions, $userGroupOptions) {
            $form->block(8, function (Form\BlockForm $form) use ($pid, $userBankTypeOptions, $merchantOptions, $userGroupOptions) {
                $title = '基本设置';
                $listUrl = $form->getKey() ? Admin::app()->getRoute('tusers.index') : Admin::app()->getRoute('agents.index');
                $listText = $form->getKey() ? '金主列表' : '金主代理列表';
                $title .= '<a href="' . $listUrl . '" class="btn btn-sm btn-primary" style="position:absolute;right:15px;top:8px"><i class="feather icon-list"></i><span class="d-none d-sm-inline"> ' . $listText . '</span></a>';
                $form->title($title);
                $form->showFooter();

                $id = $form->getKey();
                if ($id) {
                    $form->display('username', "金主帐号");
                } else {
                    $form->hidden('google_two_fa_enable')->default(2);
                    $form->hidden('level')->default(1);
                    $form->hidden('pid')->default($pid);
                    if ($pid > 0) {
                        $uagent_user_item = User::find($pid, ['name']);
                        $form->display('uagent_name', '上级代理名称')->default(optional($uagent_user_item)->name);
                    }

                    $form->text('username', "金主用户名")->rules(['mobile'], ['mobile' => '请输入正确的手机号码'])
                        ->required()
                        ->creationRules(['required', "unique:users"], [
                            'required' => '用户名不能为空',
                            'unique' => '该用户名已存在，请更换',
                        ])
                        ->updateRules(['required', "unique:users,username,$id"], [
                            'required' => '用户名不能为空',
                            'unique' => '该用户名已存在，请更换',
                        ])->maxLength(200)->prepend('<i class="feather icon-user"></i>')->attribute(['autocomplete' => 'off']);
                }
                $form->text('name', "金主名称")->required()->maxLength(100)->prepend('<i class="feather icon-user"></i>');

                if (!$id) {
                    $form->passwordTool('password', '登录密码')->length(12)->attribute(['autocomplete' => 'off']);
                    $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password')->attribute(['autocomplete' => 'off']);
                    $form->ignore(['password_confirmation']);
                }
                $form->radio('status', "账号状态")->options([0 => '禁用', 1 => '启用'])->default(1);
                $form->radio('acquisition_status', "收款状态")->options([0 => '收款关闭', 1 => '收款开启'])->default(1);
                $form->radio('action_collection_status', "自主开关收款卡")->options([0 => '关闭', 1 => '开启'])->default(1);
                $form->radio('self_add_bank', "自主操作收款卡")->options([0 => '关闭', 1 => '开启'])->default(0);
                $form->radio('action_delete', "自主操作删除")->options([0 => '关闭', 1 => '开启'])->default(0);
                $form->radio('action_limit_card', "自主操作收款限制")->options([0 => '关闭', 1 => '开启'])->default(0)->help("操作收款卡限制能力，如：代收单笔最低限额等");
                $form->radio('income_settlement_to_deposit_on', "收益按天结算至保证金")->options([0 => '关闭', 1 => '开启'])->default(0)->help("开启后每天凌晨自动把佣金账户余额结算到保证金");
                $form->text('mobile', "金主手机号码")->required()->maxLength(100)->prepend('<i class="feather icon-mobile"></i>');
                $form->number('collection_limit_min', '代收单笔最低限额')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代收单笔最低限额0-999999999'])->default(0)->required()->help("0为不限制");
                $form->number('collection_limit_max', '代收单笔最高限额')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:collection_limit_min'], ['numeric' => '数值不合法', 'between' => '代收单笔最高限额0-999999999', 'gte' => '收款单笔最高限额必须大于等于收款单笔最低限额'])->default(0)->required()->help("0为不限制");
                $form->number('pay_limit_min', '代付单笔最低限额')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代付单笔最低限额0-999999999'])->default(0)->required()->help("0为不限制");
                $form->number('pay_limit_max', '代付单笔最高限额')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:pay_limit_min'], ['numeric' => '数值不合法', 'between' => '代付单笔最高限额0-999999999', 'gte' => '代付单笔最高限额必须大于等于代付单笔最低限额'])->default(0)->required()->help("0为不限制");
                $form->number('limit_deposit_paid_number', '代收待付款相同金额订单限制')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces(0)], ['numeric' => '数值不合法', 'between' => '代收待付款相同金额订单限制0-100'])->default(0)->required()->help("0为不限制");
                $form->number('round_times', '排单次数')->rules(['numeric', 'between:1,5'],['numeric' => '数值不合法', 'between' => '排单次数1-5'])->help("主要是让金主收款卡，在一次轮询中，多排单，默认1次")->default(1);
                $form->multipleSelect('account_types', '收款卡类型')->options($userBankTypeOptions)->saving(function ($value) {
                    $filteredArray = array_filter($value, function ($value) {
                        return ($value !== null);
                    });
                    if (!empty($filteredArray)) {
                        return implode(",", $value);
                    }
                    return null;
                })->help('支持收款卡类型，不填表示支持所有类型');
                $form->multipleSelect('pay_group_merchant_user_ids', '商户代付分组标示')->options($merchantOptions)->saving(function ($value) {
                    $filteredArray = array_filter($value, function ($value) {
                        return ($value !== null);
                    });
                    if (!empty($filteredArray)) {
                        return implode(",", $value);
                    }
                    return null;
                })->help('专门匹配给当前设置的商户代付');
                $form->multipleSelect('collection_group_merchant_ids', '商户代收分组标示')->options($merchantOptions)->saving(function ($value) {
                    $filteredArray = array_filter($value, function ($value) {
                        return ($value !== null);
                    });
                    if (!empty($filteredArray)) {
                        return implode(",", $value);
                    }
                    return null;
                })->help('专门匹配给当前设置的商户代收');
                $form->select("user_group_id", '推送分组')->options($userGroupOptions)->default(0)->disableClearButton();
                $form->textarea('remark', '备注');
                app(\App\Services\Google\AdminGoogle2faService::class)->appendField($form);
            });
            $form->block(4, function (Form\BlockForm $form) use ($paymentOptions) {
                $form->title('金主代收代付结算默认费率');
                $form->rate('user_rate', '金主费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '金主费率0-100'])->default(0)->required();
                $form->rate('agent1_rate', '一级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '一级代理费率0-100'])->default(0)->required();
                $form->rate('agent2_rate', '二级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '二级代理费率0-100'])->default(0)->required();
                $form->rate('agent3_rate', '三级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '三级代理费率0-100'])->default(0)->required();
                $form->rate('agent4_rate', '四级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '四级代理费率0-100'])->default(0)->required();
                $form->rate('agent5_rate', '五级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '五级代理费率0-100'])->default(0)->required();

                $form->next(function (Form\BlockForm $form) use ($paymentOptions) {
                    $form->title('金主代收费率');
                    $form->rate('deposit_user_rate', '金主费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '金主费率0-100'])->default(0)->required();
                    $form->rate('deposit_agent1_rate', '一级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '一级代理费率0-100'])->default(0)->required();
                    $form->rate('deposit_agent2_rate', '二级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '二级代理费率0-100'])->default(0)->required();
                    $form->rate('deposit_agent3_rate', '三级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '三级代理费率0-100'])->default(0)->required();
                    $form->rate('deposit_agent4_rate', '四级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '四级代理费率0-100'])->default(0)->required();
                    $form->rate('deposit_agent5_rate', '五级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '五级代理费率0-100'])->default(0)->required();
                    $form->table("user_deposit_payment_rate", "金主代收编码费率", function (Form\NestedForm $form) use ($paymentOptions) {
                        $form->select("payment_id", "通道编码")->options($paymentOptions)->required();
                        $form->rate("deposit_user_rate", "金主费率")->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '金主费率0-100'])->attribute('style', 'width:40px;text-align:center;')->default(0)->required();
                    });
                });

                $form->next(function (Form\BlockForm $form) {
                    $form->title('金主代付费率');
                    $form->rate('transfer_user_rate', '金主费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '金主费率0-100'])->default(0)->required();
                    $form->rate('transfer_agent1_rate', '一级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '一级代理费率0-100'])->default(0)->required();
                    $form->rate('transfer_agent2_rate', '二级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '二级代理费率0-100'])->default(0)->required();
                    $form->rate('transfer_agent3_rate', '三级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '三级代理费率0-100'])->default(0)->required();
                    $form->rate('transfer_agent4_rate', '四级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '四级代理费率0-100'])->default(0)->required();
                    $form->rate('transfer_agent5_rate', '五级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '五级代理费率0-100'])->default(0)->required();
                });

                $form->next(function (Form\BlockForm $form) {
                    $form->title('金主结算费率');
                    $form->rate('settlement_user_rate', '金主费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '金主费率0-100'])->default(0)->required();
                    $form->rate('settlement_agent1_rate', '一级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '一级代理费率0-100'])->default(0)->required();
                    $form->rate('settlement_agent2_rate', '二级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '二级代理费率0-100'])->default(0)->required();
                    $form->rate('settlement_agent3_rate', '三级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '三级代理费率0-100'])->default(0)->required();
                    $form->rate('settlement_agent4_rate', '四级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '四级代理费率0-100'])->default(0)->required();
                    $form->rate('settlement_agent5_rate', '五级代理费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '五级代理费率0-100'])->default(0)->required();
                });
            });
        })->saving(function (Form $form) use ($updatePermissionSlug) {
            $permissionSlug = $updatePermissionSlug();
            if ($form->isEditing() && !Admin::user()->can($permissionSlug)) {
                return $form->response()->error('无金主操作权限');
            }

            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }
            if (!$form->password) {
                $form->deleteInput('password');
            }
            if ($form->isCreating() || $permissionSlug === 'user-edit') {
                try {
                    app(\App\Services\Google\AdminGoogle2faService::class)->verify($form->google_2fa_code);
                } catch (\Exception $e) {
                    return $form->response()->error($e->getMessage());
                }
                $form->deleteInput('google_2fa_code');
            }

            if ($form->pid > 0) {
                $uagent_user_item = User::find($form->pid);
                if (!$uagent_user_item) {
                    return $form->response()->error('非法操作');
                } else {
                    $form->pid = $uagent_user_item->id;
                    $form->level = $uagent_user_item->level + 1;
                }
            }
        })->saved(function (Form $form) {
            if ((int) request('pid', 0) > 0) {
                return $form->response()->success('新增下级金主成功')->redirect(Admin::app()->getRoute('agents.index'));
            }
        });
    }

    private function listColumns(): array
    {
        return [
            'id', 'name', 'username', 'mobile', 'status', 'acquisition_status', 'balance_amount', 'deposit_balance_amount', 'transfer_balance_amount',
            'commission_balance_amount', 'income_settlement_to_deposit_on', 'zeros_balance', 'deposit_amount', 'collection_limit_min', 'collection_limit_max', 'pay_limit_min', 'pay_limit_max',
            'user_rate', 'agent1_rate', 'agent2_rate', 'agent3_rate', 'agent4_rate', 'agent5_rate',
            'deposit_user_rate', 'deposit_agent1_rate', 'deposit_agent2_rate', 'deposit_agent3_rate', 'deposit_agent4_rate', 'deposit_agent5_rate',
            'transfer_user_rate', 'transfer_agent1_rate', 'transfer_agent2_rate', 'transfer_agent3_rate', 'transfer_agent4_rate', 'transfer_agent5_rate',
            'settlement_user_rate', 'settlement_agent1_rate', 'settlement_agent2_rate', 'settlement_agent3_rate', 'settlement_agent4_rate', 'settlement_agent5_rate',
            'pay_group_merchant_user_ids', 'collection_group_merchant_ids', 'account_types', 'created_at', 'deleted_at', 'admin_user_id',
            'google_two_fa_enable', 'google_two_fa_secret', 'lock_user', 'last_login_ip', 'last_login_time', 'telegram_group_id', 'is_agent',
        ];
    }

    public function userBankStatsRows(int $userId, string $accountTypes = '', string $userName = ''): string
    {
        if ($userId <= 0) {
            return '<span class="text-muted">-</span>';
        }

        $bankTypes = App::make(GetSelfUserBankTypeService::class)->excute();
        $accountTypeIds = array_filter(array_map('intval', explode(',', $accountTypes)));
        if (!empty($accountTypeIds)) {
            $bankTypes = $bankTypes->whereIn('id', $accountTypeIds);
        }
        if ($bankTypes->isEmpty()) {
            return '<span class="text-muted">暂无可用收款卡类型</span>';
        }

        $counts = UserBank::query()
            ->where('user_id', $userId)
            ->where('collection_status', 1)
            ->groupBy('payment_id')
            ->selectRaw('payment_id, count(*) as total')
            ->pluck('total', 'payment_id');

        $items = [];
        foreach ($bankTypes as $bankType) {
            $paymentId = (int)($bankType['id'] ?? 0);
            $name = (string)($bankType['name'] ?? '');
            $count = (int)($counts[$paymentId] ?? 0);
            $url = Admin::app()->getRoute('bank-users.index', ['user_id' => $userId, 'payment_id' => $paymentId]);
            $tabKey = 'user-bank-list-tab-' . $userId . '-' . $paymentId;
            $tabTitle = ($userName ?: '金主') . $name . '收款卡';
            $style = $count > 0
                ? 'background:#586cb1;color:#fff;border-color:#586cb1;'
                : 'background:#f4f5f7;color:#8a92a6;border-color:#e8eaf0;';

            $items[] = '<a href="javascript:void(0)" data-url="' . e($url) . '" data-tab="' . e($tabKey) . '" data-tab-title="' . e($tabTitle) . '" data-tab-icon="icon-credit-card" style="display:inline-flex;align-items:center;gap:4px;margin:0 4px 5px 0;padding:3px 7px;border:1px solid;border-radius:12px;font-size:12px;line-height:1.2;' . $style . '">'
                . '<span>' . e($name) . '</span><b>' . $count . '</b></a>';
        }

        return '<div style="min-width:170px;max-width:260px;white-space:normal;">' . implode('', $items) . '</div>';
    }

    private function userBankTypeOptions(): array
    {
        $paymentCodes = collect(config('payment'))
            ->mapWithKeys(fn (array $payment) => [(int)($payment['id'] ?? 0) => (string)($payment['code'] ?? '')]);

        return App::make(GetSelfUserBankTypeService::class)->excute()
            ->mapWithKeys(function (array $item) use ($paymentCodes) {
                $id = (int)($item['id'] ?? 0);
                $name = (string)($item['name'] ?? '');
                $code = trim((string)$paymentCodes->get($id, ''));
                $label = $code !== '' ? '【' . $code . '】' . $name : $name;

                return [$id => $label];
            })
            ->all();
    }

    private function paymentCodeOptions(): array
    {
        return collect(config('payment'))
            ->mapWithKeys(function (array $payment) {
                $id = (int)($payment['id'] ?? 0);
                $code = trim((string)($payment['code'] ?? ''));
                $name = trim((string)($payment['name'] ?? ''));
                $label = $code !== '' ? '【' . $code . '】' . $name : $name;

                return [$id => $label];
            })
            ->all();
    }

    private function merchantGroupOptions(): array
    {
        return MerchantInfo::query()
            ->orderBy('merchant_user_id')
            ->get(['merchant_user_id', 'name', 'coder', 'currency_id'])
            ->mapWithKeys(function (MerchantInfo $merchant) {
                $coder = trim((string)$merchant->coder);
                $label = trim((string)$merchant->name);
                if ($coder !== '') {
                    $label = '【' . $coder . '】' . $label;
                }

                return [(int)$merchant->merchant_user_id => $label];
            })
            ->all();
    }

    public function userAccountRows($user, array $remainingDeposit): string
    {
        $rows = [
            ["佣金账户", bob_unit_format($user->commission_balance_amount)],
            ["剩余押金", (float)$user->deposit_amount > 0 ? bob_unit_format($remainingDeposit['remaining_deposit'] ?? 0) : '不限制'],
        ];
        if (config('app.name') != 'lixiangpay') {
            $rows[] = ["金主余额", bob_unit_format($user->balance_amount)];
        }
        $rows[] = ["代收账户", bob_unit_format($user->deposit_balance_amount)];
        $rows[] = ["代付账户", bob_unit_format($user->transfer_balance_amount)];
        $rows[] = ["0点剩余押金", bob_unit_format($user->zeros_balance)];

        return bob_show_table_info($rows, [], ['tr-6', 'tr-7', 'tr-8', 'tr-1', 'tr-2', 'tr-3'], 3);
    }

    private function agentRows($user): array
    {
        if (empty($user)) {
            return [];
        }

        $rows = [];
        foreach ($this->agentLevels() as $key => $label) {
            if (!empty($user[$key])) {
                $rows[] = [$label, "【#" . $user[$key]['id'] . "】" . $user[$key]['name']];
            }
        }

        return $rows;
    }

    private function rateRows($row, string $prefix): array
    {
        return [
            ["金主费率", (floatval($row->{$prefix . '_user_rate'}) ?: floatval($row->user_rate)) . "%"],
            ["一级代理费率", (floatval($row->{$prefix . '_agent1_rate'}) ?: floatval($row->agent1_rate)) . "%"],
            ["二级代理费率", (floatval($row->{$prefix . '_agent2_rate'}) ?: floatval($row->agent2_rate)) . "%"],
            ["三级代理费率", (floatval($row->{$prefix . '_agent3_rate'}) ?: floatval($row->agent3_rate)) . "%"],
            ["四级代理费率", (floatval($row->{$prefix . '_agent4_rate'}) ?: floatval($row->agent4_rate)) . "%"],
            ["五级代理费率", (floatval($row->{$prefix . '_agent5_rate'}) ?: floatval($row->agent5_rate)) . "%"],
        ];
    }

    private function agentLevels(): array
    {
        return [
            'one' => '一级代理',
            'two' => '二级代理',
            'three' => '三级代理',
            'four' => '四级代理',
            'five' => '五级代理',
        ];
    }
}
