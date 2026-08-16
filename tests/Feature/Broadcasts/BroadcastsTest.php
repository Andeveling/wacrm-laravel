<?php

use App\Models\Account;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\MessageTemplateStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\MessageTemplate;
use App\Models\Tag;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

function activeConnectionFor(Account $account): WhatsappPhoneNumberConnection
{
    return WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
}

test('broadcasts page returns current account broadcasts with real metrics', function () {
    [$user, $account] = memberWithRole('admin');
    $broadcast = Broadcast::factory()->for($account)->sent()->create([
        'name' => 'Campaña de agosto',
        'template_name' => 'promo_agosto',
        'status' => BroadcastStatus::Sent,
    ]);

    Broadcast::factory()->for(Account::factory())->create(['name' => 'Privada']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts')
            ->has('broadcasts', 1)
            ->where('broadcasts.0.id', $broadcast->id)
            ->where('broadcasts.0.name', 'Campaña de agosto')
            ->where('broadcasts.0.status', 'sent')
            ->where('broadcasts.0.template_name', 'promo_agosto')
            ->where('broadcasts.0.total_recipients', 5)
            ->where('broadcasts.0.created_at', $broadcast->created_at?->toISOString()));
});

test('broadcast detail returns recipients and isolates another account', function () {
    [$user, $account] = memberWithRole('admin');
    $contact = Contact::factory()->for($account)->create([
        'name' => 'Ana Pérez',
        'phone' => '+573001112233',
    ]);
    $broadcast = Broadcast::factory()->for($account)->create(['name' => 'Seguimiento']);
    $recipient = BroadcastRecipient::factory()
        ->for($broadcast)
        ->for($contact)
        ->delivered()
        ->create();
    $foreignBroadcast = Broadcast::factory()->for(Account::factory())->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.show', $broadcast->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts/show')
            ->where('broadcast.id', $broadcast->id)
            ->where('broadcast.name', 'Seguimiento')
            ->has('recipients', 1)
            ->where('recipients.0.id', $recipient->id)
            ->where('recipients.0.contact.name', 'Ana Pérez')
            ->where('recipients.0.status', 'delivered'));

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.show', $foreignBroadcast->id))
        ->assertNotFound();
});

test('broadcast detail eager loads recipients and contacts', function () {
    [$user, $account] = memberWithRole('admin');
    $broadcast = Broadcast::factory()->for($account)->create();
    $contacts = Contact::factory()->count(2)->for($account)->create();

    foreach ($contacts as $contact) {
        BroadcastRecipient::factory()->for($broadcast)->for($contact)->create();
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/from [\"`]?([a-z_]+)/i', $query->sql, $matches) === 1
            && in_array($matches[1], ['broadcasts', 'broadcast_recipients', 'contacts'], true)) {
            $queries[] = $matches[1];
        }
    });

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.show', $broadcast->id))
        ->assertOk();

    expect(array_count_values($queries))->toMatchArray([
        'broadcasts' => 1,
        'broadcast_recipients' => 1,
        'contacts' => 1,
    ]);
});

test('message templates page returns current account templates and approval status', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create([
        'name' => 'promo_agosto',
        'status' => MessageTemplateStatus::Approved,
        'rejection_reason' => null,
    ]);

    MessageTemplate::factory()->for(Account::factory())->create(['name' => 'privada']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/templates')
            ->has('templates', 1)
            ->where('templates.0.id', $template->id)
            ->where('templates.0.name', 'promo_agosto')
            ->where('templates.0.status', 'APPROVED'));
});

test('broadcast and template pages return empty collections', function () {
    [$user, $account] = memberWithRole('admin');

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts')
            ->has('broadcasts', 0));

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/templates')
            ->has('templates', 0));
});

test('broadcast detail exposes the pinned connection', function () {
    [$user, $account] = memberWithRole('admin');
    $connection = activeConnectionFor($account);
    $broadcast = Broadcast::factory()->for($account)->create([
        'connection_id' => $connection->id,
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.show', $broadcast->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts/show')
            ->where('broadcast.id', $broadcast->id)
            ->where('broadcast.connection_id', $connection->id));
});

test('new broadcast page only returns approved tenant templates and tags', function () {
    [$user, $account] = memberWithRole('admin');
    $approved = MessageTemplate::factory()->for($account)->create(['status' => MessageTemplateStatus::Approved]);
    MessageTemplate::factory()->for($account)->create(['status' => MessageTemplateStatus::Draft]);
    MessageTemplate::factory()->for(Account::factory())->create(['status' => MessageTemplateStatus::Approved]);
    $tag = Tag::factory()->for($account)->create();
    Tag::factory()->for(Account::factory())->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.new'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts/new')
            ->has('templates', 1)
            ->where('templates.0.id', $approved->id)
            ->has('tags', 1)
            ->where('tags.0.id', $tag->id)
            ->has('connections', 0));
});

