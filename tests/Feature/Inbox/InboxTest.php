<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\ConversationStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Message;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('inbox page returns the current account conversations and messages', function () {
    [$user, $account] = memberWithRole('member');
    $foreignAccount = Account::factory()->create();

    $newerContact = Contact::factory()->for($account)->create([
        'name' => 'Ana Pérez',
        'phone' => '+57 300 000 0001',
    ]);
    $olderContact = Contact::factory()->for($account)->create([
        'name' => 'Luis Gómez',
        'phone' => '+57 300 000 0002',
    ]);
    $foreignContact = Contact::factory()->for($foreignAccount)->create([
        'name' => 'Fuera de cuenta',
        'phone' => '+57 300 000 0099',
    ]);

    $newerConversation = Conversation::factory()->for($account)->create([
        'contact_id' => $newerContact->id,
        'user_id' => $user->id,
        'status' => ConversationStatus::Open,
        'last_message_text' => '¿Sigues ahí?',
        'last_message_at' => now()->subMinutes(5),
    ]);
    $olderConversation = Conversation::factory()->for($account)->create([
        'contact_id' => $olderContact->id,
        'user_id' => $user->id,
        'status' => ConversationStatus::Pending,
        'last_message_text' => 'Necesito ayuda con mi pedido.',
        'last_message_at' => now()->subHour(),
    ]);
    $foreignConversation = Conversation::factory()->for($foreignAccount)->create([
        'contact_id' => $foreignContact->id,
        'status' => ConversationStatus::Closed,
        'last_message_text' => 'No debe aparecer',
        'last_message_at' => now()->subMinutes(2),
    ]);

    Message::factory()->for($newerConversation)->incoming()->read()->create([
        'content_text' => 'Hola, buenas tardes.',
        'created_at' => now()->subMinutes(7),
    ]);
    Message::factory()->for($newerConversation)->outgoing()->delivered()->create([
        'content_text' => 'Hola, te leo.',
        'created_at' => now()->subMinutes(5),
    ]);
    Message::factory()->for($olderConversation)->incoming()->read()->create([
        'content_text' => 'Necesito ayuda con mi pedido.',
        'created_at' => now()->subHour(),
    ]);
    Message::factory()->for($foreignConversation)->incoming()->read()->create([
        'content_text' => 'Mensaje aislado',
        'created_at' => now()->subMinutes(2),
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inbox')
            ->has('conversations', 2)
            ->where('conversations.0.id', $newerConversation->id)
            ->where('conversations.0.contact.name', 'Ana Pérez')
            ->where('conversations.0.status', ConversationStatus::Open->value)
            ->where('conversations.0.last_message_text', '¿Sigues ahí?')
            ->where('conversations.1.id', $olderConversation->id)
            ->where('conversations.1.contact.name', 'Luis Gómez')
            ->where('conversations.1.status', ConversationStatus::Pending->value)
            ->has('messages', 3)
            ->where('messages.0.conversation_id', $newerConversation->id)
            ->where('messages.0.content_text', 'Hola, buenas tardes.')
            ->where('messages.1.conversation_id', $newerConversation->id)
            ->where('messages.1.content_text', 'Hola, te leo.')
            ->where('messages.2.conversation_id', $olderConversation->id)
            ->where('messages.2.content_text', 'Necesito ayuda con mi pedido.')
            ->has('contacts', 2)
            ->has('connections', 0)
        );
});

test('inbox page exposes the conversation connection for message traceability', function () {
    [$user, $account] = memberWithRole('member');
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    $contact = Contact::factory()->for($account)->create();
    $conversation = Conversation::factory()->for($account)->create([
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inbox')
            ->where('conversations.0.id', $conversation->id)
            ->where('conversations.0.connection_id', $sales->id));
});

test('inbox page shows an empty state for accounts without conversations', function () {
    [$user, $account] = memberWithRole('member');

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inbox')
            ->has('conversations', 0)
            ->has('messages', 0)
        );
});

test('inbox page loads conversations, contacts, and messages without n plus one', function () {
    [$user, $account] = memberWithRole('member');
    $pipelineContactOne = Contact::factory()->for($account)->create();
    $pipelineContactTwo = Contact::factory()->for($account)->create();

    $firstConversation = Conversation::factory()->for($account)->create([
        'contact_id' => $pipelineContactOne->id,
        'user_id' => $user->id,
        'last_message_at' => now()->subMinutes(10),
    ]);
    $secondConversation = Conversation::factory()->for($account)->create([
        'contact_id' => $pipelineContactTwo->id,
        'user_id' => $user->id,
        'last_message_at' => now()->subMinutes(20),
    ]);

    Message::factory()->for($firstConversation)->incoming()->read()->create([
        'created_at' => now()->subMinutes(11),
    ]);
    Message::factory()->for($firstConversation)->outgoing()->delivered()->create([
        'created_at' => now()->subMinutes(10),
    ]);
    Message::factory()->for($secondConversation)->incoming()->read()->create([
        'created_at' => now()->subMinutes(21),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/from ["`]?([a-z_]+)/i', $query->sql, $matches) === 1
            && in_array($matches[1], ['conversations', 'contacts', 'messages'], true)) {
            $queries[] = $matches[1];
        }
    });

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('inbox'))
        ->assertOk();

    expect($queries)->toHaveCount(4)
        ->and(array_count_values($queries))->toMatchArray([
            'conversations' => 1,
            'contacts' => 2,
            'messages' => 1,
        ]);
});

