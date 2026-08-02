<?php

namespace App\Admin\Controllers\Agent;

use Dcat\Admin\Form;
use Dcat\Admin\Tree;
use Dcat\Admin\Admin;
use Illuminate\Support\Str;
use Dcat\Admin\Layout\Content;
use App\Repositories\AgentPermission;
use Dcat\Admin\Http\Controllers\AdminController;

class PermissionController extends AdminController
{
    protected function title()
    {
        return trans('admin.permissions');
    }

    public function index(Content $content)
    {
        return $content
            ->title($this->title())
            ->description(trans('admin.list'))
            ->body($this->treeView());
    }

    protected function treeView()
    {
        $permissionModel = config('agent-admin.database.permissions_model');
        $routePrefix = (string) config('agent-admin.route.prefix');
        $color = Admin::color()->primaryDarker();

        return new Tree(new $permissionModel(), function (Tree $tree) use ($routePrefix, $color) {
            $tree->disableCreateButton();
            $tree->disableEditButton();

            $tree->branch(function ($branch) use ($routePrefix, $color) {
                $branchName = htmlspecialchars($branch['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $branchSlug = htmlspecialchars($branch['slug'] ?? '', ENT_QUOTES, 'UTF-8');
                $payload = "<div class='pull-left' style='min-width:310px'><b>{$branchName}</b>&nbsp;&nbsp;[<span class='text-primary'>{$branchSlug}</span>]";

                $paths = array_values(array_filter((array) ($branch['http_path'] ?? [])));
                if (empty($paths)) {
                    return $payload . '</div>&nbsp;';
                }

                $max = 3;
                if (count($paths) > $max) {
                    $paths = array_slice($paths, 0, $max);
                    $paths[] = '...';
                }

                $methods = (array) ($branch['http_method'] ?: []);
                $pathHtml = collect($paths)->map(function ($path) use (&$methods, $routePrefix, $color) {
                    if (Str::contains($path, ':')) {
                        [$methodText, $path] = explode(':', $path, 2);
                        $methods = array_merge($methods, explode(',', $methodText));
                    }
                    if ($path !== '...' && $routePrefix !== '' && ! Str::contains($path, '.')) {
                        $path = trim(bob_admin_base_path($path, 'agent-admin'), '/');
                    }

                    return "<code style='color:{$color}'>$path</code>";
                })->implode('&nbsp;&nbsp;');

                $methodHtml = collect($methods ?: ['ANY'])->unique()->map(function ($name) {
                    return strtoupper($name);
                })->map(function ($name) {
                    return "<span class='label bg-primary'>{$name}</span>";
                })->implode('&nbsp;') . '&nbsp;';

                $payload .= "</div>&nbsp; $methodHtml<a class=\"dd-nodrag\">$pathHtml</a>";

                return $payload;
            });
        });
    }

    public function form()
    {
        $with = [];
        $bindMenu = config('agent-admin.menu.permission_bind_menu', true);

        if ($bindMenu) {
            $with[] = 'menus';
        }

        return Form::make(AgentPermission::with($with), function (Form $form) use ($bindMenu) {
            $permissionTable = config('agent-admin.database.permissions_table');
            $connection = config('agent-admin.database.connection');
            $permissionModel = config('agent-admin.database.permissions_model');
            $menuModel = config('agent-admin.database.menu_model');

            $id = $form->getKey();

            $form->display('id', 'ID');

            $form->select('parent_id', trans('admin.parent_id'))->options($permissionModel::selectOptions())->saving(function ($v) {
                return (int) $v;
            });

            $form->text('slug', trans('admin.slug'))
                ->required()
                ->creationRules(['required', "unique:{$connection}.{$permissionTable}"])
                ->updateRules(['required', "unique:{$connection}.{$permissionTable},slug,$id"]);
            $form->text('name', trans('admin.name'))->required();

            $form->multipleSelect('http_method', trans('admin.http.method'))
                ->options($this->getHttpMethodsOptions())
                ->help(trans('admin.all_methods_if_empty'));

            $form->tags('http_path', trans('admin.http.path'))
                ->options($this->getRoutes());

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

            $form->disableViewButton();
            $form->disableViewCheck();
        })->saved(function () {
            $model = config('agent-admin.database.menu_model');
            (new $model())->flushCache();
        });
    }

    public function getRoutes()
    {
        $prefix = (string) config('agent-admin.route.prefix');
        $hasPrefix = $prefix !== '' && $prefix !== '/';

        return collect(app('router')->getRoutes())->flatMap(function ($route) use ($prefix, $hasPrefix) {
            $uri = $route->uri();
            if ($hasPrefix && ! Str::startsWith($uri, $prefix)) {
                return [];
            }

            $paths = [];
            if (! Str::contains($uri, '{')) {
                $wildcardPath = $hasPrefix ? Str::replaceFirst($prefix, '', $uri . '*') : $uri . '*';
                if ($wildcardPath !== '*') {
                    $paths[] = $wildcardPath;
                }
            }

            $path = preg_replace('/{.*}+/', '*', $uri);
            $paths[] = $hasPrefix ? Str::replaceFirst($prefix, '', $path) : $path;

            return $paths;
        })->filter()->unique()->values()->all();
    }

    protected function getHttpMethodsOptions()
    {
        $permissionModel = config('agent-admin.database.permissions_model');

        return array_combine($permissionModel::$httpMethods, $permissionModel::$httpMethods);
    }
}