test('new broadcast page lists only active tenant connections', function () {
    [$user, $account] = memberWithRole('admin');
    $active = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create([
        'phone_number_id' => 'phone-sales',
    ]);
    WhatsappPhoneNumberConnection::factory()->for($account)->create([
        'readiness' => WhatsappConnectionReadiness::Disconnected,
        'phone_number_id' => 'phone-old',
    ]);
    WhatsappPhoneNumberConnection::factory()->active()->create([
        'phone_number_id' => 'phone-foreign',
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.new'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts/new')
            ->has('connections', 1)
            ->where('connections.0.id', $active->id)
            ->where('connections.0.phone_number_id', 'phone-sales'));
});

test('broadcast audience count matches contacts with any selected tag without duplicates', function () {
    [$user, $account] = memberWithRole('admin');
    $vip = Tag::factory()->for($account)->create();
    $customer = Tag::factory()->for($account)->create();
    $both = Contact::factory()->for($account)->create();
    $both->tags()->attach([$vip->id, $customer->id]);
    Contact::factory()->for($account)->hasAttached($vip)->create();
    Contact::factory()->for($account)->hasAttached($customer)->create();
    Contact::factory()->for($account)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->getJson(route('broadcasts.audience-count', ['tag_ids' => [$vip->id, $customer->id]]))
        ->assertOk()
        ->assertExactJson(['count' => 3]);

    $this->getJson(route('broadcasts.audience-count'))
        ->assertOk()
        ->assertExactJson(['count' => 4]);
});

test('creating a broadcast freezes the matched audience', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create([
        'status' => MessageTemplateStatus::Approved,
        'language' => 'es_CO',
    ]);
    $tag = Tag::factory()->for($account)->create();
    $matched = Contact::factory()->for($account)->create();
    $matched->tags()->attach($tag);
    Contact::factory()->for($account)->create();
    $connection = activeConnectionFor($account);

    $response = $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('broadcasts.store'), [
            'name' => 'Promoción VIP',
            'template_id' => $template->id,
            'audience_type' => 'tags',
            'tag_ids' => [$tag->id],
            'template_variables' => ['1' => 'Amiga'],
            'connection_id' => $connection->id,
        ]);

    $broadcast = Broadcast::query()->sole();

    $response->assertRedirect(route('broadcasts.show', $broadcast));
    expect($broadcast)
        ->name->toBe('Promoción VIP')
        ->template_name->toBe($template->name)
        ->template_language->toBe('es_CO')
        ->audience_filter->toBe(['type' => 'tags', 'tag_ids' => [$tag->id]])
        ->template_variables->toBe(['1' => 'Amiga'])
        ->status->toBe(BroadcastStatus::Draft)
        ->total_recipients->toBe(1);
    expect(BroadcastRecipient::query()->whereBelongsTo($broadcast)->pluck('contact_id')->all())->toBe([$matched->id]);
});

test('creating a broadcast pins an active connection', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create([
        'status' => MessageTemplateStatus::Approved,
    ]);
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    Contact::factory()->for($account)->create();

    $response = $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('broadcasts.store'), [
            'name' => 'Promoción VIP',
            'template_id' => $template->id,
            'audience_type' => 'all',
            'template_variables' => [],
            'connection_id' => $sales->id,
        ]);

    $broadcast = Broadcast::query()->sole();

    $response->assertRedirect(route('broadcasts.show', $broadcast));
    expect($broadcast->connection_id)->toBe($sales->id);
});

test('creating a broadcast rejects a disconnected or foreign connection', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create([
        'status' => MessageTemplateStatus::Approved,
    ]);
    Contact::factory()->for($account)->create();
    $disconnected = WhatsappPhoneNumberConnection::factory()
        ->for($account)
        ->create(['readiness' => WhatsappConnectionReadiness::Disconnected]);
    $foreign = WhatsappPhoneNumberConnection::factory()->active()->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('broadcasts.new'))
        ->post(route('broadcasts.store'), [
            'name' => 'Sin canal',
            'template_id' => $template->id,
            'audience_type' => 'all',
            'template_variables' => [],
            'connection_id' => $disconnected->id,
        ])
        ->assertRedirect(route('broadcasts.new'))
        ->assertSessionHasErrors('connection_id');

    $this->post(route('broadcasts.store'), [
        'name' => 'Ajena',
        'template_id' => $template->id,
        'audience_type' => 'all',
        'template_variables' => [],
        'connection_id' => $foreign->id,
    ])->assertSessionHasErrors('connection_id');

    expect(Broadcast::query()->count())->toBe(0);
});

