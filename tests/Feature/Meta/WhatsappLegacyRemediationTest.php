<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\AccountRole;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Enums\WhatsappLegacyMigrationIssueKind;
use App\Models\User;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappLegacyMigrationIssue;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Account, 2: WhatsappPhoneNumberConnection}
 */
function remediableConnection(string $role = 'admin'): array
{
    [$actor, $account] = memberWithRole($role);
    $integration = WhatsappIntegration::factory()->for($account)->create([
        'created_by' => $actor->id,
        'access_token' => 'never-return-this-token',
    ]);
    $waba = WabaSubscription::factory()->forIntegration($integration)->create([
        'account_id' => $account->id,
        'waba_id' => 'waba-'.$account->id,
    ]);
    $connection = WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-'.$account->id,
        'readiness' => WhatsappConnectionReadiness::Active,
    ]);

    return [$actor, $account, $connection];
}

test('settings lists unresolved legacy migration issues without tokens', function () {
    [$admin, $account, $connection] = remediableConnection();
    $contact = Contact::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'name' => 'Ana Pérez',
    ]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'contact_id' => $contact->id,
    ]);
    WhatsappLegacyMigrationIssue::factory()->create([
        'account_id' => $account->id,
        'conversation_id' => $conversation->id,
        'kind' => WhatsappLegacyMigrationIssueKind::AmbiguousConversationConnection,
        'details' => [
            'candidate_connections' => 2,
            'action' => 'select_connection_explicitly',
            'access_token' => 'never-return-this-token',
        ],
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.whatsapp'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/whatsapp')
        ->has('legacyIssues', 1)
        ->where('legacyIssues.0.kind', 'ambiguous_conversation_connection')
        ->where('legacyIssues.0.conversation_id', $conversation->id)
        ->where('legacyIssues.0.contact_name', 'Ana Pérez')
        ->where('legacyIssues.0.action', 'select_connection_explicitly')
        ->missing('legacyIssues.0.details.access_token')
        ->where('connections.0.id', $connection->id)
    );

    expect($response->getContent())->not->toContain('never-return-this-token');
});

test('admin can assign a connection to an ambiguous conversation', function () {
    [$admin, $account, $connection] = remediableConnection();
    $contact = Contact::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'name' => 'Ana Pérez',
    ]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'contact_id' => $contact->id,
    ]);
    $issue = WhatsappLegacyMigrationIssue::factory()->create([
        'account_id' => $account->id,
        'conversation_id' => $conversation->id,
        'kind' => WhatsappLegacyMigrationIssueKind::AmbiguousConversationConnection,
        'details' => [
            'candidate_connections' => 2,
            'action' => 'select_connection_explicitly',
        ],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('settings.whatsapp'))
        ->post(route('settings.whatsapp.legacy-issues.assign', $issue), [
            'connection_id' => $connection->id,
        ])
        ->assertRedirect(route('settings.whatsapp'));

    expect($conversation->fresh()->connection_id)->toBe($connection->id)
        ->and($issue->fresh()->resolved_at)->not->toBeNull();
});

test('members cannot assign a legacy conversation', function () {
    [$admin, $account, $connection] = remediableConnection();
    $member = attachUserToAccount($account, AccountRole::Member);
    $contact = Contact::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
    ]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'contact_id' => $contact->id,
    ]);
    $issue = WhatsappLegacyMigrationIssue::factory()->create([
        'account_id' => $account->id,
        'conversation_id' => $conversation->id,
        'kind' => WhatsappLegacyMigrationIssueKind::AmbiguousConversationConnection,
        'details' => ['action' => 'select_connection_explicitly'],
    ]);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.legacy-issues.assign', $issue), [
            'connection_id' => $connection->id,
        ])
        ->assertForbidden();

    expect($conversation->fresh()->connection_id)->toBeNull()
        ->and($issue->fresh()->resolved_at)->toBeNull();
});

test('admin can dismiss a claimed WABA issue without assigning a conversation', function () {
    [$admin, $account] = remediableConnection();
    $issue = WhatsappLegacyMigrationIssue::factory()->create([
        'account_id' => $account->id,
        'kind' => WhatsappLegacyMigrationIssueKind::WabaClaimedByAnotherAccount,
        'details' => [
            'resource' => 'waba_id',
            'action' => 'explicit_reconnect_required',
        ],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('settings.whatsapp'))
        ->post(route('settings.whatsapp.legacy-issues.dismiss', $issue))
        ->assertRedirect(route('settings.whatsapp'));

    expect($issue->fresh()->resolved_at)->not->toBeNull();
});

test('members cannot dismiss a claimed WABA issue', function () {
    [$admin, $account] = remediableConnection();
    $member = attachUserToAccount($account, AccountRole::Member);
    $issue = WhatsappLegacyMigrationIssue::factory()->create([
        'account_id' => $account->id,
        'kind' => WhatsappLegacyMigrationIssueKind::WabaClaimedByAnotherAccount,
        'details' => ['action' => 'explicit_reconnect_required'],
    ]);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.legacy-issues.dismiss', $issue))
        ->assertForbidden();

    expect($issue->fresh()->resolved_at)->toBeNull();
});

test('a resolved issue is omitted from settings', function () {
    [$admin, $account] = remediableConnection();
    WhatsappLegacyMigrationIssue::factory()->create([
        'account_id' => $account->id,
        'kind' => WhatsappLegacyMigrationIssueKind::IncompleteLegacyConfig,
        'details' => ['missing' => ['waba_id'], 'action' => 'explicit_reconnect_required'],
        'resolved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/whatsapp')
            ->has('legacyIssues', 0)
        );
});

test('a legacy issue from another account cannot be assigned or dismissed', function () {
    [$admin, $account, $connection] = remediableConnection();
    [, $foreignAccount] = remediableConnection();
    $issue = WhatsappLegacyMigrationIssue::factory()->create([
        'account_id' => $foreignAccount->id,
        'kind' => WhatsappLegacyMigrationIssueKind::WabaClaimedByAnotherAccount,
        'details' => ['action' => 'explicit_reconnect_required'],
    ]);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.legacy-issues.assign', $issue), [
            'connection_id' => $connection->id,
        ])
        ->assertNotFound();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.legacy-issues.dismiss', $issue))
        ->assertNotFound();

    expect($issue->fresh()->resolved_at)->toBeNull();
});
