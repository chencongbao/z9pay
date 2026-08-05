<?php

namespace App\Admin\Forms\MerchantChannel;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\Channel;
use App\Models\ChannelRate;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Form\NestedForm;
use App\Models\MerchantChannel;
use App\Models\MerchantPayment;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Admin\Controllers\MerchantInfoMerchantChannelTable;
use App\Services\Cache\MerchantChannel\GetMerchantChannelListService;

class BatchAddForm extends Form implements LazyRenderable
{
    use LazyWidget;

    private const PAYMENT_VALUE_PREFIX = '__payment_';

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $merchantUserIds = $this->normalizeMerchantUserIds($input['merchant_user_ids'] ?? []);
            $channelPayments = $this->normalizeChannelPayments($input['channel_payments'] ?? []);
            $channelIds = array_values(array_unique(array_column($channelPayments, 'channel_id')));
            $paymentIds = array_values(array_unique(array_column($channelPayments, 'payment_id')));
            $data = [
                'priority' => intval($input['priority'] ?? 0),
                'weight' => max(1, intval($input['weight'] ?? 1)),
                'pay_min_amount' => bob_amount_format($input['pay_min_amount'] ?? 0),
                'pay_max_amount' => bob_amount_format($input['pay_max_amount'] ?? 0),
                'collection_min_amount' => bob_amount_format($input['collection_min_amount'] ?? 0),
                'collection_max_amount' => bob_amount_format($input['collection_max_amount'] ?? 0),
                'fee' => bob_amount_format($input['fee'] ?? 0),
                'deposit_fee' => bob_amount_format($input['deposit_fee'] ?? 0),
                'status' => intval($input['status'] ?? 1),
                'float_status' => intval($input['float_status'] ?? 0),
            ];

            if (empty($merchantUserIds)) {
                throw new RuntimeException('请选择商户');
            }

            if (empty($channelPayments)) {
                throw new RuntimeException('请至少添加一组渠道和通道');
            }

            if ($data['pay_max_amount'] < $data['pay_min_amount']) {
                throw new RuntimeException('代收单笔上限必须大于等于代收单笔下限');
            }

            if ($data['collection_max_amount'] < $data['collection_min_amount']) {
                throw new RuntimeException('代付单笔上限必须大于等于代付单笔下限');
            }

            $channels = $this->getChannels($channelIds);
            $this->assertChannelsSupportPayments($channels, $channelPayments);

            $merchantNames = MerchantInfo::query()->whereIn('merchant_user_id', $merchantUserIds)->pluck('name', 'merchant_user_id');
            if ($merchantNames->count() !== count($merchantUserIds)) {
                throw new RuntimeException('选择的商户信息不存在或已失效');
            }

            $this->assertNoExistingChannel($merchantUserIds, $channelPayments, $merchantNames);

            $channelRates = ChannelRate::query()
                ->whereIn('channel_id', $channelIds)
                ->whereIn('payment_id', $paymentIds)
                ->get(['channel_id', 'payment_id', 'type', 'rate', 'rate_ranges'])
                ->keyBy(fn ($rate) => $rate->channel_id . ':' . $rate->payment_id);

            $merchantPayments = MerchantPayment::query()
                ->whereIn('merchant_user_id', $merchantUserIds)
                ->whereIn('payment_id', $paymentIds)
                ->orderBy('id', 'desc')
                ->get(['merchant_user_id', 'payment_id', 'pay_rate'])
                ->unique(fn ($payment) => $payment->merchant_user_id . ':' . $payment->payment_id)
                ->keyBy(fn ($payment) => $payment->merchant_user_id . ':' . $payment->payment_id);

            foreach ($merchantUserIds as $merchantUserId) {
                foreach ($channelPayments as $channelPaymentItem) {
                    $channelId = $channelPaymentItem['channel_id'];
                    $paymentId = $channelPaymentItem['payment_id'];
                    $merchantPayment = $merchantPayments->get($merchantUserId . ':' . $paymentId);
                    $channelPayment = $channelRates->get($channelId . ':' . $paymentId);
                    $maxChannelRate = $this->maxChannelPercentRate($channelPayment);
                    if ($merchantPayment && $channelPayment && $maxChannelRate >= floatval($merchantPayment->pay_rate)) {
                        throw new RuntimeException('商户：【' . ($merchantNames[$merchantUserId] ?? '') . '】渠道：【' . ($channels[$channelId]->name ?? $channelId) . '】成本费率不能大于等于商户通道费率');
                    }
                }
            }

