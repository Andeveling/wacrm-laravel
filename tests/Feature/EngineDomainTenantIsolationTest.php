<?php

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
});

test('model is tenant isolated', function (string $modelClass, string $factoryClass) {
    $this->assertTenantIsolation($modelClass, $factoryClass::new());
})->with('tenantScopedModels');

test('second active flow run for same contact is rejected', function () {
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
});

test('ended run frees the active slot for the contact', function () {
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

    expect($again->fresh()->status)->toBe(FlowRunStatus::Active);
});

test('duplicate whatsapp message id on recipients is rejected', function () {
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
});

test('second ai config for same account is rejected', function () {
    $account = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    AiConfig::factory()->create(['account_id' => $account->id]);

    $this->expectException(QueryException::class);

    AiConfig::factory()->create(['account_id' => $account->id]);
});
