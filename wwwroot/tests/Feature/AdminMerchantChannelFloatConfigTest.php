<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Channel;
use App\Models\AdminRole;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use App\Models\MerchantChannel;
use App\Models\AdminAdministrator;
use Illuminate\Support\Facades\Hash;
use App\Http\Middleware\CheckAdminUserStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Dcat\Admin\Http\Middleware\Permission as AdminPermissionMiddleware;

class AdminMerchantChannelFloatConfigTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(CheckAdminUserStatus::class);
        $this->withoutMiddleware(AdminPermissionMiddleware::class);
        $this->actingAsAdmin($this->createAdminUser());
    }

    public function test_float_column_shows_merchant_float_disabled_first(): void
    {
        $merchant = $this->createMerchant(['amount_float_type' => 0, 'float_amount' => '0.00']);
        $merchantChannel = $this->createMerchantChannel($merchant->merchant_user_id, ['float_status' => 1]);

        $content = $this->get($this->adminUrl("merchant-channels?id={$merchantChannel->id}&iframe_tab_child=1"))->assertOk()->getContent();

        $this->assertStringContainsString('已关闭', $content);
        $this->assertStringNotContainsString('通道开关', $content);
    }

    public function test_float_column_shows_direction_amount_and_channel_switch_when_merchant_float_enabled(): void
    {
        $merchant = $this->createMerchant(['amount_float_type' => 2, 'float_amount' => '9.87']);
        $merchantChannel = $this->createMerchantChannel($merchant->merchant_user_id, ['float_status' => 1]);

        $content = $this->get($this->adminUrl("merchant-channels?id={$merchantChannel->id}&iframe_tab_child=1"))->assertOk()->getContent();

        $this->assertStringContainsString('浮动方向', $content);
        $this->assertStringContainsString('向下浮动', $content);
        $this->assertStringContainsString('最大差额', $content);
        $this->assertStringContainsString('9.87', $content);
        $this->assertStringContainsString('merchant-channel-float-switch', $content);
        $this->assertStringContainsString('float_status', $content);
    }

    private function createMerchant(array $attributes = []): MerchantInfo
    {
        $suffix = str_replace('.', '_', uniqid('', true));
        $merchantUser = MerchantUser::query()->forceCreate([
            'username' => 'codex_mc_float_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Merchant Channel Float',
            'status' => 1,
        ]);

        return MerchantInfo::query()->forceCreate(array_merge([
            'merchant_user_id' => $merchantUser->id,
            'agent_user_id' => 0,
            'coder' => 'codex_mc_float_' . $suffix,
            'appkey' => 'codex_appkey_' . $suffix,
            'appsecret' => 'codex_appsecret_' . $suffix,
            'currency_id' => 1,
            'name' => 'Codex Merchant Channel Float',
            'amount_float_type' => 0,
            'float_amount' => '0.00',
            'auto_transfer' => 1,
        ], $attributes));
    }

    private function createMerchantChannel(int $merchantUserId, array $attributes = []): MerchantChannel
    {
        $suffix = str_replace('.', '_', uniqid('', true));
        $channel = Channel::query()->forceCreate([
            'name' => 'Codex Float Channel',
            'code' => 'codex_float_' . $suffix,
            'classname' => '',
            'payment_ids' => '1',
            'status' => 1,
        ]);

        return MerchantChannel::query()->forceCreate(array_merge([
            'merchant_user_id' => $merchantUserId,
            'channel_id' => $channel->id,
            'payment_id' => 1,
            'priority' => 1,
            'weight' => 1,
            'status' => 1,
            'float_status' => 1,
            'pay_min_amount' => '0.00',
            'pay_max_amount' => '0.00',
            'collection_min_amount' => '0.00',
            'collection_max_amount' => '0.00',
            'fee' => '0.00',
            'deposit_fee' => '0.00',
            'settlement_mode' => 0,
        ], $attributes));
    }

    private function createAdminUser(): AdminAdministrator
    {
        $suffix = uniqid('', true);
        $user = AdminAdministrator::query()->create([
            'username' => 'codex_mc_float_' . $suffix,
            'password' => Hash::make('codex-password'),
            'name' => 'Codex Merchant Channel Float',
            'status' => 1,
            'session_id' => 'codex-session-' . $suffix,
        ]);
        $role = AdminRole::query()->firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']);
        $user->roles()->attach($role->id);
        $user->load('roles.permissions');

        return $user;
    }

    private function actingAsAdmin(AdminAdministrator $user): void
    {
        $this->actingAs($user, 'admin');
        $user->forceFill(['session_id' => session()->getId()])->save();
        $user->refresh()->load('roles.permissions');
    }

    private function adminUrl(string $path): string
    {
        return 'http://' . config('admin.route.domain') . '/' . trim(config('admin.route.prefix'), '/') . '/' . ltrim($path, '/');
    }
}
