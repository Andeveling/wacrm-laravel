<?php

use App\Domain\Automations\Actions\EditAutomation;
use App\Domain\Automations\Actions\ShowAutomationLogs;
use App\Domain\Automations\Actions\ShowAutomations;
use App\Domain\Contacts\Actions\BulkDestroyContacts;
use App\Domain\Contacts\Actions\DestroyContact;
use App\Domain\Contacts\Actions\DestroyCustomField;
use App\Domain\Contacts\Actions\DestroyTag;
use App\Domain\Contacts\Actions\ExportContacts;
use App\Domain\Contacts\Actions\ImportContacts;
use App\Domain\Contacts\Actions\ShowContacts;
use App\Domain\Contacts\Actions\StoreContact;
use App\Domain\Contacts\Actions\StoreCustomField;
use App\Domain\Contacts\Actions\StoreTag;
use App\Domain\Contacts\Actions\UpdateContact;
use App\Domain\Contacts\Actions\UpdateCustomField;
use App\Domain\Contacts\Actions\UpdateTag;
use App\Domain\Dashboard\Actions\ShowDashboard;
use App\Domain\Invitations\Actions\PreviewInvitation;
use App\Domain\Invitations\Actions\RedeemInvitation;
use App\Domain\Invitations\Actions\RevokeInvitation;
use App\Domain\Invitations\Actions\StoreInvitation;
use App\Domain\Pipelines\Actions\DestroyDeal;
use App\Domain\Pipelines\Actions\ShowPipelines;
use App\Domain\Pipelines\Actions\StoreDeal;
use App\Domain\Pipelines\Actions\UpdateDeal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Public preview + redeem-after-signup flow.
// Preview is throttled per IP; redeem requires an authenticated user
// (the route enforces `auth`) so signed-out visitors get a 401
// instead of redeeming through this path.
Route::get('join/{token}', PreviewInvitation::class)
    ->middleware('throttle:30,1')
    ->name('invitations.preview');

Route::post('join/{token}/redeem', RedeemInvitation::class)
    ->middleware(['auth', 'throttle:30,1'])
    ->name('invitations.redeem');

Route::middleware(['auth', 'verified', 'ensure.current-account'])->group(function () {
    Route::get('dashboard', ShowDashboard::class)->name('dashboard');
    Route::get('contacts', ShowContacts::class)->name('contacts');
    Route::post('contacts', StoreContact::class)->name('contacts.store');
    Route::patch('contacts/{contact}', UpdateContact::class)->name('contacts.update');
    Route::delete('contacts/{contact}', DestroyContact::class)->name('contacts.destroy');
    Route::delete('contacts', BulkDestroyContacts::class)->name('contacts.bulk-destroy');
    Route::post('contacts/import', ImportContacts::class)->name('contacts.import');
    Route::get('contacts/export', ExportContacts::class)->name('contacts.export');
    Route::post('contacts/custom-fields', StoreCustomField::class)->name('contacts.custom-fields.store');
    Route::patch('contacts/custom-fields/{customField}', UpdateCustomField::class)->name('contacts.custom-fields.update');
    Route::delete('contacts/custom-fields/{customField}', DestroyCustomField::class)->name('contacts.custom-fields.destroy');
    Route::post('contacts/tags', StoreTag::class)->name('contacts.tags.store');
    Route::patch('contacts/tags/{tag}', UpdateTag::class)->name('contacts.tags.update');
    Route::delete('contacts/tags/{tag}', DestroyTag::class)->name('contacts.tags.destroy');
    Route::get('pipelines', ShowPipelines::class)->name('pipelines');
    Route::post('pipelines/{pipeline}/deals', StoreDeal::class)->name('pipelines.deals.store');
    Route::patch('pipelines/deals/{deal}', UpdateDeal::class)->name('pipelines.deals.update');
    Route::delete('pipelines/deals/{deal}', DestroyDeal::class)->name('pipelines.deals.destroy');
    Route::inertia('notifications', 'notifications')->name('notifications');
    Route::inertia('agents', 'agents')->name('agents');
    Route::inertia('broadcasts', 'broadcasts')->name('broadcasts');
    Route::inertia('broadcasts/new', 'broadcasts/new')->name('broadcasts.new');
    Route::get('broadcasts/{id}', fn (string $id) => inertia('broadcasts/show', ['id' => $id]))->name('broadcasts.show');

    Route::get('automations', ShowAutomations::class)->name('automations');
    Route::inertia('automations/new', 'automations/new')->name('automations.new');
    Route::get('automations/{automation}/edit', EditAutomation::class)->name('automations.edit');
    Route::get('automations/{automation}/logs', ShowAutomationLogs::class)->name('automations.logs');

    Route::inertia('flows', 'flows')->name('flows');
    Route::get('flows/{id}/runs', fn (string $id) => inertia('flows/runs', ['id' => $id]))->name('flows.runs');
    Route::get('flows/{id}', fn (string $id) => inertia('flows/editor', ['id' => $id]))->name('flows.show');

    Route::inertia('inbox', 'inbox')->name('inbox');

    Route::post('invitations', StoreInvitation::class)
        ->name('invitations.store');
    Route::delete('invitations/{invitation}', RevokeInvitation::class)
        ->name('invitations.revoke');
});

require __DIR__.'/settings.php';
require __DIR__.'/accounts.php';
