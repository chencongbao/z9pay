<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Services\Report\ReportRunStateService;

class ReportStatusCommand extends Command
{
    protected $signature = 'report-status {batch_no?}';

    protected $description = '查看报表统计运行状态';

    public function handle(ReportRunStateService $service)
    {
        $status = $service->status($this->argument('batch_no'));
        if (empty($status)) {
            $this->info('暂无报表运行记录。');
            return 0;
        }

        $this->line('报表批次：' . $status['batch_no']);
        $this->line('当前状态：' . $this->statusText($status['status'] ?? 'unknown'));
        $this->line('统计日期：' . implode(',', $status['dates'] ?? []));
        $this->line('商户数量：' . ($status['merchant_count'] ?? 0));
        $this->line('开始时间：' . ($status['started_at'] ?? '-'));
        $this->line('完成时间：' . ($status['finished_at'] ?? '-'));
        $this->line('总任务数：' . ($status['total'] ?? 0));
        $this->line('已完成：' . ($status['done'] ?? 0));
        $this->line('失败数：' . ($status['failed'] ?? 0));
        $this->line('剩余数：' . ($status['remaining'] ?? 0));
        $this->line('完成进度：' . ($status['progress'] ?? 0) . '%');
        $this->showMerchantShardStatus($status);

        if (!empty($status['last_error'])) {
            $this->warn('最近错误：' . $status['last_error']);
        }

        return 0;
    }

    private function showMerchantShardStatus(array $status): void
    {
        $batchNo = (string)($status['batch_no'] ?? '');
        foreach ($status['dates'] ?? [] as $date) {
            $merchantIds = Cache::get($this->merchantIdsKey($batchNo, $date), []);
            if (empty($merchantIds)) {
                continue;
            }

            $pending = [];
            $running = [];
            $failed = [];
            foreach ($merchantIds as $mid) {
                $merchantStatus = Cache::get($this->merchantStatusKey($batchNo, $date, (int)$mid), []);
                $shardStatus = $merchantStatus['status'] ?? 'pending';
                if ($shardStatus === 'done') {
                    continue;
                }
                if ($shardStatus === 'failed') {
                    $failed[] = $mid;
                    continue;
                }
                if ($shardStatus === 'running') {
                    $running[] = $mid;
                    continue;
                }
                $pending[] = $mid;
            }

            $finalizeStatus = Cache::get($this->finalizeStatusKey($batchNo, $date), []);
            if (!empty($running)) {
                $this->line("运行中的商户分片({$date})：" . implode(',', $running));
            }
            if (!empty($pending)) {
                $this->line("等待中的商户分片({$date})：" . implode(',', $pending));
            }
            if (!empty($failed)) {
                $this->warn("失败的商户分片({$date})：" . implode(',', $failed));
            }
            if (!empty($finalizeStatus)) {
                $this->line("汇总任务({$date})：" . $this->shardStatusText((string)($finalizeStatus['status'] ?? 'unknown')) . '，更新时间：' . ($finalizeStatus['updated_at'] ?? '-'));
            }
        }
    }

    private function statusText(string $status): string
    {
        return [
            'running' => '运行中',
            'finished' => '已完成',
            'finished_with_failed' => '已完成，但有失败任务',
            'stopped' => '已停止',
            'reset' => '已重置',
        ][$status] ?? $status;
    }

    private function shardStatusText(string $status): string
    {
        return [
            'pending' => '等待中',
            'running' => '运行中',
            'waiting' => '等待商户分片完成',
            'done' => '已完成',
            'failed' => '失败',
        ][$status] ?? $status;
    }

    private function merchantIdsKey(string $batchNo, string $date): string
    {
        return "report:batch:{$batchNo}:{$date}:merchant_ids";
    }

    private function merchantStatusKey(string $batchNo, string $date, int $mid): string
    {
        return "report:batch:{$batchNo}:{$date}:merchant:{$mid}:status";
    }

    private function finalizeStatusKey(string $batchNo, string $date): string
    {
        return "report:batch:{$batchNo}:{$date}:finalize_status";
    }
}
