<?php

namespace App\Telegram\Commands;

use App\Models\ReportCurrency;
use Illuminate\Support\Facades\DB;
use App\Services\Telegram\TelegramManagerService;
use Telegram\Bot\Commands\Command;

abstract class AbstractCurrencyStatsCommand extends Command
{
    protected string $pattern = '{mode} {window}';

    abstract protected function currencyCode(): string;

    public function handle()
    {
        $chatId = intval($this->getUpdate()->getMessage()->chat->id ?? 0);
        if ($chatId <= 0) {
            return null;
        }

        $fromId = intval($this->getUpdate()->getMessage()->from->id ?? 0);
        if (!app(TelegramManagerService::class)->isManager($fromId)) {
            return null;
        }

        $currencyCode = strtoupper($this->currencyCode());
        $currency = collect(config('default.currency'))->firstWhere('short_name', $currencyCode);

        if (empty($currency)) {
            return $this->replyWithMessage(['text' => '币种不存在']);
        }

        $mode = strtolower(trim((string) $this->argument('mode', '')));
        if ($mode === '-c') {
            $response = null;
            $window = strtolower(trim((string) $this->argument('window', '')));
            foreach ($this->buildChannelStatsMessages($currency, $window) as $message) {
                $response = $this->replyWithMessage([
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);
            }

            return $response;
        }

        if ($mode !== '') {
            return $this->replyWithMessage([
                'text' => "指令格式错误\n示例：/" . strtolower($this->currencyCode()) . "\n示例：/" . strtolower($this->currencyCode()) . " -c\n示例：/" . strtolower($this->currencyCode()) . " -c 10m",
            ]);
        }

        $currencyId = intval($currency['id'] ?? 0);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $yesterdayReport = ReportCurrency::where('cid', $currencyId)->where('date_add', $yesterday)->first();

        $todayDepositStats = DB::table('deposit_orders')
            ->where('currency_id', $currencyId)
            ->where('created_at', '>=', $today . ' 00:00:00')
            ->where('created_at', '<=', $today . ' 23:59:59')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 5 THEN actual_amount ELSE 0 END), 0) as success_amount')
            ->first();

