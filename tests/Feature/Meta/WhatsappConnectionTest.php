<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AutomationConnectionMode;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\User;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function fakeMetaConnectionRequests(bool $registrationSucceeds = true): void
{
    Http::fake(function (HttpRequest $request) use ($registrationSucceeds) {
        $url = $request->url();

        if (str_contains($url, '/debug_token')) {
            return Http::response([
                'data' => [
                    'scopes' => [
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ],
                ],
            ]);
        }

        if (str_ends_with($url, '/phone-123') || str_contains($url, '/phone-123?')) {
            return Http::response([
                'id' => 'phone-123',
                'display_phone_number' => '+57 300 123 4567',
                'verified_name' => 'Wacrm',
                'whatsapp_business_account' => ['id' => 'waba-123'],
            ]);
        }

        if (str_contains($url, '/waba-123/phone_numbers')) {
            return Http::response(['data' => [['id' => 'phone-123']]]);
        }

        if (str_ends_with($url, '/waba-123') || str_contains($url, '/waba-123?')) {
            return Http::response(['id' => 'waba-123']);
        }

        if (str_ends_with($url, '/waba-123/subscribed_apps')) {
            return Http::response(['success' => true]);
        }

        if (str_ends_with($url, '/phone-123/register')) {
            return $registrationSucceeds
                ? Http::response(['success' => true])
                : Http::response(['error' => ['code' => 100, 'message' => 'Invalid PIN']], 400);
        }

        return Http::response(['error' => ['code' => 1, 'message' => 'Unexpected request']], 500);
    });
}

test('guests are redirected to login', function () {
    $this->get(route('settings.whatsapp'))->assertRedirect(route('login'));
});

test('read-only members receive connection projection without credentials', function () {
    [$member, $account] = memberWithRole('viewer');
    $integration = WhatsappIntegration::factory()->for($account)->create([
        'access_token' => 'never-return-this-token',
    ]);
    $waba = WabaSubscription::factory()->forIntegration($integration)->create([
        'account_id' => $account->id,
        'waba_id' => 'waba-123',
    ]);
    WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-123',
        'readiness' => WhatsappConnectionReadiness::WebhookWaiting,
    ]);

    $response = $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.whatsapp'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/whatsapp')
        ->where('canManage', false)
        ->has('connections', 1)
        ->where('connections.0.phone_number_id', 'phone-123')
        ->where('connections.0.readiness', 'webhook_waiting')
        ->missing('connections.0.access_token')
        ->missing('integration.access_token')
    );

    expect($response->getContent())->not->toContain('never-return-this-token');
});

test('admin can connect a number and persists each verified step without returning the token', function () {
    [$admin, $account] = memberWithRole('admin');
    fakeMetaConnectionRequests();

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
            'pin' => '123456',
        ]);

    $response->assertRedirect(route('settings.whatsapp'));

    $integration = WhatsappIntegration::withoutGlobalScopes()->sole();
    $waba = WabaSubscription::withoutGlobalScopes()->sole();
    $connection = WhatsappPhoneNumberConnection::withoutGlobalScopes()->sole();

    expect($integration->access_token)->toBe('secret-meta-token')
        ->and($integration->getRawOriginal('access_token'))->not->toBe('secret-meta-token')
        ->and($waba->subscribed_apps_at)->not->toBeNull()
        ->and($connection->registered_at)->not->toBeNull()
        ->and($connection->readiness)->toBe(WhatsappConnectionReadiness::WebhookWaiting)
        ->and($response->getContent())->not->toContain('secret-meta-token');

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/waba-123/subscribed_apps'));
    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/phone-123/register')
        && $request['pin'] === '123456');
});

