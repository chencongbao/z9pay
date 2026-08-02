<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Content;
use App\Models\UserModel;
use Dcat\Admin\Http\Auth\Permission;
use Illuminate\Support\Facades\App;
use App\Admin\Actions\Grid\User\UnlockUser;
use App\Admin\Actions\Grid\UserAgent\Delete;
use App\Admin\Actions\Grid\User\ForceLogout;
use App\Admin\Actions\Grid\User\CreateNextUser;
use App\Admin\Actions\Grid\User\CreateNextAgent;
use App\Services\Cache\User\GetUserDetailService;
use App\Admin\Extensions\Tools\Agent\CreateButton;
use App\Services\Cache\User\GetUserAgentListService;
use App\Admin\Actions\Grid\UserAgent\ResetGooglePassword;
use App\Admin\Actions\Grid\UserAgent\TodayDepositStats;
use App\Services\UserBank\GetSelfUserBankTypeService;

class AgentController extends CommonController
{
    protected $translation = 'agent';

    protected function grid(): Grid
    {
        $admin = Admin::user();
        $canCreateAgent = $admin->can('user-agent-create');
        $canEditAgent = $admin->can('user-agent-edit');
        $canDeleteAgent = $admin->can('user-agent-delete');
        $canResetGoogle = $admin->can('user-agent-reset-googlecode');
        $canUnlockLogin = $admin->can('user-agent-unlock-login');
        $canForceLogout = $admin->can('user-agent-force-logout');
        $userDetailService = App::make(GetUserDetailService::class);
        $bankTypeOptions = App::make(GetSelfUserBankTypeService::class)->excute();
        $agentOptions = collect(App::make(GetUserAgentListService::class)->excute())->mapWithKeys(function ($item) {
            return [$item['id'] => '【' . $item['id'] . '】' . $item['name']];
        });
        $getAgentDetail = function (int $id) use ($userDetailService): array {
            static $cache = [];

            if (!array_key_exists($id, $cache)) {
                $cache[$id] = $userDetailService->excute($id) ?: [];
            }

            return $cache[$id];
        };
        $renderAgentLevelInfo = function (array $result, string $key, string $rowClass): string {
            return $this->renderAgentLevelInfo($result, $key, $rowClass);
        };

        $query = UserModel::query()->select([
            'id',
            'pid',
            'name',
            'level',
            'status',
            'username',
            'is_agent',
            'lock_user',
            'created_at',
            'account_types',
            'self_add_bank',
            'balance_amount',
            'last_login_ip',
            'last_login_time',
            'google_two_fa_enable',
            'google_two_fa_secret',
        ]);

        return Grid::make($query, function (Grid $grid) use ($canCreateAgent, $canEditAgent, $canDeleteAgent, $canResetGoogle, $canUnlockLogin, $canForceLogout, $bankTypeOptions, $agentOptions, $getAgentDetail, $renderAgentLevelInfo) {
            $grid->model()->where('is_agent', 1);
            $grid->column('id', '编号')->sortable()->center();
            $grid->column('self_info', '一级代理')->display(function () {
                $data[] = ['账号', $this->username];
                $data[] = ['名称', $this->name];

                return bob_show_table_info($data, [], ['tr-3']);
            })->top();
            $grid->column('two_info', '二级代理')->display(function () use ($getAgentDetail, $renderAgentLevelInfo) {
                return $renderAgentLevelInfo($getAgentDetail((int) $this->id), 'one', 'tr-2');
            })->top();
            $grid->column('three_info', '三级代理')->display(function () use ($getAgentDetail, $renderAgentLevelInfo) {
                return $renderAgentLevelInfo($getAgentDetail((int) $this->id), 'two', 'tr-4');
            })->top();
            $grid->column('four_info', '四级代理')->display(function () use ($getAgentDetail, $renderAgentLevelInfo) {
                return $renderAgentLevelInfo($getAgentDetail((int) $this->id), 'three', 'tr-5');
            })->top();
            $grid->column('five_info', '五级代理')->display(function () use ($getAgentDetail, $renderAgentLevelInfo) {
                return $renderAgentLevelInfo($getAgentDetail((int) $this->id), 'four', 'tr-6');
            })->top();
            $grid->column('balance_amount', '余额')->amount()->center();
            $grid->column('level', '层级')->sortable()->center();
            $grid->column('status', '账号状态')->status()->center();
            $grid->column('account_types_info', '操作金主收款卡类型')->display(function () use ($bankTypeOptions) {
                $result = $bankTypeOptions;
                if (!empty($this->account_types)) {
                    $accountTypes = explode(',', $this->account_types);
                    $result = $result->filter(function ($item) use ($accountTypes) {
                        return in_array($item['id'], $accountTypes);
                    });
                }

                $data[] = ['操作金主', (int) $this->self_add_bank === 1 ? '<span class="label" style="background:#21b978">是</span>' : '<span class="label" style="background:#ef5228">否</span>'];
                if ((int) $this->self_add_bank === 1) {
                    foreach ($result as $v) {
                        $data[] = [$v['name'] ?? ''];
                    }
                }

                return bob_show_table_info($data, [], ['tr-1'], 2);
            })->top();
            $grid->column('other_info', '相关信息')->display(function () {
                $data[] = ['锁定状态', $this->lock_user ? '<span class="label" style="background:#ef5228">是</span>' : '<span class="label" style="background:#21b978">否</span>'];
                $data[] = ['登陆IP', $this->last_login_ip];
                $data[] = ['登陆时间', $this->last_login_time];
                $data[] = ['创建时间', $this->created_at];
                if ((int) $this->google_two_fa_enable === 2) {
                    if (empty($this->google_two_fa_secret)) {
                        $data[] = ['谷歌验证码', '<span class="label" style="background:#21b978;">已开启</span><br/><span class="label" style="background:#ef5228;">已重置</span>'];
                    } else {
                        $data[] = ['谷歌验证码', '<span class="label" style="background:#21b978;">已开启</span>'];
                    }
                } else {
                    $data[] = ['谷歌验证码', '<span class="label" style="background:#ef5228">未开启</span>'];
                }

                return bob_show_table_info($data, [], [], 2);
            })->width(300);
            $grid->disableCreateButton();
            if ($canCreateAgent) {
                $grid->tools(new CreateButton($grid));
            }
            if ($canEditAgent) {
                $grid->showQuickEditButton();
            }
            $grid->disableEditButton();
            if (request()->input('_scope_') === 'trashed') {
                $grid->disableActions();
            } else {
                $grid->actions(function (Grid\Displayers\Actions $actions) use ($canCreateAgent, $canDeleteAgent, $canResetGoogle, $canUnlockLogin, $canForceLogout) {
                    $actions->disableDelete();
                    if ($canDeleteAgent) {
                        $actions->append(new Delete());
                    }
                    if ($canCreateAgent && (int) $actions->row['level'] <= 4) {
                        $actions->append(new CreateNextAgent(Admin::app()->getRoute('agents.create', ['pid' => $actions->getKey()])));
                    }
                    $actions->append(new CreateNextUser());
                    if ($canUnlockLogin) {
                        $actions->append(new UnlockUser('user-agent-unlock-login'));
                    }
                    if ($canForceLogout) {
                        $actions->append(new ForceLogout('user-agent-force-logout'));
                    }
                    $actions->append(new TodayDepositStats());
                    if ($canResetGoogle) {
                        $actions->append(new ResetGooglePassword());
                    }
                });
            }

            $grid->filter(function (Grid\Filter $filter) use ($agentOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like('username', '代理账号')->width(3);
                $filter->like('name', '代理名称')->width(3);
                $filter->equal('pid', '上级代理')->select($agentOptions)->width(3);
                $filter->scope('trashed', '回收站')->onlyTrashed();
            });
        });
    }

