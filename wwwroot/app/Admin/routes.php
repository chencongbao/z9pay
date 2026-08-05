<?php

use App\Admin\Controllers\ActivityLogController;
use App\Admin\Controllers\CaptchaController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Dcat\Admin\Admin;

Route::group([
    'prefix' => admin_base_path('dcat-api'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {
    $router->post('tinymce/upload', function () {
        abort(403, 'Tinymce upload disabled');
    });
});

Admin::routes();

Route::group([
    'prefix' => config('admin.route.prefix'),
    'namespace' => config('admin.route.namespace'),
    'middleware' => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index');
    $router->get('dashboard', 'HomeController@dashboard')->name('home.dashboard');
    $router->get('/api/deoisit/test', 'HomeController@apiDepositTest');
    $router->get('/api/transfer/test', 'HomeController@apiTransferTest');
    $router->get('/payment', 'HomeController@payment')->name('home.payment');
    $router->get('/telegramQunSend', 'HomeController@telegramQunSend');

    $router->post('auth/verify', 'AuthController@postVerify');

    $router->resource('auth/users', 'Admin\UserController');
    $router->resource('auth/roles', 'Admin\RoleController');
    $router->resource('auth/menu', 'Admin\MenuController');
    $router->resource('auth/permissions', 'Admin\PermissionController');

    //商户
    $router->get('merchant/user/detail', "Merchant\UserController@detail")->name("merchant.user.detail");

    //商户
    $router->group([
        'prefix' => 'merchant/auth',
        'as'     => 'merchant.',
    ], function (Router $router) {
        $router->resource('users', 'Merchant\UserController');
        $router->resource('menu', 'Merchant\MenuController');
        $router->resource('permissions', 'Merchant\PermissionController');
        $router->resource('roles', 'Merchant\RoleController');
    });

    //商户代理
    $router->group([
        'prefix' => 'agent/auth',
        'as'     => 'agent.',
    ], function (Router $router) {
        $router->resource('users', 'Agent\UserController');
        $router->resource('menu', 'Agent\MenuController');
        $router->resource('permissions', 'Agent\PermissionController');
        $router->resource('roles', 'Agent\RoleController');
    });

    $router->resource('merchant-payments', 'Merchant\PaymentController');
    $router->resource('rates-agent-payments', 'Agent\PaymentRateController');
    $router->resource('day-balance-logs', 'Merchant\DayBalanceLogController');
    $router->resource('user-day-balance-logs', 'User\DayBalanceLogController');


    $router->resource('tusers', 'UserController');
    $router->get('bank-users/parseQrcodeUrl', 'UserBankController@parseQrcodeUrl')->name('bank-users.parseQrcodeUrl');
    $router->resource('bank-users', 'UserBankController');
    $router->resource('agents', 'AgentController');

    $router->resource('bank-codes', 'BankCodeController');


    $router->resource('channels', 'ChannelController');
    $router->resource('channel-accounts', 'ChannelAccountController');
    $router->resource('merchant-channels', 'MerchantChannelController');
    $router->resource('rates-channels', 'ChannelRateController');

    $router->get('group-users/rule', 'UserGroupController@rules');
    $router->resource('group-users', 'UserGroupController');

    $router->resource('deposit-orders', 'DepositOrderController');
    $router->resource('transfer-orders', 'TransferOrderController');
    $router->resource('freeze-orders', 'FreezeOrderController');
    $router->resource('settlement-orders', 'SettlementOrderController');
    $router->resource('merchant-balance-logs', 'MerchantBalanceLogController');
    $router->resource('agent-balance-logs', 'AgentBalanceLogController');
    $router->resource('user-agent-rates', 'UserAgentRateController');
    $router->resource('user-agent-balance-logs', 'UserAgentBalanceLogController');

    // 按端分开展示：登录日志
    $router->get('admin-login-logs', 'LoginLogController@index')->defaults('log_name', 'admin');
    $router->get('merchant-login-logs', 'LoginLogController@index')->defaults('log_name', 'merchant');
    $router->get('agent-login-logs', 'LoginLogController@index')->defaults('log_name', 'agent');
    $router->get('user-login-logs', 'LoginLogController@index')->defaults('log_name', 'user');

    // 按端分开展示：操作日志
    $router->get('admin-operation-logs', 'OtherLogController@index')->defaults('log_name', 'admin');
    $router->get('merchant-operation-logs', 'OtherLogController@index')->defaults('log_name', 'merchant');
    $router->get('agent-operation-logs', 'OtherLogController@index')->defaults('log_name', 'agent');
    $router->get('user-operation-logs', 'OtherLogController@index')->defaults('log_name', 'user');

    $router->resource('user-balance-logs', 'UserBalanceLogController')->only(['index']);

    $router->resource('black-contents', 'BlackContentController');
    $router->resource('ip-blacklists', 'IpBlacklistController')->only(['index', 'destroy']);


    //report
    $router->resource('report-days', 'ReportDayController')->only(['index']);
    $router->resource('report-merchants', 'ReportMerchantController')->only(['index']);
    $router->resource('report-channels', 'ReportChannelController')->only(['index']);
    $router->resource('report-currency-merchants', 'ReportCurrencyMerchantController')->only(['index']);
    $router->resource('report-currencys', 'ReportCurrencyController')->only(['index']);
    $router->resource('report-payments', 'ReportPaymentController')->only(['index']);
    $router->resource('report-users', 'ReportUserController')->only(['index']);
    $router->resource('report-user-banks', 'ReportUserBankController')->only(['index']);
    $router->resource('report-merchant-agents', 'ReportMerchantAgentController')->only(['index']);
    $router->resource('report-user-agents', 'ReportUserAgentController')->only(['index']);


    //ajax
    $router->get('ajax/merchantChannelPaymentField', 'AjaxController@merchantChannelPaymentField')->name('ajax.merchantChannelPaymentField');
    $router->get('ajax/merchantChannelBatchPaymentField', 'AjaxController@merchantChannelBatchPaymentField')->name('ajax.merchantChannelBatchPaymentField');
    $router->get('ajax/getBankCode', 'AjaxController@getBankCode')->name('ajax.getBankCode');
    $router->get('ajax/deleteChannelBankCode', 'AjaxController@deleteChannelBankCode')->name('ajax.deleteChannelBankCode');
    $router->get('ajax/getMerchantInfo', 'AjaxController@getMerchantInfo')->name('ajax.getMerchantInfo');
    $router->get('ajax/getUserBankList', 'AjaxController@getUserBankList')->name('ajax.getUserBankList');
    $router->get('ajax/getMerchantList', 'AjaxController@getMerchantList')->name('ajax.getMerchantList');
    $router->get('ajax/getUserList', 'AjaxController@getUserList')->name('ajax.getUserList');
    $router->get('ajax/getMerchantTransferChannel', 'AjaxController@getMerchantTransferChannel')->name('ajax.getMerchantTransferChannel');

    Route::post('captcha/get', [CaptchaController::class,'get'])->name('admin.captcha.get');
    Route::post('captcha/check', [CaptchaController::class,'check'])->name('admin.captcha.check');

    $router->get('config/base', 'ConfigController@base');
    $router->get('config/deposit', 'ConfigController@deposit');
    $router->get('config/transfer', 'ConfigController@transfer');
    $router->get('config/notice', 'ConfigController@notice');
    $router->get('config/telegram', 'ConfigController@telegram');
    $router->get('config/merchant', 'ConfigController@merchant');
    $router->get('config/risk', 'ConfigController@risk');
    $router->get('config/okx', 'ConfigController@okx');
    $router->get('config/security', 'ConfigController@security');


    $router->get('today/index', 'TodayCentusController@index')->name("today.index");
    $router->get('today/merchantBenefit', 'TodayCentusController@merchantBenefit');
    $router->get('today/channelBenefit', 'TodayCentusController@channelBenefit');
    $router->get('today/userBenefit', 'TodayCentusController@userBenefit');
    $router->get('today/bankBenefit', 'TodayCentusController@bankBenefit');


    $router->get('selfchannels/index', 'SelfChannelController@index');
    $router->get('selfchannels/config', 'SelfChannelController@config');

    Route::resource('activity-logs', ActivityLogController::class)->only(['index']);
});
