<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\ContactTag;
use App\Models\Conversation;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithApiKeys;

uses(InteractsWithApiKeys::class);
uses(RefreshDatabase::class);

/**
 * The Contacts MCP tools, exercised through the real seam: a JSON-RPC
 * POST to the published server route, authenticated by API key. Calling
 * the tool classes directly would skip the guard, the tenant scope
 * binding and the throttle — the three things most likely to break.
 *
 * The shape these tests pin is `App\Domain\Contacts\Support\
 * ContactProjection`, the one definition every transport reads.
 */

/**
 * The public Contact shape, in the order the projection emits it.
 *
 * @var list<string>
 */
const PUBLIC_CONTACT_KEYS = [
    'id',
    'phone',
    'name',
    'email',
    'company',
    'avatar_url',
    'created_at',
    'updated_at',
    'tags',
];

/**
 * @param  array<string, mixed>  $arguments
 */
function mcpTool(string $bearer, string $tool, array $arguments = []): TestResponse
{
    return test()
        ->withHeader('Authorization', 'Bearer '.$bearer)
        ->postJson('/mcp/wacrm', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments],
        ]);
}

it('rejects a call without a valid API key', function () {
    $this->postJson('/mcp/wacrm', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list-contacts-tool', 'arguments' => []],
    ])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_token')
        // The MCP package rewrites the challenge to add its realm.
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');
});

it('lists contacts with the full public shape', function () {
    $apiKey = ApiKey::factory()->create();
    $tag = Tag::factory()->create(['account_id' => $apiKey->account_id, 'name' => 'VIP', 'color' => '#f59e0b']);
    $contact = Contact::factory()->create([
        'account_id' => $apiKey->account_id,
        'phone' => '+573001112222',
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'company' => 'Acme SAS',
        'avatar_url' => 'https://cdn.example.com/ana.png',
    ]);
    ContactTag::create(['contact_id' => $contact->id, 'tag_id' => $tag->id]);

    $response = mcpTool($this->reissuePlaintext($apiKey), 'list-contacts-tool');

    $response->assertOk();
    $item = $response->json('result.structuredContent.data.0');

    expect(array_keys($item))->toBe(PUBLIC_CONTACT_KEYS)
        ->and($item['id'])->toBe($contact->id)
        ->and($item['phone'])->toBe('+573001112222')
        ->and($item['name'])->toBe('Ana Pérez')
        ->and($item['email'])->toBe('ana@example.com')
        ->and($item['company'])->toBe('Acme SAS')
        ->and($item['avatar_url'])->toBe('https://cdn.example.com/ana.png')
        ->and($item['created_at'])->toBe($contact->created_at?->toIso8601String())
        ->and($item['updated_at'])->toBe($contact->updated_at?->toIso8601String())
        ->and($item['tags'])->toBe([['id' => $tag->id, 'name' => 'VIP', 'color' => '#f59e0b']]);
});

it('never leaks contacts from another account', function () {
    $apiKey = ApiKey::factory()->create();
    $foreign = Account::factory()->create();
    Contact::factory()->create(['account_id' => $apiKey->account_id, 'name' => 'Propio']);
    Contact::factory()->create(['account_id' => $foreign->id, 'name' => 'Ajeno']);

    $response = mcpTool($this->reissuePlaintext($apiKey), 'list-contacts-tool');

    expect($response->json('result.structuredContent.data'))->toHaveCount(1)
        ->and($response->json('result.structuredContent.data.0.name'))->toBe('Propio')
        ->and($response->json('result.structuredContent.pagination.total'))->toBe(1);
});

it('paginates the list and caps the page size at fifty', function () {
    $apiKey = ApiKey::factory()->create();
    Contact::factory()->count(60)->create(['account_id' => $apiKey->account_id]);
    $bearer = $this->reissuePlaintext($apiKey);

    $page = mcpTool($bearer, 'list-contacts-tool', ['limit' => 10]);
    expect($page->json('result.structuredContent.data'))->toHaveCount(10)
        ->and($page->json('result.structuredContent.pagination'))->toBe([
            'current_page' => 1,
            'per_page' => 10,
            'total' => 60,
            'last_page' => 6,
        ]);

    $capped = mcpTool($bearer, 'list-contacts-tool', ['limit' => 500]);
    expect($capped->json('result.structuredContent.data'))->toHaveCount(50)
        ->and($capped->json('result.structuredContent.pagination.per_page'))->toBe(50);
});

