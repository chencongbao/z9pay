<?php

namespace Tests\Feature;

use Tests\TestCase;
use Dcat\Admin\Admin;
use App\Models\MerchantRole;
use App\Models\MerchantUser;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\Cache\CacheConstPrefixService;
use App\MerchantAdmin\Actions\User\Delete as DeleteUserAction;
use App\MerchantAdmin\Form\User\ResetGooglePassword as ResetGooglePasswordForm;

class MerchantUserSensitiveActionPermissionTest extends TestCase
{
    use DatabaseTransactions;

    private MerchantUser $mainMerchant;

    private MerchantUser $ownChild;

    private MerchantUser $otherChild;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.auth.guard' => 'merchant-admin',
            'default.admin_google_2fa_disabled' => false,
            'system-log.enabled' => true,
        ]);

        $this->mainMerchant = $this->createMerchantUser('codex_main', 0, 'main-password');
        $this->ownChild = $this->createMerchantUser('codex_child', $this->mainMerchant->id);
        $otherMain = $this->createMerchantUser('codex_other_main', 0);
        $this->otherChild = $this->createMerchantUser('codex_other_child', $otherMain->id);

        $this->actingAsMerchant($this->mainMerchant);
    }

    public function test_main_merchant_can_only_delete_own_child_account(): void
    {
        $this->assertDeleteRejected($this->mainMerchant->id, $this->mainMerchant);
        $this->assertDeleteRejected($this->otherChild->id, $this->otherChild);
        $this->assertDeleteRejected(0, $this->ownChild);
        $this->assertDeleteRejected(-1, $this->ownChild);
        $this->assertDeleteRejected([$this->ownChild->id], $this->ownChild);
        $this->assertDeleteRejected((object)['id' => $this->ownChild->id], $this->ownChild);
        $this->assertDeleteRejected((float)$this->ownChild->id, $this->ownChild);
        $this->assertDeleteRejected($this->ownChild->id . 'e0', $this->ownChild);

        $response = $this->runDeleteAction($this->ownChild->id);
        $this->assertTrue($response->toArray()['status']);
        $this->assertSoftDeleted('merchant_users', ['id' => $this->ownChild->id]);

        $log = $this->latestMerchantActivity('merchant.user.delete');
        $this->assertSame($this->ownChild->id, (int)$log->subject_id);
        $this->assertSame($this->ownChild->id, (int)data_get($log->properties, 'merchant_user_id'));
        $this->assertLogHasNoSensitiveValue($log);
    }

    public function test_child_account_cannot_delete_even_with_administrator_role(): void
    {
        $childOperator = $this->createMerchantUser('codex_child_operator', $this->mainMerchant->id);
        $this->attachAdministratorRole($childOperator);
        $this->actingAsMerchant($childOperator);

        $this->assertDeleteRejected($this->ownChild->id, $this->ownChild);
    }

    public function test_reset_google_rejects_cross_scope_invalid_ids_child_operator_and_wrong_credentials_without_changes_or_logs(): void
    {
        $before = $this->snapshotGoogleState($this->ownChild);
        $logCount = Activity::query()->where('properties->action', 'merchant.user.reset_google')->count();

        $this->assertResetRejected($this->mainMerchant->id, 'main-password', $this->validGoogleCode(), $this->mainMerchant);
        $this->assertResetRejected($this->otherChild->id, 'main-password', $this->validGoogleCode(), $this->otherChild);
        $this->assertResetRejected(0, 'main-password', $this->validGoogleCode(), $this->ownChild);
        $this->assertResetRejected(-1, 'main-password', $this->validGoogleCode(), $this->ownChild);
        $this->assertResetRejected([$this->ownChild->id], 'main-password', $this->validGoogleCode(), $this->ownChild);
        $this->assertResetRejected((object)['id' => $this->ownChild->id], 'main-password', $this->validGoogleCode(), $this->ownChild);
        $this->assertResetRejected((float)$this->ownChild->id, 'main-password', $this->validGoogleCode(), $this->ownChild);
        $this->assertResetRejected($this->ownChild->id . 'e0', 'main-password', $this->validGoogleCode(), $this->ownChild);
        $this->assertResetRejected($this->ownChild->id, 'wrong-password', $this->validGoogleCode(), $this->ownChild);
        $this->assertResetRejected($this->ownChild->id, 'main-password', $this->invalidGoogleCode(), $this->ownChild);

        $this->assertSame($before, $this->snapshotGoogleState($this->ownChild));
        $this->assertSame($logCount, Activity::query()->where('properties->action', 'merchant.user.reset_google')->count());

        $childOperator = $this->createMerchantUser('codex_reset_child_operator', $this->mainMerchant->id);
        $this->attachAdministratorRole($childOperator);
        $this->actingAsMerchant($childOperator);
        $this->assertResetRejected($this->ownChild->id, 'main-password', $this->validGoogleCode(), $this->ownChild);
    }

    public function test_main_merchant_can_reset_own_child_google_with_password_and_2fa_and_log_is_safe(): void
    {
        $before = $this->snapshotGoogleState($this->ownChild);

        $response = $this->runResetGoogleForm($this->ownChild->id, 'main-password', $this->validGoogleCode());
        $this->assertTrue($response->toArray()['status']);

        $after = $this->snapshotGoogleState($this->ownChild);
        $this->assertNotSame($before['google_two_fa_secret'], $after['google_two_fa_secret']);
        $this->assertSame(0, $after['google_two_fa_bind']);
        $this->assertSame(1, $after['google_two_fa_enable']);
        $this->assertSame('', $after['session_id']);
        $this->assertSame($before['password'], $after['password']);

        $log = $this->latestMerchantActivity('merchant.user.reset_google');
        $this->assertSame($this->ownChild->id, (int)$log->subject_id);
        $this->assertSame($this->ownChild->id, (int)data_get($log->properties, 'merchant_user_id'));
        $this->assertLogHasNoSensitiveValue($log);
    }

    private function assertDeleteRejected(mixed $id, MerchantUser $target): void
    {
        $before = $this->snapshotDeleteState($target);
        $logCount = Activity::query()->where('properties->action', 'merchant.user.delete')->count();
        $response = $this->runDeleteAction($id);

        $this->assertFalse($response->toArray()['status']);
        $this->assertSame($before, $this->snapshotDeleteState($target));
        $this->assertSame($logCount, Activity::query()->where('properties->action', 'merchant.user.delete')->count());
    }

    private function assertResetRejected(mixed $id, string $password, string $googleCode, MerchantUser $target): void
    {
        $before = $this->snapshotGoogleState($target);
        $response = $this->runResetGoogleForm($id, $password, $googleCode);

        $this->assertFalse($response->toArray()['status']);
        $this->assertSame($before, $this->snapshotGoogleState($target));
    }

    private function runDeleteAction(mixed $id)
    {
        $action = new DeleteUserAction();
        $action->setKey($id);

        return $action->handle(Request::create('/merchant/dcat-api/action', 'POST'));
    }

    private function runResetGoogleForm(mixed $id, string $password, string $googleCode)
    {
        Cache::forget(CacheConstPrefixService::ADMIN_OPERATE_GOOGLE_2FA_CODE_TIME . Admin::user()->id);
        $form = ResetGooglePasswordForm::make()->payload(['id' => $id]);

        return $form->handle([
            'password' => $password,
            'google_2fa_code' => $googleCode,
        ]);
    }

    private function snapshotDeleteState(MerchantUser $user): array
    {
        $fresh = MerchantUser::query()->withTrashed()->find($user->id);

        return [
            'exists' => (bool)$fresh,
            'deleted_at' => optional($fresh)->deleted_at ? (string)$fresh->deleted_at : null,
        ];
    }

    private function snapshotGoogleState(MerchantUser $user): array
    {
        $fresh = MerchantUser::query()->withTrashed()->findOrFail($user->id);

        return [
            'password' => $fresh->password,
            'google_two_fa_secret' => $fresh->google_two_fa_secret,
            'google_two_fa_bind' => (int)$fresh->google_two_fa_bind,
            'google_two_fa_enable' => (int)$fresh->google_two_fa_enable,
            'session_id' => (string)$fresh->session_id,
        ];
    }

    private function latestMerchantActivity(string $action): Activity
    {
        return Activity::query()->where('properties->action', $action)->latest('id')->firstOrFail();
    }

    private function assertLogHasNoSensitiveValue(Activity $log): void
    {
        $encoded = json_encode([
            'description' => $log->description,
            'properties' => $log->properties,
            'request_input' => $log->request_input,
        ], JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('main-password', $encoded);
        $this->assertStringNotContainsString('child-secret', $encoded);
        $this->assertStringNotContainsString($this->mainMerchant->google_two_fa_secret, $encoded);
    }

    private function validGoogleCode(): string
    {
        return (string)(new Google2FA())->getCurrentOtp($this->mainMerchant->google_two_fa_secret);
    }

    private function invalidGoogleCode(): string
    {
        $valid = $this->validGoogleCode();
        $invalid = str_pad((string)(((int)$valid + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        return $invalid === $valid ? '999999' : $invalid;
    }

    private function actingAsMerchant(MerchantUser $user): void
    {
        $user->load('roles.permissions');
        $this->actingAs($user, 'merchant-admin');
        config(['admin.auth.guard' => 'merchant-admin']);
    }

    private function createMerchantUser(string $usernamePrefix, int $pid, string $password = 'child-password'): MerchantUser
    {
        $suffix = uniqid('', true);

        return MerchantUser::query()->create([
            'username' => $usernamePrefix . '_' . $suffix,
            'password' => Hash::make($password),
            'name' => 'Codex Merchant User',
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
}
