<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\User;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

const CRITICAL_TOKEN = 'secret-meta-token-never-shown';
const CRITICAL_PHONE = 'phone-123';
const CRITICAL_WABA = 'waba-123';

function fakeCriticalMetaGraph(): void
{
    Http::fake(function (HttpRequest $request) {
        $url = $request->url();

        if (str_ends_with($url, '/phone-123') || str_contains($url, '/phone-123?')) {
            return Http::response([
                'id' => CRITICAL_PHONE,
                'display_phone_number' => '+57 300 123 4567',
                'verified_name' => 'Wacrm',
                'whatsapp_business_account' => ['id' => CRITICAL_WABA],
            ]);
        }

        if (str_contains($url, '/waba-123/phone_numbers')) {
            return Http::response(['data' => [['id' => CRITICAL_PHONE]]]);
        }

        if (str_ends_with($url, '/waba-123') || str_contains($url, '/waba-123?')) {
            return Http::response(['id' => CRITICAL_WABA]);
        }

        if (str_ends_with($url, '/waba-123/subscribed_apps')) {
            return Http::response(['success' => true]);
        }

        if (str_ends_with($url, '/phone-123/register')) {
            return Http::response(['success' => true]);
        }

        return Http::response(['error' => ['code' => 1, 'message' => 'Unexpected request']], 500);
    });
}

beforeEach(function (): void {
    config()->set('services.meta.app_secret', META_WEBHOOK_SECRET);
    config()->set('services.meta.webhook_verify_token', META_WEBHOOK_VERIFY_TOKEN);
});

test('owner configures a number and sees the routed contact after the controlled delivery', function () {
    $owner = User::factory()->create(['password' => 'password']);
    $account = Account::factory()->create(['type' => AccountType::Team]);
    AccountUser::create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'role' => AccountRole::Owner,
    ]);

    signInAndSelectAccount($owner);

    fakeCriticalMetaGraph();

    /** @phpstan-ignore-next-line Browser visit is supplied by Pest at runtime. */
    $this->visit('/settings/whatsapp')
        ->assertNoSmoke()
        ->assertSee('Conectar primer número')
        ->type('input[name="phone_number_id"]', CRITICAL_PHONE)
        ->type('input[name="waba_id"]', CRITICAL_WABA)
        ->type('input[name="access_token"]', CRITICAL_TOKEN)
        ->type('input[name="pin"]', '123456')
        ->press('button[type="submit"]')
        ->assertSee('Esperando webhook')
        ->assertDontSee(CRITICAL_TOKEN);

    $connection = WhatsappPhoneNumberConnection::query()->withoutGlobalScopes()->sole();
    expect($connection->readiness)->toBe(WhatsappConnectionReadiness::WebhookWaiting)
        ->and($connection->phone_number_id)->toBe(CRITICAL_PHONE);

    $body = inboundMessagesPayload([[
        'phone_number_id' => CRITICAL_PHONE,
        'wa_id' => '573001112233',
        'name' => 'Ana Pérez',
        'message_id' => 'wamid.critical-1',
        'text' => 'Hola desde el número de prueba',
        'waba_id' => CRITICAL_WABA,
    ]]);

    $this->call(
        'POST',
        '/api/whatsapp/webhook',
        [],
        [],
        [],
        signedWebhookServer($body),
        $body,
    )->assertOk();

    /** @phpstan-ignore-next-line Browser visit is supplied by Pest at runtime. */
    $this->visit('/settings/whatsapp')
        ->assertNoSmoke()
        ->assertSee('Activo')
        ->assertDontSee(CRITICAL_TOKEN);

    expect($connection->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active);

    /** @phpstan-ignore-next-line Browser visit is supplied by Pest at runtime. */
    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Ana Pérez')
        ->assertSee('Hola desde el número de prueba');
});
