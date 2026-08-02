<?php

namespace Tests\Feature;

use Tests\TestCase;
use Dcat\Admin\Form;
use ReflectionMethod;
use App\Models\AgentUser;
use Illuminate\Contracts\Auth\StatefulGuard;
use App\AgentAdmin\Controllers\AuthController;

class AgentSettingFormSecurityTest extends TestCase
{
    public function test_setting_form_uses_agent_user_and_explicit_agent_url(): void
    {
        $form = (new AgentSettingFormAuthController())->settingFormForTest();
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');

        $this->assertInstanceOf(AgentUser::class, $form->repository()->model());
        $this->assertSame('agent_users', $form->repository()->model()->getTable());
        $this->assertSame(url('/' . ($prefix ? $prefix . '/' : '') . 'auth/setting'), $form->action());
        $this->assertNotNull($form->field('avatar'));
    }

    public function test_setting_form_discards_undeclared_sensitive_fields(): void
    {
        $form = (new AgentSettingFormAuthController())->settingFormForTest();
        $prepared = $form->prepareUpdate([
            'name' => 'Safe Name',
            'balance' => '999999.99',
            'status' => 0,
            'pid' => 999,
            'role_id' => 1,
        ]);

        $this->assertSame(['name' => 'Safe Name'], $prepared);
    }

    public function test_old_password_validation_resolves_agent_guard_provider(): void
    {
        config(['admin.auth' => config('agent-admin.auth')]);
        $controller = new AgentSettingFormAuthController();
        $guard = $controller->guardForTest();
        $provider = $guard->getProvider();
        $validationMethod = new ReflectionMethod($controller, 'validateCredentialsWhenUpdatingPassword');
        $agent = new AgentUser();
        $agent->password = bcrypt('correct-password');
        $guard->setUser($agent);

        $this->assertInstanceOf(StatefulGuard::class, $guard);
        $this->assertSame('agent-admin', config('agent-admin.auth.guard'));
        $this->assertInstanceOf(AgentUser::class, $provider->createModel());
        $this->assertSame(\Dcat\Admin\Http\Controllers\AuthController::class, $validationMethod->getDeclaringClass()->getName());

        request()->merge(['old_password' => 'correct-password', 'password' => 'new-password']);
        $this->assertTrue($controller->oldPasswordIsValidForTest());

        request()->merge(['old_password' => 'wrong-password']);
        $this->assertFalse($controller->oldPasswordIsValidForTest());
    }
}

class AgentSettingFormAuthController extends AuthController
{
    public function settingFormForTest(): Form
    {
        $form = $this->settingForm();
        $build = new ReflectionMethod($form, 'build');
        $build->setAccessible(true);
        $build->invoke($form);

        return $form;
    }

    public function guardForTest(): StatefulGuard
    {
        return $this->guard();
    }

    public function oldPasswordIsValidForTest(): bool
    {
        return $this->validateCredentialsWhenUpdatingPassword();
    }
}
