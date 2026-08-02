<?php

namespace App\Admin\Controllers\Agent;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Widgets\Tree;
use Dcat\Admin\Support\Helper;
use Dcat\Admin\Http\Auth\Permission;
use App\Models\AgentRole as AgentRoleModel;
use Dcat\Admin\Http\Controllers\AdminController;
use App\Repositories\AgentRole as AgentRoleRepository;

class RoleController extends AdminController
{
    public function title()
    {
        return trans('admin.roles');
    }

    protected function grid()
    {
        return new Grid(new AgentRoleRepository(), function (Grid $grid) {
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
                if (AgentRoleModel::isAdministrator($actions->row->slug)) {
                    $actions->disableDelete();
                }
            });
        });
    }

    protected function detail($id)
    {
        $isProtectedRole = $this->isProtectedRoleId($id);

        return Show::make($id, new AgentRoleRepository('permissions'), function (Show $show) use ($isProtectedRole) {
            $show->field('id');
            $show->field('slug');
            $show->field('name');

            $show->field('permissions')->unescape()->as(function ($permission) {
                $permissionModel = config('agent-admin.database.permissions_model');
                $permission = Helper::array($permission);
                $permissionModel = new $permissionModel();

                $tree = Tree::make($permissionModel->allNodes());
                $tree->check(array_column($permission, $permissionModel->getKeyName()));

                return $tree->render();
            });

            $show->field('created_at');
            $show->field('updated_at');

            if ($isProtectedRole) {
                $show->disableDeleteButton();
            }
        });
    }

    public function form()
    {
        $with = ['permissions'];
        $bindMenu = config('agent-admin.menu.role_bind_menu', true);

        if ($bindMenu) {
            $with[] = 'menus';
        }

        return Form::make(AgentRoleRepository::with($with), function (Form $form) use ($bindMenu) {
            $roleTable = config('agent-admin.database.roles_table');
            $connection = config('agent-admin.database.connection');
            $menuModel = config('agent-admin.database.menu_model');
            $permissionModel = config('agent-admin.database.permissions_model');

            $id = $form->getKey();

            $form->display('id', 'ID');

            $form->text('slug', trans('admin.slug'))
                ->required()
                ->creationRules(['required', "unique:{$connection}.{$roleTable}"])
                ->updateRules(['required', "unique:{$connection}.{$roleTable},slug,$id"]);

            $form->text('name', trans('admin.name'))->required();

            $form->tree('permissions')
                ->nodes(function () use ($permissionModel) {
                    return (new $permissionModel())->allNodes();
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
                    ->nodes(function () use ($menuModel) {
                        return (new $menuModel())->allNodes();
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

            if ($this->isProtectedRoleId($id)) {
                $form->disableDeleteButton();
            }
        })->saved(function () {
            $model = config('agent-admin.database.menu_model');
            (new $model())->flushCache();
        });
    }

    public function destroy($id)
    {
        $ids = array_map('intval', Helper::array($id));
        if ($this->hasProtectedRoleIds($ids)) {
            Permission::error();
        }

        return parent::destroy($id);
    }

    private function isProtectedRoleId($id): bool
    {
        if (empty($id)) {
            return false;
        }

        $slug = AgentRoleModel::query()->whereKey($id)->value('slug');

        return AgentRoleModel::isAdministrator($slug);
    }

    private function hasProtectedRoleIds(array $ids): bool
    {
        if (empty($ids)) {
            return false;
        }

        return AgentRoleModel::query()
            ->whereIn((new AgentRoleModel())->getKeyName(), $ids)
            ->pluck('slug')
            ->contains(fn ($slug) => AgentRoleModel::isAdministrator($slug));
    }
}
