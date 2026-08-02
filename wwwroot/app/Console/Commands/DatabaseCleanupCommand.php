<?php

namespace App\Console\Commands;

use Throwable;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use App\Services\Common\ReportExceptionService;

class DatabaseCleanupCommand extends Command
{
    protected $signature = 'database:cleanup
                            {--dry-run : 只统计待删除数量，不真正删除}
                            {--table= : 只清理指定表}
                            {--force : 跳过生产环境手动确认}
                            {--months= : 临时覆盖数据保留月份}
                            {--batch= : 临时覆盖单批删除数量}
                            {--max-batches= : 临时覆盖单次最大执行批次数}
                            {--sleep-ms= : 临时覆盖每批删除后的休眠毫秒数}
                            {--max-runtime= : 临时覆盖最大运行秒数}';

    protected $description = '统一清理数据库历史数据';

    public function handle(): int
    {
        if (!config('database-cleanup.enabled', true)) {
            $this->info('数据库清理未启用。');

            return self::SUCCESS;
        }

        $options = $this->resolveNumericOptions();
        if ($options === null) {
            return self::FAILURE;
        }

        $tables = config('database-cleanup.tables', []);
        if (empty($tables) || !is_array($tables)) {
            $this->warn('没有配置需要清理的表。');

            return self::SUCCESS;
        }

        $onlyTable = $this->option('table');
        $dryRun = (bool) $this->option('dry-run');
        $defaultMonths = $options['months'];
        $batch = $options['batch'];
        $maxBatches = $options['max_batches'];
        $sleepMs = $options['sleep_ms'];
        $maxRuntime = $options['max_runtime'];
        $startedAt = microtime(true);

        if (!$this->confirmProductionRun($dryRun)) {
            $this->warn('已取消数据库清理。');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '开始数据库清理：默认保留 %d 个月，单批 %d 条，最多 %d 批，最大运行 %d 秒，dry-run=%s',
            $defaultMonths,
            $batch,
            $maxBatches,
            $maxRuntime,
            $dryRun ? 'true' : 'false'
        ));

        $matched = false;
        $totalDeleted = 0;

        foreach ($tables as $table => $rule) {
            if ($onlyTable && $onlyTable !== $table) {
                continue;
            }

            $matched = true;

            if (!$this->validIdentifier($table)) {
                $this->warn("跳过 {$table}：表名不合法。");
                continue;
            }

            if (!is_array($rule) || !($rule['enabled'] ?? true)) {
                $this->line("跳过 {$table}：未启用。");
                continue;
            }

            if (!Schema::hasTable($table)) {
                $this->warn("跳过 {$table}：表不存在。");
                continue;
            }

            if ($this->runtimeExceeded($startedAt, $maxRuntime)) {
                $this->warn('达到本次最大运行时间，停止后续表清理。');
                break;
            }

            try {
                $deleted = $this->cleanupTable($table, $rule, $defaultMonths, $batch, $maxBatches, $sleepMs, $dryRun, $startedAt, $maxRuntime);
                $totalDeleted += $deleted;
            } catch (Throwable $e) {
                $this->error("清理 {$table} 异常：" . $e->getMessage());
                App::make(ReportExceptionService::class)->report('数据库历史数据清理异常', $e, [
                    'table' => $table,
                    'dry_run' => $dryRun,
                ]);
            }
        }

        if ($onlyTable && !$matched) {
            $this->warn("没有找到表配置：{$onlyTable}");

            return self::FAILURE;
        }

        $this->info("数据库清理结束，累计删除：{$totalDeleted}");

