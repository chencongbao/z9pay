<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\Channel;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Box;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\ChannelAccount;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Http\Auth\Permission;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\Channel\GetChannelListService;
use App\Services\Cache\ChannelAccount\CacheLastChannelAccountInfoService;

class ChannelAccountController extends CommonController
{
    protected $disableDestroy = false;

    protected function grid(): Grid
    {
        $channelId = (int) request('channel_id', 0);
        $adminUser = Admin::user();
        $isAdministrator = $adminUser->isAdministrator();
        $canCreate = $adminUser->can('channel-account-create');
        $canEdit = $adminUser->can('channel-account-edit');
        $canDelete = $adminUser->can('channel-account-delete');
        $channelList = App::make(GetChannelListService::class)->excute();
        $renderChannelCoderList = function (?object $channel): string {
            return $this->renderChannelCoderList($channel);
        };
        $query = ChannelAccount::query()->select([
            'id',
            'name',
            'status',
            'channel_id',
            'debug_logs',
            'pay_min_amount',
            'pay_max_amount',
            'pay_total_amount',
            'collection_min_amount',
            'collection_max_amount',
            'collection_total_amount',
        ])->with(['channel' => function ($query) {
            $query->select('id', 'name', 'classname', 'payment_ids');
        }]);

        return Grid::make($query, function (Grid $grid) use ($channelId, $isAdministrator, $canCreate, $canEdit, $canDelete, $channelList, $renderChannelCoderList) {
            if ($channelId > 0) {
                $grid->model()->where('channel_id', $channelId);
            }

            $grid->column('id', '编号')->sortable();
            $grid->column('name', '账号名称');
            $grid->column('channel.name', '渠道名称');
            $grid->column('status', '账号状态')->status();
            if ($isAdministrator) {
                $grid->column('debug_logs', '调试状态')->status();
            }
            $grid->column('channel_list', '可用通道')->display(function () use ($renderChannelCoderList) {
                return $renderChannelCoderList($this->channel);
            });
            $grid->column('pay_limit_info', '充值日限额')->display(function () {
                $data[] = ['单笔：' . bob_unit_format($this->pay_min_amount) . ' - ' . bob_unit_format($this->pay_max_amount)];
                $data[] = ['全天：' . bob_unit_format($this->pay_total_amount)];
                return bob_show_table_info($data);
            });
            $grid->column('collection_limit_info', '代付日限额')->display(function () {
                $data[] = ['单笔：' . bob_unit_format($this->collection_min_amount) . ' - ' . bob_unit_format($this->collection_max_amount)];
                $data[] = ['全天：' . bob_unit_format($this->collection_total_amount)];
                return bob_show_table_info($data);
            });
            $grid->filter(function (Grid\Filter $filter) use ($isAdministrator) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                if ($isAdministrator) {
                    $filter->equal('debug_logs', '调试状态')->select(['禁用', '启用'])->width(3);
                }
            });
            if ($channelId > 0) {
                $grid->model()->setConstraints(['channel_id' => $channelId]);
            }
            if (!$canCreate || $channelId <= 0) {
                $grid->disableCreateButton();
            }
            if (!$canEdit) {
                $grid->disableEditButton();
                $grid->disableQuickEditButton();
            }
            if (!$canDelete) {
                $grid->disableDeleteButton();
            }

            $grid->wrap(function (Renderable $view) use ($channelId, $channelList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($channelId, $channelList) {
                    $box = Box::make('渠道列表', view('admin.channel-accounts.channel-list', ['result' => $channelList, 'channel_id' => $channelId, 'title' => '渠道列表']));
                    $box->padding('15px 0px');
                    $column->row($box);
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
        $isAdministrator = Admin::user()->isAdministrator();
        $amountRules = function (string $label, array $extraRules = []): array {
            return [array_merge(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], $extraRules), ['numeric' => '数值不合法', 'between' => "{$label}0-999999999"]];
        };

        return Form::make(ChannelAccount::with('channel'), function (Form $form) use ($isAdministrator, $amountRules) {
            $id = $form->getKey();
            if ($id) {
                $form->display('channel.name', '渠道类型');
            } else {
                $channelId = (int) request('channel_id', 0);
                $form->hidden('channel_id')->default($channelId);
                $channel = Channel::query()->find($channelId, ['id', 'name']);
                $form->display('channel_name', '渠道类型')->default(optional($channel)->name);
            }

            [$payMinRules, $payMinMessages] = $amountRules('充值单笔下限');
            [$payMaxRules, $payMaxMessages] = $amountRules('充值单笔上限', ['gte:pay_min_amount']);
            [$payTotalRules, $payTotalMessages] = $amountRules('充值日总额');
            [$collectionMinRules, $collectionMinMessages] = $amountRules('代付单笔下限');
            [$collectionMaxRules, $collectionMaxMessages] = $amountRules('代付单笔上限', ['gte:collection_min_amount']);
            [$collectionTotalRules, $collectionTotalMessages] = $amountRules('代付日总额');
            $payMaxMessages['gte'] = '充值单笔上限必须大于等于充值单笔下限';
            $collectionMaxMessages['gte'] = '代付单笔上限必须大于等于代付单笔下限';

            $form->text('name', '账号名称')->rules(['max:50'], ['max' => '不能超过50个字符'])->default('默认账号')->required();
            $form->text('pay_min_amount')->rules($payMinRules, $payMinMessages)->default(0)->required()->help('最多保留2位小数');
            $form->text('pay_max_amount')->rules($payMaxRules, $payMaxMessages)->default(0)->required()->help('最多保留2位小数');
            $form->text('pay_total_amount')->rules($payTotalRules, $payTotalMessages)->default(0)->required()->help('最多保留2位小数');
            $form->text('collection_min_amount')->rules($collectionMinRules, $collectionMinMessages)->default(0)->required()->help('最多保留2位小数');
            $form->text('collection_max_amount')->rules($collectionMaxRules, $collectionMaxMessages)->default(0)->required()->help('最多保留2位小数');
            $form->text('collection_total_amount')->rules($collectionTotalRules, $collectionTotalMessages)->default(0)->required()->help('最多保留2位小数');
            $form->textarea('params')->placeholder('键=值');
            $form->textarea('secret_params', '保密参数1');
            $form->textarea('public_params', '保密参数2');
            $form->radio('status', '账号状态')->options([0 => '禁用', 1 => '启用'])->default(1);
            if ($isAdministrator) {
                $form->radio('debug_logs', '调试状态')->options([0 => '禁用', 1 => '启用'])->default(0);
            }
            $form->saving(function (Form $form) {
                if ($form->isCreating() && !Admin::user()->can('channel-account-create')) {
                    return $form->response()->error('无新增渠道账号权限');
                }
                if ($form->isEditing() && !Admin::user()->can('channel-account-edit')) {
                    return $form->response()->error('无编辑渠道账号权限');
                }

                if ($form->isCreating()) {
                    $channel = Channel::query()->find($form->channel_id, ['id']);
                    if (!$channel) {
                        return $form->response()->error('渠道类型不存在，非法操作');
                    }

                    $form->channel_id = $channel->id;
                }
            });

            $form->saved(function (Form $form) {
                App::make(CacheLastChannelAccountInfoService::class)->excute($form->repository()->model()->channel_id, true);
            });
        });
    }

    public function store()
    {
        if (!Admin::user()->can('channel-account-create')) {
            Permission::error();
        }

        return parent::store();
    }

    public function update($id)
    {
        if (!Admin::user()->can('channel-account-edit')) {
            Permission::error();
        }

        return parent::update($id);
    }

    public function destroy($id)
    {
        if (!Admin::user()->can('channel-account-delete')) {
            Permission::error();
        }

        return parent::destroy($id);
    }

    private function renderChannelCoderList(?object $channel): string
    {
        if (!$channel || empty($channel->classname)) {
            return '';
        }

        $path = base_path('vendor/richard/payment/src/Channel/' . $channel->classname . '.php');
        if (!File::exists($path)) {
            return '';
        }

        $classname = 'Richard\\Payment\\Channel\\' . $channel->classname;
        if (!class_exists($classname)) {
            return '';
        }

        $pay = new $classname();
        return (string) $pay->getChanelCoderList($channel->payment_ids);
    }
}