        $todayTransferStats = DB::table('transfer_orders')
            ->where('currency_id', $currencyId)
            ->where('created_at', '>=', $today . ' 00:00:00')
            ->where('created_at', '<=', $today . ' 23:59:59')
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 4 THEN actual_amount ELSE 0 END), 0) as success_amount')
            ->first();

        $todayDepositAmount = floatval($todayDepositStats->success_amount ?? 0);
        $yesterdayDepositAmount = floatval($yesterdayReport->deposit_order_total_amount ?? 0);
        $todayDepositTotal = intval($todayDepositStats->total_count ?? 0);
        $todayDepositSuccess = intval($todayDepositStats->success_count ?? 0);
        $todayDepositRate = $todayDepositTotal > 0 ? round($todayDepositSuccess * 100 / $todayDepositTotal, 2) : 0;

        $todayTransferAmount = floatval($todayTransferStats->success_amount ?? 0);
        $yesterdayTransferAmount = floatval($yesterdayReport->transfer_order_total_amount ?? 0);
        $todayTransferTotal = intval($todayTransferStats->total_count ?? 0);
        $todayTransferSuccess = intval($todayTransferStats->success_count ?? 0);
        $todayTransferRate = $todayTransferTotal > 0 ? round($todayTransferSuccess * 100 / $todayTransferTotal, 2) : 0;

        $text = "币种：{$currency['name']}\n";
        $text .= "今日代收跑量：{$todayDepositAmount}\n";
        $text .= "昨日代收跑量：{$yesterdayDepositAmount}\n";
        $text .= "今日代收总单：{$todayDepositTotal}\n";
        $text .= "今日代收成率：{$todayDepositRate}%\n";
        $text .= "今日代付跑量：{$todayTransferAmount}\n";
        $text .= "昨日代付跑量：{$yesterdayTransferAmount}\n";
        $text .= "今日代付总单：{$todayTransferTotal}\n";
        $text .= "今日代付成率：{$todayTransferRate}%";

        return $this->replyWithMessage([
            'text' => $text,
        ]);
    }

    private function buildChannelStatsMessages(array $currency, string $window = ''): array
    {
        $currencyId = intval($currency['id'] ?? 0);
        [$startTime, $endTime, $label] = $this->resolveChannelWindow($window);

        if ($startTime === null || $endTime === null) {
            return ["指令格式错误\n示例：/" . strtolower($this->currencyCode()) . " -c\n示例：/" . strtolower($this->currencyCode()) . " -c 10m\n支持时间：10m、20m、30m、1h"];
        }

        $rows = DB::table('deposit_orders as d')
            ->leftJoin('channels as c', 'c.id', '=', 'd.channel_id')
            ->where('d.currency_id', $currencyId)
            ->where('d.created_at', '>=', $startTime)
            ->where('d.created_at', '<=', $endTime)
            ->groupBy('d.channel_id', 'c.code', 'c.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('d.channel_id')
            ->selectRaw('d.channel_id')
            ->selectRaw('c.code as channel_code')
            ->selectRaw('c.name as channel_name')
            ->selectRaw('COUNT(*) as today_total')
            ->selectRaw('SUM(CASE WHEN d.status = 5 THEN 1 ELSE 0 END) as today_success')
            ->get();

        if ($rows->isEmpty()) {
            return ["币种：{$currency['name']}\n{$label}暂无渠道代收数据"];
        }

        $messages = [];
        $current = "<pre>\n";
        $current .= $this->buildChannelHeader($label . ' ' . ($currency['name'] ?? '') . ' 统计') . "\n";
        $current .= $this->formatChannelTableRow('名称', '总单数', '成功单数', '成功率') . "\n";
        $current .= $this->buildChannelDivider() . "\n";
        foreach ($rows as $row) {
            $todayTotal = intval($row->today_total ?? 0);
            $todaySuccess = intval($row->today_success ?? 0);

            $todayRate = $todayTotal > 0 ? round($todaySuccess * 100 / $todayTotal, 2) : 0;

            $channelLabel = $this->formatChannelLabel($row);
            $line = $this->formatChannelTableRow(
                $channelLabel,
                number_format($todayTotal),
                number_format($todaySuccess),
                $todayRate . '%'
            ) . "\n";

            if (mb_strlen($current . $line . "</pre>") > 3500) {
                $messages[] = rtrim($current) . "\n</pre>";
                $current = "<pre>\n";
                $current .= $this->buildChannelHeader($label . ' ' . ($currency['name'] ?? '') . ' 统计（续）') . "\n";
                $current .= $this->formatChannelTableRow('名称', '总单数', '成功单数', '成功率') . "\n";
                $current .= $this->buildChannelDivider() . "\n";
            }

            $current .= $line;
        }

        if (trim($current) !== '<pre>') {
            $messages[] = rtrim($current) . "\n</pre>";
        }

        return $messages;
    }

    private function formatChannelLabel(object $row): string
    {
        $channelId = intval($row->channel_id ?? 0);
        $channelCode = trim((string) ($row->channel_code ?? ''));
        $channelName = trim((string) ($row->channel_name ?? ''));

        if ($channelCode === '' && $channelName === '') {
            return "【#{$channelId}】未匹配到渠道";
        }

        return "【#{$channelId}】【{$channelCode}】{$channelName}";
    }

    private function buildChannelHeader(string $title): string
    {
        return '======== ' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ========';
    }

    private function formatChannelTableRow(
        string $name,
        string $todayTotal,
        string $todaySuccess,
        string $todayRate
    ): string {
        return $this->padDisplay($name, 28, STR_PAD_RIGHT, false)
            . ' '
            . $this->padDisplay($todayTotal, 9, STR_PAD_LEFT)
            . ' '
            . $this->padDisplay($todaySuccess, 9, STR_PAD_LEFT)
            . ' '
            . $this->padDisplay($todayRate, 8, STR_PAD_LEFT);
    }

    private function buildChannelDivider(): string
    {
        return str_repeat('-', 58);
    }

    private function resolveChannelWindow(string $window): array
    {
        $now = now();

        return match ($window) {
            '', 'day' => [$now->copy()->startOfDay()->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '今日'],
            '10m' => [$now->copy()->subMinutes(10)->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '近10m'],
            '20m' => [$now->copy()->subMinutes(20)->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '近20m'],
            '30m' => [$now->copy()->subMinutes(30)->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '近30m'],
            '1h' => [$now->copy()->subHour()->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '近1h'],
            default => [null, null, null],
        };
    }

    private function padDisplay(string $value, int $width, int $padType = STR_PAD_RIGHT, bool $truncate = true): string
    {
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($truncate && mb_strwidth($value, 'UTF-8') > $width) {
            $value = $this->trimDisplayWidth($value, $width - 1) . '…';
        }

        $padding = max(0, $width - mb_strwidth($value, 'UTF-8'));

        return match ($padType) {
            STR_PAD_LEFT => str_repeat(' ', $padding) . $value,
            STR_PAD_BOTH => str_repeat(' ', intdiv($padding, 2)) . $value . str_repeat(' ', $padding - intdiv($padding, 2)),
            default => $value . str_repeat(' ', $padding),
        };
    }

    private function trimDisplayWidth(string $value, int $width): string
    {
        $result = '';
        $currentWidth = 0;
        $length = mb_strlen($value, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($value, $i, 1, 'UTF-8');
            $charWidth = mb_strwidth($char, 'UTF-8');

            if ($currentWidth + $charWidth > $width) {
                break;
            }

            $result .= $char;
            $currentWidth += $charWidth;
        }

        return $result;
    }
}