test('a subscription failure preserves verified credentials for a retry', function () {
    [$admin, $account] = memberWithRole('admin');
    Http::fake(function (HttpRequest $request) {
        $url = $request->url();

        if (str_contains($url, '/debug_token')) {
            return Http::response([
                'data' => [
                    'scopes' => [
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ],
                ],
            ]);
        }
        if (str_ends_with($url, '/phone-123') || str_contains($url, '/phone-123?')) {
            return Http::response(['id' => 'phone-123', 'whatsapp_business_account' => ['id' => 'waba-123']]);
        }
        if (str_contains($url, '/waba-123/phone_numbers')) {
            return Http::response(['data' => [['id' => 'phone-123']]]);
        }
        if (str_ends_with($url, '/waba-123') || str_contains($url, '/waba-123?')) {
            return Http::response(['id' => 'waba-123']);
        }
        if (str_ends_with($url, '/waba-123/subscribed_apps')) {
            return Http::response(['error' => ['code' => 10, 'message' => 'Permissions error']], 403);
        }

        return Http::response([], 500);
    });

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
        ]);

    $response->assertRedirect(route('settings.whatsapp'));
    expect(WhatsappIntegration::withoutGlobalScopes()->count())->toBe(1)
        ->and(WhatsappPhoneNumberConnection::withoutGlobalScopes()->sole()->readiness)
        ->toBe(WhatsappConnectionReadiness::CredentialsVerified)
        ->and(session('whatsapp_notice'))->toContain('Guardamos el número')
        ->and(session('whatsapp_notice'))->toContain('permisos')
        ->and(session('whatsapp_notice'))->not->toContain('secret-meta-token')
        ->and(session('whatsapp_error'))->toBeNull()
        ->and(session('whatsapp_status'))->toBeNull();
});

test('a registration failure preserves the subscribed step', function () {
    [$admin, $account] = memberWithRole('admin');
    fakeMetaConnectionRequests(registrationSucceeds: false);

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
            'pin' => '000000',
        ]);

    $response->assertRedirect(route('settings.whatsapp'));
    expect(WabaSubscription::withoutGlobalScopes()->sole()->subscribed_apps_at)->not->toBeNull()
        ->and(WhatsappPhoneNumberConnection::withoutGlobalScopes()->sole()->readiness)
        ->toBe(WhatsappConnectionReadiness::AttentionRequired)
        ->and(session('whatsapp_notice'))->toContain('Guardamos el número')
        ->and(session('whatsapp_notice'))->toContain('registrar')
        ->and(session('whatsapp_error'))->toBeNull();
});

test('admin can rotate a token without repeating completed Meta steps', function () {
    [$admin, $account] = memberWithRole('admin');
    $integration = WhatsappIntegration::factory()->for($account)->create([
        'access_token' => 'old-meta-token',
    ]);
    $waba = WabaSubscription::factory()->forIntegration($integration)->create([
        'account_id' => $account->id,
        'waba_id' => 'waba-123',
        'subscribed_apps_at' => now()->subMinute(),
    ]);
    WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-123',
        'readiness' => WhatsappConnectionReadiness::WebhookWaiting,
        'registered_at' => now()->subMinute(),
    ]);
    fakeMetaConnectionRequests();

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'new-meta-token',
        ]);

    $response->assertRedirect(route('settings.whatsapp'));
    expect(WhatsappIntegration::withoutGlobalScopes()->sole()->access_token)->toBe('new-meta-token');
    Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'POST');
});

test('members cannot connect a number', function () {
    [$member, $account] = memberWithRole('member');

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
        ])
        ->assertForbidden();
});

/**
 * @return array{0: User, 1: Account, 2: WhatsappIntegration, 3: WabaSubscription, 4: WhatsappPhoneNumberConnection}
 */
function seededConnection(
    string $role = 'admin',
    string $phoneNumberId = 'phone-123',
    string $wabaId = 'waba-123',
    WhatsappConnectionReadiness $readiness = WhatsappConnectionReadiness::Active,
    bool $isDefault = false,
): array {
    [$actor, $account] = memberWithRole($role);
    $integration = WhatsappIntegration::factory()->for($account)->create([
        'created_by' => $actor->id,
        'access_token' => 'secret-meta-token',
    ]);
    $waba = WabaSubscription::factory()->forIntegration($integration)->create([
        'account_id' => $account->id,
        'waba_id' => $wabaId,
        'subscribed_apps_at' => now()->subMinute(),
    ]);
    $connection = WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => $phoneNumberId,
        'readiness' => $readiness,
        'is_default' => $isDefault,
        'registered_at' => now()->subMinute(),
        'connected_at' => now()->subMinute(),
    ]);

    return [$actor, $account, $integration, $waba, $connection];
}

