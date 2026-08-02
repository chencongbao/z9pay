<?php

namespace App\Admin\Forms\MerchantChannel;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\Channel;
use App\Models\ChannelRate;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
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

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $merchantUserIds = $this->normalizeMerchantUserIds($input['merchant_user_ids'] ?? []);
            $channelId = intval($input['channel_id'] ?? 0);
            $paymentId = intval($input['payment_id'] ?? 0);
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

            if ($channelId <= 0 || $paymentId <= 0) {
                throw new RuntimeException('请选择通道');
            }

            if ($data['pay_max_amount'] < $data['pay_min_amount']) {
                throw new RuntimeException('代收单笔上限必须大于等于代收单笔下限');
            }

            if ($data['collection_max_amount'] < $data['collection_min_amount']) {
                throw new RuntimeException('代付单笔上限必须大于等于代付单笔下限');
            }

            $this->assertChannelExists($channelId);

            $merchantNames = MerchantInfo::query()->whereIn('merchant_user_id', $merchantUserIds)->pluck('name', 'merchant_user_id');
            if ($merchantNames->count() !== count($merchantUserIds)) {
                throw new RuntimeException('选择的商户信息不存在或已失效');
            }

            $this->assertNoExistingChannel($merchantUserIds, $channelId, $paymentId, $merchantNames);

            $channelPayment = ChannelRate::query()
                ->where('channel_id', $channelId)
                ->where('payment_id', $paymentId)
                ->first(['type', 'rate', 'rate_ranges']);

            $merchantPayments = MerchantPayment::query()
                ->whereIn('merchant_user_id', $merchantUserIds)
                ->where('payment_id', $paymentId)
                ->orderBy('id', 'desc')
                ->get(['merchant_user_id', 'pay_rate'])
                ->unique('merchant_user_id')
                ->keyBy('merchant_user_id');

            foreach ($merchantUserIds as $merchantUserId) {
                $merchantPayment = $merchantPayments->get($merchantUserId);
                $maxChannelRate = $this->maxChannelPercentRate($channelPayment);
                if ($merchantPayment && $channelPayment && $maxChannelRate >= floatval($merchantPayment->pay_rate)) {
                    throw new RuntimeException('商户：【' . ($merchantNames[$merchantUserId] ?? '') . '】渠道成本费率不能大于等于商户通道费率');
                }
            }

            $insertCount = DB::transaction(function () use ($merchantUserIds, $channelId, $paymentId, $data, $admin) {
                $now = now()->toDateTimeString();
                $rows = [];
                foreach ($merchantUserIds as $merchantUserId) {
                    $rows[] = array_merge($data, [
                        'merchant_user_id' => $merchantUserId,
                        'channel_id' => $channelId,
                        'payment_id' => $paymentId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
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
                        'channel_id' => $channelId,
                        'payment_id' => $paymentId,
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

            $this->refreshMerchantChannelCache($merchantUserIds, $paymentId);

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
        $this->multipleSelectTable('merchant_user_ids', '选择商户')->title('选择商户')->from(MerchantInfoMerchantChannelTable::make())->options(function ($v) {
            if (!$v) {
                return [];
            }
            $ids = is_string($v) ? explode(',', $v) : (array) $v;
            return MerchantInfo::query()->whereIn('merchant_user_id', $ids)->get(['merchant_user_id', 'currency_id', 'name'])->pluck('bname', 'merchant_user_id');
        })->pluck('bname', 'merchant_user_id');

        $this->select('channel_id', '选择渠道')->disableClearButton()->options(Channel::query()->orderBy('id', 'desc')->get(['id', 'code', 'name'])->mapWithKeys(fn ($channel) => [$channel->id => '【#' . $channel->id . '】【' . $channel->code . '】' . $channel->name]))->load('payment_id', 'ajax/merchantChannelPaymentField')->rules(['numeric', 'min:1'], ['numeric' => '请选择渠道', 'min' => '请选择渠道'])->required();
        $this->select('payment_id', '选择通道')->rules(['numeric', 'min:1'], ['numeric' => '请选择通道', 'min' => '请选择通道'])->required()->disableClearButton();
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

    private function assertNoExistingChannel(array $merchantUserIds, $channelId, $paymentId, $merchantNames): void
    {
        $existsMerchantUserId = MerchantChannel::query()
            ->whereIn('merchant_user_id', $merchantUserIds)
            ->where('channel_id', $channelId)
            ->where('payment_id', $paymentId)
            ->value('merchant_user_id');

        if ($existsMerchantUserId) {
            throw new RuntimeException('商户：【' . ($merchantNames[$existsMerchantUserId] ?? '') . '】当前通道类型已经存在，请勿重复添加');
        }
    }

    private function assertChannelExists(int $channelId): void
    {
        if (!Channel::query()->whereKey($channelId)->exists()) {
            throw new RuntimeException('渠道类型不存在');
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

    private function refreshMerchantChannelCache(array $merchantUserIds, int $paymentId): void
    {
        $service = app(GetMerchantChannelListService::class);
        foreach ($merchantUserIds as $merchantUserId) {
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
