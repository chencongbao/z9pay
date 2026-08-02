<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\TelegramController;

Route::get('/',[HomeController::class,"index"]);
Route::any("test",[TestController::class,"index"])->name('test');
Route::any("test1",[TestController::class,"test1"])->name('test1');
Route::any("test2",[TestController::class,"test2"])->name('test2');
Route::any("notice",[TestController::class,"notice"])->name('notice');
Route::get('/query',[TestController::class,"query"])->name('query');
Route::get('/login',[HomeController::class,"index"])->name('login');
Route::get('/downExcel',[HomeController::class,"downExcel"])->name('downExcel');
Route::any("ip",[TestController::class,"ip"])->name('ip');
Route::get("domain",[TestController::class,"domain"])->name('domain');

//收营台域名
Route::middleware(["check.domain"])->group(function (){
    Route::get('/cashier', [CashierController::class, 'index'])->name('cashier');
    Route::get('/cashier/gold', [CashierController::class, 'gold'])->name('cashier.gold');
    Route::get('/cashier/deposit/gold/order', [CashierController::class, 'getDepositGoldOrder'])->name('cashier.deposit.gold.order');
    Route::get('/cashier/deposit/order', [CashierController::class, 'getDepositOrder'])->name('cashier.deposit.order');
    Route::post('/cashier/deposit/payname', [CashierController::class, 'setPayName'])->name('cashier.deposit.payname');
    Route::get('/cashier/deposit/cancel', [CashierController::class, 'cancelDepositOrder'])->name('cashier.deposit.cancel');
    Route::get('/cashier/deposit/download', [CashierController::class, 'downloadQrcode'])->name('cashier.deposit.download');
    Route::any("cashier/callback/url", [CashierController::class, 'callbackUrl'])->name('cashier.callback.url');
    Route::post('cashier/upload/certificate', [CashierController::class, 'uploadPayCertificate'])->name('cashier.upload.certificate');
    Route::post('cashier/confirmPay', [CashierController::class, 'confirmPay'])->name('cashier.confirmPay');
    Route::get('/cashier/deposit/query', [CashierController::class, 'queryDepositOrderStatus'])->name('cashier.deposit.query');
});

Route::group(array_filter(['domain'=>config('default.telegram_webhook_domain')]),function (){
    Route::post('telegram/webhook', [TelegramController::class, 'webhook'])->name('telegram.webhook');
});
