<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Channel;
use Illuminate\Console\Command;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Channel\QueryChannelBalanceService;

class ChannelBalanceQueryCommand extends Command
{
    protected $signature = 'channel:balance-query {--channel-id= : 只查询指定渠道ID}';

    protected $description = '查询渠道余额，并更新';

    public function handle(): int
    {
        $channelId = $this->positiveIntegerOption('channel-id');
        if ($channelId === false) {
            return self::FAILURE;
        }

        $total = 0;
        $success = 0;
        $skipped = 0;
        $failed = 0;
        $service = app(QueryChannelBalanceService::class);

        Channel::query()
            ->select(['id', 'name', 'classname'])
            ->where('status', 1)
            ->when($channelId !== null, function ($query) use ($channelId) {
                $query->whereKey($channelId);
            })
            ->chunkById(100, function ($channels) use ($service, &$total, &$success, &$skipped, &$failed) {
                foreach ($channels as $channel) {
                    $total++;

                    if (!$service->supportsBalanceQuery($channel)) {
                        $skipped++;
                        continue;
                    }

                    try {
                        $service->execute($channel, true);
                        $success++;
                    } catch (Throwable $e) {
                        if ($service->isUnsupportedBalanceQueryException($e) || $service->isBalanceQueryCooldownException($e)) {
                            $skipped++;
                            continue;
                        }

                        $failed++;
                        $this->warn("渠道余额查询失败：#{$channel->id} {$channel->name}，{$e->getMessage()}");

                        app(SystemNoticeService::class)->warning('system_manual_notice', [
                            'message' => $e->getMessage(),
                            'line' => $e->getLine(),
                            'file' => $e->getFile(),
                            'action' => '定时查询渠道余额失败',
                            'channel_id' => $channel->id,
                            'channel_name' => $channel->name,
                        ]);
                    }
                }
            });

        $this->info("渠道余额查询完成，总数：{$total}，成功：{$success}，跳过：{$skipped}，失败：{$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function positiveIntegerOption(string $name): int|false|null
    {
        $value = $this->option($name);
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '' || !ctype_digit($value) || (int)$value <= 0) {
            $this->error('--' . $name . ' 必须是正整数。');
            return false;
        }

        return (int)$value;
    }
}
