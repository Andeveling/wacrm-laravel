<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('join/{token}', \App\Http\Controllers\Invitations\PreviewInvitationController::class)
    ->middleware('throttle:30,1')
    ->name('invitations.preview');

Route::middleware(['auth', 'verified', 'ensure.current-account'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('invitations', \App\Http\Controllers\Invitations\StoreInvitationController::class)
        ->name('invitations.store');
    Route::delete('invitations/{invitation}', \App\Http\Controllers\Invitations\RevokeInvitationController::class)
        ->name('invitations.revoke');
});

require __DIR__.'/settings.php';
require __DIR__.'/accounts.php';
