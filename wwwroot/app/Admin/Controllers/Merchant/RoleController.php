<?php

namespace App\Admin\Controllers\Merchant;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Widgets\Tree;
use Dcat\Admin\Support\Helper;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Http\Auth\Permission;
use Dcat\Admin\Http\Controllers\AdminController;
use App\Models\MerchantRole as MerchantRoleModel;
use App\Repositories\MerchantRole as MerchantRoleRepository;

class RoleController extends AdminController
{
    public function title()
    {
        return trans('admin.roles');
    }

    protected function grid()
    {
        return new Grid(new MerchantRoleRepository(), function (Grid $grid) {
            $grid->column('id', 'ID')->sortable();
            $grid->column('slug')->label('primary');
            $grid->column('name');

            $grid->column('created_at');
            $grid->column('updated_at')->sortable();

            $grid->disableEditButton();
            $grid->showQuickEditButton();
            $grid->quickSearch(['id', 'name', 'slug']);
            $grid->enableDialogCreate();

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                if (MerchantRoleModel::isAdministrator($actions->row->slug)) {
                    $actions->disableDelete();
                }
            });
        });
    }

    protected function detail($id)
    {
        $translatePermissionNodes = function ($nodes) {
            return $nodes->transform(function ($item) {
                $item->name = __("menu.titles." . $item->name);
                return $item;
            });
        };

        return Show::make($id, new MerchantRoleRepository('permissions'), function (Show $show) use ($translatePermissionNodes) {
            $show->field('id');
            $show->field('slug');
            $show->field('name');

            $show->field('permissions')->unescape()->as(function ($permission) use ($translatePermissionNodes) {
                $permissionModel = config('merchant-admin.database.permissions_model');
                $permission = Helper::array($permission);
                $permissionModel = new $permissionModel();

                $tree = Tree::make($translatePermissionNodes($permissionModel->allNodes()));
                $tree->check(array_column($permission, $permissionModel->getKeyName()));

                return $tree->render();
            });

            $show->field('created_at');
            $show->field('updated_at');

            if ((int) $show->getKey() === (int) MerchantRoleModel::ADMINISTRATOR_ID) {
                $show->disableDeleteButton();
            }
        });
    }

    public function form()
    {
        $with = ['permissions'];
        $bindMenu = config('merchant-admin.menu.role_bind_menu', true);
        $translatePermissionNodes = function ($nodes) {
            return $nodes->transform(function ($item) {
                $item->name = __("menu.titles." . $item->name);
                return $item;
            });
        };
        $translateMenuNodes = function ($nodes) {
            return $nodes->transform(function ($item) {
                $item->title = __("menu.titles." . $item->title);
                return $item;
            });
        };

        if ($bindMenu) {
            $with[] = 'menus';
        }

        return Form::make(MerchantRoleRepository::with($with), function (Form $form) use ($bindMenu, $translatePermissionNodes, $translateMenuNodes) {
            $roleTable = config('merchant-admin.database.roles_table');
            $connection = config('merchant-admin.database.connection');
            $menuModel = config('merchant-admin.database.menu_model');
            $permissionModel = config('merchant-admin.database.permissions_model');

            $id = $form->getKey();

            $form->display('id', 'ID');

            $form->text('slug', trans('admin.slug'))
                ->required()
                ->creationRules(['required', "unique:{$connection}.{$roleTable}"])
                ->updateRules(['required', "unique:{$connection}.{$roleTable},slug,$id"]);

            $form->text('name', trans('admin.name'))->required();

            $form->tree('permissions')
                ->treeState(false)
                ->options(['checkbox' => ['keep_selected_style' => false, 'three_state' => false, 'cascade' => 'up']])
                ->nodes(function () use ($permissionModel, $translatePermissionNodes) {
                    return $translatePermissionNodes((new $permissionModel())->allNodes());
                })
                ->customFormat(function ($v) {
                    if (empty($v)) {
                        return [];
                    }

                    return array_column($v, 'id');
                });

            if ($bindMenu) {
                $form->tree('menus', trans('admin.menu'))
                    ->treeState(false)
                    ->setTitleColumn('title')
                    ->nodes(function () use ($menuModel, $translateMenuNodes) {
                        return $translateMenuNodes((new $menuModel())->allNodes());
                    })
                    ->customFormat(function ($v) {
                        if (empty($v)) {
                            return [];
                        }

                        return array_column($v, 'id');
                    });
            }

            $form->display('created_at', trans('admin.created_at'));
            $form->display('updated_at', trans('admin.updated_at'));

            if ((int) $id === (int) MerchantRoleModel::ADMINISTRATOR_ID) {
                $form->disableDeleteButton();
            }
        })->saved(function () {
            $model = config('merchant-admin.database.menu_model');
            (new $model())->flushCache();
        });
    }

    public function destroy($id)
    {
        $ids = array_map('intval', Helper::array($id));
        if (in_array((int) MerchantRoleModel::ADMINISTRATOR_ID, $ids, true)) {
            Permission::error();
        }

        return DB::transaction(function () use ($id) {
            return parent::destroy($id);
        });
    }
}
