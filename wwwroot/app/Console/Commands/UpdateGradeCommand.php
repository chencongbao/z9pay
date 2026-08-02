<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateGradeCommand extends Command
{
    protected $signature = 'update {--delete} {--export} {--force : 确认执行破坏性重置}';

    protected $description = '系统更新升级数据库';

    private const TABLE_SQL_MAP = [
        'admin_menu' => 'app/Console/temp_db/admin_menu.sql',
        'admin_permissions' => 'app/Console/temp_db/admin_permissions.sql',
        'merchant_menu' => 'app/Console/temp_db/merchant_menu.sql',
        'merchant_permissions' => 'app/Console/temp_db/merchant_permissions.sql',
        'agent_menu' => 'app/Console/temp_db/agent_menu.sql',
    ];

    public function handle(): int
    {
        if (!$this->option('delete') && !$this->option('export')) {
            $this->warn('请指定 --delete 或 --export 参数。');
            return self::SUCCESS;
        }

        if ($this->option('delete') && !$this->canDropTables()) {
            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $this->dropTables();
        }

        if ($this->option('export') && !$this->restoreMissingTables()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function canDropTables(): bool
    {
        if (!$this->option('export')) {
            $this->error('删除菜单权限表必须同时指定 --export，禁止单独执行 --delete。');
            return false;
        }

        if (!$this->option('force')) {
            $this->error('删除菜单权限表属于破坏性操作，必须显式指定 --force。');
            return false;
        }

        return true;
    }

    private function dropTables(): void
    {
        // 重置菜单权限表，通常配合 --export 重新导入初始化数据。
        foreach (array_keys(self::TABLE_SQL_MAP) as $table) {
            Schema::dropIfExists($table);
            $this->info("已删除表：{$table}");
        }
    }

    private function restoreMissingTables(): bool
    {
        // 只导入不存在的表，避免覆盖已有线上配置。
        foreach (self::TABLE_SQL_MAP as $table => $sqlFile) {
            if (Schema::hasTable($table)) {
                $this->line("表已存在，跳过导入：{$table}");
                continue;
            }

            $path = base_path($sqlFile);
            if (!is_file($path)) {
                $this->error("SQL文件不存在：{$sqlFile}");
                return false;
            }

            DB::unprepared(file_get_contents($path));
            $this->info("已导入表：{$table}");
        }

        return true;
    }
}
