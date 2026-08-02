<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\BlackContent;
use App\Models\MerchantInfo;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Http\Auth\Permission;
use App\Services\BlackContent\ResetBlackContentCacheService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class BlackContentController extends CommonController
{
    protected $disableDestroy = false;

    protected function grid(): Grid
    {
        $typeText = config('default.black_content.type_text', []);
        $formatContent = function (int $type, ?string $content): string {
            return $this->formatContent($type, $content);
        };
        $query = BlackContent::query()->select(['id', 'type', 'mid', 'content', 'status', 'remark', 'created_at'])->with('merchant_info');

        return Grid::make($query, function (Grid $grid) use ($typeText, $formatContent) {
            $adminUser = Admin::user();
            $canCreate = $adminUser->can('black-content-create');
            $canEdit = $adminUser->can('black-content-edit');
            $canStatus = $adminUser->can('black-content-status');
            $canDelete = $adminUser->can('black-content-delete');

            $grid->column('id')->sortable();
            $grid->column('type', '类型')->display(function ($value) use ($typeText) {
                return $typeText[$value] ?? '';
            });
            $grid->column('content')->display(function ($value) use ($formatContent) {
                return $formatContent((int) $this->type, $value);
            });
            $grid->column('merchant_info.bname', '所属商户')->display(function ($value) {
                return $value ?: '所有商户';
            });
            $statusColumn = $grid->column('status');
            $canStatus ? $statusColumn->switch(Admin::color()->green()) : $statusColumn->display(fn ($value) => config('default.status_text')[$value] ?? $value);
            $grid->column('remark');
            $grid->column('created_at');
            if ($canEdit) {
                $grid->showQuickEditButton();
            }
            $grid->disableEditButton();
            if ($canCreate) {
                $grid->enableDialogCreate();
            } else {
                $grid->disableCreateButton();
            }
            if (!$canDelete) {
                $grid->disableDeleteButton();
            }
            $grid->showRowSelector();
            if ($canDelete) {
                $grid->showBatchDelete();
            }

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->equal('mid', '商户')->select(function ($mid) {
                    if ($mid) {
                        $result = App::make(CacheMerchantBaseInfoService::class)->excute($mid);
                        if (!empty($result)) {
                            return [$result['merchant_user_id'] => $result['bname']];
                        }
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(3);
                $filter->like('content', '内容')->width(3);
            });
        });
    }

    protected function form(): Form
    {
        $merchantOptions = MerchantInfo::query()
            ->select(['merchant_user_id', 'name', 'coder', 'currency_id'])
            ->get()
            ->pluck('bname', 'merchant_user_id')
            ->prepend('所有商户', 0);

        Admin::script(
            <<<JS
             $(document).off('change', '.field_type').on('change', '.field_type', function () {
                 if($(this).val() == 1){
                    $(".field_content").attr("placeholder","127.0.0.1,127.0.0.2...");
                    $(".help").addClass('hidden');
                    $(".merchant").removeClass('hidden');
                 }
                 if($(this).val() == 2){
                    $(".field_content").attr("placeholder","姓名1,姓名2...");
                    $(".help").addClass('hidden');
                    $(".merchant").removeClass('hidden');
                 }
                 if($(this).val() == 3){
                    $(".field_content").attr("placeholder","浙江\\r" + "浙江,宁波\\r" + "浙江,宁波,镇海区");
                    $(".help").removeClass('hidden');
                    $(".merchant").addClass('hidden');
                 }
            });
JS
        );

        $updatePermissionSlug = fn (): string => $this->updatePermissionSlug();

        return Form::make(new BlackContent(), function (Form $form) use ($merchantOptions, $updatePermissionSlug) {
            $type = 1;
            $result = BlackContent::query()->whereKey($form->getKey())->first(['id', 'type']);
            if ($result) {
                $type = (int) $result->type;
            }
            $form->select('type', '类型')->options(collect(config('default.black_content.type_text')))->default(1)->disableClearButton()->required();
            if ($type === 3) {
                $form->select('mid', '选择商户')->options($merchantOptions)->default(0)->disableClearButton()->setFormGroupClass('merchant hidden');
                $form->html('<div class="help" style="font-size: 12px;color: red">收银台地区黑名单,省市区名称尽量短写</div>');
            } else {
                $form->select('mid', '选择商户')->options($merchantOptions)->default(0)->disableClearButton()->setFormGroupClass('merchant');
                $form->html('<div class="help hidden" style="font-size: 12px;color: red">收银台地区黑名单,省市区名称尽量短写</div>');
            }
            $form->textarea('content')->required();
            $form->radio('status', '启用状态')->options(config('default.status_text'))->default(1);
            $form->textarea('remark');
            $form->saving(function (Form $form) use ($updatePermissionSlug) {
                if ($form->isCreating() && !Admin::user()->can('black-content-create')) {
                    return $form->response()->error('无新增黑名单权限');
                }
                if ($form->isEditing() && !Admin::user()->can($updatePermissionSlug())) {
                    return $form->response()->error('无编辑黑名单权限');
                }

                if ((int) $form->type === 3) {
                    $form->mid = 0;
                }
            });
            $form->saved(function () {
                App::make(ResetBlackContentCacheService::class)->excute();
            });
            $form->deleted(function () {
                App::make(ResetBlackContentCacheService::class)->excute();
            });
        });
    }

    public function store()
    {
        Permission::check('black-content-create');

        return parent::store();
    }

    public function update($id)
    {
        Permission::check($this->updatePermissionSlug());

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('black-content-delete');

        return parent::destroy($id);
    }

    private function updatePermissionSlug(): string
    {
        $keys = collect(array_keys(request()->all()))->reject(fn ($key) => in_array($key, ['_token', '_method'], true))->values();

        return $keys->count() === 1 && $keys->first() === 'status' ? 'black-content-status' : 'black-content-edit';
    }

    private function formatContent(int $type, ?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $separator = $type === 3 ? "\r\n" : ',';
        $rows = collect(explode($separator, $content))
            ->filter(fn ($item) => trim((string) $item) !== '')
            ->map(fn ($item) => [trim((string) $item)])
            ->all();

        return bob_show_table_info($rows);
    }
}