test('disconnecting a pinned connection pauses the broadcast instead of switching sender', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create([
        'status' => MessageTemplateStatus::Approved,
    ]);
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    $support = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    Contact::factory()->for($account)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('broadcasts.store'), [
            'name' => 'Campaña ventas',
            'template_id' => $template->id,
            'audience_type' => 'all',
            'template_variables' => [],
            'connection_id' => $sales->id,
        ])
        ->assertRedirect();

    $broadcast = Broadcast::query()->sole();

    $this->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertRedirect(route('settings.whatsapp'));

    $broadcast = $broadcast->fresh();

    expect($broadcast)->not->toBeNull()
        ->and($broadcast->status)->toBe(BroadcastStatus::Paused)
        ->and($broadcast->connection_id)->toBe($sales->id)
        ->and($support->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active);
});

test('creating a scheduled broadcast stores its scheduled status', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create(['status' => MessageTemplateStatus::Approved]);
    Contact::factory()->for($account)->create();
    $connection = activeConnectionFor($account);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('broadcasts.store'), [
            'name' => 'Programada',
            'template_id' => $template->id,
            'audience_type' => 'all',
            'template_variables' => [],
            'scheduled_at' => '2026-12-01T10:30',
            'connection_id' => $connection->id,
        ])
        ->assertRedirect();

    $broadcast = Broadcast::query()->sole();

    expect($broadcast->status)->toBe(BroadcastStatus::Scheduled);
    expect($broadcast->scheduled_at?->format('Y-m-d H:i'))->toBe('2026-12-01 10:30');
});

test('broadcast creation rejects empty, foreign, and unapproved inputs', function () {
    [$user, $account] = memberWithRole('admin');
    $approved = MessageTemplate::factory()->for($account)->create(['status' => MessageTemplateStatus::Approved]);
    $unapproved = MessageTemplate::factory()->for($account)->create(['status' => MessageTemplateStatus::Draft]);
    $foreignTemplate = MessageTemplate::factory()->for(Account::factory())->create(['status' => MessageTemplateStatus::Approved]);
    $foreignTag = Tag::factory()->for(Account::factory())->create();
    $connection = activeConnectionFor($account);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('broadcasts.new'))
        ->post(route('broadcasts.store'), [
            'name' => 'Vacía',
            'template_id' => $approved->id,
            'audience_type' => 'all',
            'template_variables' => [],
            'connection_id' => $connection->id,
        ])
        ->assertRedirect(route('broadcasts.new'))
        ->assertSessionHasErrors('audience');

    foreach ([$unapproved->id, $foreignTemplate->id] as $templateId) {
        $this->post(route('broadcasts.store'), [
            'name' => 'Inválida',
            'template_id' => $templateId,
            'audience_type' => 'all',
            'template_variables' => [],
            'connection_id' => $connection->id,
        ])->assertSessionHasErrors('template_id');
    }

    $this->post(route('broadcasts.store'), [
        'name' => 'Extranjera',
        'template_id' => $approved->id,
        'audience_type' => 'tags',
        'tag_ids' => [$foreignTag->id],
        'template_variables' => [],
        'connection_id' => $connection->id,
    ])->assertSessionHasErrors('tag_ids');

    expect(Broadcast::query()->count())->toBe(0);
});

test('all-contacts broadcasts ignore manipulated tag ids', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create(['status' => MessageTemplateStatus::Approved]);
    $tag = Tag::factory()->for($account)->create();
    $tagged = Contact::factory()->for($account)->create();
    $tagged->tags()->attach($tag);
    $untagged = Contact::factory()->for($account)->create();
    $connection = activeConnectionFor($account);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('broadcasts.store'), [
            'name' => 'Todos',
            'template_id' => $template->id,
            'audience_type' => 'all',
            'tag_ids' => [$tag->id],
            'template_variables' => [],
            'connection_id' => $connection->id,
        ])
        ->assertRedirect();

    $broadcast = Broadcast::query()->sole();

    expect($broadcast->audience_filter)->toBe(['type' => 'all', 'tag_ids' => []]);
    expect(BroadcastRecipient::query()->whereBelongsTo($broadcast)->pluck('contact_id')->sort()->values()->all())
        ->toBe(collect([$tagged->id, $untagged->id])->sort()->values()->all());
});
