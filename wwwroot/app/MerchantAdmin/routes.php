<?php

use App\MerchantAdmin\Controllers\CaptchaController;
use App\MerchantAdmin\Controllers\ExportDownloadController;
use App\MerchantAdmin\Controllers\ForbiddenController;
use App\MerchantAdmin\Controllers\SecureDcatApiController;
use App\MerchantAdmin\Controllers\SecureUploadController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Dcat\Admin\Admin;

Route::group([
    'prefix' => trim(config('merchant-admin.route.prefix'), '/') . '/dcat-api',
    'domain' => config('merchant-admin.route.domain'),
    'middleware' => array_merge(['admin.app:merchant-admin'], config('merchant-admin.route.middleware')),
    'as' => 'dcat-api.',
], function (Router $router) {
    $router->post('action', [SecureDcatApiController::class, 'action'])->name('action');
    $router->post('form', [SecureDcatApiController::class, 'form'])->name('form');
    $router->get('render', [SecureDcatApiController::class, 'render'])->name('render');
    $router->post('value', [SecureDcatApiController::class, 'value'])->name('value');
    $router->post('editor-md/upload', [SecureUploadController::class, 'disabled'])->name('editor-md.upload');
    $router->post('tinymce/upload', [SecureUploadController::class, 'disabled'])->name('tinymce.upload');
    $router->post('form/upload', [SecureUploadController::class, 'uploadFile'])->name('form.upload');
    $router->post('form/destroy-file', [SecureUploadController::class, 'destroyFile'])->name('form.destroy-file');
});

$blockedMerchantAuthRoutes = ['extensions', 'menu', 'permissions', 'roles', 'users'];

Admin::routes();

Route::group([
    'prefix'     => config('merchant-admin.route.prefix'),
    'middleware' => ['admin.app:merchant-admin', 'merchant.admin.security.headers', 'web', 'set.lang', 'merchant.config', 'check.merchant.user.status'],
], function (Router $router) use ($blockedMerchantAuthRoutes) {
    foreach ($blockedMerchantAuthRoutes as $route) {
        $router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], "auth/{$route}", [ForbiddenController::class, 'auth']);
        $router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], "auth/{$route}/{path}", [ForbiddenController::class, 'auth'])->where('path', '.*');
    }
});

Route::group([
    'prefix'     => config('merchant-admin.route.prefix'),
    'domain'     => config('merchant-admin.route.domain'),
    'middleware' => ['admin.app:merchant-admin', 'merchant.admin.security.headers', 'web', 'admin.auth', 'set.lang', 'merchant.config', 'check.merchant.user.status'],
], function (Router $router) {
    $router->get('/export-download/{type}/{filename}', [ExportDownloadController::class, 'download'])->where('filename', '[A-Za-z0-9._-]+\\.xlsx');
});

Route::group([
    'prefix'     => config('merchant-admin.route.prefix'),
    'namespace'  => config('merchant-admin.route.namespace'),
    'middleware' => array_merge(['admin.app:merchant-admin'], config('merchant-admin.route.middleware')),
], function (Router $router) {

    $router->get('/', 'HomeController@index');
    $router->get('/information', 'HomeController@information');
    $router->post('/auth/verify', 'AuthController@postVerify');
    $router->get('login-logs', 'LogController@index')->name('login-log.index');
    $router->resource('deposit-orders', 'DepositOrderController',['only'=>['index']]);
    $router->resource('transfer-orders', 'TransferOrderController',['only'=>['index']]);
    $router->get('/settlement-orders/apply', 'SettlementOrderController@apply')->name('settlement-orders-apply');
    $router->resource('settlement-orders', 'SettlementOrderController',['only'=>['index']]);
    $router->resource('balance-logs', "MerchantBalanceLogController",['only'=>['index']]);
    $router->resource('bank-codes', 'BankCodeController')->only(['index']);

    Route::post('captcha/get', [CaptchaController::class,'get'])->name('merchant-admin.captcha.get');
    Route::post('captcha/check', [CaptchaController::class,'check'])->name('merchant-admin.captcha.check');

    $router->resource('report-payments', 'ReportPaymentController')->only(['index']);
    $router->resource('report-merchants', 'ReportMerchantController')->only(['index']);

    $router->resource('musers', 'UserController');
    $router->resource('mroles', 'RoleController');

});
