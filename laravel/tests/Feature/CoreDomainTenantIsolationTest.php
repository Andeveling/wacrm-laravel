<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Conversation;
use App\Models\CustomField;
use App\Models\Deal;
use App\Models\MemberPresence;
use App\Models\MessageTemplate;
use App\Models\Notification;
use App\Models\Pipeline;
use App\Models\QuickReply;
use App\Models\Scopes\AccountScope;
use App\Models\Tag;
use App\Models\WhatsappConfig;
use Database\Factories\ContactFactory;
use Database\Factories\ContactNoteFactory;
use Database\Factories\ConversationFactory;
use Database\Factories\CustomFieldFactory;
use Database\Factories\DealFactory;
use Database\Factories\MemberPresenceFactory;
use Database\Factories\MessageTemplateFactory;
use Database\Factories\NotificationFactory;
use Database\Factories\PipelineFactory;
use Database\Factories\QuickReplyFactory;
use Database\Factories\TagFactory;
use Database\Factories\WhatsappConfigFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsTenantIsolation;

uses(AssertsTenantIsolation::class);
uses(RefreshDatabase::class);

afterEach(function () {
    app()->forgetInstance(AccountScope::CONTAINER_KEY);

});

/**
 * @return array<string, array{class-string, class-string<Factory>}>
 */
dataset('tenantScopedModels', function () {
    return [
        'contacts' => [Contact::class, ContactFactory::class],
        'tags' => [Tag::class, TagFactory::class],
        'custom_fields' => [CustomField::class, CustomFieldFactory::class],
        'contact_notes' => [ContactNote::class, ContactNoteFactory::class],
        'whatsapp_config' => [WhatsappConfig::class, WhatsappConfigFactory::class],
        'conversations' => [Conversation::class, ConversationFactory::class],
        'quick_replies' => [QuickReply::class, QuickReplyFactory::class],
        'message_templates' => [MessageTemplate::class, MessageTemplateFactory::class],
        'pipelines' => [Pipeline::class, PipelineFactory::class],
        'deals' => [Deal::class, DealFactory::class],
        'notifications' => [Notification::class, NotificationFactory::class],
        'member_presence' => [MemberPresence::class, MemberPresenceFactory::class],
    ];
});

test('model is tenant isolated', function (string $modelClass, string $factoryClass) {
    $this->assertTenantIsolation($modelClass, $factoryClass::new());
})->with('tenantScopedModels');

test('contact phone is normalized to digits', function () {
    $account = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    $contact = Contact::factory()->create(['phone' => '+1 (555) 123-4567']);

    expect($contact->fresh()->phone_normalized)->toBe('15551234567');
});

test('duplicate normalized phone is rejected within an account', function () {
    $account = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    Contact::factory()->create(['account_id' => $account->id, 'phone' => '+57 310 555 0101']);

    $this->expectException(QueryException::class);

    Contact::factory()->create(['account_id' => $account->id, 'phone' => '573105550101']);
});

test('same phone is allowed on another account', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
    Contact::factory()->create(['phone' => '+57 310 555 0102']);

    app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
    $other = Contact::factory()->create(['phone' => '573105550102']);

    expect($other->fresh()->phone_normalized)->toBe('573105550102');
});

test('second conversation for same contact is rejected', function () {
    $account = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    $conversation = Conversation::factory()->create(['account_id' => $account->id]);

    $this->expectException(QueryException::class);

    Conversation::factory()->create([
        'account_id' => $account->id,
        'contact_id' => $conversation->contact_id,
    ]);
});
