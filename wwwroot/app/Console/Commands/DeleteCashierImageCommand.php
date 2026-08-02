<?php

namespace App\Console\Commands;

use SplFileInfo;
use Illuminate\Console\Command;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\Storage;

class DeleteCashierImageCommand extends Command
{
    private const DEFAULT_KEEP_DAYS = 3;
    private const MAX_KEEP_DAYS = 30;

    protected array $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];

    protected array $excludeDirectories = ['images', 'transfer'];

    protected $signature = 'images:delete-cashier {--days=3 : 保留最近多少天图片} {--dry-run : 只统计不删除文件}';

    protected $description = '清理收银台用户上传的图片，默认删除60天之前的数据';

    public function handle()
    {
        $keepDays = $this->resolveKeepDays();
        if ($keepDays === null) {
            return self::FAILURE;
        }

        $thresholdTime = now()->subDays($keepDays)->startOfDay()->timestamp;
        $publicRoot = rtrim(Storage::disk('public')->path(''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $dryRun = (bool) $this->option('dry-run');

        if (!is_dir($publicRoot)) {
            $this->error("收银台图片目录不存在：{$publicRoot}");
            return self::FAILURE;
        }

        $scannedCount = 0;
        $matchedCount = 0;
        $deletedCount = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicRoot, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $scannedCount++;
            $relativePath = ltrim(substr($fileInfo->getPathname(), strlen($publicRoot)), DIRECTORY_SEPARATOR);
            if (!$this->isImageFile($relativePath) || $this->shouldSkip($relativePath) || $fileInfo->getMTime() >= $thresholdTime) {
                continue;
            }

            $matchedCount++;
            if ($dryRun) {
                $this->lineWhenVerbose("可删除：{$relativePath}");
                continue;
            }

            if (Storage::disk('public')->delete($relativePath)) {
                $deletedCount++;
                $this->lineWhenVerbose("已删除：{$relativePath}");
            }
        }

        $this->info(($dryRun ? '收银台图片清理试跑完成' : '收银台图片清理完成') . "：扫描 {$scannedCount} 个文件，匹配 {$matchedCount} 个，删除 {$deletedCount} 个，保留最近 {$keepDays} 天。");

        return self::SUCCESS;
    }

    private function resolveKeepDays(): ?int
    {
        $days = $this->option('days');
        if ($days === null || $days === '') {
            return self::DEFAULT_KEEP_DAYS;
        }

        $days = trim((string)$days);
        if (preg_match('/^[1-9]\d*$/', $days) !== 1) {
            $this->error('--days 必须是正整数。');
            return null;
        }

        $days = (int)$days;
        if ($days > self::MAX_KEEP_DAYS) {
            $this->error('--days 不能超过 ' . self::MAX_KEEP_DAYS . '。');
            return null;
        }

        return $days;
    }

    protected function isImageFile(string $file): bool
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return in_array($extension, $this->imageExtensions, true);
    }

    protected function shouldSkip(string $file): bool
    {
        foreach ($this->excludeDirectories as $directory) {
            $directory = trim($directory, '/');

            if ($directory === '') {
                continue;
            }

            if ($file === $directory || str_starts_with($file, $directory . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function lineWhenVerbose(string $message): void
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line($message);
        }
    }
}
