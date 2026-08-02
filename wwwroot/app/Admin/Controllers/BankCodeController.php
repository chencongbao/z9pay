<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\BankCode;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Widgets\Table;
use App\Models\ChannelBankCode;
use Illuminate\Support\Facades\App;
use App\Admin\Extensions\Layout\LeftSide;
use App\Admin\Actions\Grid\BankCode\Delete;
use Illuminate\Contracts\Support\Renderable;
use Dcat\Admin\Http\Auth\Permission;
use App\Admin\Actions\Grid\BankCode\ExportData;
use App\Services\Common\DataFormatBnameService;
use App\Admin\Actions\Grid\BankCode\AddChannelBankCode;
use App\Admin\Actions\Grid\BankCode\CopyChannelBankCode;
use App\Admin\Actions\Grid\BankCode\EditChannelBankCode;

class BankCodeController extends CommonController
{
    protected $disableDestroy = false;

    public function title()
    {
        return '银行代码';
    }

    protected function grid(): Grid
    {
        $link = Admin::app()->getRoute('ajax.deleteChannelBankCode');
        $currencyConfig = collect(config('default.currency'));
        $currencyOptions = $currencyConfig->pluck('country', 'id');
        $countryMap = $currencyConfig->keyBy('id');
        $countryList = $currencyConfig->map(function ($item) {
            $item['bname'] = '【#' . $item['id'] . '】' . $item['name'];
            return $item;
        })->toArray();

        Admin::script(
            <<<JS
            $(document).off('click', '.delete-channel-bank-code').on('click', '.delete-channel-bank-code', function () {
                let id = $(this).data('id');
                Dcat.confirm('确认要删除这行数据吗？', null, function () {
                     $.ajax({
                        type: 'GET',
                        data:{id:id},
                        url:"{$link}",
                        success:function(res){
                             Dcat.loading(false);
                            if(res.code == 200){
                               Dcat.reload();
                            }else{
                                 Dcat.error(res.message);
                                return;
                            }
                        }
                    });
                });
            });
JS
        );

        return Grid::make(BankCode::query()->select(['id', 'code', 'name', 'currency_id']), function (Grid $grid) use ($countryMap, $currencyOptions, $countryList) {
            $adminUser = Admin::user();
            $canCreate = $adminUser->can('bank-code-create');
            $canEdit = $adminUser->can('bank-code-edit');
            $canDelete = $adminUser->can('bank-code-delete');
            $canChannelCreate = $adminUser->can('bank-code-channel-create');
            $canChannelEdit = $adminUser->can('bank-code-channel-edit');
            $canChannelDelete = $adminUser->can('bank-code-channel-delete');
            $canChannelCopy = $adminUser->can('bank-code-channel-copy');

            $grid->model()->setConstraints(['currency_id' => request('currency_id')]);
            $grid->column('id', '编码')->sortable();
            $grid->column('code')->expand(function () use ($canChannelEdit, $canChannelDelete) {
                $header = ['渠道编码', '所属渠道', '操作'];
                $rows = [];
                $result = ChannelBankCode::query()
                    ->select(['id', 'code', 'bank_code_id', 'channel_id'])
                    ->where('bank_code_id', $this->id)
                    ->with(['channel' => function ($query) {
                        $query->select('id', 'name');
                    }])
                    ->get();

                foreach ($result as $item) {
                    $actions = [];
                    if ($canChannelEdit) {
                        $actions[] = new EditChannelBankCode($item->id);
                    }
                    if ($canChannelDelete) {
                        $actions[] = '<a href="javascript:;" class="btn btn-danger delete-channel-bank-code" data-id="' . $item->id . '" style="margin-left: 10px">删除</a>';
                    }
                    $rows[] = [
                        $item->code,
                        optional($item->channel)->name,
                        implode('', $actions),
                    ];
                }

                $table = new Table($header, $rows);
                $table->withBorder();
                return new Card('', $table->render());
            });
            $grid->column('name');
            $grid->column('currency_id', '所属国家')->display(function ($value) use ($countryMap) {
                return optional($countryMap->get($value))->offsetGet('country');
            });
            if (!$canCreate) {
                $grid->disableCreateButton();
            }
            if (!$canEdit) {
                $grid->disableEditButton();
            }
            if (!$canDelete) {
                $grid->disableDeleteButton();
            }
            $grid->tools(function (Grid\Tools $tools) use ($canChannelCopy) {
                if ($canChannelCopy) {
                    $tools->append(new CopyChannelBankCode());
                }
                $tools->append(new ExportData());
            });
            $grid->actions(function (Grid\Displayers\Actions $actions) use ($canDelete, $canChannelCreate) {
                $actions->disableDelete();
                if ($canDelete) {
                    $actions->append(new Delete());
                }
                if ($canChannelCreate) {
                    $actions->append(new AddChannelBankCode());
                }
            });
            $grid->filter(function (Grid\Filter $filter) use ($currencyOptions) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id', '编码')->width(3);
                $filter->equal('currency_id', '选择国家')->select($currencyOptions)->width(3);
                $filter->like('code', '银行代码')->width(3);
                $filter->like('name', '银行名称')->width(3);
            });

            $grid->wrap(function (Renderable $view) use ($countryList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($countryList) {
                    $left = new LeftSide();
                    $left->title('国家列表');
                    $left->field('currency_id')->default()->prependAll('全部国家')->data($countryList);
                    $column->row($left);
                });
                $row->column(10, function (Column $column) use ($view) {
                    $card = Card::make($view);
                    $card->padding('15px');
                    $column->row($card);
                });
                return $row->render();
            });
        });
    }

    protected function form(): Form
    {
        $currencyOptions = collect(App::make(DataFormatBnameService::class)->excute(config('default.currency')))->pluck('bname', 'id');

        return Form::make(new BankCode(), function (Form $form) use ($currencyOptions) {
            $form->select('currency_id', '选择国家')->options($currencyOptions)->default(request('currency_id', 1))->disableClearButton()->required();
            $form->text('code')->required()->help('请用，1：大写英文字母 2：不能有空格 3：有表面意思（如银行名称）来表示，如：CHINA_BANK');
            $form->text('name')->required();
            $form->saving(function (Form $form) {
                if ($form->isCreating() && !Admin::user()->can('bank-code-create')) {
                    return $form->response()->error('无新增银行代码权限');
                }
                if ($form->isEditing() && !Admin::user()->can('bank-code-edit')) {
                    return $form->response()->error('无编辑银行代码权限');
                }

                if (!preg_match('/^[A-Z0-9_]+$/', $form->code)) {
                    return $form->response()->error('银行编码必须为数字、大写字母、下划线组成');
                }

                $id = $form->getKey();
                $codeQuery = BankCode::query()->where('code', $form->code)->where('currency_id', $form->currency_id);
                $nameQuery = BankCode::query()->where('name', $form->name)->where('currency_id', $form->currency_id);
                if ($form->isEditing()) {
                    $codeQuery->where('id', '<>', $id);
                    $nameQuery->where('id', '<>', $id);
                }

                if ($codeQuery->exists()) {
                    return $form->response()->error('银行编码已存在，请勿重复添加');
                }
                if ($nameQuery->exists()) {
                    return $form->response()->error('银行名称已存在，请勿重复添加');
                }
            });
        });
    }

    public function store()
    {
        Permission::check('bank-code-create');

        return parent::store();
    }

    public function update($id)
    {
        Permission::check('bank-code-edit');

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('bank-code-delete');

        return parent::destroy($id);
    }
}