        return self::SUCCESS;
    }

    protected function cleanupTable(
        string $table,
        array $rule,
        int $defaultMonths,
        int $batch,
        int $maxBatches,
        int $sleepMs,
        bool $dryRun,
        float $startedAt,
        int $maxRuntime
    ): int {
        $dateColumn = (string) ($rule['date_column'] ?? 'created_at');
        $keyColumn = (string) ($rule['key_column'] ?? 'id');
        $strategy = (string) ($rule['strategy'] ?? 'primary_key_window');
        $windowSize = max($batch, (int) ($rule['window_size'] ?? 50000));
        $months = max(1, (int) ($rule['retention_months'] ?? $defaultMonths));
        $dateType = (string) ($rule['date_type'] ?? 'datetime');
        $cutoff = Carbon::now()->subMonths($months)->startOfDay();
        $cutoffValue = $dateType === 'timestamp' ? $cutoff->timestamp : $cutoff->toDateTimeString();

        if ($strategy !== 'primary_key_window') {
            $this->warn("跳过 {$table}：不支持的清理策略 {$strategy}。");

            return 0;
        }

        foreach ([$dateColumn, $keyColumn] as $column) {
            if (!$this->validIdentifier($column)) {
                $this->warn("跳过 {$table}：字段 {$column} 不合法。");

                return 0;
            }

            if (!Schema::hasColumn($table, $column)) {
                $this->warn("跳过 {$table}：字段 {$column} 不存在。");

                return 0;
            }
        }

        $this->line(sprintf(
            '清理 %s：保留最近 %d 个月，删除阈值 %s，按 %s 主键窗口扫描',
            $table,
            $months,
            $cutoff->toDateTimeString(),
            $keyColumn
        ));

        $stateTableExists = Schema::hasTable('database_cleanup_states');
        if (!$stateTableExists && !$dryRun) {
            $this->warn('跳过清理：database_cleanup_states 表不存在，请先执行迁移。');

            return 0;
        }

        $totalDeleted = 0;
        $state = $stateTableExists
            ? DB::table('database_cleanup_states')->where('table_name', $table)->first()
            : null;
        $lastScanId = (int) ($state->last_scan_id ?? 0);
        $maxId = (int) DB::table($table)->max($keyColumn);

        if ($stateTableExists && !$state && !$dryRun) {
            DB::table('database_cleanup_states')->updateOrInsert(['table_name' => $table], [
                'table_name' => $table,
                'last_scan_id' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($maxId <= 0) {
            $this->line("{$table} 没有数据。");

            return 0;
        }

        if ($lastScanId >= $maxId) {
            $lastScanId = 0;
        }

        $windowStart = $lastScanId + 1;
        $windowEnd = min($lastScanId + $windowSize, $maxId);

        if ($dryRun) {
            $count = DB::table($table)
                ->whereBetween($keyColumn, [$windowStart, $windowEnd])
                ->where($dateColumn, '<', $cutoffValue)
                ->count();
            $this->info("dry-run：{$table} 当前扫描窗口 ID {$windowStart}-{$windowEnd}，待删除 {$count} 条。");

            return 0;
        }

        for ($i = 1; $i <= $maxBatches; $i++) {
            if ($this->runtimeExceeded($startedAt, $maxRuntime)) {
                $this->warn("{$table} 达到本次最大运行时间，停止当前表清理。");
                break;
            }

            if ($lastScanId >= $maxId) {
                $lastScanId = 0;
                $this->line("{$table} 已扫描到当前最大 ID，下一批从头开始新一轮扫描。");
            }

            $windowStart = $lastScanId + 1;
            $windowEnd = min($lastScanId + $windowSize, $maxId);

            $keys = DB::table($table)
                ->whereBetween($keyColumn, [$windowStart, $windowEnd])
                ->where($dateColumn, '<', $cutoffValue)
                ->orderBy($keyColumn)
                ->limit($batch)
                ->pluck($keyColumn)
                ->all();

            $deleted = 0;
            $keyCount = count($keys);
            if (!empty($keys)) {
                $deleted = DB::transaction(function () use ($table, $keyColumn, $keys) {
                    return DB::table($table)
                        ->whereIn($keyColumn, $keys)
                        ->delete();
                });
            }

            $totalDeleted += $deleted;
            if ($keyCount < $batch) {
                $lastScanId = $windowEnd;
            }
            $this->saveLastScanId($table, $lastScanId);
            $this->info("{$table} 第 {$i} 批扫描 ID {$windowStart}-{$windowEnd}，删除 {$deleted} 条，累计 {$totalDeleted} 条。");

            if ($lastScanId >= $maxId) {
                break;
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return $totalDeleted;
    }

    protected function confirmProductionRun(bool $dryRun): bool
    {
        if ($dryRun || !app()->isProduction() || $this->option('force') || !$this->input->isInteractive()) {
            return true;
        }

        return $this->confirm('生产环境将删除历史数据，确认继续？');
    }

    protected function saveLastScanId(string $table, int $lastScanId): void
    {
        DB::table('database_cleanup_states')->updateOrInsert(['table_name' => $table], [
            'last_scan_id' => $lastScanId,
            'updated_at' => now(),
        ]);
    }

    protected function runtimeExceeded(float $startedAt, int $maxRuntime): bool
    {
        return microtime(true) - $startedAt >= $maxRuntime;
    }

    protected function resolveNumericOptions(): ?array
    {
        $rules = [
            'months' => ['default' => (int) config('database-cleanup.retention_months', 3), 'min' => 1, 'max' => 120],
            'batch' => ['default' => (int) config('database-cleanup.batch', 2000), 'min' => 1, 'max' => 100000],
            'max-batches' => ['default' => (int) config('database-cleanup.max_batches', 20), 'min' => 1, 'max' => 1000],
            'sleep-ms' => ['default' => (int) config('database-cleanup.sleep_ms', 500), 'min' => 0, 'max' => 60000],
            'max-runtime' => ['default' => (int) config('database-cleanup.max_runtime_seconds', 300), 'min' => 1, 'max' => 86400],
        ];
        $result = [];

        foreach ($rules as $option => $rule) {
            $value = $this->integerOption($option, $rule['default'], $rule['min'], $rule['max']);
            if ($value === null) {
                return null;
            }

            $result[str_replace('-', '_', $option)] = $value;
        }

        return [
            'months' => $result['months'],
            'batch' => $result['batch'],
            'max_batches' => $result['max_batches'],
            'sleep_ms' => $result['sleep_ms'],
            'max_runtime' => $result['max_runtime'],
        ];
    }

    protected function integerOption(string $option, int $default, int $minimum, int $maximum): ?int
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            return min($maximum, max($minimum, $default));
        }

        $value = trim((string)$value);
        if (preg_match('/^\d+$/', $value) !== 1) {
            $this->error("--{$option} 必须是整数。");
            return null;
        }

        $number = (int)$value;
        if ($number < $minimum || $number > $maximum) {
            $this->error("--{$option} 必须在 {$minimum}-{$maximum} 之间。");
            return null;
        }

        return $number;
    }

    protected function validIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $value);
    }
}
