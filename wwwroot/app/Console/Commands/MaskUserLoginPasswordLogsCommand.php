<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MaskUserLoginPasswordLogsCommand extends Command
{
    protected $signature = 'user-login-logs:mask-passwords {--dry-run : 只预览，不更新数据}';

    protected $description = '脱敏金主登录日志中的历史密码字段';

    private const FILTERED_VALUE = '[FILTERED]';

    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'google_2fa_code', 'captcha', '_token'];

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $matched = 0;
        $updated = 0;

        DB::table('activity_log')
            ->where('log_name', 'user')
            ->where('log_type', 'login')
            ->orderBy('id')
            ->select(['id', 'description', 'properties', 'request_input'])
            ->chunkById(500, function ($rows) use ($dryRun, &$matched, &$updated) {
                foreach ($rows as $row) {
                    $changes = [];
                    $description = $this->maskDescription((string)$row->description);
                    if ($description !== (string)$row->description) {
                        $changes['description'] = $description;
                    }

                    [$properties, $propertiesChanged] = $this->maskJsonColumn($row->properties);
                    if ($propertiesChanged) {
                        $changes['properties'] = $properties;
                    }

                    [$requestInput, $requestInputChanged] = $this->maskJsonColumn($row->request_input);
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

        $this->info("user login logs mask finished, matched={$matched}, updated={$updated}, dry_run=" . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    private function maskDescription(string $description): string
    {
        return preg_replace('/(密码[:：])\\s*([^|\\s]+)/u', '$1' . self::FILTERED_VALUE, $description) ?? $description;
    }

    private function maskJsonColumn($value): array
    {
        if ($value === null || $value === '') {
            return [null, false];
        }

        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            return [null, false];
        }

        $changed = false;
        $masked = $this->maskArray($decoded, $changed);

        return [$changed ? json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $changed];
    }

    private function maskArray(array $data, bool &$changed): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string)$key);
            if (is_array($value)) {
                $data[$key] = $this->maskArray($value, $changed);
                continue;
            }

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true) || str_contains($normalizedKey, 'password')) {
                if ((string)$value !== self::FILTERED_VALUE) {
                    $data[$key] = self::FILTERED_VALUE;
                    $changed = true;
                }
            }
        }

        return $data;
    }
}
