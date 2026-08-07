<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\ConversationStatus;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        );
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

    expect($queries)->toHaveCount(3)
        ->and(array_count_values($queries))->toMatchArray([
            'conversations' => 1,
            'contacts' => 1,
            'messages' => 1,
        ]);
});
