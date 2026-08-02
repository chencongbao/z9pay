<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\AgentAdmin\Controllers\AuthController;

class AgentTwoFactorChallengeTest extends TestCase
{
    public function test_direct_verify_without_challenge_is_rejected_before_authentication(): void
    {
        Auth::shouldReceive('guard')->never();
        $request = $this->request(['username' => 'agent', 'password' => 'secret', 'google_2fa_code' => '123456']);

        $response = (new AuthController())->postVerify($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(__('auth.agent_login.verify_session_expired'), $response->getData(true)['message']);
    }

    public function test_matching_challenge_is_valid_for_the_next_authentication_step(): void
    {
        $request = $this->request();
        $controller = new TestableAgentAuthController();
        $controller->createChallenge($request, ' Agent ');

        $this->assertTrue($controller->validateChallenge($request, 'agent'));
        $this->assertTrue($request->session()->has('agent_admin.pending_two_factor'));
    }

    public function test_username_mismatch_is_rejected_and_clears_challenge(): void
    {
        $request = $this->request();
        $controller = new TestableAgentAuthController();
        $controller->createChallenge($request, 'agent-a');

        $this->assertFalse($controller->validateChallenge($request, 'agent-b'));
        $this->assertFalse($request->session()->has('agent_admin.pending_two_factor'));
    }

    public function test_expired_challenge_is_rejected_and_cleared(): void
    {
        $request = $this->request();
        $controller = new TestableAgentAuthController();
        $controller->createChallenge($request, 'agent');
        $this->travel(6)->minutes();

        $this->assertFalse($controller->validateChallenge($request, 'agent'));
        $this->assertFalse($request->session()->has('agent_admin.pending_two_factor'));
    }

    public function test_successful_login_cleanup_removes_challenge(): void
    {
        $request = $this->request();
        $controller = new TestableAgentAuthController();
        $controller->createChallenge($request, 'agent');

        $controller->completeChallenge($request);

        $this->assertFalse($request->session()->has('agent_admin.pending_two_factor'));
    }

    private function request(array $input = []): Request
    {
        $session = app('session.store');
        $session->flush();
        $session->start();

        $request = Request::create('/agent/auth/verify', 'POST', $input);
        $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return $request;
    }
}

class TestableAgentAuthController extends AuthController
{
    public function createChallenge(Request $request, string $username): void
    {
        $this->storePendingTwoFactorChallenge($request, $username);
    }

    public function validateChallenge(Request $request, string $username): bool
    {
        return $this->hasValidPendingTwoFactorChallenge($request, $username);
    }

    public function completeChallenge(Request $request): void
    {
        $this->clearPendingTwoFactorChallenge($request);
    }
}
