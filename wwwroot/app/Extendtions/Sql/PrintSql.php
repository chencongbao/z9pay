<?php
namespace App\Extendtions\Sql;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class PrintSql
{
    /**
     * SQL打印配置都放在这里，生产环境默认不会启用本类。
     */
    private $includeTables = [];
    private $excludeTables = [
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'websockets_statistics_entries',
    ];
    private $slowMs = 0;
    private $trace = true;
    private $traceStack = false;
    private $includeRoutes = [];
    private $includeConsoleCommands = ['api:v3-smoke-test'];

    function tosql()
    {
        // 打印sql
        DB::listen(function ($sql) {
            if ($this->shouldSkipSlowSql($sql->time)) {
                return;
            }

            if (!$this->shouldLogContext()) {
                return;
            }

            $query = $this->formatQuery($sql->sql, $sql->bindings);

            if (!$this->shouldLog($query)) {
                return;
            }

            $logData = [
                '[' . date('Y-m-d H:i:s') . '] SQL Query',
                '  duration   : ' . $sql->time . 'ms',
            ];

            if (app()->runningInConsole()) {
                $logData[] = '  console    : ' . implode(' ', $_SERVER['argv'] ?? []);
            } else {
                $route = Route::current();
                $logData[] = '  method     : ' . request()->method();
                $logData[] = '  route      : ' . ($route?->getName() ?: '-');
                $logData[] = '  route_uri  : ' . ($route?->uri() ?: request()->path());
                $logData[] = '  url        : ' . request()->fullUrl();
            }

            if ($source = $this->findSqlSource()) {
                $logData[] = '  source     : ' . $source;
            }

            $logData[] = '  sql        :';
            $logData[] = $this->indentSql($this->formatSqlForLog($query));
            $logData[] = str_repeat('-', 100);

            $this->writeLog($logData);
        });
    }

    private function writeLog(array $logData): void
    {
        $logDir = storage_path('logs' . DIRECTORY_SEPARATOR . date('Y-m-d'));
        $logPath = $logDir . DIRECTORY_SEPARATOR . 'query.log';
        $content = implode(PHP_EOL, $logData) . PHP_EOL;

        try {
            if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
                Log::warning('SQL query log directory is not writable', ['path' => $logDir]);
                return;
            }

            if (!is_writable($logDir) && (!file_exists($logPath) || !is_writable($logPath))) {
                Log::warning('SQL query log path is not writable', ['path' => $logPath]);
                return;
            }

            if (file_put_contents($logPath, $content, FILE_APPEND | LOCK_EX) === false) {
                Log::warning('SQL query log write failed', ['path' => $logPath]);
            }
        } catch (\Throwable $e) {
            Log::warning('SQL query log write exception', [
                'path' => $logPath,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function formatQuery($query, $bindings)
    {
        foreach ($bindings as $i => $binding) {
            if ($binding instanceof \DateTime) {
                $bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
            } elseif (is_string($binding)) {
                $bindings[$i] = "'" . str_replace("'", "\\'", $binding) . "'";
            } elseif ($binding === null) {
                $bindings[$i] = 'null';
            } elseif (is_bool($binding)) {
                $bindings[$i] = $binding ? 1 : 0;
            }
        }

        $query = str_replace([
            '%',
            '?',
        ], [
            '%%',
            '%s',
        ], $query);

        return vsprintf($query, $bindings);
    }

    private function shouldSkipSlowSql($time)
    {
        return $this->slowMs > 0 && $time < $this->slowMs;
    }

    private function shouldLogContext()
    {
        if (app()->runningInConsole()) {
            if (empty($this->includeConsoleCommands)) {
                return true;
            }

            $argv = $_SERVER['argv'] ?? [];
            $command = $argv[1] ?? '';
            $commandLine = implode(' ', $argv);

            return $this->matchesAnyPattern($command, $this->includeConsoleCommands)
                || $this->matchesAnyPattern($commandLine, $this->includeConsoleCommands);
        }

        if (empty($this->includeRoutes)) {
            return true;
        }

        $routeName = optional(Route::current())->getName() ?: '';
        $path = request()->path();
        $url = request()->fullUrl();

        return $this->matchesAnyPattern($routeName, $this->includeRoutes)
            || $this->matchesAnyPattern($path, $this->includeRoutes)
            || $this->matchesAnyPattern($url, $this->includeRoutes);
    }

    private function matchesAnyPattern($value, $patterns)
    {
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);

            if ($pattern === '') {
                continue;
            }

            if ($value === $pattern || $this->matchesWildcard($value, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesWildcard($value, $pattern)
    {
        if (strpos($pattern, '*') === false) {
            return false;
        }

        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';

        return preg_match($regex, $value) === 1;
    }

    private function formatSqlForLog($query)
    {
        $query = preg_replace('/\s+/', ' ', trim($query));

        return preg_replace(
            '/\s+(from|where|and|or|group by|order by|having|limit|offset|inner join|left join|right join|join|values|set)\s+/i',
            PHP_EOL . '$1 ',
            $query
        );
    }

    private function indentSql($query)
    {
        return '    ' . str_replace(PHP_EOL, PHP_EOL . '    ', $query);
    }

    private function shouldLog($query)
    {
        $tables = $this->extractTables($query);

        if (!empty($this->includeTables)) {
            return !empty(array_intersect($tables, $this->formatTables($this->includeTables))) || $this->queryHasTable($query, $this->includeTables);
        }

        if (!empty($this->excludeTables)) {
            return empty(array_intersect($tables, $this->formatTables($this->excludeTables))) && !$this->queryHasTable($query, $this->excludeTables);
        }

        return true;
    }

    private function extractTables($query)
    {
        $tables = [];
        preg_match_all('/\b(?:from|join|update|into)\s+((?:[`"]?[a-zA-Z0-9_]+[`"]?\.)?[`"]?[a-zA-Z0-9_]+[`"]?)/i', $query, $matches);

        foreach ($matches[1] ?? [] as $table) {
            $table = str_replace(['`', '"'], '', $table);
            $parts = explode('.', $table);
            $tables[] = strtolower(end($parts));
        }

        return array_unique($tables);
    }

    private function formatTables($tables)
    {
        return array_values(array_filter(array_map(function ($item) {
            return strtolower(trim($item));
        }, $tables)));
    }

    private function queryHasTable($query, $tables)
    {
        $query = strtolower(str_replace(['`', '"'], '', $query));

        foreach ($this->formatTables($tables) as $table) {
            if (preg_match('/\b' . preg_quote($table, '/') . '\b/', $query)) {
                return true;
            }
        }

        return false;
    }

    private function findSqlSource()
    {
        if (!$this->trace) {
            return null;
        }

        $basePath = base_path() . DIRECTORY_SEPARATOR;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);
        $sources = [];

        foreach ($trace as $item) {
            $file = $item['file'] ?? '';

            if (empty($file) || strpos($file, $basePath) !== 0 || strpos($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }

            $relativeFile = str_replace($basePath, '', $file);

            if ($relativeFile === 'app/Extendtions/Sql/PrintSql.php') {
                continue;
            }

            $source = $relativeFile . ':' . ($item['line'] ?? 0);

            if (!empty($item['class']) || !empty($item['function'])) {
                $source .= ':' . trim(($item['class'] ?? '') . ($item['type'] ?? '::') . ($item['function'] ?? ''), ':');
            }

            $sources[] = $source;

            if (!$this->traceStack) {
                break;
            }
        }

        return implode(' <- ', $sources);
    }
}