test('disconnecting a number keeps its conversation and leaves a sibling WABA subscribed', function () {
    [$admin, $account, $integration, $waba, $sales] = seededConnection(
        phoneNumberId: 'phone-sales',
        isDefault: true,
    );
    $support = WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-support',
        'readiness' => WhatsappConnectionReadiness::Active,
        'registered_at' => now()->subMinute(),
    ]);
    $contact = Contact::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
    ]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
    ]);
    Http::fake();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertRedirect(route('settings.whatsapp'));

    $sales = $sales->fresh();
    $waba = $waba->fresh();
    $conversation = $conversation->fresh();

    expect($sales)->not->toBeNull()
        ->and($sales->readiness)->toBe(WhatsappConnectionReadiness::Disconnected)
        ->and($sales->is_default)->toBeFalse()
        ->and($conversation)->not->toBeNull()
        ->and($conversation->connection_id)->toBe($sales->id)
        ->and($support->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active)
        ->and($waba->subscribed_apps_at)->not->toBeNull()
        ->and($integration->fresh()->access_token)->toBe('secret-meta-token');
    Http::assertNothingSent();
});

test('disconnecting a connection pauses only outbound work pinned to that connection', function () {
    [$admin, $account, , $waba, $sales] = seededConnection(phoneNumberId: 'phone-sales');
    $support = WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-support',
        'readiness' => WhatsappConnectionReadiness::Active,
    ]);

    $legacyDraft = Broadcast::factory()->for($account)->create(['connection_id' => null, 'status' => BroadcastStatus::Draft]);
    $pinnedSending = Broadcast::factory()->for($account)->create(['connection_id' => $sales->id, 'status' => BroadcastStatus::Sending]);
    $unaffected = Broadcast::factory()->for($account)->create(['connection_id' => $support->id, 'status' => BroadcastStatus::Draft]);
    $legacyAutomation = Automation::factory()->for($account)->active()->create([
        'user_id' => $admin->id,
        'connection_mode' => AutomationConnectionMode::Pinned,
        'connection_id' => null,
    ]);
    $pinnedAutomation = Automation::factory()->for($account)->active()->create(['user_id' => $admin->id, 'connection_id' => $sales->id]);
    $unaffectedAutomation = Automation::factory()->for($account)->active()->create(['user_id' => $admin->id, 'connection_id' => $support->id]);
    Http::fake();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertRedirect(route('settings.whatsapp'));

    expect($legacyDraft->fresh()->status)->toBe(BroadcastStatus::Draft)
        ->and($pinnedSending->fresh()->status)->toBe(BroadcastStatus::Paused)
        ->and($unaffected->fresh()->status)->toBe(BroadcastStatus::Draft)
        ->and($legacyAutomation->fresh()->is_active)->toBeTrue()
        ->and($pinnedAutomation->fresh()->is_active)->toBeFalse()
        ->and($unaffectedAutomation->fresh()->is_active)->toBeTrue();
});

test('claiming another account number fails without revealing the owner', function () {
    [, $foreignAccount] = seededConnection(
        role: 'owner',
        phoneNumberId: 'phone-claimed',
        wabaId: 'waba-foreign',
    );
    [$admin, $account] = memberWithRole('admin');
    fakeMetaConnectionRequests();

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('settings.whatsapp'))
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-claimed',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
            'pin' => '123456',
        ]);

    $response->assertRedirect(route('settings.whatsapp'))
        ->assertSessionHasErrors('phone_number_id');

    $error = session('errors')->first('phone_number_id');

    expect($error)->not->toContain($foreignAccount->id)
        ->and($error)->not->toContain($foreignAccount->name)
        ->and($error)->not->toContain('owner')
        ->and(WhatsappPhoneNumberConnection::query()->where('account_id', $account->id)->count())->toBe(0);
    Http::assertNothingSent();
});

