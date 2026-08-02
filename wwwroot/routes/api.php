<?php


use Illuminate\Support\Facades\Route;

Route::prefix('v3')->group(function () {
    Route::middleware(['checkapi'])->group(function () {
        Route::post('deposits', [App\Http\Controllers\Api\V3\HomeController::class, 'depositsIndex'])->name('api.v3.deposits');
        Route::post('deposits/query', [App\Http\Controllers\Api\V3\HomeController::class, 'depositsQuery']);
        Route::post('deposits/cashier/utr', [App\Http\Controllers\Api\V3\HomeController::class, 'submitUtr']);
        Route::post('deposits/cashier/card', [App\Http\Controllers\Api\V3\HomeController::class, 'submitCard']);
        Route::post('transfers', [App\Http\Controllers\Api\V3\HomeController::class, 'transfersIndex'])->name('api.v3.transfers');
        Route::post('transfers/query', [App\Http\Controllers\Api\V3\HomeController::class, 'transfersQuery']);
        Route::post('balance', [App\Http\Controllers\Api\V3\HomeController::class, 'balance'])->middleware('merchant.balance.throttle');
    });
    Route::post('transfers/check', [App\Http\Controllers\Api\V3\HomeController::class, 'transferCheck']);
});

Route::prefix('v2')->group(function () {

    Route::get('captcha/{config?}', [App\Http\Controllers\Api\V2\HomeController::class, 'getCaptcha'])->name('v2.captcha');
    Route::post('checkLogin', [App\Http\Controllers\Api\V2\HomeController::class, 'checkLogin'])->name('api.v2.check-login');
    Route::post('checkGoogleVcode', [App\Http\Controllers\Api\V2\HomeController::class, 'checkGoogleVcode'])->name('api.v2.check-google-vcode');

    Route::middleware([
        "auth:sanctum",
        "force.change.password",
        "check.user.status"
    ])->name('api.v2.users.')->group(function () {

        Route::get('users/setDepositNotice', [App\Http\Controllers\Api\V2\UserController::class, 'setDepositNotice'])->name('users.set-deposit-notice');
        Route::get('users/setTransferNotice', [App\Http\Controllers\Api\V2\UserController::class, 'setTransferNotice'])->name('users.set-transfer-notice');
        Route::get('users/setAutoRefresh', [App\Http\Controllers\Api\V2\UserController::class, 'setAutoRefresh'])->name('users.set-auto-refresh');


        Route::get('users/balanceLogIndex', [App\Http\Controllers\Api\V2\UserController::class, 'balanceLogIndex'])->name('users.balance-log-index');
        Route::get('users/teamBalanceLogIndex', [App\Http\Controllers\Api\V2\UserController::class, 'teamBalanceLogIndex'])->name('users.team-balance-log-index');
        Route::get('users/teamTransferOrderIndex', [App\Http\Controllers\Api\V2\UserController::class, 'teamTransferOrderIndex'])->name('users.team-transfer-order-index');
        Route::get('users/teamDepositOrderIndex', [App\Http\Controllers\Api\V2\UserController::class, 'teamDepositOrderIndex'])->name('users.team-deposit-order-index');
        Route::get('users/teamUserIndex', [App\Http\Controllers\Api\V2\UserController::class, 'teamUserIndex'])->name('users.team-user-index');
        Route::get('users/index', [App\Http\Controllers\Api\V2\UserController::class, 'index'])->name('users.index');
        Route::post('users/updatePassword', [App\Http\Controllers\Api\V2\UserController::class, 'updatePassword'])->name('users.update-password');
        Route::get('deposit-orders/index', [App\Http\Controllers\Api\V2\DepositOrderController::class, 'index'])->name('deposit-orders.index');
        Route::get('deposit-orders/logs', [App\Http\Controllers\Api\V2\DepositOrderController::class, 'logs'])->name('deposit-orders.logs');
        Route::post('deposit-orders/confirmPay', [App\Http\Controllers\Api\V2\DepositOrderController::class, 'confirmPay'])->name('deposit-orders.confirm-pay');

        Route::get('transfer-orders/logs', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'logs'])->name('transfer-orders.logs');
        Route::get('transfer-orders/index', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'index'])->name('transfer-orders.index');
        Route::get('transfer-orders/initOrder', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'initOrder'])->name('transfer-orders.init-order');
        Route::get('transfer-orders/searchOrder', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'searchOrder'])->name('transfer-orders.search-order');
        Route::get('transfer-orders/receviceOrder', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'receviceOrder'])->name('transfer-orders.receive-order');
        Route::get('transfer-orders/cancelOrder', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'cancelOrder'])->name('transfer-orders.cancel-order');
        Route::post('transfer-orders/submitOrder', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'submitOrder'])->name('transfer-orders.submit-order');
        Route::post('transfer-orders/uploadImage', [App\Http\Controllers\Api\V2\TransferOrderController::class, 'uploadImage'])->name('transfer-orders.upload-image');

        Route::get('user-banks/setStatus/{id}', [App\Http\Controllers\Api\V2\UserBankController::class, 'setStatus'])->name('user-banks.set-status');
        Route::get('user-banks/clearBank', [App\Http\Controllers\Api\V2\UserBankController::class, 'clearBank'])->name('user-banks.clear-bank');
        Route::get('user-banks/closeAll', [App\Http\Controllers\Api\V2\UserBankController::class, 'closeAll'])->name('user-banks.close-all');
        Route::resource('user-banks', App\Http\Controllers\Api\V2\UserBankController::class)->only(['index', 'store', 'update', 'destroy']);


        Route::post('agent-users/confirm-pay', [App\Http\Controllers\Api\V2\AgentUserController::class, 'confirmPay'])->name('agent-users.confirm-pay');
        Route::get('agent-users/clear-bank', [App\Http\Controllers\Api\V2\AgentUserController::class, 'clearBank'])->name('agent-users.clear-bank');
        Route::get('agent-users/set-status/{id}', [App\Http\Controllers\Api\V2\AgentUserController::class, 'setStatus'])->name('agent-users.set-status');
        Route::delete('agent-users/bank-destroy/{id}', [App\Http\Controllers\Api\V2\AgentUserController::class, 'bankDestroy'])->name('agent-users.bank-destroy');
        Route::put('agent-users/bank-update/{id}', [App\Http\Controllers\Api\V2\AgentUserController::class, 'bankUpdate'])->name('agent-users.bank-update');
        Route::post('agent-users/bank-store', [App\Http\Controllers\Api\V2\AgentUserController::class, 'bankStore'])->name('agent-users.bank-store');
        Route::get('agent-users/bank-index', [App\Http\Controllers\Api\V2\AgentUserController::class, 'bankIndex'])->name('agent-users.bank-index');
    });

});
