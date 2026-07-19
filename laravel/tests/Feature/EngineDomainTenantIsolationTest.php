<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AiConfig;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Models\AiUsageLog;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\AutomationPendingExecution;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Enums\FlowRunStatus;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\Scopes\AccountScope;
use App\Models\WebhookEndpoint;
use Database\Factories\AiConfigFactory;
use Database\Factories\AiKnowledgeChunkFactory;
use Database\Factories\AiKnowledgeDocumentFactory;
use Database\Factories\AiUsageLogFactory;
use Database\Factories\AutomationFactory;
use Database\Factories\AutomationLogFactory;
use Database\Factories\AutomationPendingExecutionFactory;
use Database\Factories\BroadcastFactory;
use Database\Factories\FlowFactory;
use Database\Factories\FlowRunFactory;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsTenantIsolation;
use Tests\TestCase;

/**
 * Issue #40: cada modelo de motores + IA con tenencia (BelongsToAccount)
 * pasa las cinco aserciones de aislamiento, más los invariantes únicos
 * que custodian índices parciales/UNIQUE (portados de Supabase 003/010/029).
 * Las tablas hijas sin account_id (broadcast_recipients, automation_steps,
 * flow_nodes, flow_run_events) heredan tenencia vía su padre.
 */
class EngineDomainTenantIsolationTest extends TestCase
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
            'broadcasts' => [Broadcast::class, BroadcastFactory::class],
            'automations' => [Automation::class, AutomationFactory::class],
            'automation_logs' => [AutomationLog::class, AutomationLogFactory::class],
            'automation_pending_executions' => [AutomationPendingExecution::class, AutomationPendingExecutionFactory::class],
            'flows' => [Flow::class, FlowFactory::class],
            'flow_runs' => [FlowRun::class, FlowRunFactory::class],
            'webhook_endpoints' => [WebhookEndpoint::class, WebhookEndpointFactory::class],
            'ai_configs' => [AiConfig::class, AiConfigFactory::class],
            'ai_knowledge_documents' => [AiKnowledgeDocument::class, AiKnowledgeDocumentFactory::class],
            'ai_knowledge_chunks' => [AiKnowledgeChunk::class, AiKnowledgeChunkFactory::class],
            'ai_usage_log' => [AiUsageLog::class, AiUsageLogFactory::class],
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
    public function second_active_flow_run_for_same_contact_is_rejected(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $run = FlowRun::factory()->create([
            'account_id' => $account->id,
            'contact_id' => Contact::factory()->create(['account_id' => $account->id])->id,
            'status' => FlowRunStatus::Active,
        ]);

        $this->expectException(QueryException::class);

        FlowRun::factory()->create([
            'account_id' => $account->id,
            'contact_id' => $run->contact_id,
            'status' => FlowRunStatus::Active,
        ]);
    }

    #[Test]
    public function ended_run_frees_the_active_slot_for_the_contact(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $contact = Contact::factory()->create(['account_id' => $account->id]);
        FlowRun::factory()->create([
            'account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => FlowRunStatus::Completed,
        ]);

        $again = FlowRun::factory()->create([
            'account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => FlowRunStatus::Active,
        ]);

        $this->assertSame(FlowRunStatus::Active, $again->fresh()->status);
    }

    #[Test]
    public function duplicate_whatsapp_message_id_on_recipients_is_rejected(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $broadcast = Broadcast::factory()->create(['account_id' => $account->id]);
        BroadcastRecipient::create([
            'broadcast_id' => $broadcast->id,
            'whatsapp_message_id' => 'wamid.duplicated',
        ]);

        $this->expectException(QueryException::class);

        BroadcastRecipient::create([
            'broadcast_id' => $broadcast->id,
            'whatsapp_message_id' => 'wamid.duplicated',
        ]);
    }

    #[Test]
    public function second_ai_config_for_same_account_is_rejected(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        AiConfig::factory()->create(['account_id' => $account->id]);

        $this->expectException(QueryException::class);

        AiConfig::factory()->create(['account_id' => $account->id]);
    }
}