            $insertCount = DB::transaction(function () use ($merchantUserIds, $channelPayments, $data, $admin) {
                $now = now()->toDateTimeString();
                $rows = [];
                foreach ($merchantUserIds as $merchantUserId) {
                    foreach ($channelPayments as $channelPayment) {
                        $rows[] = array_merge($data, $channelPayment, [
                            'merchant_user_id' => $merchantUserId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                if (!MerchantChannel::query()->insert($rows)) {
                    throw new RuntimeException('商户渠道添加失败');
                }

                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.channel.batch_add',
                    text: '批量新增 商户渠道',
                    subject: null,
                    properties: array_merge($data, [
                        'merchant_user_ids' => $merchantUserIds,
                        'channel_payments' => $channelPayments,
                        'insert_count' => count($rows),
                    ]),
                    remark: '批量新增 商户渠道',
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $admin
                );

                return count($rows);
            });

            $this->refreshMerchantChannelCache($merchantUserIds, $paymentIds);

            return $this->response()->success('添加成功，共添加' . $insertCount . '条')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-channel-batch-add');
    }

    public function form()
    {
        Admin::style(<<<'CSS'
.has-many-table-channel_payments{table-layout:fixed}
.has-many-table-channel_payments th:nth-child(1),.has-many-table-channel_payments td:nth-child(1){width:58%}
.has-many-table-channel_payments th:nth-child(2),.has-many-table-channel_payments td:nth-child(2){width:36%}
.has-many-table-channel_payments th:last-child,.has-many-table-channel_payments td:last-child{width:56px}
.has-many-table-channel_payments .select2-selection__rendered{padding-right:32px!important}
CSS);

        $this->multipleSelectTable('merchant_user_ids', '选择商户')->title('选择商户')->from(MerchantInfoMerchantChannelTable::make())->options(function ($v) {
            if (!$v) {
                return [];
            }
            $ids = is_string($v) ? explode(',', $v) : (array) $v;
            return MerchantInfo::query()->whereIn('merchant_user_id', $ids)->get(['merchant_user_id', 'currency_id', 'name'])->pluck('bname', 'merchant_user_id');
        })->pluck('bname', 'merchant_user_id');

        $channelOptions = Channel::query()->orderBy('id', 'desc')->get(['id', 'code', 'name'])->mapWithKeys(fn ($channel) => [$channel->id => '【#' . $channel->id . '】【' . $channel->code . '】' . $channel->name]);
        $this->table('channel_payments', '渠道与通道', function (NestedForm $table) use ($channelOptions) {
            $table->select('channel_id', '选择渠道')->options($channelOptions)->load('payment_id', 'ajax/merchantChannelBatchPaymentField')->required();
            $table->select('payment_id', '选择通道')->required();
        })->help('渠道与通道一一对应；填写完成后点击“新增”添加下一组');
        $this->number('priority', '优先级(数小优先)')->rules(['numeric', 'integer', 'between:0,999999'], ['numeric' => '请输入合法的数值', 'integer' => '请输入整数', 'between' => '优先级0-999999'])->min(0)->required();
        $this->number('weight', '权重')->rules(['numeric', 'integer', 'between:1,9999'], ['numeric' => '请输入合法的数值', 'integer' => '请输入整数', 'between' => '权重1-9999'])->default(1)->required()->help('仅按权重模式生效，数值越大分配比例越高');
        $this->number('pay_min_amount', '代收单笔下限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代收单笔下限0-999999999'])->default(0)->required();
        $this->number('pay_max_amount', '代收单笔上限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:pay_min_amount'], ['numeric' => '数值不合法', 'between' => '代收单笔上限0-999999999', 'gte' => '代收单笔上限必须大于等于代收单笔下限'])->default(0)->required();
        $this->number('collection_min_amount', '代付单笔下限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代付单笔下限0-999999999'])->default(0)->required();
        $this->number('collection_max_amount', '代付单笔上限')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces(), 'gte:collection_min_amount'], ['numeric' => '数值不合法', 'between' => '代付单笔上限0-999999999', 'gte' => '代付单笔上限必须大于等于代付单笔下限'])->default(0)->required();
        $this->number('fee', '代付额外手续费')->default(0)->required()->help('最多保留2位小数')->rules(['numeric', 'between:0,999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代付额外手续费0-999999']);
        $this->number('deposit_fee', '代收额外手续费')->default(0)->required()->help('最多保留2位小数')->rules(['numeric', 'between:0,999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '代收额外手续费0-999999']);
        $this->radio('status', '状态')->options([0 => '禁用', 1 => '启用'])->default(1);
        $this->radio('float_status', '是否浮动')->options([0 => '否', 1 => '是'])->default(0);
    }

    public function default()
    {
        return [
            'channel_payments' => [['channel_id' => '', 'payment_id' => '']],
            'priority' => 0,
            'weight' => 1,
            'pay_min_amount' => 0,
            'pay_max_amount' => 0,
            'collection_min_amount' => 0,
            'collection_max_amount' => 0,
            'fee' => 0,
            'deposit_fee' => 0,
            'status' => 1,
            'float_status' => 0,
        ];
    }

    private function normalizeMerchantUserIds($merchantUserIds): array
    {
        if (is_string($merchantUserIds)) {
            $merchantUserIds = explode(',', $merchantUserIds);
        }

        return array_values(array_unique(array_filter(array_map('intval', (array) $merchantUserIds), fn ($id) => $id > 0)));
    }

    private function normalizeChannelPayments($rows): array
    {
        $rows = array_values(array_filter((array)$rows, fn ($row) => is_array($row) && intval($row['_remove_'] ?? 0) === 0));
        $result = [];
        $pairKeys = [];
        foreach ($rows as $row) {
            $channelId = $row['channel_id'] ?? null;
            if ((!is_int($channelId) && !is_string($channelId)) || !ctype_digit((string)$channelId) || (int)$channelId <= 0) {
                throw new RuntimeException('每一组都必须选择渠道');
            }

            if (!array_key_exists('payment_id', $row) || $row['payment_id'] === null || $row['payment_id'] === '') {
                throw new RuntimeException('每一组都必须选择通道');
            }

            $paymentIds = $this->normalizePaymentIds([$row['payment_id']]);
            if (count($paymentIds) !== 1) {
                throw new RuntimeException('每一组都必须选择通道');
            }

            $pair = ['channel_id' => (int)$channelId, 'payment_id' => $paymentIds[0]];
            $pairKey = $pair['channel_id'] . ':' . $pair['payment_id'];
            if (isset($pairKeys[$pairKey])) {
                throw new RuntimeException('渠道与通道组合不能重复');
            }

            $pairKeys[$pairKey] = true;
            $result[] = $pair;
        }

        return $result;
    }

    private function normalizePaymentIds($paymentIds): array
    {
        $paymentIds = is_string($paymentIds) ? explode(',', $paymentIds) : (array)$paymentIds;
        $result = [];
        foreach ($paymentIds as $paymentId) {
            if (!is_int($paymentId) && !is_string($paymentId)) {
                throw new RuntimeException('通道编号不合法');
            }

            $paymentId = (string)$paymentId;
            if (str_starts_with($paymentId, self::PAYMENT_VALUE_PREFIX)) {
                $paymentId = substr($paymentId, strlen(self::PAYMENT_VALUE_PREFIX));
            }

            if (!ctype_digit($paymentId)) {
                throw new RuntimeException('通道编号不合法');
            }

            $result[] = (int)$paymentId;
        }

        return array_values(array_unique($result));
    }

    private function assertNoExistingChannel(array $merchantUserIds, array $channelPayments, $merchantNames): void
    {
        $pairKeys = array_fill_keys(array_map(fn ($item) => $item['channel_id'] . ':' . $item['payment_id'], $channelPayments), true);
        $existingItems = MerchantChannel::query()
            ->whereIn('merchant_user_id', $merchantUserIds)
            ->whereIn('channel_id', array_column($channelPayments, 'channel_id'))
            ->whereIn('payment_id', array_column($channelPayments, 'payment_id'))
            ->get(['merchant_user_id', 'channel_id', 'payment_id']);

        foreach ($existingItems as $existing) {
            if (isset($pairKeys[$existing->channel_id . ':' . $existing->payment_id])) {
                throw new RuntimeException('商户：【' . ($merchantNames[$existing->merchant_user_id] ?? '') . '】渠道【#' . $existing->channel_id . '】通道【#' . $existing->payment_id . '】已经存在，请勿重复添加');
            }
        }
    }

    private function getChannels(array $channelIds)
    {
        $channels = Channel::query()->whereKey($channelIds)->get(['id', 'name', 'payment_ids'])->keyBy('id');
        if ($channels->count() !== count($channelIds)) {
            throw new RuntimeException('部分渠道不存在或已失效');
        }

        return $channels;
    }

    private function assertChannelsSupportPayments($channels, array $channelPayments): void
    {
        foreach ($channelPayments as $channelPayment) {
            $channel = $channels[$channelPayment['channel_id']];
            $supportedIds = array_map('intval', array_filter(explode(',', (string)$channel->payment_ids), 'strlen'));
            if (!in_array($channelPayment['payment_id'], $supportedIds, true)) {
                throw new RuntimeException('渠道：【' . $channel->name . '】不支持通道【#' . $channelPayment['payment_id'] . '】');
            }
        }
    }

    private function maxChannelPercentRate(?ChannelRate $channelRate): float
    {
        if (!$channelRate) {
            return 0;
        }

        if ((int)$channelRate->type !== 0) {
            return 0;
        }

        $rates = [(float)$channelRate->rate];
        foreach (($channelRate->rate_ranges ?: []) as $range) {
            if (is_array($range)) {
                $rates[] = (float)($range['rate'] ?? 0);
            }
        }

        return empty($rates) ? 0 : max($rates);
    }

    private function refreshMerchantChannelCache(array $merchantUserIds, array $paymentIds): void
    {
        $service = app(GetMerchantChannelListService::class);
        foreach ($merchantUserIds as $merchantUserId) {
            foreach ($paymentIds as $paymentId) {
                try {
                    $service->update($merchantUserId, $paymentId);
                } catch (Throwable $e) {
                    app(SystemNoticeService::class)->warning('merchant_channel_cache_refresh_failed', [
                        'error' => '批量新增商户通道后刷新商户通道缓存失败',
                        'merchant_user_id' => $merchantUserId,
                        'payment_id' => $paymentId,
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
