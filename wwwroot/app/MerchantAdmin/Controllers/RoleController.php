<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Widgets\Tree;
use Dcat\Admin\Support\Helper;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Http\Auth\Permission;
use App\Services\Common\SystemLogService;
use App\Models\MerchantRole as MerchantRoleModel;
use App\Repositories\MerchantRole as MerchantRoleRepository;
use Dcat\Admin\Http\Controllers\AdminController;

class RoleController extends AdminController
{
    public function title(): string
    {
        return __('menu.titles.merchant_roles');
    }

    public function create(Content $content): Content
    {
        $this->authorizeMainAccount();

        return parent::create($content);
    }

    public function store()
    {
        $this->authorizeMainAccount();

        return parent::store();
    }

    public function edit($id, Content $content): Content
    {
        $this->authorizeMainAccount();
        $this->authorizeRole($id);

        return parent::edit($id, $content);
    }

    public function update($id)
    {
        $this->authorizeMainAccount();
        $this->authorizeRole($id);

        return parent::update($id);
    }

    protected function grid(): Grid
    {
        $isMerchantChildAccount = $this->isChildAccount();

        return Grid::make(new MerchantRoleRepository(), function (Grid $grid) use ($isMerchantChildAccount) {
            $grid->model()->where('mid', bob_merchant_user_pid());
            $grid->column('id', 'ID')->sortable();
            $grid->column('slug')->label('primary');
            $grid->column('name');
            $grid->column('created_at');
            $grid->column('updated_at')->sortable();

            $grid->disableEditButton();
            if ($isMerchantChildAccount) {
                $grid->disableCreateButton();
                $grid->disableQuickEditButton();
                $grid->actions(function (Grid\Displayers\Actions $actions) {
                    $actions->disableDelete();
                });
            } else {
                $grid->showQuickEditButton();
                $grid->enableDialogCreate();
            }
        });
    }

    protected function detail($id): Show
    {
        $this->authorizeRole($id);

        return Show::make($id, new MerchantRoleRepository('permissions'), function (Show $show) {
            $show->field('id');
            $show->field('slug');
            $show->field('name');

            $show->field('permissions')->unescape()->as(function ($permission) {
                $permissionModel = config('admin.database.permissions_model');
                $permissionModel = new $permissionModel();
                $nodes = $permissionModel->allNodes();

                $tree = Tree::make($nodes);

                $keyName = $permissionModel->getKeyName();
                $tree->check(
                    array_column(Helper::array($permission), $keyName)
                );

                return $tree->render();
            });

            $show->field('created_at');
            $show->field('updated_at');

            if ($show->getKey() == MerchantRoleModel::ADMINISTRATOR_ID) {
                $show->disableDeleteButton();
            }
        });
    }

