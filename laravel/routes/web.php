<?php

use App\Http\Controllers\Invitations\PreviewInvitationController;
use App\Http\Controllers\Invitations\RedeemInvitationController;
use App\Http\Controllers\Invitations\RevokeInvitationController;
use App\Http\Controllers\Invitations\StoreInvitationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public preview + redeem-after-signup flow.
// Preview is throttled per IP; redeem requires an authenticated user
// (the controller enforces `auth`) so signed-out visitors get a 401
// instead of redeeming through this path.
Route::get('join/{token}', PreviewInvitationController::class)
    ->middleware('throttle:30,1')
    ->name('invitations.preview');

Route::post('join/{token}/redeem', RedeemInvitationController::class)
    ->middleware(['auth', 'throttle:30,1'])
    ->name('invitations.redeem');

Route::middleware(['auth', 'verified', 'ensure.current-account'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('contacts', 'contacts')->name('contacts');
    Route::inertia('pipelines', 'pipelines')->name('pipelines');
    Route::inertia('notifications', 'notifications')->name('notifications');
    Route::inertia('agents', 'agents')->name('agents');
    Route::inertia('broadcasts', 'broadcasts')->name('broadcasts');
    Route::inertia('broadcasts/new', 'broadcasts/new')->name('broadcasts.new');
    Route::get('broadcasts/{id}', fn (string $id) => inertia('broadcasts/show', ['id' => $id]))->name('broadcasts.show');

    Route::inertia('automations', 'automations')->name('automations');
    Route::inertia('automations/new', 'automations/new')->name('automations.new');
    Route::get('automations/{id}/edit', fn (string $id) => inertia('automations/edit', ['id' => $id]))->name('automations.edit');
    Route::get('automations/{id}/logs', fn (string $id) => inertia('automations/logs', ['id' => $id]))->name('automations.logs');

    Route::inertia('flows', 'flows')->name('flows');
    Route::get('flows/{id}/runs', fn (string $id) => inertia('flows/runs', ['id' => $id]))->name('flows.runs');
    Route::get('flows/{id}', fn (string $id) => inertia('flows/editor', ['id' => $id]))->name('flows.show');

    Route::inertia('inbox', 'inbox')->name('inbox');

    Route::post('invitations', StoreInvitationController::class)
        ->name('invitations.store');
    Route::delete('invitations/{invitation}', RevokeInvitationController::class)
        ->name('invitations.revoke');
});

require __DIR__.'/settings.php';
require __DIR__.'/accounts.php';
