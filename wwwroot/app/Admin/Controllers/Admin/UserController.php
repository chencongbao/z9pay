<?php

namespace App\Admin\Controllers\Admin;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Traits\AdminTrait;
use Dcat\Admin\Support\Helper;
use App\Models\AdminAdministrator;
use Dcat\Admin\Http\Auth\Permission;
use App\Admin\Actions\Grid\Admin\UnlockUser;
use App\Admin\Actions\Grid\Admin\TelegramRole;
use App\Services\Google\AdminGoogle2faService;
use App\Services\IpWhite\WhiteIpFormatService;
use Dcat\Admin\Http\Repositories\Administrator;
use Dcat\Admin\Http\Controllers\AdminController;
use App\Admin\Actions\Grid\Admin\ResetGooglePassword;
use Dcat\Admin\Models\Administrator as AdministratorModel;

class UserController extends AdminController
{
    use AdminTrait;

    public function title()
    {
        return trans('admin.administrator');
    }

    protected function grid()
    {
        $protectedAdminIds = $this->protectedAdminIds();
        $telegramRoleOptions = $this->telegramRoleOptions();
        $hiddenRoleSlugs = $this->listHiddenRoleSlugs();

        return Grid::make(Administrator::with(['roles']), function (Grid $grid) use ($protectedAdminIds, $telegramRoleOptions, $hiddenRoleSlugs) {
            $adminUser = Admin::user();
            $canCreate = $adminUser->can('admin-user-create');
            $canEdit = $adminUser->can('admin-user-edit');
            $canDelete = $adminUser->can('admin-user-delete');

            if (!Admin::user()->isAdministrator()) {
                $grid->model()->where('id', '>', 1)->orderByDesc('id');
            }
            if ($hiddenRoleSlugs !== []) {
                $grid->model()->whereDoesntHave('roles', function ($query) use ($hiddenRoleSlugs) {
                    $query->whereIn('slug', $hiddenRoleSlugs);
                });
            }

            $grid->column('id', '编号')->sortable();
            $grid->column('username', '管理员用户名');
            $grid->column('name', '管理员姓名');
            $grid->column('roles')->pluck('name')->label('primary');
            $grid->column('telegram_role', '飞机权限')->using($telegramRoleOptions)->label([
                AdminAdministrator::TELEGRAM_ROLE_NONE => 'default',
                AdminAdministrator::TELEGRAM_ROLE_MANAGER => 'primary',
                AdminAdministrator::TELEGRAM_ROLE_SUPER_MANAGER => 'danger',
            ]);
            $grid->column('status', '账号状态')->status();
            $grid->column('google', '谷歌验证器')->google();
            $grid->column('login_white_ip', '登录IP白名单')->display(function ($value) {
                if (empty($value)) {
                    return '';
                }

                return bob_show_table_info(collect(bob_format_muti_data_to_array($value))->map(function ($item) {
                    return [$item];
                })->all());
            });
            $grid->column('last_login_ip', '登录IP');
            $grid->column('last_login_time', '登录时间');
            $grid->column('created_at', '创建时间');
            if ($canEdit) {
                $grid->showQuickEditButton();
            }
            if ($canCreate) {
                $grid->enableDialogCreate();
            } else {
                $grid->disableCreateButton();
            }
            if (!$canDelete) {
                $grid->disableDeleteButton();
            }
            $grid->disableEditButton();
            $grid->setDialogFormDimensions('900px', '720px');
            $grid->actions(function (Grid\Displayers\Actions $actions) use ($protectedAdminIds) {
                if (in_array((int)$actions->getKey(), $protectedAdminIds, true)) {
                    $actions->disableDelete();
                }

                $actions->append(new ResetGooglePassword());
                $actions->append(new TelegramRole());
                $actions->append(new UnlockUser());
            });
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like('username')->width(3);
            });
        });
    }

    public function form()
    {
        $protectedAdminIds = $this->protectedAdminIds();
        $roleOptions = $this->roleOptions();
        $unassignableRoleIds = RoleController::hiddenRoleIdsBySlugs($this->unassignableRoleSlugs());
        $userModel = config('admin.database.users_model');
        $hasSuperAdministratorRole = static function (int $userId) use ($userModel): bool {
            return $userModel::query()->whereKey($userId)->whereHas('roles', function ($query) {
                $query->where('slug', 'administrator');
            })->exists();
        };

        return Form::make(Administrator::with(['roles']), function (Form $form) use ($protectedAdminIds, $roleOptions, $hasSuperAdministratorRole) {
            $adminUser = Admin::user();
            $userTable = config('admin.database.users_table');
            $connection = config('admin.database.connection');
            $id = $form->getKey();
            $isSelf = $id && (int)$id === (int)$adminUser->id;
            if ($id && !$isSelf && $hasSuperAdministratorRole((int)$id)) {
                Permission::error();
            }
            if ($id) {
                $form->display('username', '管理员用户名');
                $form->passwordTool('password', '登录密码')->length(12);
            } else {
                $form->text('username', '管理员用户名')->required()->creationRules(['required', "unique:{$connection}.{$userTable}"], ['required' => '请输入管理员用户名', 'unique' => '管理员用户名已存在'])->updateRules(['required', "unique:{$connection}.{$userTable},username,$id"], ['required' => '请输入管理员用户名', 'unique' => '管理员用户名已存在']);
                $form->passwordTool('password', '登录密码')->length(12)->attribute(['autocomplete' => 'off']);
            }
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password')->attribute(['autocomplete' => 'off']);
            $form->ignore(['password_confirmation']);
            if ($id) {
                if (config('admin.permission.enable') && $id != AdministratorModel::DEFAULT_ID && !$isSelf) {
                    $form->select('roles', trans('admin.roles'))
                        ->options($roleOptions)
                        ->customFormat(function ($v) {
                            return collect(array_column($v, 'id'))->first();
                        })->disableClearButton()->rules(['numeric', 'min:1'], ['numeric' => '请选择角色', 'min' => "请选择角色"])->required();
                }
                $form->text('name', '管理员姓名')->required();
                if ($id != AdministratorModel::DEFAULT_ID && !$isSelf) {
                    $form->radio('status', '账号状态')->options([0 => '禁用', 1 => '启用'])->default(1)->required();
                }
            } else {
                if (config('admin.permission.enable')) {
                    $form->select('roles', trans('admin.roles'))
                        ->options($roleOptions)
                        ->disableClearButton()
                        ->rules(['numeric', 'min:1'], ['numeric' => '请选择角色', 'min' => '请选择角色'])
                        ->required();
                }
                $form->text('name', '管理员姓名')->required();
                $form->radio('status', '账号状态')->options([0 => '禁用', 1 => '启用'])->default(1)->required();
            }
            $form->textarea('login_white_ip', '登录IP白名单')->help('多个IP请用逗号或换行隔开，支持单个IP或CIDR，如：1.1.1.1、1.1.1.0/24');

            app(AdminGoogle2faService::class)->appendField($form);

            if (in_array((int)$id, $protectedAdminIds, true)) {
                $form->disableDeleteButton();
            }
            if (!$adminUser->can('admin-user-delete')) {
                $form->disableDeleteButton();
            }
        })->saving(function (Form $form) use ($unassignableRoleIds) {
            $adminUser = Admin::user();
            if ($form->isCreating() && !$adminUser->can('admin-user-create')) {
                return $form->response()->error('无新增管理员权限');
            }
            if ($form->isEditing() && !$adminUser->can('admin-user-edit')) {
                return $form->response()->error('无编辑管理员权限');
            }

            if ($unassignableRoleIds !== [] && $form->roles !== null && $form->roles !== '' && in_array((int)$form->roles, $unassignableRoleIds, true)) {
                return $form->response()->error('无权分配该角色');
            }
            if ($form->password && $form->model()->get('password') != $form->password) {
                $form->password = bcrypt($form->password);
            }
            if (!$form->password) {
                $form->deleteInput('password');
            }
            try {
                $form->input('login_white_ip', app(WhiteIpFormatService::class)->normalize($form->login_white_ip, '登录IP白名单'));
            } catch (\Exception $e) {
                return $form->response()->error($e->getMessage());
            }

            try {
                app(AdminGoogle2faService::class)->verify((string)$form->google_2fa_code);
            } catch (\Exception $e) {
                return $form->response()->error($e->getMessage());
            }
            $form->deleteInput('google_2fa_code');
        });
    }

    public function store()
    {
        Permission::check('admin-user-create');

        return parent::store();
    }

    public function update($id)
    {
        Permission::check('admin-user-edit');

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('admin-user-delete');

        $ids = array_map('intval', Helper::array($id));
        if (array_intersect($this->protectedAdminIds(), $ids)) {
            Permission::error();
        }
        if ($this->superAdministratorRoleUserExists($ids)) {
            Permission::error();
        }

        return parent::destroy($id);
    }

    private function roleOptions()
    {
        $roleModel = config('admin.database.roles_model');
        $hiddenRoleSlugs = $this->unassignableRoleSlugs();
        $query = $roleModel::query();

        if ($hiddenRoleSlugs !== []) {
            $query->whereNotIn('slug', $hiddenRoleSlugs);
        }

        return $query->pluck('name', 'id');
    }

    private function unassignableRoleSlugs(): array
    {
        return Admin::user()->isAdministrator() ? [] : ['administrator'];
    }

    private function protectedAdminIds(): array
    {
        return [AdministratorModel::DEFAULT_ID];
    }

    private function listHiddenRoleSlugs(): array
    {
        if (Admin::user()->isAdministrator()) {
            return [];
        }

        return ['administrator'];
    }

    private function superAdministratorRoleUserExists(array $userIds): bool
    {
        $userModel = config('admin.database.users_model');

        return $userModel::query()
            ->whereIn('id', $userIds)
            ->whereHas('roles', function ($query) {
                $query->where('slug', 'administrator');
            })
            ->exists();
    }

    private function telegramRoleOptions(): array
    {
        return [
            AdminAdministrator::TELEGRAM_ROLE_NONE => '无',
            AdminAdministrator::TELEGRAM_ROLE_MANAGER => '飞机命令管理员',
            AdminAdministrator::TELEGRAM_ROLE_SUPER_MANAGER => '飞机命令超级管理员',
        ];
    }

    private function isProtectedAdmin(int $id): bool
    {
        return in_array($id, $this->protectedAdminIds(), true);
    }
}