test('admin can add a second number from another WABA without duplicating the first', function () {
    [$admin, $account, $integration, $salesWaba] = seededConnection(
        phoneNumberId: 'phone-sales',
        wabaId: 'waba-sales',
        readiness: WhatsappConnectionReadiness::WebhookWaiting,
    );
    Http::fake(function (HttpRequest $request) {
        $url = $request->url();

        if (str_contains($url, '/debug_token')) {
            return Http::response([
                'data' => [
                    'scopes' => [
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ],
                ],
            ]);
        }
        if (str_ends_with($url, '/phone-support') || str_contains($url, '/phone-support?')) {
            return Http::response([
                'id' => 'phone-support',
                'display_phone_number' => '+57 300 765 4321',
                'verified_name' => 'Soporte',
                'whatsapp_business_account' => ['id' => 'waba-support'],
            ]);
        }
        if (str_contains($url, '/waba-support/phone_numbers')) {
            return Http::response(['data' => [['id' => 'phone-support']]]);
        }
        if (str_ends_with($url, '/waba-support') || str_contains($url, '/waba-support?')) {
            return Http::response(['id' => 'waba-support']);
        }
        if (str_ends_with($url, '/waba-support/subscribed_apps')) {
            return Http::response(['success' => true]);
        }
        if (str_ends_with($url, '/phone-support/register')) {
            return Http::response(['success' => true]);
        }

        return Http::response(['error' => ['code' => 1, 'message' => 'Unexpected request']], 500);
    });

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-support',
            'waba_id' => 'waba-support',
            'pin' => '654321',
        ])
        ->assertRedirect(route('settings.whatsapp'));

    $connections = WhatsappPhoneNumberConnection::query()
        ->withoutGlobalScopes()
        ->where('account_id', $account->id)
        ->orderBy('phone_number_id')
        ->get();
    $wabas = WabaSubscription::query()
        ->withoutGlobalScopes()
        ->where('account_id', $account->id)
        ->orderBy('waba_id')
        ->get();

    expect($connections)->toHaveCount(2)
        ->and($connections->pluck('phone_number_id')->all())->toBe(['phone-sales', 'phone-support'])
        ->and($connections->firstWhere('phone_number_id', 'phone-support')?->readiness)
        ->toBe(WhatsappConnectionReadiness::WebhookWaiting)
        ->and($wabas)->toHaveCount(2)
        ->and($wabas->pluck('waba_id')->all())->toBe(['waba-sales', 'waba-support'])
        ->and($wabas->firstWhere('waba_id', 'waba-support')?->integration_id)->toBe($integration->id)
        ->and($salesWaba->fresh()->subscribed_apps_at)->not->toBeNull()
        ->and(WhatsappIntegration::query()->withoutGlobalScopes()->where('account_id', $account->id)->count())->toBe(1);
});

test('only one active connection can be the default and disconnecting it clears the default', function () {
    [$admin, $account, , $waba, $sales] = seededConnection(
        phoneNumberId: 'phone-sales',
        isDefault: true,
    );
    $support = WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-support',
        'readiness' => WhatsappConnectionReadiness::Active,
        'registered_at' => now()->subMinute(),
    ]);
    Http::fake();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('settings.whatsapp.default', $support))
        ->assertRedirect(route('settings.whatsapp'));

    expect($support->fresh()->is_default)->toBeTrue()
        ->and($sales->fresh()->is_default)->toBeFalse();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $support))
        ->assertRedirect(route('settings.whatsapp'));

    expect($support->fresh()->is_default)->toBeFalse()
        ->and($support->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Disconnected)
        ->and($sales->fresh()->is_default)->toBeFalse()
        ->and($sales->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active);
});

test('disconnecting the last number of a WABA keeps the subscription and history', function () {
    [$admin, $account, , $waba, $sales] = seededConnection(phoneNumberId: 'phone-sales');
    $contact = Contact::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
    ]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $admin->id,
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
    ]);
    Http::fake();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertRedirect(route('settings.whatsapp'));

    expect($sales->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Disconnected)
        ->and($conversation->fresh()?->connection_id)->toBe($sales->id)
        ->and($waba->fresh()->subscribed_apps_at)->not->toBeNull();
    Http::assertNothingSent();
});

test('disconnecting twice is idempotent', function () {
    [$admin, $account, , $waba, $sales] = seededConnection(phoneNumberId: 'phone-sales');
    $sales->readiness = WhatsappConnectionReadiness::Disconnected;
    $sales->save();
    Http::fake();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertRedirect(route('settings.whatsapp'));

    expect($sales->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Disconnected)
        ->and(WhatsappPhoneNumberConnection::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and($waba->fresh()->subscribed_apps_at)->not->toBeNull();
    Http::assertNothingSent();
});

test('reconnecting a disconnected number reuses the same row', function () {
    [$admin, $account, , $waba, $sales] = seededConnection(phoneNumberId: 'phone-123');
    $sales->readiness = WhatsappConnectionReadiness::Disconnected;
    $sales->is_default = false;
    $sales->save();
    $waba->subscribed_apps_at = null;
    $waba->save();
    fakeMetaConnectionRequests();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'pin' => '123456',
        ])
        ->assertRedirect(route('settings.whatsapp'));

    $reconnected = WhatsappPhoneNumberConnection::query()->withoutGlobalScopes()->sole();

    expect($reconnected->id)->toBe($sales->id)
        ->and($reconnected->readiness)->toBe(WhatsappConnectionReadiness::WebhookWaiting)
        ->and($waba->fresh()->subscribed_apps_at)->not->toBeNull();
});

