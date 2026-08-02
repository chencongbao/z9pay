<?php

namespace App\Admin\Controllers\Agent;

use Dcat\Admin\Form;
use Dcat\Admin\Tree;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Box;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use App\Repositories\AgentMenu;
use Dcat\Admin\Http\Actions\Menu\Show;
use Dcat\Admin\Widgets\Form as WidgetForm;
use Dcat\Admin\Http\Controllers\AdminController;

class MenuController extends AdminController
{
    public function title()
    {
        return '菜单设置';
    }

    public function index(Content $content)
    {
        return $content
            ->title($this->title())
            ->description(trans('admin.list'))
            ->body(function (Row $row) {
                $row->column(7, $this->treeView()->render());

                $row->column(5, function (Column $column) {
                    $form = new WidgetForm();
                    $form->action(admin_url('agent/auth/menu'));
                    $menuModel = $this->menuModel();

                    $form->select('parent_id', trans('admin.parent_id'))->options($menuModel::selectOptions());
                    $form->text('title', trans('admin.title'))->required();
                    $form->icon('icon', trans('admin.icon'))->help($this->iconHelp());
                    $form->text('uri', trans('admin.uri'));

                    $form->width(9, 2);

                    $column->append(Box::make(trans('admin.new'), $form));
                });
            });
    }

    protected function treeView(): Tree
    {
        $menuModel = $this->menuModel();

        return new Tree(new $menuModel(), function (Tree $tree) {
            $tree->disableCreateButton();
            $tree->disableQuickCreateButton();
            $tree->disableEditButton();
            $tree->maxDepth(3);

            $tree->actions(function (Tree\Actions $actions) {
                if ($actions->getRow()->extension) {
                    $actions->disableDelete();
                }

                $actions->prepend(new Show());
            });

            $tree->branch(function ($branch) {
                $payload = "<i class='fa {$branch['icon']}'></i>&nbsp;<strong>{$branch['title']}</strong>";

                if (!isset($branch['children'])) {
                    $uri = url()->isValidUrl($branch['uri']) ? $branch['uri'] : bob_admin_base_path($branch['uri'], 'agent-admin');
                    $payload .= "&nbsp;&nbsp;&nbsp;<a href=\"$uri\" class=\"dd-nodrag\">$uri</a>";
                }

                return $payload;
            });
        });
    }

    public function form()
    {
        $menuModel = $this->menuModel();
        $relations = $menuModel::withPermission() ? ['permissions', 'roles'] : 'roles';

        return Form::make(AgentMenu::with($relations), function (Form $form) use ($menuModel) {
            $form->tools(fn (Form\Tools $tools) => $tools->disableView());

            $form->display('id', 'ID');

            $form->select('parent_id', trans('admin.parent_id'))->options(fn () => $menuModel::selectOptions())->saving(fn ($v) => (int)$v);
            $form->text('title', trans('admin.title'))->required();
            $form->icon('icon', trans('admin.icon'))->help($this->iconHelp());
            $form->text('uri', trans('admin.uri'));
            $form->switch('show', trans('admin.show'));

            $form->display('created_at', trans('admin.created_at'));
            $form->display('updated_at', trans('admin.updated_at'));
        })->saved(function (Form $form, $result) {
            $response = $form->response()->location('agent/auth/menu');

            if ($result) {
                return $response->success(__('admin.save_succeeded'));
            }

            return $response->info(__('admin.nothing_updated'));
        });
    }

    protected function iconHelp(): string
    {
        return 'For more icons please see <a href="http://fontawesome.io/icons/" target="_blank">http://fontawesome.io/icons/</a>';
    }

    private function menuModel(): string
    {
        return config('agent-admin.database.menu_model');
    }
}
