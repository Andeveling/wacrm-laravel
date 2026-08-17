<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

function seedInboxThreadForSend(): User
{
    $member = User::factory()->create(['password' => 'password']);
    $account = Account::factory()->create(['type' => AccountType::Team]);
    AccountUser::create([
        'account_id' => $account->id,
        'user_id' => $member->id,
        'role' => AccountRole::Member,
    ]);
    WhatsappIntegration::factory()->for($account)->create(['access_token' => 'secret-meta-token']);
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'phone_number_id' => 'phone-sales',
    ]);
    $contact = Contact::factory()->for($account)->create([
        'name' => 'Ana Pérez',
        'phone' => '+573001112233',
    ]);
    Conversation::factory()->for($account)->create([
        'user_id' => $member->id,
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
        'last_message_text' => 'Hola desde el número de prueba',
        'last_message_at' => now()->subMinute(),
    ]);

    return $member;
}

test('graph failure leaves a failed bubble and keeps the text', function () {
    $member = seedInboxThreadForSend();
    Http::fake([
        '*phone-sales/messages' => Http::response(['error' => ['message' => 'Graph refused', 'code' => 131047]], 400),
        '*' => Http::response(['error' => ['message' => 'Unexpected request']], 500),
    ]);

    signInAndSelectAccount($member);

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Ana Pérez')
        ->type('textarea[placeholder="Escribe un mensaje…"]', 'Este texto no se pierde.')
        ->click('[data-testid="inbox-send-button"]')
        ->assertSee('Este texto no se pierde.')
        ->assertVisible('[data-testid="inbox-message-failed"]')
        ->assertVisible('[data-testid="inbox-retry-send"]');
});

test('send shows a sending bubble and spinner while graph is in flight', function () {
    $member = seedInboxThreadForSend();
    Http::fake(function (HttpRequest $request) {
        if (str_contains($request->url(), 'phone-sales/messages')) {
            usleep(400_000);

            return Http::response(['messages' => [['id' => 'wamid.sales-1']]]);
        }

        return Http::response(['error' => ['message' => 'Unexpected request']], 500);
    });

    signInAndSelectAccount($member);

    $page = $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Ana Pérez')
        ->type('textarea[placeholder="Escribe un mensaje…"]', 'Te confirmo el pedido.')
        ->click('[data-testid="inbox-send-button"]');

    $page
        ->assertVisible('[data-testid="inbox-message-sending"]')
        ->assertVisible('[data-testid="inbox-send-button"] [role="status"]')
        ->assertDisabled('[data-testid="inbox-send-button"]')
        ->assertSee('Te confirmo el pedido.');

    $page
        ->assertNotPresent('[data-testid="inbox-message-sending"]')
        ->assertSee('Te confirmo el pedido.');
});
