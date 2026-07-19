<?php

use App\Domain\Accounts\Actions\ChangeMemberRole;
use App\Domain\Accounts\Actions\InviteMember;
use App\Domain\Accounts\Actions\ListMembers;
use App\Domain\Accounts\Actions\RemoveMember;
use App\Http\Controllers\Accounts\ShowSwitcherController;
use App\Http\Controllers\Accounts\SwitchAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('accounts/switch', ShowSwitcherController::class)->name('accounts.switch');
    Route::post('accounts/{account}/switch', SwitchAccountController::class)->name('accounts.switch.update');
});

Route::middleware(['auth', 'verified', 'ensure.current-account'])->group(function () {
    Route::post('accounts/{account}/members', InviteMember::class)->name('accounts.members.store');
    Route::get('accounts/{account}/members', ListMembers::class)->name('accounts.members.index');
    Route::patch('accounts/{account}/members/{member}', ChangeMemberRole::class)
        ->name('accounts.members.update');
    Route::delete('accounts/{account}/members/{member}', RemoveMember::class)
        ->name('accounts.members.destroy');
});
