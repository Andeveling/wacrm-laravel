<?php

namespace Tests\Feature;

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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsTenantIsolation;
use Tests\TestCase;

/**
 * Issue #39: cada modelo de dominio core con tenencia (BelongsToAccount)
 * pasa las cinco aserciones de aislamiento, más los invariantes de
 * anti-duplicados que en pgsql custodian columnas GENERATED/índices únicos.
 */
class CoreDomainTenantIsolationTest extends TestCase
{
    use AssertsTenantIsolation, RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        parent::tearDown();
    }

    /**
     * @return array<string, array{class-string, class-string<Factory>}>
     */
    public static function tenantScopedModels(): array
    {
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
    }

    /**
     * @param  class-string  $modelClass
     * @param  class-string<Factory>  $factoryClass
     */
    #[Test]
    #[DataProvider('tenantScopedModels')]
    public function model_is_tenant_isolated(string $modelClass, string $factoryClass): void
    {
        $this->assertTenantIsolation($modelClass, $factoryClass::new());
    }

    #[Test]
    public function contact_phone_is_normalized_to_digits(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $contact = Contact::factory()->create(['phone' => '+1 (555) 123-4567']);

        $this->assertSame('15551234567', $contact->fresh()->phone_normalized);
    }

    #[Test]
    public function duplicate_normalized_phone_is_rejected_within_an_account(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        Contact::factory()->create(['account_id' => $account->id, 'phone' => '+57 310 555 0101']);

        $this->expectException(QueryException::class);

        Contact::factory()->create(['account_id' => $account->id, 'phone' => '573105550101']);
    }

    #[Test]
    public function same_phone_is_allowed_on_another_account(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
        Contact::factory()->create(['phone' => '+57 310 555 0102']);

        app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
        $other = Contact::factory()->create(['phone' => '573105550102']);

        $this->assertSame('573105550102', $other->fresh()->phone_normalized);
    }

    #[Test]
    public function second_conversation_for_same_contact_is_rejected(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $conversation = Conversation::factory()->create(['account_id' => $account->id]);

        $this->expectException(QueryException::class);

        Conversation::factory()->create([
            'account_id' => $account->id,
            'contact_id' => $conversation->contact_id,
        ]);
    }
}
