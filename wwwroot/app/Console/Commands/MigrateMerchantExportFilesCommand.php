<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrateMerchantExportFilesCommand extends Command
{
    protected $signature = 'merchant:export-migrate-private {--dry-run : 只预览，不移动文件}';

    protected $description = '迁移商户后台历史导出文件到私有目录';

    private const EXPORT_TYPES = [
        'merchant_bank_codes',
        'merchant_deposit_order',
        'merchant_transfer_orders',
        'merchant_settlement_orders',
        'merchant_merchant_balance_logs',
    ];

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $moved = 0;
        $skipped = 0;

        foreach (self::EXPORT_TYPES as $type) {
            $sourceRoot = storage_path("app/public/export/{$type}");
            $targetRoot = storage_path("app/export/{$type}");
            if (!File::isDirectory($sourceRoot)) {
                $this->line("skip missing: {$sourceRoot}");
                continue;
            }

            foreach (File::allFiles($sourceRoot) as $file) {
                if (strtolower($file->getExtension()) !== 'xlsx') {
                    $skipped++;
                    $this->line("skip non-xlsx: {$file->getPathname()}");
                    continue;
                }

                $relativePath = ltrim(str_replace($sourceRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                if ($relativePath === '' || str_contains($relativePath, '..')) {
                    $skipped++;
                    $this->line("skip unsafe path: {$file->getPathname()}");
                    continue;
                }

                $targetPath = $this->safeTargetPath($targetRoot . DIRECTORY_SEPARATOR . $relativePath, $file->getPathname());
                $this->line(($dryRun ? 'dry-run move: ' : 'move: ') . $file->getPathname() . ' => ' . $targetPath);
                if ($dryRun) {
                    continue;
                }

                File::ensureDirectoryExists(dirname($targetPath));
                if (File::exists($targetPath) && hash_file('sha256', $targetPath) === hash_file('sha256', $file->getPathname())) {
                    File::delete($file->getPathname());
                    $skipped++;
                    continue;
                }

                File::move($file->getPathname(), $targetPath);
                $moved++;
            }

            if (!$dryRun) {
                $this->deleteEmptyDirectories($sourceRoot);
            }
        }

        $this->info("merchant export migrate finished, moved={$moved}, skipped={$skipped}, dry_run=" . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    private function safeTargetPath(string $targetPath, string $sourcePath): string
    {
        if (!File::exists($targetPath) || hash_file('sha256', $targetPath) === hash_file('sha256', $sourcePath)) {
            return $targetPath;
        }

        $directory = dirname($targetPath);
        $filename = pathinfo($targetPath, PATHINFO_FILENAME);
        $extension = pathinfo($targetPath, PATHINFO_EXTENSION);
        for ($index = 1; $index <= 1000; $index++) {
            $candidate = $directory . DIRECTORY_SEPARATOR . $filename . '-migrated-' . $index . '.' . $extension;
            if (!File::exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Unable to resolve target conflict: {$targetPath}");
    }

    private function deleteEmptyDirectories(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }

        foreach (File::directories($path) as $directory) {
            $this->deleteEmptyDirectories($directory);
        }

        if (count(File::files($path)) === 0 && count(File::directories($path)) === 0) {
            File::deleteDirectory($path);
        }
    }
}
