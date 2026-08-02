<?php

namespace Tests\Feature;

use ZipArchive;
use Tests\TestCase;
use App\Models\MerchantRole;
use App\Models\MerchantUser;
use Illuminate\Http\UploadedFile;
use App\Models\MerchantPermission;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\MerchantAdmin\Controllers\SecureUploadController;
use App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm;
use App\Services\MerchantAdmin\MerchantSettlementUploadTokenService;

class MerchantSecureUploadTest extends TestCase
{
    use DatabaseTransactions;

    private MerchantUser $merchantUser;

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(storage_path('framework/testing/sessions'));
        Storage::fake('admin');
        config([
            'session.driver' => 'file',
            'session.files' => storage_path('framework/testing/sessions'),
            'admin.upload.disk' => 'admin',
            'admin.upload.directory.file' => 'files',
        ]);

        $this->merchantUser = $this->createMerchantUserWithSettlementPermission('codex_ms_upload_a');
        $this->actingAs($this->merchantUser, 'merchant-admin');
    }

    public function test_secure_upload_routes_are_named_and_point_to_secure_controller(): void
    {
        $expected = [
            'dcat.merchant-admin.dcat-api.form.upload' => 'uploadFile',
            'dcat.merchant-admin.dcat-api.form.destroy-file' => 'destroyFile',
            'dcat.merchant-admin.dcat-api.tinymce.upload' => 'disabled',
            'dcat.merchant-admin.dcat-api.editor-md.upload' => 'disabled',
        ];

        foreach ($expected as $name => $method) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertSame(SecureUploadController::class . '@' . $method, $route->getActionName());
        }
    }

    public function test_valid_xlsx_upload_registers_token_and_returns_files_path(): void
    {
        $response = $this->postUpload($this->xlsxFile());

        $response->assertOk()->assertJson(['status' => true]);
        $path = (string)$response->json('data.id');

        $this->assertNotSame('', $path);
        $this->assertStringStartsWith('files/', $path);
        $this->assertTrue(Storage::disk('admin')->exists($path));

        app(MerchantSettlementUploadTokenService::class)->assertUsable($path);
    }

    public function test_editor_and_tinymce_upload_are_disabled(): void
    {
        $this->post(route('dcat.merchant-admin.dcat-api.editor-md.upload'))->assertNotFound();
        $this->post(route('dcat.merchant-admin.dcat-api.tinymce.upload'))->assertNotFound();
    }

    public function test_non_whitelisted_upload_parameters_are_forbidden(): void
    {
        $cases = [
            ['_form_' => self::class],
            ['upload_column' => 'other_column', '_column' => 'other_column'],
            ['disk' => 'public'],
            ['dir' => 'images'],
            ['directory' => 'images'],
            ['path' => 'files/example.xlsx'],
            ['_relation' => 'items'],
        ];

        foreach ($cases as $payload) {
            $this->postUpload($this->xlsxFile(), $payload)->assertForbidden();
        }

        $this->assertSame([], Storage::disk('admin')->allFiles('files'));
    }

    public function test_fake_xlsx_wrong_extension_fake_xls_and_oversized_files_are_rejected_without_persisting(): void
    {
        $this->postUpload(UploadedFile::fake()->createWithContent('fake.xlsx', 'not-a-zip'))->assertStatus(422);
        $this->postUpload(UploadedFile::fake()->createWithContent('fake.txt', $this->xlsxContent()))->assertStatus(422);
        $this->postUpload(UploadedFile::fake()->createWithContent('fake.xls', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . 'not-real-xls'))->assertStatus(422);
        $this->postUpload(UploadedFile::fake()->create('too-large.xlsx', 10241, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))->assertStatus(422);

        $this->assertSame([], Storage::disk('admin')->allFiles('files'));
    }

    public function test_upload_token_is_bound_to_user_and_session_for_destroy_and_form_usage(): void
    {
        $path = (string)$this->postUpload($this->xlsxFile())->json('data.id');
        $this->assertTrue(Storage::disk('admin')->exists($path));

        $this->startFreshAuthenticatedSession($this->merchantUser);
        $this->postDestroy($path)->assertForbidden();
        $this->assertTrue(Storage::disk('admin')->exists($path));
        $this->expectResolveUploadPathToFail($path);

        $otherUser = $this->createMerchantUserWithSettlementPermission('codex_ms_upload_b');
        $this->startFreshAuthenticatedSession($otherUser);
        $this->postDestroy($path)->assertForbidden();
        $this->assertTrue(Storage::disk('admin')->exists($path));
        $this->expectResolveUploadPathToFail($path);
    }

    public function test_same_session_destroy_removes_file_and_token_only_for_registered_path(): void
    {
        $path = (string)$this->postUpload($this->xlsxFile())->json('data.id');

        $response = $this->postDestroy($path);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $response->assertJson(['status' => true]);

        $this->assertFalse(Storage::disk('admin')->exists($path));
        $this->expectTokenToBeExpired($path);
    }

    public function test_token_expiry_and_manual_consume_make_upload_unusable(): void
    {
        $path = (string)$this->postUpload($this->xlsxFile())->json('data.id');
        $service = app(MerchantSettlementUploadTokenService::class);

        $service->assertUsable($path);
        $service->consume($path);
        $this->expectTokenToBeExpired($path);

        $path = (string)$this->postUpload($this->xlsxFile())->json('data.id');
        $this->travel(61)->minutes();
        $this->expectTokenToBeExpired($path);
    }

    public function test_upload_error_messages_are_translated_for_supported_locales(): void
    {
        foreach (['zh_CN', 'en', 'vi'] as $locale) {
            app()->setLocale($locale);
            $message = __('handle-form.fields.upload_invalid_file_type');

            $this->assertNotSame('handle-form.fields.upload_invalid_file_type', $message);
            $this->assertStringNotContainsString('XLS or XLSX', $message);
            $this->assertStringContainsString('XLSX', mb_strtoupper($message));
        }
    }

    private function postUpload(UploadedFile $file, array $overrides = [])
    {
        return $this->post(route('dcat.merchant-admin.dcat-api.form.upload'), array_merge($this->baseUploadPayload(), $overrides, [
            '_file_' => $file,
        ]));
    }

    private function postDestroy(string $path, array $overrides = [])
    {
        return $this->post(route('dcat.merchant-admin.dcat-api.form.destroy-file'), array_merge($this->baseUploadPayload(), $overrides, [
            'key' => $path,
        ]));
    }

    private function baseUploadPayload(): array
    {
        return [
            '_form_' => ApplySettlementOrderForm::class,
            '_column' => 'excel_file',
            'upload_column' => 'excel_file',
            '_id' => 'excel_file',
        ];
    }

    private function xlsxFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('example.xlsx', $this->xlsxContent());
    }

    private function xlsxContent(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"></workbook>');
        $zip->close();
        $content = file_get_contents($path);
        @unlink($path);

        return (string)$content;
    }

    private function createMerchantUserWithSettlementPermission(string $username): MerchantUser
    {
        $suffix = uniqid('', true);
        $user = MerchantUser::query()->create([
            'username' => $username . '_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Upload Test',
            'status' => 1,
            'pid' => 0,
            'session_id' => 'codex-session-' . $suffix,
        ]);

        $role = MerchantRole::query()->create([
            'name' => 'Codex Upload Role',
            'slug' => 'codex-upload-role-' . $suffix,
            'mid' => $user->id,
        ]);
        $permission = MerchantPermission::query()->firstOrCreate(['slug' => 'merchant-settlement-order-add'], [
            'name' => 'Codex Settlement Add',
            'http_method' => '',
            'http_path' => '',
            'order' => 0,
            'parent_id' => 0,
        ]);

        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $user->load('roles.permissions');

        return $user;
    }

    private function startFreshAuthenticatedSession(MerchantUser $user): void
    {
        session()->flush();
        session()->migrate(true);
        $user->load('roles.permissions');
        $this->actingAs($user, 'merchant-admin');
    }

    private function expectResolveUploadPathToFail(string $path): void
    {
        try {
            $method = new \ReflectionMethod(ApplySettlementOrderForm::class, 'resolveSettlementUploadPath');
            $method->setAccessible(true);
            $method->invoke(new ApplySettlementOrderForm(), $path);
            $this->fail('Upload path should be rejected for this user/session.');
        } catch (\ReflectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    private function expectTokenToBeExpired(string $path): void
    {
        try {
            app(MerchantSettlementUploadTokenService::class)->assertUsable($path);
            $this->fail('Upload token should not be usable.');
        } catch (\RuntimeException $e) {
            $this->assertTrue(true);
        }
    }
}