test('replies inherit the conversation connection and do not switch sender', function () {
    [$user, $account] = memberWithRole('member');
    WhatsappIntegration::factory()->for($account)->create(['access_token' => 'secret-meta-token']);
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'phone_number_id' => 'phone-sales',
    ]);
    $support = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'phone_number_id' => 'phone-support',
        'is_default' => true,
    ]);
    $contact = Contact::factory()->for($account)->create(['phone' => '+573001112233']);
    $conversation = Conversation::factory()->for($account)->create([
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
    ]);
    Http::fake([
        '*phone-sales/messages' => Http::response(['messages' => [['id' => 'wamid.sales-1']]]),
        '*' => Http::response(['error' => ['message' => 'Unexpected request']], 500),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->postJson(route('inbox.messages.store', $conversation), [
            'content_text' => 'Te confirmo el pedido.',
        ]);

    $message = Message::query()->sole();

    $response
        ->assertSuccessful()
        ->assertJson([
            'id' => $message->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'content_text' => 'Te confirmo el pedido.',
            'status' => 'sent',
        ]);

    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->content_text)->toBe('Te confirmo el pedido.')
        ->and($message->sender_type)->toBe('agent')
        ->and($message->message_id)->toBe('wamid.sales-1')
        ->and($conversation->fresh()->connection_id)->toBe($sales->id)
        ->and($support->fresh()->is_default)->toBeTrue();

    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), 'phone-sales/messages')
        && $request['to'] === '573001112233');
    Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'phone-support/messages'));
});

test('a disconnected conversation connection pauses the reply instead of falling back', function () {
    [$user, $account] = memberWithRole('member');
    WhatsappIntegration::factory()->for($account)->create(['access_token' => 'secret-meta-token']);
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->create([
        'phone_number_id' => 'phone-sales',
        'readiness' => WhatsappConnectionReadiness::Disconnected,
    ]);
    WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'phone_number_id' => 'phone-support',
        'is_default' => true,
    ]);
    $contact = Contact::factory()->for($account)->create(['phone' => '+573001112233']);
    $conversation = Conversation::factory()->for($account)->create([
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
    ]);
    Http::fake();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->postJson(route('inbox.messages.store', $conversation), [
            'content_text' => 'No debe salir por soporte.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('connection_id');

    expect(Message::query()->count())->toBe(0)
        ->and($conversation->fresh()->connection_id)->toBe($sales->id);
    Http::assertNothingSent();
});

