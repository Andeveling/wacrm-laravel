<?php

declare(strict_types=1);

use App\Models\Enums\WhatsappConnectionReadiness;
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
        ->and(session('whatsapp_error'))->toContain('permisos')
        ->and(session('whatsapp_error'))->not->toContain('secret-meta-token');
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
        ->toBe(WhatsappConnectionReadiness::Subscribed)
        ->and(session('whatsapp_error'))->toContain('registrar');
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
