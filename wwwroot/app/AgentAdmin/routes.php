<?php

use Dcat\Admin\Admin;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use App\AgentAdmin\Controllers\CaptchaController;

$routeAttributes = [
    'prefix' => config('agent-admin.route.prefix'),
    'middleware' => config('agent-admin.route.middleware'),
];

if (config('agent-admin.auth.enable', true)) {
    Route::group($routeAttributes, function (Router $router) {
        $authController = config('agent-admin.auth.controller');
        $router->get('auth/login', $authController . '@getLogin');
        $router->post('auth/login', $authController . '@postLogin');
        $router->get('auth/logout', $authController . '@getLogout');
        $router->get('auth/setting', $authController . '@getSetting');
        $router->put('auth/setting', $authController . '@putSetting');
    });
}

Admin::registerHelperRoutes();

// 禁用代理后台未使用的编辑器上传入口，避免任意扩展文件写入公开存储。
Route::group([
    'prefix' => admin_base_path('dcat-api'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {
    $router->post('editor-md/upload', function () {
        abort(403, 'EditorMD upload disabled');
    });
    $router->post('tinymce/upload', function () {
        abort(403, 'Tinymce upload disabled');
    });
});

Route::group([
    'prefix'     => config('agent-admin.route.prefix'),
    'namespace'  => config('agent-admin.route.namespace'),
    'middleware' => config('agent-admin.route.middleware'),
], function (Router $router) {
    $router->get('/', 'HomeController@index');
    $router->post('/auth/verify', 'AuthController@postVerify');
    $router->resource('reports-merchant-agents', 'ReportMerchantAgentController', ['only' => ['index']]);
    $router->resource('report-merchants', 'ReportMerchantController', ['only' => ['index']]);
    $router->resource('deposit-orders', 'DepositOrderController', ['only' => ['index']]);
    $router->resource('transfer-orders', 'TransferOrderController', ['only' => ['index']]);
    $router->resource('settlement-orders', 'SettlementOrderController', ['only' => ['index']]);
    $router->resource('payment-rates', 'PaymentRateController', ['only' => ['index']]);
    $router->resource('balance-logs', 'BalanceLogController', ['only' => ['index']]);
    $router->resource('merchant-users', 'MerchantUserController', ['only' => ['index']]);
    Route::post('captcha/get', [CaptchaController::class, 'get'])->middleware('throttle:agent-captcha-get')->name('agent-admin.captcha.get');
    Route::post('captcha/check', [CaptchaController::class, 'check'])->middleware('throttle:agent-captcha-check')->name('agent-admin.captcha.check');
});