it('returns a contact detail as the public shape plus notes and conversations', function () {
    $apiKey = ApiKey::factory()->create();
    $tag = Tag::factory()->create(['account_id' => $apiKey->account_id, 'name' => 'Prospecto', 'color' => '#3b82f6']);
    $contact = Contact::factory()->create([
        'account_id' => $apiKey->account_id,
        'name' => 'Bruno Díaz',
        'avatar_url' => 'https://cdn.example.com/bruno.png',
    ]);
    ContactTag::create(['contact_id' => $contact->id, 'tag_id' => $tag->id]);
    $note = ContactNote::factory()->create([
        'account_id' => $apiKey->account_id,
        'contact_id' => $contact->id,
        'note_text' => 'Pidió cotización.',
    ]);
    $conversation = Conversation::factory()->create([
        'account_id' => $apiKey->account_id,
        'contact_id' => $contact->id,
        'last_message_text' => 'Gracias.',
        'unread_count' => 2,
    ]);

    $response = mcpTool($this->reissuePlaintext($apiKey), 'get-contact-tool', [
        'contactId' => $contact->id,
    ]);

    $response->assertOk();
    $detail = $response->json('result.structuredContent');

    expect(array_keys($detail))->toBe([...PUBLIC_CONTACT_KEYS, 'notes', 'conversations'])
        ->and($detail['id'])->toBe($contact->id)
        ->and($detail['name'])->toBe('Bruno Díaz')
        ->and($detail['avatar_url'])->toBe('https://cdn.example.com/bruno.png')
        ->and($detail['tags'])->toBe([['id' => $tag->id, 'name' => 'Prospecto', 'color' => '#3b82f6']])
        ->and($detail['notes'])->toBe([[
            'id' => $note->id,
            'content' => 'Pidió cotización.',
            'created_at' => $note->created_at?->toIso8601String(),
        ]])
        ->and($detail['conversations'])->toBe([[
            'id' => $conversation->id,
            'status' => $conversation->status->value,
            'last_message_text' => 'Gracias.',
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => 2,
        ]]);
});

it('cannot fetch a contact belonging to another account', function () {
    $apiKey = ApiKey::factory()->create();
    $foreign = Account::factory()->create();
    $foreignContact = Contact::factory()->create(['account_id' => $foreign->id]);

    $response = mcpTool($this->reissuePlaintext($apiKey), 'get-contact-tool', [
        'contactId' => $foreignContact->id,
    ]);

    $response->assertOk();
    expect($response->json('result.isError'))->toBeTrue()
        ->and($response->json('result.content.0.text'))->toContain('Contacto no encontrado.');
});

it('searches contacts by name, phone, email and company', function () {
    $apiKey = ApiKey::factory()->create();
    $target = Contact::factory()->create([
        'account_id' => $apiKey->account_id,
        'name' => 'Carla Gómez',
        'phone' => '+573005556666',
        'email' => 'carla@zenith.co',
        'company' => 'Zenith Labs',
    ]);
    Contact::factory()->create([
        'account_id' => $apiKey->account_id,
        'name' => 'Otro',
        'phone' => '+573009990000',
        'email' => 'otro@example.com',
        'company' => 'Distinta',
    ]);
    $bearer = $this->reissuePlaintext($apiKey);

    foreach (['Carla', '5556666', 'zenith.co', 'Zenith Labs'] as $term) {
        $response = mcpTool($bearer, 'search-contacts-tool', ['query' => $term]);

        $response->assertOk();
        $data = $response->json('result.structuredContent.data');

        expect($data)->toHaveCount(1, "El término [{$term}] debe encontrar exactamente un contacto.")
            ->and($data[0]['id'])->toBe($target->id)
            ->and(array_keys($data[0]))->toBe(PUBLIC_CONTACT_KEYS);
    }
});

it('emits the same Contact keys over Inertia and over MCP', function () {
    // The proof that the projection is the only definition: a field
    // added in one place shows up on both transports, and a field added
    // to only one transport fails right here.
    $apiKey = ApiKey::factory()->create();
    [$user, $account] = memberWithRole('owner');
    Contact::factory()->create(['account_id' => $apiKey->account_id]);
    Contact::factory()->create(['account_id' => $account->id]);

    $mcpKeys = array_keys(
        mcpTool($this->reissuePlaintext($apiKey), 'list-contacts-tool')
            ->json('result.structuredContent.data.0')
    );

    $inertiaKeys = [];
    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts'))
        ->assertInertia(function ($page) use (&$inertiaKeys) {
            $inertiaKeys = array_keys((array) $page->toArray()['props']['contacts'][0]);
        });

    expect($mcpKeys)->toBe(PUBLIC_CONTACT_KEYS)
        ->and($inertiaKeys)->toBe(PUBLIC_CONTACT_KEYS);
});
