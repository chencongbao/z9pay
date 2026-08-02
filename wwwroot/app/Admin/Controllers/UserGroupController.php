<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\UserGroup;
use App\Models\MerchantInfo;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Http\Auth\Permission;

class UserGroupController extends CommonController
{
    protected $disableDestroy = false;

    protected function grid(): Grid
    {
        $status = (int) request('status', 1);
        $userRows = User::query()->select(['id', 'name', 'username', 'user_group_id'])->get();
        $usersByGroup = $userRows->groupBy('user_group_id');
        $userNameMap = $userRows->pluck('bname', 'id');
        $merchantNameMap = MerchantInfo::query()->select(['merchant_user_id', 'name', 'coder', 'currency_id'])->get()->pluck('bname', 'merchant_user_id');
        $parseIds = function ($value): array {
            if (empty($value)) {
                return [];
            }

            return collect(is_array($value) ? $value : explode(',', $value))->filter(function ($id) {
                return $id !== null && $id !== '';
            })->values()->all();
        };
        $namesToRows = function (array $ids, $nameMap): array {
            return collect($ids)->map(function ($id) use ($nameMap) {
                return isset($nameMap[$id]) ? [$nameMap[$id]] : null;
            })->filter()->values()->all();
        };

        $query = UserGroup::query()->select($this->listColumns())->where('status', $status)->orderBy('priority')->orderBy('id');

        return Grid::make($query, function (Grid $grid) use ($usersByGroup, $userNameMap, $merchantNameMap, $parseIds, $namesToRows) {
            $adminUser = Admin::user();
            $canCreate = $adminUser->can('user-group-create');
            $canEdit = $adminUser->can('user-group-edit');
            $canDelete = $adminUser->can('user-group-delete');
            $canStatus = $adminUser->can('user-group-status');
            $canPriority = $adminUser->can('user-group-priority');

            $grid->column('id')->sortable()->width(80)->center();
            $grid->column('name');
            $grid->column('all_user_ids', '分组金主（包括补充金主）')->display(function () use ($usersByGroup, $userNameMap, $parseIds) {
                $groupUsers = collect($usersByGroup->get($this->id, collect()))->pluck('bname');
                $extraUsers = collect($parseIds($this->extra_user_ids))->map(function ($id) use ($userNameMap) {
                    return $userNameMap[$id] ?? null;
                })->filter();
                $data = $groupUsers->merge($extraUsers)->unique()->map(function ($name) {
                    return [$name];
                })->values()->toArray();

                return bob_show_table_info($data);
            });
            $grid->column('specialized_merchant_user_ids', '专接商户标识')->display(function ($value) use ($merchantNameMap, $parseIds, $namesToRows) {
                return bob_show_table_info($namesToRows($parseIds($value), $merchantNameMap));
            });
            $grid->column('merchant_user_ids', '排除商户标识')->display(function ($value) use ($merchantNameMap, $parseIds, $namesToRows) {
                return bob_show_table_info($namesToRows($parseIds($value), $merchantNameMap));
            });
            $priorityColumn = $grid->column("priority", "优先级，从小到大排序")->center()->width(200);
            $canPriority ? $priorityColumn->editable(['refresh' => true]) : $priorityColumn;
            $statusColumn = $grid->column("status", "状态")->center();
            $canStatus ? $statusColumn->switch(Admin::color()->green()) : $statusColumn->display(fn ($value) => ["关闭", "开启"][$value] ?? $value);
            $grid->column('created_at')->center();
            $grid->column('updated_at')->center();
            if (!$canCreate) {
                $grid->disableCreateButton();
            }
            if (!$canEdit) {
                $grid->disableEditButton();
            }
            if (!$canDelete) {
                $grid->disableDeleteButton();
            }
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->like('name')->width(3);
                $filter->equal("status", "状态")->select(["禁用", "启用"])->default(1)->width(3)->addDefaultConfig(['allowClear' => false]);
            });
        });
    }

    protected function form(): Form
    {
        $merchantOptions = MerchantInfo::query()->select(['merchant_user_id', 'name', 'coder', 'currency_id'])->get()->pluck('bname', 'merchant_user_id');
        $allUserOptions = User::query()->select(['id', 'name', 'username'])->get()->pluck('bname', 'id');
        $updatePermissionSlug = fn (): string => $this->updatePermissionSlug();
        $parseIds = function ($value): array {
            if (empty($value)) {
                return [];
            }

            return collect(is_array($value) ? $value : explode(',', $value))->filter(function ($id) {
                return $id !== null && $id !== '';
            })->values()->all();
        };
        $saveIds = function ($value) use ($parseIds): ?string {
            $ids = $parseIds($value);
            return empty($ids) ? null : implode(',', $ids);
        };

        return Form::make(new UserGroup(), function (Form $form) use ($merchantOptions, $allUserOptions, $updatePermissionSlug, $parseIds, $saveIds) {
            $form->hidden("priority")->default(0);
            $form->text('name')->rules(['required', 'max:100'])->required();
            $id = $form->getKey();
            if ($id) {
                $userOptions = User::query()->where('is_agent', 0)->where(function ($query) use ($id) {
                    $query->where('user_group_id', 0)->orWhere('user_group_id', $id);
                })->select(['id', 'name', 'username'])->get()->pluck('bname', 'id');
                $form->multipleSelect('user_ids', "分组金主")->options($userOptions)->customFormat(function () use ($id) {
                    return User::query()->where('user_group_id', $id)->pluck('id')->all();
                })->help("一个金主只能所属一个分组");
            } else {
                $userOptions = User::query()->where('is_agent', 0)->where('user_group_id', 0)->select(['id', 'name', 'username'])->get()->pluck('bname', 'id');
                $form->multipleSelect('user_ids', "分组金主")->options($userOptions)->help("一个金主只能所属一个分组");
            }
            $form->multipleSelect('specialized_merchant_user_ids', '专接商户标识')->options($merchantOptions)->saving(function ($value) use ($saveIds) {
                return $saveIds($value);
            })->help('当前分组所有收款卡专接给设置的商户，如果同一个商户设置了专接又设置了排除，排除将不失效');
            $form->multipleSelect('merchant_user_ids', '排除商户标识')->options($merchantOptions)->saving(function ($value) use ($saveIds) {
                return $saveIds($value);
            })->help('当前分组所有收款卡不分配给设置的商户');
            $form->multipleSelect('extra_user_ids', '分组补充金主')->options($allUserOptions)->saving(function ($value) use ($saveIds) {
                return $saveIds($value);
            })->help('当前设置是对当前分组金主的一个补充');
            $form->radio("status", "状态")->options([1 => "开启", 0 => "关闭"])->default(1);
            $form->saving(function (Form $form) use ($updatePermissionSlug) {
                if ($form->isCreating() && !Admin::user()->can('user-group-create')) {
                    return $form->response()->error('无新增自营分组权限');
                }
                if ($form->isEditing() && !Admin::user()->can($updatePermissionSlug())) {
                    return $form->response()->error('无编辑自营分组权限');
                }

                $form->deleteInput("user_ids");
            });
            $form->saved(function (Form $form, $result) use ($parseIds) {
                if ($result) {
                    $userIds = $parseIds(request('user_ids', []));
                    $groupId = (int) $form->repository()->model()->id;
                    if ($groupId <= 0) {
                        return;
                    }

                    DB::transaction(function () use ($groupId, $userIds) {
                        User::query()->where('user_group_id', $groupId)->update(['user_group_id' => 0]);
                        if (!empty($userIds)) {
                            User::query()->where('is_agent', 0)->whereIn('id', $userIds)->update(['user_group_id' => $groupId]);
                        }
                    });
                }
            });
            $form->deleted(function (Form $form, $result) {
                if ($result) {
                    $groupId = (int) $form->getKey();
                    if ($groupId <= 0) {
                        $data = collect($form->model()->toArray())->first();
                        $groupId = (int) ($data['id'] ?? 0);
                    }

                    if ($groupId > 0) {
                        User::query()->where('user_group_id', $groupId)->update(['user_group_id' => 0]);
                    }
                }
            });
        });
    }

    public function store()
    {
        Permission::check('user-group-create');

        return parent::store();
    }

    public function update($id)
    {
        Permission::check($this->updatePermissionSlug());

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('user-group-delete');

        return parent::destroy($id);
    }

    private function listColumns(): array
    {
        return [
            'id', 'name', 'specialized_merchant_user_ids', 'merchant_user_ids', 'extra_user_ids', 'priority', 'status', 'created_at', 'updated_at',
        ];
    }

    private function updatePermissionSlug(): string
    {
        $keys = collect(array_keys(request()->all()))->reject(fn ($key) => in_array($key, ['_token', '_method'], true))->values();

        if ($keys->count() === 1 && $keys->first() === 'status') {
            return 'user-group-status';
        }
        if ($keys->count() === 1 && $keys->first() === 'priority') {
            return 'user-group-priority';
        }

        return 'user-group-edit';
    }
}
