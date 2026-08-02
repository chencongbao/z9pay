<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{

    public function index(Request $request)
    {
        if (! config('app.debug')) {
            abort(404);
        }

        return response()->view('home.entry', [
            'entries' => [
                ['name' => __('admin.manager_admin_title'), 'url' => $this->adminUrl($request, 'admin')],
                ['name' => __('admin.merchant_admin_title'), 'url' => $this->adminUrl($request, 'merchant-admin')],
                ['name' => __('admin.agent_admin_title'), 'url' => $this->adminUrl($request, 'agent-admin')],
            ],
        ]);
    }

    public function downExcel()
    {
        return response()->download(realpath(base_path('public')).'/style/example.xlsx', 'example.xlsx');
    }

    private function adminUrl(Request $request, string $configKey): string
    {
        $domain = config($configKey . '.route.domain');
        $prefix = trim((string) config($configKey . '.route.prefix', $configKey), '/');

        if ($domain) {
            return $request->getScheme() . '://' . trim($domain, '/') . '/' . $prefix;
        }

        return url($prefix);
    }

}
