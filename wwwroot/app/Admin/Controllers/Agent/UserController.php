<?php

namespace App\Admin\Controllers\Agent;

use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Content;
use App\Models\AgentUser;
use Dcat\Admin\Http\Auth\Permission;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use App\Admin\Controllers\CommonController;
use App\Admin\Actions\Grid\AgentUser\Delete;
use App\Services\IpWhite\WhiteIpFormatService;
use App\Admin\Actions\Grid\AgentUser\UnlockUser;
use App\Admin\Extensions\Tools\Agent\CreateButton;
use App\Admin\Actions\Grid\AgentUser\CreateNextAgent;
use App\Repositories\AgentUser as AgentUserRepository;
use App\Admin\Actions\Grid\AgentUser\ResetGooglePassword;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class UserController extends CommonController
{

    protected $translation = "agent-user";

    protected function grid()
    {
        $admin = Admin::user();
        $canCreate = $admin->can('merchant-agent-create');
        $canEdit = $admin->can('merchant-agent-edit');
        $canDelete = $admin->can('merchant-agent-delete');
        $canResetGoogle = $admin->can('merchant-agent-reset-googlecode');
        $canUnlockLogin = $admin->can('merchant-agent-unlock-login');
        $agentDetailService = App::make(GetMerchantAgentDetailService::class);
        $agentOptions = collect(App::make(GetMerchantAgentListService::class)->excute())->pluck('bname', 'id');
        $agentInfoTable = function (array $agent) {
            return [
                ["账号", $agent['bname'] ?? ''],
                ["层级", $agent['level'] ?? ''],
                ["状态", ($agent['status'] ?? 0) ? '<span class="label" style="background:#21b978">启用</span>' : '<span class="label" style="background:#ef5228">禁用</span>'],
                ["余额", bob_unit_format($agent['balance_amount'] ?? 0)],
            ];
        };

        return Grid::make(AgentUserRepository::with(['roles', 'parent_user' => function ($q) {
            $q->withTrashed();
        }]), function (Grid $grid) use ($canCreate, $canEdit, $canDelete, $canResetGoogle, $canUnlockLogin, $agentDetailService, $agentOptions, $agentInfoTable) {
            $grid->column('id', "编号")->sortable()->center();
            $grid->column('info', "一级代理")->display(function () {
                $data = [
                    ["账号", $this->username],
                    ["名称", $this->name],
                ];

                return bob_show_table_info($data, [], ["tr-3"]);
            })->top();

            $grid->column('parent_user_info', "二级代理")->display(function () use ($agentDetailService, $agentInfoTable) {
                $agent = $agentDetailService->excute($this->id);
                if (empty($agent['one'])) {
                    return null;
                }

                $data = $agentInfoTable($agent['one']);
                return bob_show_table_info($data, [], ["tr-2"], 2);
            })->top();

            $grid->column('parent_parent_user_info', "三级代理")->display(function () use ($agentDetailService, $agentInfoTable) {
                $agent = $agentDetailService->excute($this->id);
                if (empty($agent['two'])) {
                    return null;
                }

                $data = $agentInfoTable($agent['two']);
                return bob_show_table_info($data, [], ["tr-1"], 2);
            })->top();

            $grid->column('balance_amount', "余额")->amount()->center();
            $grid->column('level', "层级")->sortable()->center();
            $grid->column('status', "状态")->status()->center();
            $grid->column('google', '谷歌验证器')->google()->center();
            $grid->column('login_white_ip', '登录IP白名单')->display(function ($value) {
                if (empty($value)) {
                    return null;
                }

                return bob_show_table_info(collect(bob_format_muti_data_to_array($value))->map(function ($item) {
                    return [$item];
                })->all());
            });

            $grid->column('last_login_ip', "登录IP");
            $grid->column('last_login_time', "登录时间");
            $grid->column('created_at', trans('admin.created_at'))->center();
            $grid->disableCreateButton();
            $grid->enableDialogCreate();
            $grid->disableEditButton();
            if ($canEdit) {
                $grid->showQuickEditButton();
            }
            $grid->disableRowSelector();
            $grid->withBorder();
            if ($canCreate) {
                $grid->tools(new CreateButton($grid));
            }

            if (request()->input('_scope_') == 'trashed') {
                $grid->disableActions();
            } else {
                $grid->actions(function ($actions) use ($canCreate, $canDelete, $canResetGoogle, $canUnlockLogin) {
                    $actions->disableDelete();
                    if ($canDelete) {
                        $actions->append(new Delete());
                    }
                    if ($canCreate && $actions->row['level'] <= 2) {
                        $actions->append(new CreateNextAgent(Admin::app()->getRoute('agent.users.create', ['pid' => $actions->getKey()])));
                    }
                    if ($canResetGoogle) {
                        $actions->append(new ResetGooglePassword());
                    }
                    if ($canUnlockLogin) {
                        $actions->append(new UnlockUser());
                    }
                });
            }
            $grid->filter(function (Grid\Filter $filter) use ($agentOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like("name", "名称")->width(3);
                $filter->like("username", "账号")->width(3);
                $filter->equal('pid', "上级代理")->select($agentOptions)->width(3);
                $filter->equal('status', "启用状态")->select(config('default.status_text'))->width(3);
                $filter->scope('trashed', '回收站')->onlyTrashed();
            });
        });
    }

    public function create(Content $content)
    {
        Permission::check('merchant-agent-create');

        return parent::create($content);
    }

    public function store()
    {
        Permission::check('merchant-agent-create');

        return parent::store();
    }

    public function edit($id, Content $content)
    {
        Permission::check('merchant-agent-edit');

        return parent::edit($id, $content);
    }

    public function update($id)
    {
        Permission::check('merchant-agent-edit');

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('merchant-agent-delete');

        return parent::destroy($id);
    }

    public function form()
    {
        $pid = intval(Request::input('pid', 0));

        return Form::make(AgentUserRepository::with(['roles']), function (Form $form) use ($pid) {
            $userTable = config('agent-admin.database.users_table');
            $connection = config('agent-admin.database.connection');

            $id = $form->getKey();

            if ($id) {
                $form->display('username', "代理用户名");
                $form->text('name', "代理名称")->rules(['required', 'max:100'], ['required' => '请输入代理名称', 'max' => '代理名称不能超过100个字符'])->required()->maxLength(100)->prepend('<i class="feather icon-user"></i>');
            } else {
                $form->hidden('level')->default(1);
                $form->hidden('pid')->default($pid);
                if ($pid > 0) {
                    $parentAgent = AgentUser::query()->whereKey($pid)->first(['id', 'name']);
                    $form->display('magent_name', '上级代理名称')->default(optional($parentAgent)->name);
                }
                $form->text('username', "代理用户名")
                    ->required()
                    ->creationRules(['required', 'max:200', "unique:$connection.$userTable"], ['required' => '请输入代理用户名', 'max' => '代理用户名不能超过200个字符', 'unique' => '代理用户名已存在'])
                    ->updateRules(['required', 'max:200', "unique:$connection.$userTable,username,$id"], ['required' => '请输入代理用户名', 'max' => '代理用户名不能超过200个字符', 'unique' => '代理用户名已存在'])->maxLength(200)->prepend('<i class="feather icon-user"></i>');
                $form->text('name', "代理名称")->rules(['required', 'max:100'], ['required' => '请输入代理名称', 'max' => '代理名称不能超过100个字符'])->required()->maxLength(100)->prepend('<i class="feather icon-user"></i>');
            }
            $form->passwordTool('password', '登录密码')->length(12)->attribute(['autocomplete' => 'off']);
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password');
            $form->ignore(['password_confirmation']);

            $form->radio('status', '启用状态')->options(config('default.status_text'))->rules(['required', 'in:0,1'], ['required' => '请选择启用状态', 'in' => '启用状态不正确'])->default(1);

            $form->textarea("login_white_ip", "登录IP白名单")->help("多个IP请用逗号或换行隔开，支持单个IP或CIDR，如：1.1.1.1、1.1.1.0/24");
        })->saving(function (Form $form) {
            $admin = Admin::user();
            if ($form->isCreating() && !$admin->can('merchant-agent-create')) {
                return $form->response()->error('无新增商户代理权限');
            }
            if ($form->isEditing() && !$admin->can('merchant-agent-edit')) {
                return $form->response()->error('无编辑商户代理权限');
            }

            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }
            if (!$form->password) {
                $form->deleteInput('password');
            }

            try {
                $form->input('login_white_ip', app(WhiteIpFormatService::class)->normalize($form->login_white_ip, '登录IP白名单'));
            } catch (\Throwable $e) {
                return $form->response()->error($e->getMessage());
            }

            // 创建代理时根据上级代理重新计算层级，避免前端传入 level 被直接信任。
            if ($form->isCreating()) {
                if (intval($form->pid) <= 0) {
                    $form->pid = 0;
                    $form->level = 1;
                    return null;
                }

                $parentAgent = AgentUser::query()->whereKey($form->pid)->first(['id', 'level']);
                if (!$parentAgent) {
                    return $form->response()->error('上级代理不存在，请刷新页面后重试');
                }

                $form->pid = $parentAgent->id;
                $form->level = $parentAgent->level + 1;
            }
        });
    }

}
