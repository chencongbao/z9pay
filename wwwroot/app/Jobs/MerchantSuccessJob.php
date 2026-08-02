<?php

namespace App\Jobs;

use App\Models\DepositOrder;
use Illuminate\Bus\Queueable;
use App\Models\MerchantPayment;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class MerchantSuccessJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $message = [];

    public $mid = 0;

    public $uniqueFor = 30;

    public function __construct($mid = 0, $message = [])
    {
        $this->mid = $mid;
        $this->message = $message;
    }

    public function uniqueId(): string
    {
        return $this->mid . ':' . ($this->message['chat']['id'] ?? 0);
    }

    public function handle(): void
    {
        $data = [];
        $payments = collect(config('payment'))->keyBy('id');
        $result = MerchantPayment::query()
            ->where('merchant_user_id', $this->mid)
            ->where('status', 1)
            ->whereNotIn('payment_id', [0, 7])
            ->get(['payment_id', 'pay_rate']);

        if (!$result->isEmpty()) {
            // 一次聚合今日各支付方式订单，避免循环内重复查询和跨日缓存偏差。
            $today = now();
            $orderStats = DepositOrder::query()
                ->where('mid', $this->mid)
                ->whereIn('payment_id', $result->pluck('payment_id')->all())
                ->where('created_at', '>=', $today->copy()->startOfDay())
                ->where('created_at', '<', $today->copy()->addDay()->startOfDay())
                ->select('payment_id')
                ->selectRaw('COUNT(*) AS total_count')
                ->selectRaw('SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) AS success_count')
                ->groupBy('payment_id')
                ->get()
                ->keyBy('payment_id');

            foreach ($result as $item) {
                $stats = $orderStats->get($item->payment_id);
                $total = intval($stats->total_count ?? 0);
                $success = intval($stats->success_count ?? 0);
                $payment = optional($payments->get($item->payment_id));

                $data[] = [
                    'code' => $payment->offsetGet('code'),
                    'name' => $payment->offsetGet('name'),
                    'percent_rate' => floatval($item->pay_rate) . "%",
                    'percent_success' => $total == 0 ? 0 : (bob_amount_format($success / $total) * 100) . "%",
                ];
            }
        }

        if (!empty($data) && !empty($this->message)) {
            $merchant_info = App::make(CacheMerchantBaseInfoService::class)->excute($this->mid);
            $html = "商户：" . $merchant_info['bname'] . "\n-------------------------------------------------------------------------------------------------\n";
            foreach ($data as $value) {
                $html .= $value['code'] . "  " . $value['name'] . "  费率：<code>" . $value['percent_rate'] . "</code>";
                if (config('app.name') != 'sgpay') {
                    $html .= " 今日成功率：<code>" . $value['percent_success'] . "</code>";
                }
                $html .= "\n";
            }
            $data['telegram_group_id'] = $this->message['chat']['id'];
            $data['reply_to_message_id'] = $this->message['message_id'];
            $data['send_content'] = $html;
            dispatch(new TelegramQunSendJob($data))->onQueue("query");
        }
    }
}
