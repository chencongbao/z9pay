<?php

namespace Tests\Feature;

use Closure;
use Tests\TestCase;
use App\AgentAdmin\Controllers\AuthController;

class AgentAdminRouteSecurityTest extends TestCase
{
    public function test_agent_admin_only_registers_required_auth_routes(): void
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $routes = collect(app('router')->getRoutes()->getRoutes());

        foreach (['auth/users', 'auth/menu', 'auth/extensions', 'auth/roles', 'auth/permissions'] as $path) {
            $uri = $prefix . '/' . $path;
            $this->assertFalse($routes->contains(fn ($route) => $route->uri() === $uri || str_starts_with($route->uri(), $uri . '/')));
        }

        $requiredRoutes = [
            ['GET', 'auth/login', 'getLogin'],
            ['POST', 'auth/login', 'postLogin'],
            ['GET', 'auth/logout', 'getLogout'],
            ['GET', 'auth/setting', 'getSetting'],
            ['PUT', 'auth/setting', 'putSetting'],
            ['POST', 'auth/verify', 'postVerify'],
        ];

        foreach ($requiredRoutes as [$method, $path, $action]) {
            $route = $routes->first(fn ($route) => $route->uri() === $prefix . '/' . $path && in_array($method, $route->methods(), true));
            $this->assertNotNull($route, "Missing agent route: {$method} {$path}");
            $this->assertSame(AuthController::class . '@' . $action, $route->getActionName());
        }
    }

    public function test_agent_editor_upload_routes_are_disabled_after_dcat_helpers_register(): void
    {
        $prefix = trim((string) config('agent-admin.route.prefix'), '/');
        $routes = collect(app('router')->getRoutes()->getRoutes());

        foreach (['editor-md/upload', 'tinymce/upload'] as $path) {
            $uri = $prefix . '/dcat-api/' . $path;
            $route = $routes->last(fn ($route) => $route->uri() === $uri && in_array('POST', $route->methods(), true));

            $this->assertNotNull($route, "Missing disabled agent upload route: POST {$uri}");
            $this->assertInstanceOf(Closure::class, $route->getAction('uses'));

            $url = $route->getDomain() ? 'http://' . $route->getDomain() . '/' . $uri : '/' . $uri;

            $this->withoutMiddleware()->post($url)->assertForbidden();
        }
    }
}
