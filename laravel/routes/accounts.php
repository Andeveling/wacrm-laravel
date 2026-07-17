<?php

use App\Http\Controllers\Accounts\ShowSwitcherController;
use App\Http\Controllers\Accounts\SwitchAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('accounts/switch', ShowSwitcherController::class)->name('accounts.switch');
    Route::post('accounts/{account}/switch', SwitchAccountController::class)->name('accounts.switch.update');
});