test('a graph rejection leaves no message and returns a validation error', function () {
    [$user, $account] = memberWithRole('member');
    WhatsappIntegration::factory()->for($account)->create(['access_token' => 'secret-meta-token']);
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'phone_number_id' => 'phone-sales',
    ]);
    $contact = Contact::factory()->for($account)->create(['phone' => '+573001112233']);
    $conversation = Conversation::factory()->for($account)->create([
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
    ]);
    Http::fake([
        '*phone-sales/messages' => Http::response(['error' => ['message' => 'Graph refused', 'code' => 131047]], 400),
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->postJson(route('inbox.messages.store', $conversation), [
            'content_text' => 'Este texto no se pierde en el cliente.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content_text');

    expect(Message::query()->count())->toBe(0);
});

test('a new conversation requires an explicit connection when no active default exists', function () {
    [$user, $account] = memberWithRole('member');
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    $contact = Contact::factory()->for($account)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('inbox'))
        ->post(route('inbox.conversations.store'), [
            'contact_id' => $contact->id,
        ])
        ->assertRedirect(route('inbox'))
        ->assertSessionHasErrors('connection_id');

    expect(Conversation::query()->count())->toBe(0);

    $this->post(route('inbox.conversations.store'), [
        'contact_id' => $contact->id,
        'connection_id' => $sales->id,
    ])->assertRedirect(route('inbox'));

    $conversation = Conversation::query()->sole();

    expect($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->connection_id)->toBe($sales->id);
});

test('a new conversation uses the active default when no connection is selected', function () {
    [$user, $account] = memberWithRole('member');
    WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    $support = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'is_default' => true,
    ]);
    $contact = Contact::factory()->for($account)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('inbox.conversations.store'), [
            'contact_id' => $contact->id,
        ])
        ->assertRedirect(route('inbox'));

    expect(Conversation::query()->sole()->connection_id)->toBe($support->id);
});

test('disconnecting the default requires an explicit connection for new conversations', function () {
    [$admin, $account] = memberWithRole('admin');
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'is_default' => true,
    ]);
    $contact = Contact::factory()->for($account)->create();
    Http::fake();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertRedirect(route('settings.whatsapp'));

    $this->from(route('inbox'))
        ->post(route('inbox.conversations.store'), [
            'contact_id' => $contact->id,
        ])
        ->assertRedirect(route('inbox'))
        ->assertSessionHasErrors('connection_id');

    expect(Conversation::query()->count())->toBe(0)
        ->and($sales->fresh()?->is_default)->toBeFalse();
});

test('marking a conversation seen persists unread count to zero', function (string $role) {
    $this->withoutVite();

    [$user, $account] = memberWithRole($role);
    $contact = Contact::factory()->for($account)->create(['name' => 'Ana Pérez']);
    $conversation = Conversation::factory()->for($account)->create([
        'contact_id' => $contact->id,
        'user_id' => $user->id,
        'unread_count' => 3,
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('inbox.conversations.seen', $conversation))
        ->assertNoContent();

    $this->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.id', $conversation->id)
            ->where('conversations.0.unread_count', 0));
})->with(['member', 'admin', 'owner']);

test('a viewer cannot mark a conversation seen', function () {
    $this->withoutVite();

    [$viewer, $account] = memberWithRole('viewer');
    $contact = Contact::factory()->for($account)->create(['name' => 'Luis Gómez']);
    $conversation = Conversation::factory()->for($account)->create([
        'contact_id' => $contact->id,
        'user_id' => $viewer->id,
        'unread_count' => 2,
    ]);

    $this->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('inbox.conversations.seen', $conversation))
        ->assertForbidden();

    $this->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.0.id', $conversation->id)
            ->where('conversations.0.unread_count', 2));
});

test('a conversation from another account cannot be marked seen', function () {
    [$member, $account] = memberWithRole('member');
    $foreignAccount = Account::factory()->create();
    $foreignContact = Contact::factory()->for($foreignAccount)->create();
    $foreignConversation = Conversation::factory()->for($foreignAccount)->create([
        'contact_id' => $foreignContact->id,
        'unread_count' => 4,
    ]);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('inbox.conversations.seen', $foreignConversation))
        ->assertNotFound();

    expect(Conversation::withoutGlobalScopes()->find($foreignConversation->id)?->unread_count)->toBe(4);
});
