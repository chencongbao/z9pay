<?php

namespace App\Services\MerchantAdmin;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class MerchantExportFileService
{
    private const TYPES = [
        'merchant_bank_codes',
        'merchant_deposit_order',
        'merchant_transfer_orders',
        'merchant_settlement_orders',
        'merchant_merchant_balance_logs',
    ];

    public function directory(string $type, int $adminId): string
    {
        $this->assertType($type);

        return 'export/' . $type . '/' . $adminId;
    }

    public function absoluteDirectory(string $type, int $adminId): string
    {
        return storage_path('app/' . $this->directory($type, $adminId));
    }

    public function ensureDirectory(string $type, int $adminId): void
    {
        Storage::disk('local')->makeDirectory($this->directory($type, $adminId));
    }

    public function absoluteFile(string $type, int $adminId, string $filename): string
    {
        return storage_path('app/' . $this->relativeFile($type, $adminId, $filename));
    }

    public function relativeFile(string $type, int $adminId, string $filename): string
    {
        $this->assertFilename($filename);

        return $this->directory($type, $adminId) . '/' . $filename;
    }

    public function exists(string $type, int $adminId, string $filename): bool
    {
        return Storage::disk('local')->exists($this->relativeFile($type, $adminId, $filename));
    }

    public function downloadUrl(string $type, string $filename, ?string $baseUrl = null): string
    {
        $this->assertType($type);
        $this->assertFilename($filename);
        $baseUrl = $baseUrl ? rtrim($baseUrl, '/') : rtrim(admin_url('export-download'), '/');

        return $baseUrl . '/' . rawurlencode($type) . '/' . rawurlencode($filename);
    }

    public function historyRows(string $type, int $adminId): array
    {
        $path = $this->directory($type, $adminId);
        $rows = [];

        foreach (Storage::disk('local')->allFiles($path) as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            $fileDate = Carbon::createFromTimestamp($lastModified)->rawFormat('Y-m-d H:i:s');
            if (strtotime($fileDate) < strtotime(date('Y-m-d') . ' 00:00:00')) {
                Storage::disk('local')->delete($file);
                continue;
            }

            $filename = basename($file);
            $rows[] = [
                $fileDate,
                'timestamp' => $lastModified,
                '<a href="' . e($this->downloadUrl($type, $filename)) . '" target="_blank" class="blue">' . admin_trans_label('export_download') . '</a>',
            ];
        }

        usort($rows, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return collect($rows)->map(function ($item) {
            unset($item['timestamp']);
            return $item;
        })->all();
    }

    public function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            abort(404);
        }
    }

    public function assertFilename(string $filename): void
    {
        if ($filename !== basename($filename) || !preg_match('/^[A-Za-z0-9._-]+\\.xlsx$/', $filename)) {
            abort(404);
        }
    }
}
