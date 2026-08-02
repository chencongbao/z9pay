<?php

namespace App\MerchantAdmin\Controllers;

use App\Services\MerchantAdmin\MerchantExportFileService;
use Dcat\Admin\Admin;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportDownloadController
{
    public function download(string $type, string $filename, MerchantExportFileService $exportFileService): BinaryFileResponse
    {
        $user = Admin::user();
        if (!$user) {
            abort(401);
        }

        $adminId = (int)$user->id;
        $exportFileService->assertType($type);
        $exportFileService->assertFilename($filename);
        if (!$exportFileService->exists($type, $adminId, $filename)) {
            abort(404);
        }

        return response()->download(
            $exportFileService->absoluteFile($type, $adminId, $filename),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