    public function create(Content $content)
    {
        Permission::check('user-agent-create');

        return parent::create($content);
    }

    public function store()
    {
        Permission::check('user-agent-create');

        return parent::store();
    }

    public function edit($id, Content $content)
    {
        Permission::check('user-agent-edit');

        return parent::edit($id, $content);
    }

    public function update($id)
    {
        Permission::check('user-agent-edit');

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('user-agent-delete');

        return parent::destroy($id);
    }

    public function form(): Form
    {
        $bankTypeOptions = App::make(GetSelfUserBankTypeService::class)->excute()->pluck('name', 'id');

        return Form::make(new User(), function (Form $form) use ($bankTypeOptions) {
            $id = $form->getKey();
            if ($id) {
                $form->display('username', '代理帐号');
            } else {
                $form->hidden('is_agent')->default(1);
                $form->hidden('level')->default(1);
                $form->hidden('pid')->default(request('pid', 0));
                if ((int) request('pid', 0) > 0) {
                    $parentAgent = User::whereKey(request('pid', 0))->first(['id', 'name']);
                    $form->display('uagent_name', '上级代理名称')->default(optional($parentAgent)->name);
                }
                $form->text('username', '代理帐号')
                    ->required()
                    ->creationRules(['required', 'max:200', 'unique:users'], ['required' => '请输入代理账号', 'max' => '代理账号不能超过200个字符', 'unique' => '代理账号已存在'])
                    ->updateRules(['required', 'max:200', "unique:users,username,$id"], ['required' => '请输入代理账号', 'max' => '代理账号不能超过200个字符', 'unique' => '代理账号已存在'])
                    ->maxLength(200)
                    ->prepend('<i class="feather icon-user"></i>')
                    ->attribute(['autocomplete' => 'off']);
            }

            $form->text('name', '代理名称')->rules(['required', 'max:100'], ['required' => '请输入代理名称', 'max' => '代理名称不能超过100个字符'])->required()->maxLength(100)->prepend('<i class="feather icon-user"></i>');
            $form->radio('status', '账号状态')->options([0 => '禁用', 1 => '启用'])->rules(['required', 'in:0,1'], ['required' => '请选择账号状态', 'in' => '账号状态不正确'])->default(1);
            $form->radio('self_add_bank', '操作金主')->options([0 => '关闭', 1 => '开启'])->rules(['required', 'in:0,1'], ['required' => '请选择是否允许操作金主', 'in' => '操作金主开关不正确'])->default(0);
            $form->radio('action_delete', '操作删除收款卡')->options([0 => '关闭', 1 => '开启'])->rules(['required', 'in:0,1'], ['required' => '请选择是否允许删除收款卡', 'in' => '删除收款卡开关不正确'])->default(0);
            $form->radio('action_limit_card', '操作限制收款卡')->options([0 => '关闭', 1 => '开启'])->rules(['required', 'in:0,1'], ['required' => '请选择是否允许限制收款卡', 'in' => '限制收款卡开关不正确'])->default(0)->help('操作金主收款卡限制能力，如：代收单笔最低限额等');
            $form->passwordTool('password', '登录密码')->length(12)->attribute(['autocomplete' => 'off']);
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password')->attribute(['autocomplete' => 'off']);
            $form->ignore(['password_confirmation']);
            $form->multipleSelect('account_types', '收款卡类型')->options($bankTypeOptions)->saving(function ($value) {
                $filteredArray = array_filter((array) $value, fn ($item) => $item !== null && $item !== '');
                if (!empty($filteredArray)) {
                    return implode(',', $filteredArray);
                }

                return null;
            })->help('支持收款卡类型，不填表示支持所有类型');
        })->saving(function (Form $form) {
            $admin = Admin::user();
            if ($form->isCreating() && !$admin->can('user-agent-create')) {
                return $form->response()->error('无新增金主代理权限');
            }
            if ($form->isEditing() && !$admin->can('user-agent-edit')) {
                return $form->response()->error('无编辑金主代理权限');
            }

            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }
            if (!$form->password) {
                $form->deleteInput('password');
            }
            $form->is_agent = 1;

            if ($form->pid > 0) {
                $parentAgent = User::whereKey($form->pid)->first(['id', 'level']);
                if (!$parentAgent) {
                    return $form->response()->error('上级代理不存在，请刷新页面后重试');
                }

                $form->pid = $parentAgent->id;
                $form->level = $parentAgent->level + 1;
            }
        });
    }

    protected function renderAgentLevelInfo(array $result, string $key, string $rowClass): string
    {
        if (empty($result[$key])) {
            return '';
        }

        $agent = $result[$key];
        $data[] = ['账号', $agent['username']];
        $data[] = ['名称', $agent['name']];
        $data[] = ['层级', $agent['level']];
        $data[] = ['状态', (int) $agent['status'] === 1 ? '<span class="label" style="background:#21b978">启用</span>' : '<span class="label" style="background:#ef5228">禁用</span>'];
        $data[] = ['余额', bob_unit_format($agent['balance_amount'])];

        return bob_show_table_info($data, [], [$rowClass], 2);
    }
}
