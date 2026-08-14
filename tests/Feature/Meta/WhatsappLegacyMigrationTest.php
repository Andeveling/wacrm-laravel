<?php

declare(strict_types=1);

use App\Domain\Meta\Services\LegacyWhatsappConfigurationMigrator;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Enums\WhatsappLegacyMigrationIssueKind;
use App\Models\Scopes\AccountScope;
use App\Models\User;
use App\Models\WabaSubscription;
use App\Models\WhatsappConfig;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappLegacyMigrationIssue;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('preserves a legacy configuration in encrypted integration records and maps its conversation', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    $legacyToken = 'legacy-secret-token';
    $legacy = WhatsappConfig::factory()->connected()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'access_token' => $legacyToken,
    ]);
    $contact = Contact::factory()->create(['account_id' => $account->id]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'contact_id' => $contact->id,
    ]);

    app(LegacyWhatsappConfigurationMigrator::class)->run();

    $integration = WhatsappIntegration::withoutGlobalScopes()
        ->where('legacy_config_id', $legacy->id)
        ->firstOrFail();
    $connection = WhatsappPhoneNumberConnection::withoutGlobalScopes()
        ->where('legacy_config_id', $legacy->id)
        ->firstOrFail();

    expect($integration->access_token)->toBe($legacyToken)
        ->and(DB::table('whatsapp_integrations')->where('id', $integration->id)->value('access_token'))
        ->not->toBe($legacyToken)
        ->and($connection->readiness)->toBe(WhatsappConnectionReadiness::WebhookWaiting)
        ->and($connection->is_default)->toBeFalse()
        ->and($conversation->fresh()->connection_id)->toBe($connection->id)
        ->and(WhatsappConfig::withoutGlobalScopes()->find($legacy->id))->not->toBeNull();
});

test('reports a conversation that has no legacy connection instead of assigning it silently', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);
    $contact = Contact::factory()->create(['account_id' => $account->id]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'contact_id' => $contact->id,
    ]);

    app(LegacyWhatsappConfigurationMigrator::class)->run();

    $issue = WhatsappLegacyMigrationIssue::withoutGlobalScopes()
        ->where('conversation_id', $conversation->id)
        ->firstOrFail();

    expect($conversation->fresh()->connection_id)->toBeNull()
        ->and($issue->kind)->toBe(WhatsappLegacyMigrationIssueKind::MissingLegacyConnection)
        ->and($issue->details)->toEqual([
            'candidate_connections' => 0,
            'action' => 'select_connection_explicitly',
        ]);
});

test('does not claim a WABA already represented by another account', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $user = User::factory()->create();

    app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
    WhatsappConfig::factory()->create([
        'account_id' => $accountA->id,
        'user_id' => $user->id,
        'waba_id' => 'waba-shared',
        'phone_number_id' => 'phone-a',
    ]);

    app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
    $conflicting = WhatsappConfig::factory()->create([
        'account_id' => $accountB->id,
        'user_id' => $user->id,
        'waba_id' => 'waba-shared',
        'phone_number_id' => 'phone-b',
    ]);

    app(LegacyWhatsappConfigurationMigrator::class)->run();

    expect(WabaSubscription::withoutGlobalScopes()->where('waba_id', 'waba-shared')->count())->toBe(1)
        ->and(WhatsappLegacyMigrationIssue::withoutGlobalScopes()
            ->where('legacy_config_id', $conflicting->id)
            ->where('kind', WhatsappLegacyMigrationIssueKind::WabaClaimedByAnotherAccount->value)
            ->exists())->toBeTrue();
});

test('reports a phone number already represented by another account', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $user = User::factory()->create();

    app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
    WhatsappPhoneNumberConnection::factory()->create([
        'account_id' => $accountA->id,
        'phone_number_id' => 'phone-shared',
    ]);

    app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
    $conflicting = WhatsappConfig::factory()->create([
        'account_id' => $accountB->id,
        'user_id' => $user->id,
        'phone_number_id' => 'phone-shared',
    ]);

    app(LegacyWhatsappConfigurationMigrator::class)->run();

    expect(WhatsappLegacyMigrationIssue::withoutGlobalScopes()
        ->where('legacy_config_id', $conflicting->id)
        ->where('kind', WhatsappLegacyMigrationIssueKind::PhoneNumberClaimedByAnotherAccount->value)
        ->exists())->toBeTrue();
});

test('allows only one active default connection per account', function () {
    $account = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    WhatsappPhoneNumberConnection::factory()->active()->create([
        'account_id' => $account->id,
        'is_default' => true,
    ]);

    $this->expectException(QueryException::class);
    WhatsappPhoneNumberConnection::factory()->active()->create([
        'account_id' => $account->id,
        'is_default' => true,
    ]);
});

test('allows the same contact to have one conversation per connection', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);
    $contact = Contact::factory()->create(['account_id' => $account->id]);
    $connectionA = WhatsappPhoneNumberConnection::factory()->create(['account_id' => $account->id]);
    $connectionB = WhatsappPhoneNumberConnection::factory()->create(['account_id' => $account->id]);

    Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'connection_id' => $connectionA->id,
    ]);
    Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'connection_id' => $connectionB->id,
    ]);

    expect(Conversation::withoutGlobalScopes()->where('contact_id', $contact->id)->count())->toBe(2);

    $this->expectException(QueryException::class);
    Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'connection_id' => $connectionA->id,
    ]);
});

test('rerunning the legacy migration is idempotent', function () {
    $account = Account::factory()->create();
    $user = User::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);
    WhatsappConfig::factory()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);

    $migrator = app(LegacyWhatsappConfigurationMigrator::class);
    $migrator->run();
    $migrator->run();

    expect(WhatsappIntegration::withoutGlobalScopes()->where('account_id', $account->id)->count())->toBe(1)
        ->and(WhatsappPhoneNumberConnection::withoutGlobalScopes()->where('account_id', $account->id)->count())->toBe(1)
        ->and(WhatsappLegacyMigrationIssue::withoutGlobalScopes()->count())->toBe(0);
});
