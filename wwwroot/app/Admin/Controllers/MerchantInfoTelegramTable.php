<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\MerchantInfo;
use App\Models\MerchantChannel;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use App\Services\Cache\Channel\GetChannelListService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;

class MerchantInfoTelegramTable extends Grid\LazyRenderable
{
    public function grid(): Grid
    {
        Admin::script(
            <<<JS
     $(function () {
        $(document).ready(function () {
            $(".grid-filter-form").find('.input-group-sm').removeClass('input-group-sm');
        })
    });
JS);

        $currencyOptions = collect(config('default.currency', []))->pluck('name', 'id');
        $paymentOptions = collect(config('payment', []))->mapWithKeys(function ($item) {
            return [$item['id'] => ($item['name'] ?? '') . '【' . ($item['code'] ?? '') . '】'];
        });
        $channelOptions = collect(App::make(GetChannelListService::class)->excute())
            ->filter(fn ($item) => (int) $item['status'] === 1)
            ->pluck('bname', 'id')
            ->toArray();
        $agentOptions = bob_build_select_options(App::make(GetMerchantAgentListService::class)->excute());
        $query = MerchantInfo::query()->select(['merchant_user_id', 'name', 'coder', 'currency_id', 'telegram_group_id']);

        return Grid::make($query, function (Grid $grid) use ($currencyOptions, $paymentOptions, $channelOptions, $agentOptions) {
            $grid->model()->where('telegram_group_id', '<>', 0);
            $grid->column('id')->display(function () {
                return $this->merchant_user_id;
            });
            $grid->column('name', '商户名称');
            $grid->column('coder', '商户代码');
            $grid->disableActions();
            $grid->showBatchActions();
            $grid->showRowSelector();
            $grid->disablePagination();
            $grid->filter(function (Grid\Filter $filter) use ($currencyOptions, $paymentOptions, $channelOptions, $agentOptions) {
                $filter->panel();
                $filter->expand();
                $filter->like('name', '商户名称')->width(6);
                $filter->like('coder', '商户代码')->width(6);
                $filter->equal('currency_id', '选择币种')->select($currencyOptions)->width(6);
                $filter->where('payment_id', function ($query) {
                    $channelFilter = collect($this->siblings())->first(function ($filter) {
                        return $filter->originalColumn() === 'channel_id';
                    });
                    $channelId = (int) optional($channelFilter)->getValue();

                    $merchantChannelQuery = MerchantChannel::query()
                        ->select('merchant_user_id')
                        ->where('payment_id', $this->input)
                        ->where('status', 1)
                        ->when($channelId > 0, function ($builder) use ($channelId) {
                            $builder->where('channel_id', $channelId);
                        });

                    $query->whereIn('merchant_user_id', $merchantChannelQuery);
                }, '支付编码')->select($paymentOptions)->width(6);
                $filter->where('agent_user', function ($query) {
                    $query->whereIn('agent_user_id', AgentUserRelation::query()->select('child_id')->where('parent_id', $this->input));
                }, '商户代理')->select($agentOptions)->width(6);
                $filter->where('channel_id', function ($query) {
                    $paymentFilter = collect($this->siblings())->first(function ($filter) {
                        return $filter->originalColumn() === 'payment_id';
                    });
                    $paymentId = (int) optional($paymentFilter)->getValue();

                    $merchantChannelQuery = MerchantChannel::query()
                        ->select('merchant_user_id')
                        ->where('channel_id', $this->input)
                        ->where('status', 1)
                        ->when($paymentId > 0, function ($builder) use ($paymentId) {
                            $builder->where('payment_id', $paymentId);
                        });

                    $query->whereIn('merchant_user_id', $merchantChannelQuery);
                }, '商户渠道')->select($channelOptions)->width(6);
            });
        });
    }
}
