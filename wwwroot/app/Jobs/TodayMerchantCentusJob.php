<?php

namespace App\Jobs;

use App\Models\MerchantInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Telegram\TelegramInstanceService;

class TodayMerchantCentusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 1000;

    public int $mid;

    public $message;

    public function __construct($mid = 0, $message = [])
    {
        $this->mid = intval($mid);
        $this->message = $message;
    }

    public function handle(): void
    {
        $merchant = $this->merchantInfo();
        $deposit = $this->depositOrder();
        $transfer = $this->transferOrder();
        $text = "**商户号: " . $merchant['name'] . "\n";
        $text .= "**币种: " . $merchant['currency'] . "\n\n";

        $text .= "**可用余额: " . $merchant['available_balance'] . "\n";
        $text .= "**冻结余额: " . $merchant['frozen_balance'] . "\n\n";

        $text .= "**当日代收成功笔数: " . $deposit['deposit_success_count'] . "\n";
        $text .= "**当日代收成功金额: " . $deposit['deposit_success_amount'] . "\n";
        $text .= "**当日代收量: " . $deposit['deposit_total_count'] . "\n";
        $text .= "**当日代收成功率: " . $deposit['deposit_success_rate'] . "\n";
        $text .= "**近5分钟成功率: " . $deposit['deposit_rate_5m'] . "\n";
        $text .= "**近1小时成功率: " . $deposit['deposit_rate_1h'] . "\n\n";

        $text .= "**代付中金额: " . $transfer['transfer_processing_amount'] . "\n";
        $text .= "**当日代付成功笔数: " . $transfer['transfer_success_count'] . "\n";
        $text .= "**当日代付失败笔数: " . $transfer['transfer_fail_count'] . "\n";
        $text .= "**当日代付总笔数: " . $transfer['transfer_total_count'] . "\n";
        $text .= "**当日代付成功金额: " . $transfer['transfer_success_amount'] . "\n";
        $text .= "**当日总代付金额: " . $transfer['transfer_total_amount'] . "\n";
        $telegram = app(TelegramInstanceService::class)->excute();
        $telegram->sendMessage(['chat_id' => $this->message['chat']['id'], 'text' => $text, 'parse_mode' => 'html']);
    }


    private function merchantInfo()
    {
        $data = ["name" => "", 'currency' => "", "available_balance" => 0, "frozen_balance" => 0];
        $merchant = MerchantInfo::where('merchant_user_id', $this->mid)->first(['currency_id', 'balance_amount', 'freeze_amount', 'available_balance', "name"]);
        if ($merchant) {
            $data["name"] = $merchant->name;
            $data['currency'] = optional(collect(config('default.currency'))->firstWhere("id", $merchant->currency_id))->offsetGet('name');
            $data['available_balance'] = $merchant->available_balance;
            $data['frozen_balance'] = $merchant->freeze_amount;
        }
        return $data;
    }

    private function depositOrder()
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->addDay()->startOfDay();
        $minus5m = now()->subMinutes(5);
        $minus1h = now()->subHour();

        // 为了不全表扫，取最小起始时间
        $minStart = $todayStart->lessThan($minus1h) ? $todayStart : $minus1h;

        $sql = "
SELECT
  /* ===== 当日 ===== */
  SUM(CASE WHEN created_at >= ? AND created_at < ? AND status = 5 THEN 1 ELSE 0 END) AS today_success_count,
  COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? AND status = 5 THEN actual_amount ELSE 0 END), 0) AS today_success_amount,
  SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS today_total_count,

  /* ===== 近5分钟 ===== */
  SUM(CASE WHEN created_at >= ? AND status = 5 THEN 1 ELSE 0 END) AS m5_success_count,
  SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS m5_total_count,

  /* ===== 近1小时 ===== */
  SUM(CASE WHEN created_at >= ? AND status = 5 THEN 1 ELSE 0 END) AS h1_success_count,
  SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS h1_total_count

FROM deposit_orders
WHERE mid = ?
  AND created_at >= ?
  AND created_at < ?
";

        $row = DB::selectOne($sql, [
            // 当日成功笔数
            $todayStart, $todayEnd,
            // 当日成功金额
            $todayStart, $todayEnd,
            // 当日总笔数
            $todayStart, $todayEnd,

            // 近5分钟
            $minus5m,
            $minus5m,

            // 近1小时
            $minus1h,
            $minus1h,

            // where
            $this->mid,
            $minStart,
            $todayEnd,
        ]);

        // ===== 结果整理 =====
        $todaySuccessCount = (int)($row->today_success_count ?? 0);
        $todaySuccessAmount = number_format((float)($row->today_success_amount ?? 0), 2, '.', '');
        $todayTotalCount = (int)($row->today_total_count ?? 0);
        $todaySuccessRate = $todayTotalCount > 0 ? round($todaySuccessCount * 100 / $todayTotalCount, 2) : 0;

        $m5Total = (int)($row->m5_total_count ?? 0);
        $m5Success = (int)($row->m5_success_count ?? 0);
        $rate5m = $m5Total > 0 ? round($m5Success * 100 / $m5Total, 2) : 0;

        $h1Total = (int)($row->h1_total_count ?? 0);
        $h1Success = (int)($row->h1_success_count ?? 0);
        $rate1h = $h1Total > 0 ? round($h1Success * 100 / $h1Total, 2) : 0;

        return [
            'deposit_success_count' => $todaySuccessCount,
            'deposit_success_amount' => $todaySuccessAmount,
            'deposit_total_count' => $todayTotalCount,
            'deposit_success_rate' => $todaySuccessRate,
            'deposit_rate_5m' => $rate5m,
            'deposit_rate_1h' => $rate1h,
        ];
    }

    private function transferOrder()
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->addDay()->startOfDay();
        $transferToday = DB::table('transfer_orders')
            ->where('mid', $this->mid)
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->selectRaw("
        /* 代付中金额 */
        COALESCE(SUM(CASE WHEN status IN (1,2,3,6,7) THEN actual_amount ELSE 0 END), 0) AS processing_amount,

        /* 成功 / 失败 / 总笔数 */
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) AS success_count,
        SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) AS fail_count,
        COUNT(*) AS total_count,

        /* 成功金额 / 总金额 */
        COALESCE(SUM(CASE WHEN status = 4 THEN actual_amount ELSE 0 END), 0) AS success_amount,
        COALESCE(SUM(actual_amount), 0) AS total_amount
    ")
            ->first();

        return [
            'transfer_processing_amount' => $transferToday->processing_amount ?? 0,
            'transfer_success_count' => (int)($transferToday->success_count ?? 0),
            'transfer_fail_count' => (int)($transferToday->fail_count ?? 0),
            'transfer_total_count' => (int)($transferToday->total_count ?? 0),
            'transfer_success_amount' => $transferToday->success_amount ?? 0,
            'transfer_total_amount' => $transferToday->total_amount ?? 0,
        ];
    }
}