test('members cannot disconnect or change the default sender', function () {
    [$admin, $account, , , $sales] = seededConnection(isDefault: true);
    $member = attachUserToAccount($account, AccountRole::Member);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertForbidden();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('settings.whatsapp.default', $sales))
        ->assertForbidden();

    expect($sales->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active)
        ->and($sales->fresh()->is_default)->toBeTrue();
});

test('a non-active connection cannot become the default sender', function () {
    [$admin, $account, , , $waiting] = seededConnection(
        readiness: WhatsappConnectionReadiness::WebhookWaiting,
    );

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('settings.whatsapp.default', $waiting))
        ->assertRedirect(route('settings.whatsapp'));

    expect($waiting->fresh()->is_default)->toBeFalse()
        ->and(session('whatsapp_error'))->toContain('activa');
});

test('a connection from another account cannot be disconnected or set as default', function () {
    [$admin, $account] = seededConnection(phoneNumberId: 'phone-own', wabaId: 'waba-own');
    [, , , , $foreign] = seededConnection(phoneNumberId: 'phone-foreign', wabaId: 'waba-foreign');

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $foreign))
        ->assertNotFound();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('settings.whatsapp.default', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active)
        ->and($foreign->fresh()->is_default)->toBeFalse();
});

test('settings exposes the webhook url and verify token without secrets', function () {
    config()->set('services.meta.webhook_verify_token', 'instance-verify-token');
    [$admin, $account] = memberWithRole('admin');
    $integration = WhatsappIntegration::factory()->for($account)->create([
        'access_token' => 'never-return-this-token',
    ]);
    $waba = WabaSubscription::factory()->forIntegration($integration)->create([
        'account_id' => $account->id,
        'waba_id' => 'waba-123',
    ]);
    WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-123',
        'readiness' => WhatsappConnectionReadiness::AttentionRequired,
        'last_registration_error' => 'Meta no pudo registrar el número.',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.whatsapp'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('settings/whatsapp')
        ->where('verifyToken', 'instance-verify-token')
        ->where('webhookUrl', route('meta.webhook.verify'))
        ->where('connections.0.health', 'attention')
        ->where('connections.0.last_failure', 'Meta no pudo registrar el número.')
        ->missing('connections.0.access_token')
    );

    expect($response->getContent())->not->toContain('never-return-this-token');
});

test('confirming default on first connection marks pending default until active', function () {
    [$admin, $account] = memberWithRole('admin');
    fakeMetaConnectionRequests();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
            'pin' => '123456',
            'confirm_default' => true,
        ])
        ->assertRedirect(route('settings.whatsapp'));

    $connection = WhatsappPhoneNumberConnection::withoutGlobalScopes()->sole();

    expect($connection->readiness)->toBe(WhatsappConnectionReadiness::WebhookWaiting)
        ->and($connection->pending_default)->toBeTrue()
        ->and($connection->is_default)->toBeFalse();
});

