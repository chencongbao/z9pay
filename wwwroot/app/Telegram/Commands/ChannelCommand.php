<?php

namespace App\Telegram\Commands;

use App\Models\Channel;
use Illuminate\Support\Facades\DB;
use App\Services\Telegram\TelegramManagerService;
use Telegram\Bot\Commands\Command;

class ChannelCommand extends Command
{
    protected string $name = 'channel';

    protected string $description = '查询渠道币种统计';

    protected string $pattern = '{currency} {channel_id} {window}';

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

        $currencyCode = strtoupper(trim((string) $this->argument('currency', '')));
        $channelId = intval($this->argument('channel_id', 0));
        $window = strtolower(trim((string) $this->argument('window', '')));

        if ($currencyCode === '' || $channelId <= 0 || $window === '') {
            return $this->replyWithMessage([
                'text' => "指令格式错误\n示例：/channel cny 12 day\n示例：/channel cny 12 10m",
            ]);
        }

        $currency = collect(config('default.currency'))->firstWhere('short_name', $currencyCode);
        if (empty($currency)) {
            return $this->replyWithMessage(['text' => '币种不存在']);
        }

        $channel = Channel::where('id', $channelId)->first(['id', 'name']);
        if (!$channel) {
            return $this->replyWithMessage(['text' => '渠道不存在']);
        }

        [$startTime, $endTime, $label] = $this->resolveWindow($window);
        if ($startTime === null || $endTime === null) {
            return $this->replyWithMessage([
                'text' => "时间范围不支持，仅支持：10m、20m、30m、1h、day",
            ]);
        }

        $currencyId = intval($currency['id'] ?? 0);

        $depositStats = DB::table('deposit_orders')
            ->where('channel_id', $channelId)
            ->where('currency_id', $currencyId)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 5 THEN actual_amount ELSE 0 END), 0) as success_amount')
            ->first();

        $transferStats = DB::table('transfer_orders')
            ->where('channel_id', $channelId)
            ->where('currency_id', $currencyId)
            ->where('created_at', '>=', $startTime)
            ->where('created_at', '<=', $endTime)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = 4 THEN actual_amount ELSE 0 END), 0) as success_amount')
            ->first();

        $depositTotal = intval($depositStats->total_count ?? 0);
        $depositSuccess = intval($depositStats->success_count ?? 0);
        $depositAmount = floatval($depositStats->success_amount ?? 0);
        $depositRate = $depositTotal > 0 ? round($depositSuccess * 100 / $depositTotal, 2) : 0;

        $transferTotal = intval($transferStats->total_count ?? 0);
        $transferSuccess = intval($transferStats->success_count ?? 0);
        $transferAmount = floatval($transferStats->success_amount ?? 0);
        $transferRate = $transferTotal > 0 ? round($transferSuccess * 100 / $transferTotal, 2) : 0;

        $text = "币种：{$currency['name']}\n";
        $text .= "渠道：{$channel->name}（{$channel->id}）\n";
        $text .= "时间范围：{$label}\n";
        $text .= "代收跑量：{$depositAmount}\n";
        $text .= "代收总单：{$depositTotal}\n";
        $text .= "代收成率：{$depositRate}%\n";
        $text .= "代付跑量：{$transferAmount}\n";
        $text .= "代付总单：{$transferTotal}\n";
        $text .= "代付成率：{$transferRate}%";

        return $this->replyWithMessage([
            'text' => $text,
        ]);
    }

    private function resolveWindow(string $window): array
    {
        $now = now();

        return match ($window) {
            '10m' => [$now->copy()->subMinutes(10)->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '最近10分钟'],
            '20m' => [$now->copy()->subMinutes(20)->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '最近20分钟'],
            '30m' => [$now->copy()->subMinutes(30)->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '最近30分钟'],
            '1h' => [$now->copy()->subHour()->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '最近1小时'],
            'day' => [$now->copy()->startOfDay()->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'), '今天0点到现在'],
            default => [null, null, null],
        };
    }

}
