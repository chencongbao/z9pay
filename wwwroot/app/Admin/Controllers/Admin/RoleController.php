<?php

namespace App\Admin\Controllers\Admin;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Tree;
use Dcat\Admin\Support\Helper;
use Dcat\Admin\Http\Auth\Permission;
use Dcat\Admin\Http\Repositories\Role;
use Dcat\Admin\Http\Controllers\AdminController;

class RoleController extends AdminController
{
    private const HIDDEN_ROLE_SLUGS = ['administrator', 'adminmanager'];

    public function title()
    {
        return trans('admin.roles');
    }

    protected function grid()
    {
        $hiddenRoleSlugs = self::hiddenRoleSlugs();
        $hiddenRoleIds = self::hiddenRoleIdsBySlugs($hiddenRoleSlugs);

        return Grid::make(new Role(), function (Grid $grid) use ($hiddenRoleIds, $hiddenRoleSlugs) {
            if ($hiddenRoleSlugs !== []) {
                $grid->model()->whereNotIn('slug', $hiddenRoleSlugs);
            }

            $grid->column('id', 'ID')->sortable();
            $grid->column('slug')->label('primary');
            $grid->column('name');

            $grid->column('created_at');
            $grid->column('updated_at')->sortable();

            $grid->disableEditButton();
            $grid->showQuickEditButton();
            $grid->quickSearch(['id', 'name', 'slug']);
            $grid->enableDialogCreate();

            $grid->actions(function (Grid\Displayers\Actions $actions) use ($hiddenRoleIds) {
                $roleModel = config('admin.database.roles_model');
                if ($roleModel::isAdministrator($actions->row->slug) || (!Admin::user()->isAdministrator() && in_array((int)$actions->row->id, $hiddenRoleIds, true))) {
                    $actions->disableDelete();
                }
            });
        });
    }

    protected function detail($id)
    {
        $hiddenRoleIds = self::hiddenRoleIdsBySlugs(self::hiddenRoleSlugs());
        if (in_array((int)$id, $hiddenRoleIds, true)) {
            Permission::error();
        }

        return Show::make($id, new Role('permissions'), function (Show $show) use ($hiddenRoleIds) {
            $show->field('id');
            $show->field('slug');
            $show->field('name');

            $show->field('permissions')->unescape()->as(function ($permission) {
                $permissionModel = config('admin.database.permissions_model');
                $permissionModel = new $permissionModel();
                $nodes = $permissionModel->allNodes();

                $tree = Tree::make($nodes);

                $keyName = $permissionModel->getKeyName();
                $tree->check(array_column(Helper::array($permission), $keyName));

                return $tree->render();
            });

            $show->field('created_at');
            $show->field('updated_at');

            $roleModel = config('admin.database.roles_model');
            if ($show->getKey() == $roleModel::ADMINISTRATOR_ID || (!Admin::user()->isAdministrator() && in_array((int)$show->getKey(), $hiddenRoleIds, true))) {
                $show->disableDeleteButton();
            }
        });
    }

    public function form()
    {
        $with = ['permissions'];

        if ($bindMenu = config('admin.menu.role_bind_menu', true)) {
            $with[] = 'menus';
        }

        return Form::make(Role::with($with), function (Form $form) use ($bindMenu) {
            $roleTable = config('admin.database.roles_table');
            $connection = config('admin.database.connection');

            $id = $form->getKey();
            if ($id && in_array((int)$id, self::hiddenRoleIdsBySlugs(self::hiddenRoleSlugs()), true)) {
                Permission::error();
            }

            $form->display('id', 'ID');

            $form->text('slug', trans('admin.slug'))
                ->required()
                ->creationRules(['required', "unique:{$connection}.{$roleTable}"], [
                    'required' => '请输入角色标识',
                    'unique' => '角色标识已存在，请换一个',
                ])
                ->updateRules(['required', "unique:{$connection}.{$roleTable},slug,$id"], [
                    'required' => '请输入角色标识',
                    'unique' => '角色标识已存在，请换一个',
                ]);

            $form->text('name', trans('admin.name'))
                ->required()
                ->rules('required', ['required' => '请输入角色名称']);

            $form->tree('permissions')
                ->treeState(false)
                ->nodes(function () {
                    $permissionModel = config('admin.database.permissions_model');
                    $permissionModel = new $permissionModel();

                    return $permissionModel->allNodes();
                })
                ->exceptParentNode(false)
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
                        $model = config('admin.database.menu_model');

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

            $roleModel = config('admin.database.roles_model');
            if ($id == $roleModel::ADMINISTRATOR_ID) {
                $form->disableDeleteButton();
            }
        })->saving(function (Form $form) {
            if ($form->getKey() && in_array((int)$form->getKey(), self::hiddenRoleIdsBySlugs(self::hiddenRoleSlugs()), true)) {
                return $form->response()->error('无权操作该角色');
            }
        })->saved(function () {
            $model = config('admin.database.menu_model');
            (new $model())->flushCache();
        });
    }

    public function destroy($id)
    {
        $roleModel = config('admin.database.roles_model');
        $ids = array_map('intval', Helper::array($id));

        if (in_array((int)$roleModel::ADMINISTRATOR_ID, $ids, true)) {
            Permission::error();
        }

        if (array_intersect(self::hiddenRoleIdsBySlugs(self::hiddenRoleSlugs()), $ids)) {
            Permission::error();
        }

        return parent::destroy($id);
    }

    public static function hiddenRoleSlugs(): array
    {
        if (Admin::user()->isAdministrator()) {
            return [];
        }

        return self::HIDDEN_ROLE_SLUGS;
    }

    public static function hiddenRoleIdsBySlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $roleModel = config('admin.database.roles_model');

        return $roleModel::query()->whereIn('slug', $slugs)->pluck('id')->map(fn ($id) => (int)$id)->all();
    }

}