test('a disconnected number can be reclaimed by another account without leaking the previous owner', function () {
    [, $foreignAccount, , , $claimed] = seededConnection(
        role: 'owner',
        phoneNumberId: 'phone-reclaim',
        wabaId: 'waba-reclaim',
    );
    $claimed->readiness = WhatsappConnectionReadiness::Disconnected;
    $claimed->save();

    [$admin, $account] = memberWithRole('admin');
    Http::fake(function (HttpRequest $request) {
        $url = $request->url();

        if (str_contains($url, '/debug_token')) {
            return Http::response([
                'data' => [
                    'scopes' => [
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ],
                ],
            ]);
        }
        if (str_contains($url, '/phone-reclaim')) {
            return Http::response([
                'id' => 'phone-reclaim',
                'whatsapp_business_account' => ['id' => 'waba-reclaim'],
            ]);
        }
        if (str_contains($url, '/waba-reclaim/subscribed_apps')) {
            return Http::response(['success' => true]);
        }
        if (str_contains($url, '/waba-reclaim')) {
            return Http::response(['id' => 'waba-reclaim']);
        }
        if (str_ends_with($url, '/phone-reclaim/register') || str_contains($url, '/phone-reclaim/register')) {
            return Http::response(['success' => true]);
        }

        return Http::response(['error' => ['code' => 1, 'message' => 'Unexpected request']], 500);
    });

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('settings.whatsapp'))
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-reclaim',
            'waba_id' => 'waba-reclaim',
            'access_token' => 'secret-meta-token',
            'pin' => '123456',
        ]);

    $response->assertRedirect(route('settings.whatsapp'))
        ->assertSessionHasNoErrors();

    $reclaimed = WhatsappPhoneNumberConnection::query()
        ->withoutGlobalScopes()
        ->where('account_id', $account->id)
        ->where('phone_number_id', 'phone-reclaim')
        ->first();

    expect($reclaimed)->not->toBeNull()
        ->and($reclaimed->readiness)->toBe(WhatsappConnectionReadiness::WebhookWaiting)
        ->and($claimed->fresh()->phone_number_id)->toBeNull()
        ->and($response->getContent())->not->toContain($foreignAccount->id)
        ->and($response->getContent())->not->toContain($foreignAccount->name);
});

test('a token missing a required permission family still saves the number for retry', function () {
    config()->set('services.meta.app_id', 'meta-app-id');
    config()->set('services.meta.app_secret', 'meta-app-secret');
    [$admin, $account] = memberWithRole('admin');
    Http::fake(function (HttpRequest $request) {
        if (str_contains($request->url(), '/debug_token')) {
            return Http::response([
                'data' => [
                    'scopes' => ['whatsapp_business_management'],
                ],
            ]);
        }

        return Http::response(['error' => ['code' => 1, 'message' => 'Unexpected request']], 500);
    });

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('settings.whatsapp'))
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
            'pin' => '123456',
        ])
        ->assertRedirect(route('settings.whatsapp'));

    expect(session('whatsapp_notice'))->toContain('Guardamos el número')
        ->and(session('whatsapp_notice'))->toContain('familias de permisos')
        ->and(session('whatsapp_error'))->toBeNull()
        ->and(session()->getOldInput('phone_number_id'))->toBe('phone-123')
        ->and(session()->getOldInput('waba_id'))->toBe('waba-123')
        ->and(session()->getOldInput('access_token'))->toBeNull()
        ->and(WhatsappIntegration::withoutGlobalScopes()->count())->toBe(1)
        ->and(WhatsappPhoneNumberConnection::withoutGlobalScopes()->sole()->readiness)
        ->toBe(WhatsappConnectionReadiness::AttentionRequired);

    $this->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/whatsapp')
            ->where('draft.phone_number_id', 'phone-123')
            ->where('draft.waba_id', 'waba-123')
            ->where('connections.0.health', 'attention')
            ->where('notice', fn (string $notice) => str_contains($notice, 'Guardamos el número'))
            ->where('error', null)
            ->where('status', null)
        );
});

test('connecting proceeds without inspecting the token when app credentials are missing', function () {
    config()->set('services.meta.app_id', null);
    config()->set('services.meta.app_secret', null);
    [$admin, $account] = memberWithRole('admin');
    fakeMetaConnectionRequests();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.whatsapp.connect'), [
            'phone_number_id' => 'phone-123',
            'waba_id' => 'waba-123',
            'access_token' => 'secret-meta-token',
            'pin' => '123456',
        ])
        ->assertRedirect(route('settings.whatsapp'));

    expect(session('whatsapp_status'))->not->toBeNull()
        ->and(session('whatsapp_error'))->toBeNull()
        ->and(WhatsappPhoneNumberConnection::withoutGlobalScopes()->sole()->readiness)
        ->toBe(WhatsappConnectionReadiness::WebhookWaiting);

    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'debug_token'));
});

test('deleting the sole owner disables whatsapp routing and strips encrypted tokens', function () {
    [$owner, $account, $integration, , $connection] = seededConnection(role: 'owner', isDefault: true);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($owner->fresh())->toBeNull()
        ->and($connection->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Disconnected)
        ->and($connection->fresh()->is_default)->toBeFalse()
        ->and($integration->fresh()->access_token)->toBeNull();
});
