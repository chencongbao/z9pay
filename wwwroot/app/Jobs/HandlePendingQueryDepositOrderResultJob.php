<?php

namespace App\Jobs;

use Throwable;
use App\Models\DepositOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\DepositOrder\ConfirmPaySuccessService;

class HandlePendingQueryDepositOrderResultJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DUPLICATE_LOCK_SECONDS = 120;

    public $id;

    public $tries = 1;

    public $timeout = 120;

    public function __construct($id = 0)
    {
        $this->id = intval($id);
    }

    public function uniqueFor(): int
    {
        return self::DUPLICATE_LOCK_SECONDS;
    }

    public function uniqueId()
    {
        return 'HandlePendingQueryDepositOrderResultJob_' . $this->id;
    }

    public function handle()
    {
        try {
            if ($this->id <= 0) {
                return;
            }

            // ShouldBeUnique 只保证同时不重复；这里再做短时间冷却，避免频繁请求渠道查询。
            if (!Cache::add($this->duplicateLockKey(), 1, now()->addSeconds(self::DUPLICATE_LOCK_SECONDS))) {
                return;
            }

            $model = DepositOrder::query()
                ->whereKey($this->id)
                ->whereIn('status', [3, 4])
                ->with(['channel' => function ($query) {
                    $query->select('id', 'classname');
                }])
                ->first(['id', 'ordernumber', 'channel_id', 'created_at']);
            if (!$model) {
                return;
            }

            $channelClassname = optional($model->channel)->classname;
            $classname = 'Richard\\Payment\\Channel\\' . $channelClassname;
            if (empty($channelClassname) || !class_exists($classname)) {
                throw new \Exception('代收查询渠道类不存在，渠道ID：' . $model->channel_id);
            }

            $filename = LogConstService::DEPOSIT_ORDER_LOG_PREFIX . $model->id;
            $payment = new $classname($filename);
            $result = $payment->queryDepositOrderResult($model->ordernumber);
            if (empty($result)) {
                return;
            }

            if (!isset($result['callback_order_amount'])) {
                throw new \Exception('代收查询返回缺少 callback_order_amount');
            }

            app(ConfirmPaySuccessService::class)->excute($model->id, floatval($result['callback_order_amount']));
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('system_manual_notice', [
                'error' => $e->getMessage(),
                'info' => '查询代收订单异常',
                'id' => $this->id,
            ]);
        }
    }

    private function duplicateLockKey(): string
    {
        return 'deposit_order:pending_query:lock:' . $this->id;
    }
}
