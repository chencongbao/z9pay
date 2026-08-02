<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Http\Middleware\CheckAgentUserStatus;

class CheckAgentUserStatusTest extends TestCase
{
    private const SESSION_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.auth.guard' => 'agent-admin',
            'admin.route.prefix' => 'agent',
            'agent-admin.database.connection' => 'sqlite',
            'agent-admin.database.users_table' => 'agent_users',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        app('db')->purge('sqlite');

        Schema::connection('sqlite')->create('agent_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('session_id')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->dateTime('last_login_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_matching_session_is_allowed(): void
    {
        $this->insertAgent(self::SESSION_ID);
        $guard = new AgentGuardFake(1);

        $response = $this->runMiddleware($guard);

        $this->assertSame('next', $response->getContent());
        $this->assertFalse($guard->loggedOut);
    }

    public function test_mismatched_session_is_logged_out(): void
    {
        $this->insertAgent('new-session');
        $guard = new AgentGuardFake(1);

        $response = $this->runMiddleware($guard);

        $this->assertTrue($response->isRedirect(admin_url('auth/login')));
        $this->assertTrue($guard->loggedOut);
    }

    public function test_remember_login_renews_session_and_is_allowed(): void
    {
        $this->insertAgent('expired-session');
        $guard = new AgentGuardFake(1, true);

        $response = $this->runMiddleware($guard);
        $agent = app('db')->connection('sqlite')->table('agent_users')->find(1);

        $this->assertSame('next', $response->getContent());
        $this->assertFalse($guard->loggedOut);
        $this->assertSame(self::SESSION_ID, $agent->session_id);
        $this->assertSame('127.0.0.1', $agent->last_login_ip);
        $this->assertNotNull($agent->last_login_time);
    }

    private function insertAgent(string $sessionId): void
    {
        app('db')->connection('sqlite')->table('agent_users')->insert([
            'id' => 1,
            'status' => 1,
            'session_id' => $sessionId,
        ]);
    }

    private function runMiddleware(AgentGuardFake $guard)
    {
        Auth::shouldReceive('guard')->with('agent-admin')->andReturn($guard);

        $session = app('session.store');
        $session->start();
        $session->setId(self::SESSION_ID);

        $request = Request::create('/agent', 'GET');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        return (new CheckAgentUserStatus())->handle($request, fn () => response('next'));
    }
}

class AgentGuardFake
{
    public bool $loggedOut = false;

    public function __construct(private int $userId, private bool $remember = false)
    {
    }

    public function user(): object
    {
        return (object) ['id' => $this->userId];
    }

    public function viaRemember(): bool
    {
        return $this->remember;
    }

    public function logout(): void
    {
        $this->loggedOut = true;
    }
}
