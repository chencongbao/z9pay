<?php

namespace App\Console\Commands;

use App\Services\Common\ActivityLogSensitiveDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MaskActivityLogSensitiveDataCommand extends Command
{
    protected $signature = 'activity-log:mask-sensitive-data {--dry-run : 只预览，不更新数据}';

    protected $description = '脱敏历史操作日志中的密码、Token、密钥等敏感字段';

    public function handle(ActivityLogSensitiveDataService $sensitiveDataService): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $matched = 0;
        $updated = 0;

        DB::table('activity_log')
            ->orderBy('id')
            ->select(['id', 'description', 'properties', 'request_input'])
            ->chunkById(500, function ($rows) use ($dryRun, $sensitiveDataService, &$matched, &$updated) {
                foreach ($rows as $row) {
                    $changes = [];
                    $description = $sensitiveDataService->sanitizeDescription((string)$row->description);
                    if ($description !== (string)$row->description) {
                        $changes['description'] = $description;
                    }

                    [$properties, $propertiesChanged] = $sensitiveDataService->sanitizeJsonColumn($row->properties, false);
                    if ($propertiesChanged) {
                        $changes['properties'] = $properties;
                    }

                    [$requestInput, $requestInputChanged] = $sensitiveDataService->sanitizeJsonColumn($row->request_input, false);
                    if ($requestInputChanged) {
                        $changes['request_input'] = $requestInput;
                    }

                    if (empty($changes)) {
                        continue;
                    }

                    $matched++;
                    $this->line(($dryRun ? 'dry-run mask: ' : 'mask: ') . 'activity_log#' . $row->id);
                    if (!$dryRun) {
                        DB::table('activity_log')->where('id', $row->id)->update($changes);
                        $updated++;
                    }
                }
            });

        $this->info("activity logs sensitive data mask finished, matched={$matched}, updated={$updated}, dry_run=" . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
