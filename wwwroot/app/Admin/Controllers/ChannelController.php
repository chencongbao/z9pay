<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\Channel;
use Dcat\Admin\Http\Auth\Permission;
use Illuminate\Support\Facades\File;
use App\Services\IpWhite\WhiteIpFormatService;
use App\Admin\Actions\Grid\Channel\QueryBalance;
use App\Admin\Actions\Grid\Channel\QueryBankList;
use App\Admin\Actions\Grid\Channel\ResetTelegram;
use App\Admin\Actions\Grid\Channel\BatchQueryBalance;
use App\Admin\Actions\Grid\Channel\BatchQueryDepositOrder;
use App\Admin\Actions\Grid\Channel\BatchQueryTransferOrder;
use App\Admin\Actions\Grid\Channel\ChannelTransferCheckInfo;

class ChannelController extends CommonController
{
    private const PAYMENT_FORM_VALUE_PREFIX = '__payment_';

    protected $disableDestroy = false;

    protected function grid(): Grid
    {
        $adminUser = Admin::user();
        $isAdministrator = $adminUser->isAdministrator();
        $canCreate = $adminUser->can('channel-create');
        $canEdit = $adminUser->can('channel-edit');
        $canDelete = $adminUser->can('channel-delete');
        $canResetTelegram = $adminUser->can('channel-reset-telegram');
        $paymentOptions = $this->paymentOptions();
        $currencyOptions = $this->currencyOptions();
        $currencyMap = collect(config('default.currency'))->keyBy('id');
        $transferPayment = config('default.transfer_payment', []);
        $renderChannelPayment = function (?string $classname, ?string $paymentIds): string {
            return $this->renderChannelPayment($classname, $paymentIds);
        };
        $formatOptionTable = function (?string $value, array $options): string {
            return $this->formatOptionTable($value, $options);
        };
        $boolIcon = function ($value): string {
            return $this->boolIcon($value);
        };

        $query = Channel::query()->select([
            'id',
            'name',
            'status',
            'currency',
            'classname',
            'payment_ids',
            'use_cashier',
            'is_real_name',
            'transfer_payment',
            'is_json_return',
            'telegram_user_id',
            'callback_white_ip',
            'balance_amount',
            'batch_transfer',
            'auto_query_status',
            'balance_update_time',
            'deposit_order_query',
            'transfer_order_query',
        ])->orderByDesc('status')->orderByDesc('id');

        return Grid::make($query, function (Grid $grid) use ($isAdministrator, $canCreate, $canEdit, $canDelete, $canResetTelegram, $paymentOptions, $currencyOptions, $currencyMap, $transferPayment, $renderChannelPayment, $formatOptionTable, $boolIcon) {
            $grid->column('id')->sortable();
            $grid->column('channle_info', '渠道信息')->display(function () use ($isAdministrator) {
                $name = e((string) $this->name);
                if (!$isAdministrator) {
                    return $name;
                }

                return $name . '<br><span class="text-muted">' . e((string) $this->classname) . '</span>';
            });
            $grid->column('payment_ids', '支付方式')->display(function ($value) use ($renderChannelPayment) {
                return $renderChannelPayment($this->classname, $value);
            });
            $grid->column('transfer_payment', '代付方式')->display(function ($value) use ($transferPayment, $formatOptionTable) {
                if ($value === null || $value === '') {
                    return '';
                }

                return $formatOptionTable($value, $transferPayment);
            });
            $grid->column('currency_info', '支持币种')->display(function () use ($currencyMap) {
                if (empty($this->currency)) {
                    return '';
                }

                $data = [];
                foreach (explode(',', $this->currency) as $id) {
                    $data[] = [optional($currencyMap->get($id))->offsetGet('name')];
                }

                return bob_show_table_info($data);
            });
            $grid->column('callback_white_ip', '回调ip白名单')->display(function ($value) {
                if (empty($value)) {
                    return '';
                }

                return bob_show_table_info(collect(bob_format_muti_data_to_array($value))->map(fn ($item) => [$item])->all());
            });
            $grid->column('status_info', '状态信息')->display(function () use ($boolIcon) {
                $data[] = ['账号状态', (int) $this->status === 1 ? '<span class="label" style="background:#21b978">启用</span>' : '<span class="label" style="background:#ef5228">禁用</span>'];
                $data[] = ['需要实名', $boolIcon($this->is_real_name)];
                $data[] = ['支持返卡', $boolIcon($this->is_json_return)];
                $data[] = ['使用系统收银台', $boolIcon($this->use_cashier)];
                $data[] = ['支持代付手动查询', $boolIcon($this->transfer_order_query)];
                $data[] = ['支持代收手动查询', $boolIcon($this->deposit_order_query)];
                $data[] = ['自动查询', $boolIcon($this->auto_query_status)];
                return bob_show_table_info($data);
            });
            $grid->column('other_info', '其他信息')->display(function () {
                $data[] = ['账户余额', bob_unit_format($this->balance_amount)];
                if ($this->balance_update_time) {
                    $data[] = ['更新时间', $this->balance_update_time];
                }

                return bob_show_table_info($data);
            });
            if ($canCreate && $isAdministrator) {
                $grid->enableDialogCreate();
            } else {
                $grid->disableCreateButton();
            }
            if (!$canDelete) {
                $grid->disableDeleteButton();
            }
            $grid->disableEditButton();
            if ($canEdit) {
                $grid->showQuickEditButton();
            } else {
                $grid->disableQuickEditButton();
            }
            $grid->showRowSelector();
            $grid->tools(function ($tools) {
                $tools->append(new BatchQueryBalance());
            });
            $grid->actions(function ($action) use ($canResetTelegram) {
                $action->append(new QueryBalance());
                if ((int) $action->row['deposit_order_query'] === 1) {
                    $action->append(new BatchQueryDepositOrder());
                }
                if ((int) $action->row['transfer_order_query'] === 1) {
                    $action->append(new BatchQueryTransferOrder());
                }
                if ($canResetTelegram && (int) $action->row['telegram_user_id'] !== 0) {
                    $action->append(new ResetTelegram());
                }
                $action->append(new ChannelTransferCheckInfo());
                $action->append(new QueryBankList());
            });

            $grid->filter(function (Grid\Filter $filter) use ($currencyOptions, $paymentOptions) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like('name')->width(3);
                $filter->like('classname', '类名')->width(3);
                $filter->where('currency_id', function ($query) {
                    $query->whereRaw('FIND_IN_SET(?, `currency`)', [$this->input]);
                }, '请选择币种')->select($currencyOptions)->width(3);
                $filter->where('payment_id', function ($query) {
                    $query->whereRaw('FIND_IN_SET(?, `payment_ids`)', [$this->input]);
                }, '支付通道')->select($paymentOptions)->width(3);
                $filter->equal('status', '状态')->select([0 => '禁用', 1 => '启用'])->width(3);
            });
        });
    }

    protected function form(): Form
    {
        $isAdministrator = Admin::user()->isAdministrator();
        $paymentOptions = $this->paymentFormOptions();
        $currencyOptions = collect(config('default.currency'))->pluck('name', 'id');
        $implodeFormValue = function ($value): string {
            return $this->implodeFormValue($value);
        };
        $decodePaymentFormValue = function ($value): string {
            return $this->decodePaymentFormValue($value);
        };
        $encodePaymentFormValue = function ($value): array {
            return $this->encodePaymentFormValue($value);
        };
        $hasPaymentFormValue = function ($value): bool {
            return $this->hasFormValue($this->decodePaymentFormValue($value));
        };

        return Form::make(new Channel(), function (Form $form) use ($isAdministrator, $paymentOptions, $currencyOptions, $implodeFormValue, $decodePaymentFormValue, $encodePaymentFormValue, $hasPaymentFormValue) {
            $form->text('name')->required();
            $form->multipleSelect('payment_ids', '支付方式')
                ->options($paymentOptions)
                ->customFormat(fn ($value) => $encodePaymentFormValue($value))
                ->rules([function ($attribute, $value, $fail) use ($hasPaymentFormValue) {
                    if (!$hasPaymentFormValue($value)) {
                        $fail('请选择支付方式');
                    }
                }])
                ->saving(fn ($value) => $decodePaymentFormValue($value));
            $form->radio('status', '渠道状态')->options([0 => '禁用', 1 => '启用'])->default(1);
            if ($isAdministrator) {
                $form->radio('batch_transfer', '批量代付')->options([0 => '否', 1 => '是'])->default(0);
                $form->radio('auto_query_status', '自动查询')->options([0 => '关闭', 1 => '开启'])->default(0);
                $form->radio('deposit_order_query', '支持代收手动查询')->options([0 => '否', 1 => '是'])->default(0);
                $form->radio('transfer_order_query', '支持代付手动查询')->options([0 => '否', 1 => '是'])->default(0);
                $form->radio('is_json_return', '支持返卡')->options([0 => '否', 1 => '是'])->default(0)->when(1, function (Form $form) use ($paymentOptions, $decodePaymentFormValue, $encodePaymentFormValue) {
                    $form->radio('use_cashier', '使用系统收银台')->options([0 => '否', 1 => '是'])->default(0)->when(1, function (Form $form) use ($paymentOptions, $decodePaymentFormValue, $encodePaymentFormValue) {
                        $form->multipleSelect('cashier_payment', '支持收营台')
                            ->options($paymentOptions)
                            ->customFormat(fn ($value) => $encodePaymentFormValue($value))
                            ->saving(fn ($value) => $decodePaymentFormValue($value))
                            ->help('未设置编码不支持使用系统收营台');
                    });
                });
                $form->radio('is_real_name', '需要实名')->options([0 => '否', 1 => '是'])->default(0)->help('需要实名且下游未提交实名，强制开启收银台');
                $form->text('classname', '支付类名')->required();
            }
            $form->multipleSelect('transfer_payment', '代付方式')->options(config('default.transfer_payment'))->saving(function ($value) use ($implodeFormValue) {
                return $implodeFormValue($value);
            })->help('不填表示所有都支持');
            $form->multipleSelect('currency', '支持币种')->options($currencyOptions)->saving(function ($value) use ($implodeFormValue) {
                return $implodeFormValue($value);
            })->help('不填表示所有都支持');
            $form->textarea('coder', '支付编码')->placeholder("bank=1\r\nalipay=2")->help('系统编码=三方编码，每行一个，不需要修改，请勿填写');
            $form->textarea('callback_white_ip', '回调ip白名单')->placeholder('多个IP请用,隔开')->help('多个IP请用逗号或换行隔开，支持单个IP或CIDR，如：1.1.1.1、1.1.1.0/24');
            $form->hidden('appsecret')->default(bob_create_app_secret());
            $form->saving(function (Form $form) {
                if ($form->isCreating() && !Admin::user()->can('channel-create')) {
                    return $form->response()->error('无新增渠道权限');
                }
                if ($form->isEditing() && !Admin::user()->can('channel-edit')) {
                    return $form->response()->error('无编辑渠道权限');
                }

                $classname = $form->classname ?: $form->model()->classname;
                if (empty($form->model()->code)) {
                    $form->input('code', $classname);
                }
                if ($form->isCreating()) {
                    $channelRate = Channel::query()->where('classname', $classname)->exists();
                    if ($channelRate) {
                        return $form->response()->error('通道类名已经存在，请勿重复添加');
                    }
                }
                if ($form->isEditing()) {
                    if (empty($form->model()->appsecret)) {
                        $form->input('appsecret', bob_create_app_secret());
                    }
                    $channelRate = Channel::query()->where('classname', $classname)->where('id', '<>', $form->model()->id)->exists();
                    if ($channelRate) {
                        return $form->response()->error('通道类名已经存在，请勿重复添加');
                    }
                }

                try {
                    $form->input('callback_white_ip', app(WhiteIpFormatService::class)->normalize($form->callback_white_ip, '回调ip白名单'));
                } catch (\Throwable $e) {
                    return $form->response()->error($e->getMessage());
                }
            });
        });
    }

    public function store()
    {
        if (!Admin::user()->can('channel-create')) {
            Permission::error();
        }

        return parent::store();
    }

    public function update($id)
    {
        if (!Admin::user()->can('channel-edit')) {
            Permission::error();
        }

        return parent::update($id);
    }

    public function destroy($id)
    {
        if (!Admin::user()->can('channel-delete')) {
            Permission::error();
        }

        return parent::destroy($id);
    }

    private function paymentOptions()
    {
        return collect(config('payment'))->mapWithKeys(function ($item) {
            return [$item['id'] => $item['name'] . '【' . $item['code'] . '】'];
        });
    }

    private function paymentFormOptions()
    {
        return collect(config('payment'))->mapWithKeys(function ($item) {
            return [$this->encodePaymentId($item['id']) => $item['name'] . '【' . $item['code'] . '】'];
        });
    }

    private function currencyOptions()
    {
        return collect(config('default.currency'))->mapWithKeys(function ($item) {
            return [$item['id'] => '【' . $item['id'] . '】' . $item['name']];
        });
    }

    private function renderChannelPayment(?string $classname, ?string $paymentIds): string
    {
        if (empty($classname)) {
            return '';
        }

        $path = base_path('vendor/richard/payment/src/Channel/' . $classname . '.php');
        if (!File::exists($path)) {
            return '';
        }

        $class = 'Richard\\Payment\\Channel\\' . $classname;
        if (!class_exists($class)) {
            return '';
        }

        $pay = new $class($classname);
        return (string) $pay->getChanelCoderList($paymentIds);
    }

    private function formatOptionTable(?string $value, array $options): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $data = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $data[] = [$options[$item] ?? ''];
        }

        return bob_show_table_info($data);
    }

    private function boolIcon($value): string
    {
        return (int) $value === 1
            ? '<i class="feather icon-check font-md-2 font-w-600 text-green"></i>'
            : '<i class="feather icon-x font-md-1 font-w-600 text-red"></i>';
    }

    private function implodeFormValue($value): string
    {
        return implode(',', array_filter((array) $value, fn ($item) => $item !== null && $item !== ''));
    }

    private function hasFormValue($value): bool
    {
        return array_filter((array) $value, fn ($item) => $item !== null && $item !== '') !== [];
    }

    private function encodePaymentFormValue($value): array
    {
        $paymentValue = $this->implodeFormValue($value);
        if ($paymentValue === '') {
            return [];
        }

        $paymentIds = explode(',', $paymentValue);

        return array_map(fn ($item) => $this->encodePaymentId($item), $paymentIds);
    }

    private function decodePaymentFormValue($value): string
    {
        $items = array_map(function ($item) {
            $item = (string)$item;
            if (str_starts_with($item, self::PAYMENT_FORM_VALUE_PREFIX)) {
                return substr($item, strlen(self::PAYMENT_FORM_VALUE_PREFIX));
            }

            return $item;
        }, (array)$value);

        return $this->implodeFormValue($items);
    }

    private function encodePaymentId($id): string
    {
        return self::PAYMENT_FORM_VALUE_PREFIX . $id;
    }
}
