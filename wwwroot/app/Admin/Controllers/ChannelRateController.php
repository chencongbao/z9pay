<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\Channel;
use App\Models\ChannelRate;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Form\NestedForm;
use Dcat\Admin\Http\Auth\Permission;

class ChannelRateController extends CommonController
{
    protected $disableDestroy = false;

    public $title = '渠道成本';

    protected function grid(): Grid
    {
        $adminUser = Admin::user();
        $canCreate = $adminUser->can('channel-rate-create');
        $canEdit = $adminUser->can('channel-rate-edit');
        $canDelete = $adminUser->can('channel-rate-delete');
        $channelOptions = Channel::query()->pluck('name', 'id');
        $paymentOptions = $this->paymentOptions();
        $controller = $this;
        $renderPaymentName = function (?object $channel, $paymentId): string {
            return ChannelRate::resolvePaymentName($paymentId, $channel);
        };
        $query = ChannelRate::query()->select(['id', 'channel_id', 'payment_id', 'type', 'rate', 'fixed_rate', 'rate_ranges'])->with(['channel' => function ($query) {
            $query->select('id', 'name', 'code', 'classname');
        }]);

        return Grid::make($query, function (Grid $grid) use ($channelOptions, $paymentOptions, $controller, $renderPaymentName, $canCreate, $canEdit, $canDelete) {
            $grid->column('id')->sortable();
            $grid->column('channel.bname', '渠道类型');
            $grid->column('payment_name', '通道类型')->display(function () use ($renderPaymentName) {
                return $renderPaymentName($this->channel, $this->payment_id);
            });
            $grid->column('rate_info', '成本费率')->display(function () use ($controller) {
                $default = (int)$this->type === 0 ? '默认：百分比 ' . bob_amount_format($this->rate) . '%' : '默认：固定 ' . bob_amount_format($this->fixed_rate);
                $ranges = $controller->formatRateRanges($this->rate_ranges ?? [], (int)$this->type);
                return $ranges === '' ? $default : $default . '<br>' . $ranges;
            });
            $grid->filter(function (Grid\Filter $filter) use ($channelOptions, $paymentOptions) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->equal('channel_id', '支付渠道')->select($channelOptions)->width(3);
                $filter->equal('payment_id', '通道类型')->select($paymentOptions)->width(3);
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
        });
    }

    protected function form(): Form
    {
        $channelOptions = Channel::query()->get(['id', 'code', 'name'])->mapWithKeys(fn ($channel) => [$channel->id => '【#' . $channel->id . '】【' . $channel->code . '】' . $channel->name]);
        $controller = $this;

        return Form::make(ChannelRate::with('channel'), function (Form $form) use ($channelOptions, $controller) {
            $this->clearRateValidateErrorScript();
            $id = $form->getKey();
            if ($id) {
                $form->display('channel.name', '渠道类型');
                $form->display('payment_name', '通道类型');
            } else {
                $form->select('channel_id', '渠道类型')->disableClearButton()->options($channelOptions)->load('payment_id', 'ajax/merchantChannelPaymentField')->rules(['numeric', 'min:1'], ['numeric' => '请选择渠道类型', 'min' => '请选择渠道类型'])->required();
                $form->select('payment_id', '通道类型')->rules(['numeric', 'min:1'], ['numeric' => '请选择通道类型', 'min' => '请选择通道类型'])->required()->disableClearButton();
            }
            $percentRules = ['numeric', 'between:0,100', new DecimalTwoPlaces()];
            $percentMessages = ['numeric' => '百分比费率数值不合法', 'between' => '百分比费率0-100'];
            $amountRules = ['numeric', 'between:0,999999999', new DecimalTwoPlaces()];
            $amountMessages = ['numeric' => '固定成本数值不合法', 'between' => '固定成本0-999999999'];
            $form->radio('type', '费率类型')->when(0, function (Form $form) use ($percentRules, $percentMessages) {
                $form->rate('rate', '默认成本费率')->rules($percentRules, $percentMessages)->default(0)->required();
            })->when(1, function (Form $form) use ($amountRules, $amountMessages) {
                $form->number('fixed_rate', '默认成本费率')->rules($amountRules, $amountMessages)->default(0)->required();
            })->options([0 => '百分比费率', 1 => '固定费率'])->default(0)->required();

            $form->table('rate_ranges', '区间成本费率', function (NestedForm $table) use ($amountRules, $amountMessages) {
                $table->number('min_amount', '金额下限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '金额下限数值不合法', 'between' => '金额下限0-999999999'])->default(0)->required();
                $table->number('max_amount', '金额上限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '金额上限数值不合法', 'between' => '金额上限0-999999999'])->default(0)->required()->help('填 0 表示无上限。');
                $table->text('rate', '区间成本值')->rules($amountRules, $amountMessages)->default(0)->required()->help('单位跟随上方费率类型：百分比费率时单位为%，固定费率时单位为固定金额。');
            })->help('区间成本值只填一个值，单位跟随上方费率类型：百分比费率时单位为%，固定费率时单位为固定金额。命中区间则使用区间成本，未命中或未配置区间时继续使用默认成本费率。区间不可重叠。');

            $form->saving(function (Form $form) use ($controller) {
                if ($form->isCreating() && !Admin::user()->can('channel-rate-create')) {
                    return $form->response()->error('无新增渠道成本权限');
                }
                if ($form->isEditing() && !Admin::user()->can('channel-rate-edit')) {
                    return $form->response()->error('无编辑渠道成本权限');
                }

                if ($form->isCreating()) {
                    $exists = ChannelRate::query()->where('channel_id', $form->channel_id)->where('payment_id', $form->payment_id)->exists();
                    if ($exists) {
                        return $form->response()->error('通道类型已经存在，请勿重复添加');
                    }
                }

                $rateRanges = $controller->normalizeRateRanges($form->rate_ranges ?? [], (int)$form->type);
                if ($error = $controller->validateRateRanges($rateRanges, (int)$form->type)) {
                    return $form->response()->error($error);
                }

                $form->rate_ranges = $rateRanges;
            });
        });
    }

    public function store()
    {
        Permission::check('channel-rate-create');

        return parent::store();
    }

    public function update($id)
    {
        Permission::check('channel-rate-edit');

        return parent::update($id);
    }

    public function destroy($id)
    {
        Permission::check('channel-rate-delete');

        return parent::destroy($id);
    }

    private function paymentOptions()
    {
        return collect(config('payment'))->mapWithKeys(function ($item) {
            return [$item['id'] => $item['name'] . '【' . $item['code'] . '】'];
        });
    }

    private function clearRateValidateErrorScript(): void
    {
        Admin::script(<<<'JS'
(function () {
    var formSelector = 'form[action*="/rates-channels"]';
    var inputSelector = [
        formSelector + ' input[name="rate"]',
        formSelector + ' input[name="fixed_rate"]',
        formSelector + ' input[name$="[rate]"]'
    ].join(',');

    $(document)
        .off('input.channel-rate-error-clear change.channel-rate-error-clear keyup.channel-rate-error-clear', inputSelector)
        .on('input.channel-rate-error-clear change.channel-rate-error-clear keyup.channel-rate-error-clear', inputSelector, function () {
            var $input = $(this);
            var $form = $input.closest('form');
            var $group = $input.closest('.form-group,.form-label-group,.form-field');

            $group.removeClass('has-error');
            $group.find('.with-errors').empty();
            $input.removeAttr('aria-invalid').removeClass('is-invalid');
            $form.find('[type="submit"],.submit').buttonLoading(false);
        });
})();
JS);
    }

    private function normalizeRateRanges($ranges, int $type): array
    {
        if (!is_array($ranges)) {
            return [];
        }

        $normalized = array_values(array_map(function (array $range) use ($type) {
            $value = bob_amount_format($range['rate'] ?? 0);

            return [
                'min_amount' => bob_amount_format($range['min_amount'] ?? 0),
                'max_amount' => bob_amount_format($range['max_amount'] ?? 0),
                'rate' => $type === 0 ? $value : 0,
                'fixed_rate' => $type === 1 ? $value : 0,
            ];
        }, array_filter($ranges, fn ($range) => is_array($range) && intval($range['_remove_'] ?? 0) === 0)));

        usort($normalized, fn ($a, $b) => (float)$a['min_amount'] <=> (float)$b['min_amount']);

        return $normalized;
    }

    private function validateRateRanges(array $ranges, int $type): string
    {
        usort($ranges, fn ($a, $b) => (float)$a['min_amount'] <=> (float)$b['min_amount']);
        $lastMaxAmount = null;

        foreach ($ranges as $index => $range) {
            $rowNumber = $index + 1;
            $minAmount = (float)$range['min_amount'];
            $maxAmount = (float)$range['max_amount'];
            if ($maxAmount > 0 && $maxAmount <= $minAmount) {
                return '区间成本费率第' . $rowNumber . '行金额上限必须大于金额下限，或填0表示无上限';
            }
            if ($lastMaxAmount !== null && ($lastMaxAmount <= 0 || $minAmount < $lastMaxAmount)) {
                return '区间成本费率第' . $rowNumber . '行金额区间与上一行重叠，请检查';
            }
            if ($type === 0 && ((float)$range['rate'] < 0 || (float)$range['rate'] > 100)) {
                return '区间成本费率第' . $rowNumber . '行百分比费率必须在0-100之间';
            }
            if ($type === 1 && ((float)$range['fixed_rate'] < 0 || (float)$range['fixed_rate'] > 999999999)) {
                return '区间成本费率第' . $rowNumber . '行固定成本必须在0-999999999之间';
            }
            $lastMaxAmount = $maxAmount;
        }

        return '';
    }

    private function formatRateRanges($ranges, int $type): string
    {
        if (!is_array($ranges) || empty($ranges)) {
            return '';
        }

        $items = [];
        foreach ($ranges as $range) {
            if (!is_array($range)) {
                continue;
            }

            $minAmount = bob_amount_format($range['min_amount'] ?? 0);
            $maxAmount = (float)($range['max_amount'] ?? 0) > 0 ? bob_amount_format($range['max_amount']) : '以上';
            $rateText = $type === 0 ? bob_amount_format($range['rate'] ?? 0) . '%' : '固定 ' . bob_amount_format($range['fixed_rate'] ?? 0);
            $items[] = $minAmount . ' - ' . $maxAmount . '：' . $rateText;
        }

        return empty($items) ? '' : '区间：<br>' . implode('<br>', $items);
    }
}
