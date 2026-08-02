<?php

namespace App\Admin\Forms\DepositeOrder;

use Throwable;
use Carbon\Carbon;
use Dcat\Admin\Admin;
use App\Models\DepositOrder;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Jobs\BatchMerchantDepositCallbackJob;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class BatchCallbackForm extends Form implements LazyRenderable
{
    use LazyWidget;

    private const MAX_LIMIT = 1000;
    private const DEFAULT_LIMIT = 200;
    private const LOCK_SECONDS = 60;

    public function handle(array $input)
    {
        try {
            if (Admin::user()->cannot('deposit-order-batch-callback')) {
                throw new \Exception('无批量回调代收订单权限');
            }

            $merchantUserId = $this->merchantUserId($input);
            $dateAdd = $this->dateAdd($input);
            $limit = $this->limit($input);

            if (!$this->callbackQuery($merchantUserId, $dateAdd)->select('id')->first()) {
                return $this->response()->warning('无有回调地址的终态订单');
            }

            if (!Cache::add($this->lockKey($merchantUserId, $dateAdd), 1, now()->addSeconds(self::LOCK_SECONDS))) {
                return $this->response()->warning('批量回调任务已提交，请勿重复点击');
            }

            dispatch(new BatchMerchantDepositCallbackJob($merchantUserId, $dateAdd, $limit))->onQueue('callback_low');

            $merchantText = $merchantUserId ? ('商户ID：' . $merchantUserId) : '全部商户';
            return $this->response()->success('已提交批量回调任务，日期：' . $dateAdd . '，' . $merchantText . '，最多处理' . $limit . '笔有回调地址的终态订单，队列执行后生成记录');
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-batch-callback');
    }

    public function form()
    {
        $merchantOptions = collect(App::make(GetMerchantListInfoService::class)->excute())->pluck('bname', 'id')->toArray();

        $this->select('merchant_user_id', '所有商户')
            ->options($merchantOptions)
            ->addDefaultConfig(['allowClear' => true])
            ->placeholder('请选择商户，可清空表示全部');
        $this->date('date_add', '日期')->help('只能回调一天的成功/失败终态订单；人工批量回调会强制重新推送');
        $this->number('limit', '处理数量')
            ->default(self::DEFAULT_LIMIT)
            ->rules(['required', 'integer', 'between:1,' . self::MAX_LIMIT], [
                'required' => '请输入处理数量',
                'integer' => '处理数量必须是整数',
                'between' => '处理数量范围为1-' . self::MAX_LIMIT,
            ])
            ->help('默认' . self::DEFAULT_LIMIT . '，最多' . self::MAX_LIMIT . '，避免一次性回调过多订单');
    }

    public function default()
    {
        return [
            'date_add' => date('Y-m-d'),
            'limit' => self::DEFAULT_LIMIT,
        ];
    }

    private function merchantUserId(array $input): ?int
    {
        $merchantUserId = intval($input['merchant_user_id'] ?? 0);

        return $merchantUserId > 0 ? $merchantUserId : null;
    }

    private function dateAdd(array $input): string
    {
        return Carbon::parse($input['date_add'] ?? date('Y-m-d'))->toDateString();
    }

    private function limit(array $input): int
    {
        return max(1, min(intval($input['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));
    }

    private function callbackQuery(?int $merchantUserId, string $dateAdd)
    {
        $date = Carbon::parse($dateAdd);
        $query = DepositOrder::query()
            ->whereIn('status', [5, 6])
            ->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->whereNotNull('notify_url')
            ->where('notify_url', '<>', '');

        if ($merchantUserId) {
            $query->where('mid', $merchantUserId);
        }

        return $query;
    }

    private function lockKey(?int $merchantUserId, string $dateAdd): string
    {
        return 'admin_deposit_batch_callback:' . ($merchantUserId ?: 'all') . ':' . $dateAdd;
    }
}
