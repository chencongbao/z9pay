<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MerchantInfo;
use App\Models\MerchantRole;
use App\Models\MerchantUser;
use Illuminate\Http\UploadedFile;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\MerchantAdmin\Form\AmountPassword;
use App\MerchantAdmin\Form\UpdatePassword;
use App\MerchantAdmin\Form\User\ResetGooglePassword;
use App\MerchantAdmin\Form\SettlementOrder\ApplySettlementOrderForm;

class MerchantAuthenticatedTranslationTest extends TestCase
{
    use DatabaseTransactions;

    private MerchantUser $merchant;

    private MerchantUser $child;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.auth.guard' => 'merchant-admin',
            'default.admin_google_2fa_disabled' => false,
            'admin.upload.disk' => 'admin',
            'admin.upload.directory.file' => 'files',
        ]);

        $this->merchant = $this->createMerchantUser('codex_translate_main', 0, 'current-password');
        $this->child = $this->createMerchantUser('codex_translate_child', $this->merchant->id);
        $this->attachAdministratorRole($this->merchant);
        $this->actingAs($this->merchant, 'merchant-admin');
        $this->createMerchantInfo($this->merchant);
    }

    public function test_authenticated_merchant_pages_are_translated_in_english_and_vietnamese(): void
    {
        foreach (['en', 'vi'] as $locale) {
            foreach ($this->merchantPages() as $name => $path) {
                $response = $this->withUnencryptedCookie('locale', $locale)->get($this->merchantUrl($path));

                $this->assertSame(200, $response->getStatusCode(), "{$locale}:{$name} failed: " . mb_substr($response->getContent(), 0, 500));
                $content = $response->getContent();
                $this->assertNoServerErrorText($content, "{$locale}:{$name}");
                $this->assertNoRawTranslationKeys($content, "{$locale}:{$name}");
                $this->assertNoCoreChineseUiText($content, "{$locale}:{$name}");
            }
        }
    }

    public function test_sensitive_form_error_messages_are_translated_for_supported_locales(): void
    {
        foreach (['zh_CN', 'en', 'vi'] as $locale) {
            app()->setLocale($locale);

            $messages = [
                data_get((new UpdatePassword())->handle([
                    'current_login_password' => 'wrong-password',
                    'password' => 'new-password',
                    'password_confirm' => 'new-password',
                ])->toArray(), 'data.message'),
                data_get((new AmountPassword())->handle([
                    'current_login_password' => 'wrong-password',
                    'password' => 'new-password',
                    'password_confirm' => 'new-password',
                ])->toArray(), 'data.message'),
                data_get(ResetGooglePassword::make()->payload(['id' => $this->child->id])->handle([
                    'password' => 'wrong-password',
                    'google_2fa_code' => $this->invalidGoogleCode(),
                ])->toArray(), 'data.message'),
                data_get((new ApplySettlementOrderForm())->handle([
                    'upload_type' => 0,
                    'card_no' => '',
                    'holder_name' => '',
                    'amount' => 0,
                ])->toArray(), 'data.message'),
                __('handle-form.fields.upload_invalid_file_type'),
            ];

            foreach ($messages as $message) {
                $message = (string)$message;
                $this->assertNotSame('', $message, "Empty message for {$locale}");
                $this->assertNoRawTranslationKeys($message, "form:{$locale}");
                if ($locale !== 'zh_CN') {
                    $this->assertNoCoreChineseUiText($message, "form:{$locale}");
                }
            }
        }
    }

    public function test_secure_upload_validation_error_is_translated_in_english_and_vietnamese(): void
    {
        foreach (['en', 'vi'] as $locale) {
            $response = $this->withUnencryptedCookie('locale', $locale)->post(route('dcat.merchant-admin.dcat-api.form.upload'), [
                '_form_' => ApplySettlementOrderForm::class,
                '_column' => 'excel_file',
                'upload_column' => 'excel_file',
                '_id' => 'excel_file',
                '_file_' => UploadedFile::fake()->createWithContent('fake.txt', 'not excel'),
            ]);

            $this->assertSame(422, $response->getStatusCode(), "secure-upload:{$locale} failed: " . mb_substr($response->getContent(), 0, 500));
            $this->assertNoRawTranslationKeys($response->getContent(), "secure-upload:{$locale}");
            $this->assertNoCoreChineseUiText($response->getContent(), "secure-upload:{$locale}");
        }
    }

    private function merchantPages(): array
    {
        return [
            'dashboard' => '',
            'information' => 'information',
            'deposit-orders' => 'deposit-orders',
            'transfer-orders' => 'transfer-orders',
            'settlement-orders' => 'settlement-orders',
            'settlement-orders-apply' => 'settlement-orders/apply',
            'balance-logs' => 'balance-logs',
            'bank-codes' => 'bank-codes',
            'report-payments' => 'report-payments',
            'report-merchants' => 'report-merchants',
            'login-logs' => 'login-logs',
            'musers' => 'musers',
            'musers-create' => 'musers/create',
            'mroles' => 'mroles',
            'mroles-create' => 'mroles/create',
        ];
    }

    private function assertNoServerErrorText(string $content, string $context): void
    {
        foreach (['SQLSTATE', 'TypeError', 'Exception'] as $needle) {
            $this->assertStringNotContainsString($needle, $content, "{$context} contains {$needle}");
        }
    }

    private function assertNoRawTranslationKeys(string $content, string $context): void
    {
        foreach (['admin.', 'menu.titles.', 'merchantuser.fields.', 'merchant-user.fields.', 'validation.', 'handle-form.'] as $needle) {
            $this->assertStringNotContainsString($needle, $content, "{$context} contains raw translation key {$needle}");
        }
    }

    private function assertNoCoreChineseUiText(string $content, string $context): void
    {
        foreach (['搜索', '重置', '提交', '暂无数据', '创建时间', '导出'] as $needle) {
            $this->assertStringNotContainsString($needle, $content, "{$context} contains Chinese UI text {$needle}");
        }
    }

    private function merchantUrl(string $path): string
    {
        $prefix = trim((string)config('merchant-admin.route.prefix'), '/');
        $domain = (string)config('merchant-admin.route.domain');
        $path = trim($path, '/');
        $url = 'http://' . $domain . '/' . $prefix;

        return $path === '' ? $url : $url . '/' . $path;
    }

    private function invalidGoogleCode(): string
    {
        $valid = (string)(new Google2FA())->getCurrentOtp($this->merchant->google_two_fa_secret);
        $invalid = str_pad((string)(((int)$valid + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        return $invalid === $valid ? '999999' : $invalid;
    }

    private function createMerchantUser(string $usernamePrefix, int $pid, string $password = 'child-password'): MerchantUser
    {
        $suffix = uniqid('', true);

        return MerchantUser::query()->create([
            'username' => $usernamePrefix . '_' . $suffix,
            'password' => Hash::make($password),
            'amount_password' => Hash::make('amount-password'),
            'name' => 'Codex Merchant Translate',
            'status' => 1,
            'pid' => $pid,
            'google_two_fa_secret' => (new Google2FA())->generateSecretKey(32),
            'google_two_fa_bind' => 1,
            'google_two_fa_enable' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
    }

    private function attachAdministratorRole(MerchantUser $user): void
    {
        $role = MerchantRole::query()->firstOrCreate(['slug' => 'administrator'], [
            'name' => 'Administrator',
            'mid' => 0,
        ]);

        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->load('roles.permissions');
    }

    private function createMerchantInfo(MerchantUser $user): void
    {
        MerchantInfo::query()->updateOrCreate(['merchant_user_id' => $user->id], [
            'name' => 'Codex Merchant',
            'coder' => 'CODEX' . $user->id,
            'currency_id' => 1,
            'balance_amount' => 1000,
            'available_balance' => 1000,
            'freeze_amount' => 0,
            'settlement_amount' => 0,
            'agent_user_id' => 0,
        ]);
    }
}