    public function form(): Form
    {
        $with = ['permissions'];
        $isCreating = false;

        if ($bindMenu = config('admin.menu.role_bind_menu', true)) {
            $with[] = 'menus';
        }

        return Form::make(MerchantRoleRepository::with($with), function (Form $form) use ($bindMenu) {
            $roleTable = config('admin.database.roles_table');
            $connection = config('admin.database.connection');
            $id = $form->getKey();

            $form->display('id', 'ID');

            if (!$id) {
                $form->hidden('mid')->default(bob_merchant_user_pid());
            }

            $form->text('slug', trans('admin.slug'))
                ->required()
                ->creationRules(['required', "unique:{$connection}.{$roleTable}"], [
                    'required' => __('merchantrole.fields.slug_required'),
                    'unique' => __('merchantrole.fields.slug_unique'),
                ])
                ->updateRules(['required', "unique:{$connection}.{$roleTable},slug,$id"], [
                    'required' => __('merchantrole.fields.slug_required'),
                    'unique' => __('merchantrole.fields.slug_unique'),
                ]);
            $form->text('name', trans('admin.name'))->required();

            $form->tree('permissions')
                ->treeState(false)
                ->options(['checkbox' => ['keep_selected_style' => false, 'three_state' => false, 'cascade' => 'up']])
                ->nodes(function () {
                    $permissionModel = config('merchant-admin.database.permissions_model');
                    $permissionModel = new $permissionModel();

                    return $permissionModel->allNodes()->transform(function ($item) {
                        $item->name = __('menu.titles.' . $item->name);
                        return $item;
                    });
                })
                ->customFormat(function ($v) {
                    if (!$v) {
                        return [];
                    }

                    return array_column($v, 'id');
                });

            if ($bindMenu) {
                $form->tree('menus', trans('admin.menu'))
                    ->treeState(false)
                    ->setTitleColumn('title')
                    ->nodes(function () {
                        $model = config('merchant-admin.database.menu_model');

                        return (new $model())->allNodes()->transform(function ($item) {
                            $item->title = __('menu.titles.' . $item->title);
                            return $item;
                        });
                    })
                    ->customFormat(function ($v) {
                        if (!$v) {
                            return [];
                        }

                        return array_column($v, 'id');
                    });
            }

            $form->display('created_at', trans('admin.created_at'));
            $form->display('updated_at', trans('admin.updated_at'));
        })->saving(function (Form $form) use (&$isCreating) {
            $isCreating = $form->isCreating();
            $form->mid = bob_merchant_user_pid();
        })->saved(function (Form $form) use (&$isCreating) {
            $mid = bob_merchant_user_pid();
            $model = config('admin.database.menu_model');
            (new $model())->flushCache();

            $role = $form->model();
            $roleId = data_get($role, 'id');
            if (!$roleId) {
                return;
            }
            $subject = MerchantRoleModel::query()->where('mid', $mid)->find($roleId);

            $actionKey = $isCreating ? 'merchant.role.store' : 'merchant.role.update';
            $actionText = $isCreating ? '新增 商户角色' : '修改 商户角色';
            $actionMethod = $isCreating ? 'POST' : 'PUT';

            app(SystemLogService::class)->logAction(
                actionKey: $actionKey,
                text: $actionText,
                subject: $subject,
                properties: [
                    'merchant_user_id' => $mid,
                    'role_id' => $roleId,
                    'role_slug' => data_get($role, 'slug'),
                    'role_name' => data_get($role, 'name'),
                ],
                remark: $actionText . '（名称:' . (data_get($role, 'name') ?: '-') . '）',
                logType: 'operation',
                actionMethod: $actionMethod,
                appType: 'merchant',
                user: Admin::user()
            );
        });
    }

    public function destroy($id)
    {
        $this->authorizeMainAccount();
        $mid = bob_merchant_user_pid();

        if ((int) $id === MerchantRoleModel::ADMINISTRATOR_ID) {
            Permission::error();
        }

        $result = MerchantRoleModel::query()->where('mid', $mid)->whereKey($id)->first();
        if (!$result) {
            Permission::error();
        }

        $response = parent::destroy($id);

        app(SystemLogService::class)->logAction(
            actionKey: 'merchant.role.destroy',
            text: '删除 商户角色',
            subject: $result,
            properties: [
                'merchant_user_id' => $mid,
                'role_id' => $result->id ?? null,
                'role_slug' => $result->slug ?? null,
                'role_name' => $result->name ?? null,
            ],
            remark: '删除 商户角色（名称:' . ($result->name ?? '-') . '）',
            logType: 'operation',
            actionMethod: 'DELETE',
            appType: 'merchant',
            user: Admin::user()
        );

        return $response;
    }

    private function authorizeMainAccount(): void
    {
        if ($this->isChildAccount()) {
            Permission::error();
        }
    }

    private function authorizeRole($id): void
    {
        // 角色必须属于当前商户，防止手动拼接 ID 查看或修改其他商户角色。
        $exists = MerchantRoleModel::query()
            ->where('mid', bob_merchant_user_pid())
            ->whereKey($id)
            ->exists();

        if (!$exists) {
            Permission::error();
        }
    }

    private function isChildAccount(): bool
    {
        return (int) data_get(Admin::user(), 'pid', 0) > 0;
    }
}
