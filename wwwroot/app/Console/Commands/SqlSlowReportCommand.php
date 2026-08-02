<?php

namespace App\Console\Commands;

use SplFileObject;
use Illuminate\Console\Command;

class SqlSlowReportCommand extends Command
{
    protected $signature = 'sql:slow-report
                            {--date= : 日志日期，默认今天，例如 2026-07-22}
                            {--file= : 指定 query.log 文件路径}
                            {--limit=20 : 输出 Top 数量}
                            {--min-ms=0 : 只统计超过指定毫秒的 SQL}
                            {--route= : 只统计指定路由名、route_uri 或 url，支持 * 通配}
                            {--console= : 只统计指定 console 命令，支持 * 通配}
                            {--table= : 只统计涉及指定表名的 SQL}
                            {--source= : 只统计指定调用来源，支持 * 通配}';

    protected $description = '分析本地 SQL query.log，按 SQL 指纹聚合慢 SQL';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing', 'staging'])) {
            $this->error('该命令仅允许在 local/testing/staging 环境执行。');

            return self::FAILURE;
        }

        $logPath = $this->resolveLogPath();
        if (!is_file($logPath) || !is_readable($logPath)) {
            $this->error("日志文件不存在或不可读：{$logPath}");

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $minMs = max(0, (float) $this->option('min-ms'));
        $stats = $this->collectStats($logPath, $minMs);

        if (empty($stats)) {
            $this->warn('没有匹配到 SQL 日志。');

            return self::SUCCESS;
        }

        usort($stats, function ($left, $right) {
            return $right['total_ms'] <=> $left['total_ms']
                ?: $right['max_ms'] <=> $left['max_ms']
                ?: $right['count'] <=> $left['count'];
        });

        $this->info("SQL 慢日志统计：{$logPath}");
        $this->table(
            ['#', '次数', '总耗时ms', '最大ms', '平均ms', '表', '来源/上下文', 'SQL 指纹'],
            $this->formatRows(array_slice($stats, 0, $limit))
        );

        return self::SUCCESS;
    }

    private function resolveLogPath(): string
    {
        if ($this->option('file')) {
            return (string) $this->option('file');
        }

        $date = $this->option('date') ?: date('Y-m-d');

        return storage_path('logs' . DIRECTORY_SEPARATOR . $date . DIRECTORY_SEPARATOR . 'query.log');
    }

    private function collectStats(string $logPath, float $minMs): array
    {
        $stats = [];
        $block = [];
        $file = new SplFileObject($logPath);

        while (!$file->eof()) {
            $line = rtrim((string) $file->fgets(), "\r\n");

            if ($line === str_repeat('-', 100)) {
                $this->recordBlock($stats, $block, $minMs);
                $block = [];
                continue;
            }

            if ($line !== '') {
                $block[] = $line;
            }
        }

        $this->recordBlock($stats, $block, $minMs);

        return array_values($stats);
    }

    private function recordBlock(array &$stats, array $block, float $minMs): void
    {
        $entry = $this->parseBlock($block);
        if (!$entry || $entry['duration_ms'] < $minMs || !$this->matchesFilters($entry)) {
            return;
        }

        $fingerprint = $this->fingerprintSql($entry['sql']);
        if (!isset($stats[$fingerprint])) {
            $stats[$fingerprint] = [
                'count' => 0,
                'total_ms' => 0.0,
                'max_ms' => 0.0,
                'tables' => [],
                'contexts' => [],
                'fingerprint' => $fingerprint,
            ];
        }

        $stats[$fingerprint]['count']++;
        $stats[$fingerprint]['total_ms'] += $entry['duration_ms'];
        $stats[$fingerprint]['max_ms'] = max($stats[$fingerprint]['max_ms'], $entry['duration_ms']);

        foreach ($this->extractTables($entry['sql']) as $table) {
            $stats[$fingerprint]['tables'][$table] = true;
        }

        $context = $entry['source'] ?: ($entry['route_uri'] ?: $entry['console']);
        if ($context) {
            $stats[$fingerprint]['contexts'][$context] = true;
        }
    }

    private function parseBlock(array $block): ?array
    {
        if (empty($block)) {
            return null;
        }

        $entry = [
            'duration_ms' => 0.0,
            'route' => '',
            'route_uri' => '',
            'url' => '',
            'console' => '',
            'source' => '',
            'sql' => '',
        ];
        $readingSql = false;
        $sqlLines = [];

        foreach ($block as $line) {
            if ($readingSql) {
                $sqlLines[] = trim($line);
                continue;
            }

            if (preg_match('/^\s*duration\s+:\s*([0-9.]+)ms/i', $line, $matches)) {
                $entry['duration_ms'] = (float) $matches[1];
                continue;
            }

            if (preg_match('/^\s*(route|route_uri|url|console|source)\s+:\s*(.*)$/i', $line, $matches)) {
                $entry[strtolower($matches[1])] = trim($matches[2]);
                continue;
            }

            if (preg_match('/^\s*sql\s+:/i', $line)) {
                $readingSql = true;
            }
        }

        $entry['sql'] = trim(implode(' ', $sqlLines));

        return $entry['duration_ms'] > 0 && $entry['sql'] !== '' ? $entry : null;
    }

    private function matchesFilters(array $entry): bool
    {
        $route = (string) $this->option('route');
        if ($route !== '' && !$this->matchesAny([$entry['route'], $entry['route_uri'], $entry['url']], $route)) {
            return false;
        }

        $console = (string) $this->option('console');
        if ($console !== '' && !$this->matchesPattern($entry['console'], $console)) {
            return false;
        }

        $source = (string) $this->option('source');
        if ($source !== '' && !$this->matchesPattern($entry['source'], $source)) {
            return false;
        }

        $table = (string) $this->option('table');
        if ($table !== '' && !in_array(strtolower($table), $this->extractTables($entry['sql']), true)) {
            return false;
        }

        return true;
    }

    private function matchesAny(array $values, string $pattern): bool
    {
        foreach ($values as $value) {
            if ($this->matchesPattern($value, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $value, string $pattern): bool
    {
        if ($value === $pattern) {
            return true;
        }

        if (strpos($pattern, '*') === false) {
            return false;
        }

        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';

        return preg_match($regex, $value) === 1;
    }

    private function fingerprintSql(string $sql): string
    {
        $sql = strtolower(preg_replace('/\s+/', ' ', trim($sql)));
        $sql = preg_replace("/'([^'\\\\]|\\\\.)*'/", '?', $sql);
        $sql = preg_replace('/\b\d+(\.\d+)?\b/', '?', $sql);
        $sql = preg_replace('/\bin\s*\((\s*\?\s*,?)+\)/i', 'in (?)', $sql);

        return trim($sql);
    }

    private function extractTables(string $sql): array
    {
        preg_match_all('/\b(?:from|join|update|into)\s+((?:[`"]?[a-zA-Z0-9_]+[`"]?\.)?[`"]?[a-zA-Z0-9_]+[`"]?)/i', $sql, $matches);
        $tables = [];

        foreach ($matches[1] ?? [] as $table) {
            $table = str_replace(['`', '"'], '', $table);
            $parts = explode('.', $table);
            $tables[] = strtolower(end($parts));
        }

        return array_values(array_unique($tables));
    }

    private function formatRows(array $stats): array
    {
        $rows = [];

        foreach ($stats as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['count'],
                round($item['total_ms'], 2),
                round($item['max_ms'], 2),
                round($item['total_ms'] / $item['count'], 2),
                $this->shorten(implode(',', array_keys($item['tables'])) ?: '-'),
                $this->shorten(implode(' | ', array_slice(array_keys($item['contexts']), 0, 2)) ?: '-'),
                $this->shorten($item['fingerprint'], 120),
            ];
        }

        return $rows;
    }

    private function shorten(string $value, int $length = 60): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 3) . '...' : $value;
    }
}
