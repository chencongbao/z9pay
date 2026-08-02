<?php

namespace App\Jobs;

use Throwable;
use App\Models\DepositOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\DepositOrder\ConfirmPaySuccessService;

class TimingQueryDepositOrderResultJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DUPLICATE_LOCK_SECONDS = 30;

    public $id;

    public $timeout = 120;

    public $tries = 1;

    public function __construct($id = 0)
    {
        $this->id = $id;
    }

    public function handle()
    {
        if ($this->id <= 0 || !Cache::add($this->duplicateLockKey(), 1, now()->addSeconds(self::DUPLICATE_LOCK_SECONDS))) {
            return;
        }

        try {
            $this->handleQuery();
        } catch (Throwable $e) {
            $this->noticeException($e);
        }
    }

    private function handleQuery(): void
    {
        $model = DepositOrder::where('id', $this->id)->whereIn('status', [3, 4])->with(['channel' => function ($query) {
            $query->select('id', 'classname');
        }])->first(['id', 'ordernumber', 'channel_id']);
        if (!$model) {
            return;
        }

        $classname = 'Richard\\Payment\\Channel\\' . optional($model->channel)->classname;
        if (empty(optional($model->channel)->classname) || !class_exists($classname)) {
            throw new \Exception('代收定时查询渠道类不存在，订单ID：' . $model->id);
        }

        $payment = new $classname(LogConstService::DEPOSIT_ORDER_LOG_PREFIX . $model->id);
        $result = $payment->queryDepositStatus($model->ordernumber);
        if (empty($result) || !isset($result['callback_order_amount'])) {
            return;
        }

        App::makeWith(ConfirmPaySuccessService::class, ['filename' => LogConstService::DEPOSIT_ORDER_LOG_PREFIX . $model->id])->excute($model->id, floatval($result['callback_order_amount']));
    }

    private function duplicateLockKey(): string
    {
        return 'job:timing_query_deposit_order_result:' . $this->id;
    }

    private function noticeException(Throwable $e): void
    {
        try {
            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => '代收定时查询异常：' . $e->getMessage(), 'id' => $this->id]);
        } catch (Throwable $noticeException) {
            report($noticeException);
        }
    }
}
