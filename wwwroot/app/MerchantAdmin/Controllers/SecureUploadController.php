<?php

namespace App\MerchantAdmin\Controllers;

use ZipArchive;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;
use Dcat\Admin\Http\Controllers\HandleFormController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm;
use App\Services\MerchantAdmin\MerchantSettlementUploadTokenService;

class SecureUploadController extends HandleFormController
{
    private const ALLOWED_FORM = ApplySettlementOrderForm::class;

    private const ALLOWED_COLUMN = 'excel_file';

    private const MAX_EXCEL_SIZE = 10485760;

    private const ALLOWED_EXTENSIONS = ['xlsx'];

    private const ALLOWED_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    public function disabled()
    {
        abort(404);
    }

    public function uploadFile(Request $request)
    {
        $this->validateSettlementUploadRequest($request);
        $this->validateUploadedExcel($this->file());

        $response = parent::uploadFile($request);
        $path = $this->extractUploadedPath($response);
        if ($path !== '') {
            app(MerchantSettlementUploadTokenService::class)->register($path);
        }

        return $response;
    }

    public function destroyFile(Request $request)
    {
        $this->validateSettlementUploadRequest($request);
        $key = (string)$request->get('key', '');
        try {
            app(MerchantSettlementUploadTokenService::class)->assertUsable($key);
        } catch (RuntimeException $e) {
            $this->fail(403, 'upload_forbidden');
        }
        $this->validateDestroyPath($key);

        $response = parent::destroyFile($request);
        app(MerchantSettlementUploadTokenService::class)->consume($key);

        return $response;
    }

    private function validateSettlementUploadRequest(Request $request): void
    {
        if ($request->get(Form::REQUEST_NAME) !== self::ALLOWED_FORM) {
            $this->fail(403, 'upload_forbidden');
        }

        $column = $request->get('upload_column') ?: $request->get('_column');
        if ($column !== self::ALLOWED_COLUMN || $request->filled('_relation')) {
            $this->fail(403, 'upload_forbidden');
        }

        if ($request->hasAny(['disk', 'dir', 'directory', 'path'])) {
            $this->fail(403, 'upload_forbidden');
        }

        $form = app(self::ALLOWED_FORM);
        if (!$form->passesAuthorization()) {
            $this->fail(403, 'upload_forbidden');
        }
    }

    private function validateUploadedExcel(?UploadedFile $file): void
    {
        if (!$file || !$file->isValid()) {
            $this->fail(422, 'upload_invalid_file');
        }

        if ($file->getSize() > self::MAX_EXCEL_SIZE) {
            $this->fail(422, 'upload_file_too_large');
        }

        $extension = strtolower((string)$file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->fail(422, 'upload_invalid_file_type');
        }

        $mimes = array_filter([(string)$file->getMimeType(), (string)$file->getClientMimeType()]);
        if (!array_intersect($mimes, self::ALLOWED_MIMES)) {
            $this->fail(422, 'upload_invalid_file_type');
        }

        if ($extension === 'xlsx') {
            $this->validateXlsxContent($file);
        }

    }

    private function validateDestroyPath(string $key): void
    {
        if ($key === '' || str_contains($key, "\0") || str_starts_with($key, '/') || preg_match('#(^|/)\.\.(/|$)#', $key)) {
            $this->fail(403, 'upload_forbidden');
        }

        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->fail(403, 'upload_forbidden');
        }

        $filesDir = trim((string)config('admin.upload.directory.file', 'files'), '/');
        if (!str_starts_with($key, $filesDir . '/')) {
            $this->fail(403, 'upload_forbidden');
        }

        $disk = Storage::disk(config('admin.upload.disk'));
        $root = realpath($disk->path($filesDir));
        $path = realpath($disk->path($key));
        if (!$root || !$path || !is_file($path)) {
            $this->fail(403, 'upload_forbidden');
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strncmp($path, $root, strlen($root)) !== 0) {
            $this->fail(403, 'upload_forbidden');
        }
    }

    private function validateXlsxContent(UploadedFile $file): void
    {
        $path = $file->getRealPath();
        if (!$path || file_get_contents($path, false, null, 0, 2) !== 'PK') {
            $this->fail(422, 'upload_invalid_file_type');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            $this->fail(422, 'upload_invalid_file_type');
        }

        $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
        $hasWorkbook = $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();

        if (!$hasContentTypes || !$hasWorkbook) {
            $this->fail(422, 'upload_invalid_file_type');
        }
    }

    private function extractUploadedPath($response): string
    {
        if (is_object($response) && method_exists($response, 'toArray')) {
            $data = $response->toArray();

            return (string)data_get($data, 'data.id', data_get($data, 'data.path', ''));
        }

        return '';
    }

    private function message(string $key): string
    {
        return __("handle-form.fields.{$key}");
    }

    private function fail(int $status, string $key): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $this->message($key),
        ], $status));
    }
}
