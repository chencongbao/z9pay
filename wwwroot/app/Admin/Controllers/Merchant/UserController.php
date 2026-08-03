<?php

namespace App\Admin\Controllers\Merchant;

use Throwable;
use Dcat\Admin\Form;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Grid\Filter;
use Illuminate\Support\Arr;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use App\Models\MerchantPayment;
use App\Rules\DecimalTwoPlaces;
use App\Models\AgentUserRelation;
use App\Extendtions\Dcat\src\Grid;
use Spatie\Activitylog\Facades\LogBatch;
use App\Extendtions\Dcat\Widgets\BobTable;
use App\Admin\Controllers\CommonController;
use App\Models\MerchantUser as Administrator;
use App\Services\Enums\DepositChannelModeEnum;
use App\Services\IpWhite\WhiteIpFormatService;
use App\Services\Google\AdminGoogle2faService;
use App\Admin\Actions\Grid\MerchantUser\Delete;
use App\Admin\Metrics\Admin\MerchantUser\Card1;
use App\Admin\Metrics\Admin\MerchantUser\Card2;
use App\Admin\Metrics\Admin\MerchantUser\Card3;
use App\Admin\Metrics\Admin\MerchantUser\Card4;
use App\Admin\Actions\Grid\MerchantUser\WhiteIp;
use App\Admin\Actions\Grid\MerchantUser\ExportData;
use App\Admin\Actions\Grid\MerchantUser\UnlockUser;
use App\Admin\Actions\Grid\MerchantUser\ResetPassword;
use App\Admin\Actions\Grid\MerchantUser\ResetTelegram;
use App\Admin\Actions\Grid\MerchantUser\TelegramAdmins;
use App\Services\DepositOrder\GetUsdtCurrencyRateService;
use App\Admin\Actions\Grid\MerchantUser\ResetGooglePassword;
use App\Admin\Actions\Grid\MerchantUser\MerchantDayBalanceLog;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class UserController extends CommonController
{

    protected $translation = 'merchant-user';

    protected function grid()
    {
        $agentDetailService = app(GetMerchantAgentDetailService::class);
        $agentOptions = bob_build_select_options(collect(app(GetMerchantAgentListService::class)->excute())->toArray());
        $currencyOptions = collect(config('default.currency'))->mapWithKeys(function ($item) {
            return [$item['id'] => "【" . $item['id'] . "】" . $item['name']];
        });

        return Grid::make(Administrator::with(["merchant_info" => function ($q) {
            $q->withTrashed();
        }]), function (Grid $grid) use ($agentDetailService, $agentOptions, $currencyOptions) {
            $grid->model()->where('pid', 0)->orderByDesc('status')->orderByDesc('id');
            $grid->column('id', "编号")->sortable()->display(function ($value) {
                return '<a style="color:green;text-decoration: underline;" href="' . Admin::app()->getRoute("merchant.user.detail", ['id' => $this->id]) . '">' . $value . '</a>';
            })->center();
            $grid->column('base_info', "基本信息")->display(function () {
                $data[] = ["商户账号", $this->username];
                $data[] = ["商户名称", $this->merchant_info->name];
                $data[] = ["商户代码", $this->merchant_info->coder];
                return bob_show_table_info($data, [], ['tr-1', 'tr-2', 'tr-3']);
            })->top();
            $grid->column('agent_info', "代理信息")->display(function () use ($agentDetailService) {
                if ($this->merchant_info->agent_user_id > 0) {
                    $agent = $agentDetailService->excute($this->merchant_info->agent_user_id);
                    if (!empty($agent)) {
                        $data[] = ['一级代理', "【#" . $agent['id'] . "】" . $agent['name']];
                        if (!empty($agent['one'])) {
                            $data[] = ['二级代理', "【#" . $agent['one']['id'] . "】" . $agent['one']['name']];
                        }
                        if (!empty($agent['two'])) {
                            $data[] = ['三级代理', "【#" . $agent['two']['id'] . "】" . $agent['two']['name']];
                        }
                        return bob_show_table_info($data, [], ['tr-4', 'tr-5', 'tr-6'], 5);
                    }
                }
            })->top();
            $grid->column('status', "状态")->status()->center();
            $grid->column('merchant_info.currency_id', "交易币种")->display(function ($value) {
                return bob_get_value_by_id_array(['id' => $value], 'name', config('default.currency'));
            })->center();
            $grid->column('amount_info', "账户资金信息")->display(function ($value) {
                $data[] = ["账户总额", bob_unit_format($this->merchant_info->balance_amount)];
                $data[] = ["可用余额", bob_unit_format($this->merchant_info->available_balance)];
                if ($this->merchant_info->is_usdt_ava_rate == 1) {
                    $data[] = ["USDT平均费率", floatval($this->merchant_info->usdt_ava_rate)];
                    $data[] = ["USDT总额", floatval($this->merchant_info->available_usdt_balance)];
                }
                $data[] = ["冻结资金", bob_unit_format($this->merchant_info->freeze_amount)];
                $data[] = ["结算资金", bob_unit_format($this->merchant_info->settlement_amount)];
                $data[] = ["日切余额", bob_unit_format($this->merchant_info->history_balance_amount)];
                $data[] = ["变动时间", $this->merchant_info->last_balance_amount_time];
                $data[] = ["日切时间", $this->merchant_info->history_end_balance_amount_time];
                return bob_show_table_info($data, [], ['tr-9', 'tr-8', 'tr-7', 'tr-6', 'tr-5', 'tr-4'], 3);
            })->top();
            $grid->column('login_white_ip', '登录提现Ip白名单')->display(function ($value) {
                return format_grid_line_muti_line_data($value);
            })->center();
            $grid->column('merchant_info.pay_white_ip', '代付Ip白名单')->display(function ($value) {
                return format_grid_line_muti_line_data($value);
            })->center();
            $grid->column('merchant_info.float_amount', "收款浮动/差额区间")->display(function () {
                if ($this->merchant_info->amount_float_type == 1) return bob_show_table_info([["上浮", bob_unit_format($this->merchant_info->float_amount)]]);
                if ($this->merchant_info->amount_float_type == 2) return bob_show_table_info([["下浮", bob_unit_format($this->merchant_info->float_amount)]]);
                return bob_show_table_info([["关闭"]]);
            })->top();
            $grid->column('created_at', trans('admin.created_at'))->center();
            $grid->disableDeleteButton();
            $grid->withBorder();
            $grid->disableViewButton();
            $grid->disableRowSelector();
            if (!Admin::user()->can('merchant-user-add')) {
                $grid->disableCreateButton();
            }
            if (request()->input('_scope_') == 'trashed') {
                $grid->disableActions();
            } else {
                $grid->actions(function ($actions) {
                    $actions->disableDelete();
                    if (!Admin::user()->can('merchant-user-edit')) {
                        $actions->disableEdit();
                    }
                    if (Admin::user()->can('merchant-user-delete')) {
                        $actions->append(new Delete());
                    }
                    if (Admin::user()->can('merchant-user-unbind-telegram')) {
                        if (intval($actions->row['merchant_info']['telegram_group_id'] ?? 0) !== 0) {
                            $actions->append(new ResetTelegram());
                            $actions->append(new TelegramAdmins());
                        }
                    }
                    if (Admin::user()->can('merchant-user-reset-password')) {
                        $actions->append(new ResetPassword());
                    }
                    $actions->append('<li class="dropdown-item"><a style="cursor: pointer;" class="act-8A938BWVZXRymCX5" href="javascript:void(0)" data-url="' . Admin::app()->getRoute("merchant.user.detail", ['id' => $actions->getKey()]) . '"  data-tab="merchant-user-detail-tab"><i class="feather icon-eye"></i> 商户对接 </a></li>');
                    if (Admin::user()->can('merchant-user-reset-googlecode')) {
                        $actions->append(new ResetGooglePassword());
                    }
                    if (Admin::user()->can('merchant-user-white-ip')) {
                        $actions->append(new WhiteIp());
                    }
                    $actions->append(new MerchantDayBalanceLog());
                    if (Admin::user()->can('merchant-user-unlock-login')) {
                        $actions->append(new UnlockUser());
                    }
                });
            }

            $grid->header(function ($collection) {
                $row = new Row();
                $row->column(12, function (Column $column) use ($row) {
                    $row->column(3, new Card1(request()->all()));
                    $row->column(3, new Card2(request()->all()));
                    $row->column(3, new Card3(request()->all()));
                    $row->column(3, new Card4(request()->all()));
                });
                return $row;
            });

            $grid->tools(function ($tools) {
                $tools->append(new ExportData());
            });

            $grid->filter(function (Filter $filter) use ($agentOptions, $currencyOptions) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like('username', "商户账号")->width(3);
                $filter->like('merchant_info.name', "商户名称")->width(3);
                $filter->like('merchant_info.coder', "商户代码")->width(3);
                $filter->equal('status', "启用状态")->select(config('default.status_text'))->width(3);
                $filter->equal('merchant_info.currency_id', "请选择币种")->select($currencyOptions)->width(3);
                $filter->where('agent_user_id', function ($q) {
                    $q->whereIn('id', MerchantInfo::where('agent_user_id', $this->input)->orWhere(function ($query) {
                        $query->whereIn('agent_user_id', AgentUserRelation::where('parent_id', $this->input)->pluck('child_id'));
                    })->get()->pluck('merchant_user_id'));
                }, "所属代理")->select($agentOptions)->width(3);
                $filter->scope('trashed', '回收站')->onlyTrashed();
            });
        });
    }


    public function form()
    {
        $agentOptions = collect(bob_build_select_options(collect(app(GetMerchantAgentListService::class)->excute())->toArray()))->prepend("无代理", 0)->toArray();
        $google2faService = app(AdminGoogle2faService::class);

        return Form::make(Administrator::with(['roles', 'merchant_info']), function (Form $form) use ($agentOptions, $google2faService) {
            $userTable = config('merchant-admin.database.users_table');
            $connection = config('merchant-admin.database.connection');
            $id = $form->getKey();
            if ($id) {
                $form->display('username', "商户帐号");
            } else {
                $form->text('username', "商户帐号")
                    ->required()
                    ->creationRules(['required', "unique:$connection.$userTable"], [
                        'required' => '商户帐号不能为空',
                        'unique' => '商户帐号已存在，请更换',
                    ])
                    ->updateRules(['required', "unique:$connection.$userTable,username,$id"], [
                        'required' => '商户帐号不能为空',
                        'unique' => '商户帐号已存在，请更换',
                    ])->maxLength(200)->prepend('<i class="feather icon-user"></i>')->attribute(['autocomplete' => 'off']);
                $form->hidden("roles")->default(2);
            }
            $form->text('merchant_info.name', "商户名称")->required()->maxLength(100)->prepend('<i class="feather icon-user"></i>');
            $form->text('merchant_info.coder', "商户代码")
                ->required()
                ->creationRules(['required', "unique:merchant_infos,coder"], [
                    'required' => '商户代码不能为空',
                    'unique' => '商户代码已存在，请更换',
                ])
                ->updateRules(['required', "unique:merchant_infos,coder,$id,merchant_user_id"], [
                    'required' => '商户代码不能为空',
                    'unique' => '商户代码已存在，请更换',
                ])->maxLength(200)->prepend('<i class="feather icon-code"></i>')->saving(function ($value) {
                    return strtoupper($value);
                });
            if (!$id) {
                $form->hidden('merchant_info.appkey')->default(bob_create_appkey());
                $form->hidden('merchant_info.appsecret')->default(bob_create_app_secret());
                $form->passwordTool('password', '登录密码')->length(12)->attribute(['autocomplete' => 'off']);
                $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password')->required()->attribute(['autocomplete' => 'off']);
            }

            $form->ignore(['password_confirmation']);
            $form->radio('status', '启用状态')->options(config('default.status_text'))->default(1);

            $form->select('merchant_info.currency_id', '交易币种')->options(bob_array_to_keyvalue(config('default.currency')))->rules(['numeric', 'min:1'], ['numeric' => '请选择交易币种', 'min' => "请选择交易币种"])->default(1)->disableClearButton()->required();
            $form->select('merchant_info.agent_user_id', '所属代理')->options($agentOptions)->default(0)->rules(['numeric', 'min:0'], ['numeric' => '请选择所属代理', 'min' => "请选择所属代理"])->default(0)->disableClearButton()->required();

            $form->textarea('login_white_ip', '登录提现ip白名单')->placeholder('多个IP请用,隔开');
            $form->textarea('merchant_info.pay_white_ip', '代付ip白名单')->placeholder('多个IP请用,隔开');
            $form->radio("merchant_info.amount_float_type", "金额浮动模式")->options([0 => "关闭", 1 => '上浮', 2 => '下浮'])->default(0)->when([1, 2], function (Form $form) {
                $form->number("merchant_info.float_amount", "最大差额")->default(0)->required()->help("请填写>=2的整数")->rules(['numeric', 'between:0,999', new DecimalTwoPlaces()], ['numeric' => '请填写数字', 'between' => "最大差额0-999"]);
                $form->radio('merchant_info.is_need_decimal', '需要小数')->options([0 => "否", 1 => "是"])->default(1);
            });
            $form->select('merchant_info.deposit_channel_mode', '代收渠道模式')->options(Arr::collapse([[0=>"默认配置"],DepositChannelModeEnum::MAP]))->default(0);
            $form->select('merchant_info.transfer_channel_mode', '代付渠道模式')->options([0 => "默认配置", 2 => "按随机", 3 => "按平均", 5 => "按权重"])->default(0);

            $form->hidden('merchant_info.usdt_ava_rate')->default(0);
            $form->hidden('merchant_info.available_usdt_balance')->default(0);
            $form->radio('merchant_info.is_usdt_ava_rate', '计算USDT平均费率')->options([0 => "关闭", 1 => "开启"])->default(0)->when(1, function (Form $form) {
                $form->select("merchant_info.okx_payment_method","支付方式")->options(['all'=>"全部","wxPay"=>"微信","aliPay"=>"支付宝","bank"=>"银行卡"]);
                $form->select("merchant_info.okx_side","模式")->options(['all'=>"全部",'sell'=>"购买","buy"=>"出售"]);
                $form->select("merchant_info.okx_user_type","类型")->options(['all'=>"全部用户","user"=>"普通用户","vip"=>"VIP","blockTrade"=>"大宗交易","merchant"=>"认证商家"]);
                $form->select("merchant_info.okx_index","档位")->options(["第一档","第二档","第三档","第四档","第五档","第六档","第七档","第八档","第九档","第十档"])->default(2);
                $form->text('merchant_info.default_usdt_ava_rate', "USDT默认费率")->rules(['numeric', 'between:0,100', new DecimalTwoPlaces(6)], ['numeric' => '数值不合法', 'between' => '费率0-100'])->default(0)->help("开启时或未抓取到实时汇率，则使用此汇率，例如：1usdt=7cny")->prepend("1USDT=");
                $form->text('merchant_info.usdt_float_rate', "USDT浮动费率")->rules(['numeric', 'between:0,100', new DecimalTwoPlaces(6)], ['numeric' => '数值不合法', 'between' => '费率0-100'])->default(0)->help("抓取到的实时费率上面在加上浮动费率")->prepend("1USDT=");
            })->help("在开启之前，请联系技术确认USDT实时费率抓取");
            $form->radio('merchant_info.auto_transfer', '自动代付')->options([0 => "关闭", 1 => "开启"])->default(1)->help("自动代付关闭，代付订单自动进入待处理，需要手动代付");
            if (Admin::user()->isAdministrator()) {
                $form->text('merchant_info.cashier_domain', "收营台域名");
                $form->radio('merchant_info.check_order', '订单反查')->options([0 => "关闭", 1 => "开启"])->default(0);
                $form->radio('merchant_info.sign_space', '签名空格')->options([0 => "验证空格", 1 => "不验证空格"])->default(1);
                $form->radio('merchant_info.system_create_ip', '系统生成IP')->options([0 => "否", 1 => "是"])->default(0);
            } else {
                $form->hidden('merchant_info.sign_space')->default(1);
            }
            $google2faService->appendField($form);
        })->saving(function (Form $form) use ($google2faService) {
            $admin = Admin::user();
            if ($form->isCreating() && !$admin->can('merchant-user-add')) {
                return $form->response()->error('无新增商户权限');
            }
            if ($form->isEditing() && !$admin->can('merchant-user-edit')) {
                return $form->response()->error('无编辑商户权限');
            }

            // 将同一次商户保存行为（merchant_user + merchant_info + 手工业务日志）归并到同一批次
            LogBatch::startBatch();
            request()->attributes->set('merchant_user_form_batch_uuid', LogBatch::getUuid());

            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }
            if (!$form->password) {
                $form->deleteInput('password');
            }
            try {
                // 保存前校验并规范白名单，避免后续登录/代付校验时读到非法 IP。
                $whiteIpFormatService = app(WhiteIpFormatService::class);
                $form->input('login_white_ip', $whiteIpFormatService->normalize($form->login_white_ip, '登录提现Ip白名单'));
                $form->input('merchant_info.pay_white_ip', $whiteIpFormatService->normalize($form->merchant_info['pay_white_ip'] ?? '', '代付Ip白名单'));
                $google2faService->verify($form->google_2fa_code);
                if (isset($form->merchant_info['is_usdt_ava_rate']) && $form->merchant_info['is_usdt_ava_rate'] == 1 && floatval($form->merchant_info['usdt_ava_rate']) == 0) {
                    $result = app(GetUsdtCurrencyRateService::class)->init($form->merchant_info['currency_id'], $form->merchant_info['okx_payment_method'], $form->merchant_info['okx_user_type'], $form->merchant_info['okx_side'], $form->merchant_info['okx_index']);
                    if ($result <= 0) {
                        throw new \Exception("请联系技术支持确认USDT抓取费率");
                    }
                    $form->input('merchant_info.usdt_ava_rate', $result + floatval($form->merchant_info['usdt_float_rate']));
                }
            } catch (Throwable $e) {
                return $form->response()->error($e->getMessage());
            }
            $form->deleteInput('google_2fa_code');
        });
    }

    public function detail(Content $content)
    {
        $merchantUserId = (int) request()->input('id');
        $info = MerchantInfo::query()->find($merchantUserId);
        $paymentMap = collect(config('payment'))->keyBy('id');

        return $content->title("商户信息")
            ->body(function (Row $row) use ($info, $merchantUserId, $paymentMap) {
                $row->column(6, function (Column $column) use ($row, $info) {
                    $form = new \Dcat\Admin\Widgets\Form();
                    $form->disableResetButton();
                    $form->disableSubmitButton();
                    $form->display("merchant_coder", "商户代码")->default(optional($info)->coder)->width(6, 3);
                    $form->display("merchant_name", "商户名称")->default(optional($info)->name)->width(6, 3);
                    $form->display("merchant_google", "谷歌验证器")->default(view('merchant-admin.home.google-status', ['google_two_fa_enable' => Admin::user()->google_two_fa_enable, 'google_two_fa_bind' => Admin::user()->google_two_fa_bind]))->width(6, 3);
                    $card = new Card("基本信息", $form);
                    $card->withHeaderBorder();
                    $card->style("height:400px");
                    $column->row($card);
                });
                $row->column(6, function (Column $column) use ($row, $info) {
                    $form = new \Dcat\Admin\Widgets\Form();
                    $form->disableResetButton();
                    $form->disableSubmitButton();
                    $form->display("merchant_id", "商户ID")->default(optional($info)->merchant_user_id)->width(3, 3);
                    $form->text("merchant_app_key", "商户API密钥")->default(bob_str_replace(optional($info)->appkey))->disable()->width(6, 3)->prepend('');
                    $form->text("merchant_app_secect", "商户签名密钥")->default(bob_str_replace(optional($info)->appsecret))->disable()->width(6, 3)->prepend('');
                    $card = new Card("API对接信息", $form);
                    $card->withHeaderBorder();
                    $card->style("height:400px");
                    $column->row($card);
                });
                $row->column(12, function (Column $column) use ($merchantUserId, $paymentMap) {
                    $headers = ['通道类型', '通道代码', '启用状态', '支付费率%', '单笔最低限额', '单笔最高限额'];
                    $rows = MerchantPayment::where('merchant_user_id', $merchantUserId)->get(['payment_id', 'status', 'pay_rate', 'min_limit_amount', 'max_limit_amount'])->map(function ($item) use ($paymentMap) {
                        $payment = $paymentMap->get($item->payment_id, []);
                        return [$payment['name'] ?? '', $payment['code'] ?? '', optional(['<span class="label" style="background:#ef5228">禁用</span>', '<span class="label" style="background:#586cb1">启用</span>'])[$item->status], $item->pay_rate, $item->min_limit_amount, $item->max_limit_amount];
                    });
                    $table = new BobTable($headers, $rows, 'custom-data-table data-table');
                    $table->withBorder();
                    $card = new Card("通道费率", $table);
                    $card->withHeaderBorder();
                    $column->row($card);
                });
            });
    }
}
