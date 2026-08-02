<?php

namespace App\Jobs;

use App\Models\DepositOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Telegram\TelegramInstanceService;

class QueryMerchantSuccessInfoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int|string $chatId;

    protected string $window;

    protected int $mid;

    public function __construct($chatId, string $window, $mid)
    {
        $this->chatId = $chatId;
        $this->window = $window;
        $this->mid = intval($mid);
    }

    public function handle(): void
    {
        $s = $this->statsForWindow($this->window);

        $text = "📊 {$s['label']}\n"
            . "📦 总订单数：{$s['total']}\n"
            . "✅ 成功订单：{$s['success']}/{$s['total']} ({$s['rate']}%)\n"
            . "💰 成功金额：" . number_format($s['successAmount'], 2);
        $telegram = app(TelegramInstanceService::class)->excute();
        $telegram->sendMessage(['chat_id' => $this->chatId, 'text' => $text]);
    }


    private function stats(Carbon $from, Carbon $to): array
    {

        $stats = DepositOrder::selectRaw('
        COUNT(*) as total,
        SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 5 THEN actual_amount ELSE 0 END) as success_amount
    ')
            ->where('mid', $this->mid)
            ->whereBetween('created_at', [$from, $to])
            ->first();

        $total = floatval($stats->total);
        $success = floatval($stats->success_count);
        $successAmount = floatval($stats->success_amount);

        $rate = $total > 0 ? round($success / $total * 100, 2) : 0;

        return compact('from', 'to', 'total', 'success', 'successAmount', 'rate');
    }

    private function statsForWindow(string $window): array
    {
        $now = Carbon::now();

        $delayMinutes = max(0, intval(config('default.success_rate_query_delay_minutes', 0)));
        $delayedTo = $now->copy()->subMinutes($delayMinutes);
        switch ($window) {
            case '10':
                $to = $delayedTo;
                $from = $to->copy()->subMinutes(10);
                $label = '最近 10 分钟';
                break;
            case '20':
                $to = $delayedTo;
                $from = $to->copy()->subMinutes(20);
                $label = '最近 20 分钟';
                break;
            case '30':
                $to = $delayedTo;
                $from = $to->copy()->subMinutes(30);
                $label = '最近 30 分钟';
                break;
            case '60':
                $to = $delayedTo;
                $from = $to->copy()->subMinutes(60);
                $label = '最近 60 分钟';
                break;
            case 'day':
            default:
                $to = $now;
                $from = $now->copy()->startOfDay();
                $label = '今天（0 点 ~ 现在）';
                $window = 'day';
                break;
        }

        $data = $this->stats($from, $to);
        $data['label'] = $label;
        $data['window'] = $window;

        return $data;
    }
}
