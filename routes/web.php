<?php

use App\Domain\Automations\Actions\EditAutomation;
use App\Domain\Automations\Actions\ShowAutomationLogs;
use App\Domain\Automations\Actions\ShowAutomations;
use App\Domain\Automations\Actions\ShowNewAutomation;
use App\Domain\Automations\Actions\StoreAutomation;
use App\Domain\Broadcasts\Actions\CountBroadcastAudience;
use App\Domain\Broadcasts\Actions\ShowBroadcast;
use App\Domain\Broadcasts\Actions\ShowBroadcasts;
use App\Domain\Broadcasts\Actions\ShowNewBroadcast;
use App\Domain\Broadcasts\Actions\StoreBroadcast;
use App\Domain\Contacts\Actions\BulkDestroyContacts;
use App\Domain\Contacts\Actions\DestroyContact;
use App\Domain\Contacts\Actions\DestroyContactNote;
use App\Domain\Contacts\Actions\DestroyCustomField;
use App\Domain\Contacts\Actions\DestroyTag;
use App\Domain\Contacts\Actions\ExportContacts;
use App\Domain\Contacts\Actions\ImportContacts;
use App\Domain\Contacts\Actions\ShowContactCustomValues;
use App\Domain\Contacts\Actions\ShowContactDeals;
use App\Domain\Contacts\Actions\ShowContactNotes;
use App\Domain\Contacts\Actions\ShowContacts;
use App\Domain\Contacts\Actions\StoreContact;
use App\Domain\Contacts\Actions\StoreContactCustomValues;
use App\Domain\Contacts\Actions\StoreContactNote;
use App\Domain\Contacts\Actions\StoreCustomField;
use App\Domain\Contacts\Actions\StoreTag;
use App\Domain\Contacts\Actions\UpdateContact;
use App\Domain\Contacts\Actions\UpdateCustomField;
use App\Domain\Contacts\Actions\UpdateTag;
use App\Domain\Dashboard\Actions\ShowDashboard;
use App\Domain\Flows\Actions\ShowFlowEditor;
use App\Domain\Flows\Actions\ShowFlowRuns;
use App\Domain\Flows\Actions\ShowFlows;
use App\Domain\Inbox\Actions\ShowInbox;
use App\Domain\Inbox\Actions\StoreInboxConversation;
use App\Domain\Inbox\Actions\StoreInboxMessage;
use App\Domain\Invitations\Actions\PreviewInvitation;
use App\Domain\Invitations\Actions\RedeemInvitation;
use App\Domain\Invitations\Actions\RegenerateInvitation;
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
    Route::get('contacts/{contact}/notes', ShowContactNotes::class)->name('contacts.notes');
    Route::post('contacts/{contact}/notes', StoreContactNote::class)->name('contacts.notes.store');
    Route::delete('contacts/notes/{note}', DestroyContactNote::class)->name('contacts.notes.destroy');
    Route::get('contacts/{contact}/custom-values', ShowContactCustomValues::class)->name('contacts.custom-values');
    Route::post('contacts/{contact}/custom-values', StoreContactCustomValues::class)->name('contacts.custom-values.store');
    Route::get('contacts/{contact}/deals', ShowContactDeals::class)->name('contacts.deals');
    Route::get('pipelines', ShowPipelines::class)->name('pipelines');
    Route::post('pipelines/{pipeline}/deals', StoreDeal::class)->name('pipelines.deals.store');
    Route::patch('pipelines/deals/{deal}', UpdateDeal::class)->name('pipelines.deals.update');
    Route::delete('pipelines/deals/{deal}', DestroyDeal::class)->name('pipelines.deals.destroy');
    Route::inertia('notifications', 'notifications')->name('notifications');
    Route::inertia('agents', 'agents')->name('agents');
    Route::get('broadcasts', ShowBroadcasts::class)->name('broadcasts');
    Route::get('broadcasts/new', ShowNewBroadcast::class)->name('broadcasts.new');
    Route::get('broadcasts/audience-count', CountBroadcastAudience::class)->name('broadcasts.audience-count');
    Route::post('broadcasts', StoreBroadcast::class)->name('broadcasts.store');
    Route::get('broadcasts/{id}', ShowBroadcast::class)->name('broadcasts.show');

    Route::get('automations', ShowAutomations::class)->name('automations');
    Route::get('automations/new', ShowNewAutomation::class)->name('automations.new');
    Route::post('automations', StoreAutomation::class)->name('automations.store');
    Route::get('automations/{automation}/edit', EditAutomation::class)->name('automations.edit');
    Route::get('automations/{automation}/logs', ShowAutomationLogs::class)->name('automations.logs');

    Route::get('flows', ShowFlows::class)->name('flows');
    Route::get('flows/{id}/runs', ShowFlowRuns::class)->name('flows.runs');
    Route::get('flows/{id}', ShowFlowEditor::class)->name('flows.show');

    Route::get('inbox', ShowInbox::class)->name('inbox');
    Route::post('inbox/conversations', StoreInboxConversation::class)->name('inbox.conversations.store');
    Route::post('inbox/{conversation}/messages', StoreInboxMessage::class)->name('inbox.messages.store');

    Route::post('invitations', StoreInvitation::class)
        ->name('invitations.store');
    Route::delete('invitations/{invitation}', RevokeInvitation::class)
        ->name('invitations.revoke');
    Route::post('invitations/{invitation}/regenerate', RegenerateInvitation::class)
        ->name('invitations.regenerate');
});

require __DIR__.'/settings.php';
require __DIR__.'/accounts.php';
