<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Enums\FlowNodeType;
use App\Models\Enums\FlowRunEventType;
use App\Models\Enums\FlowRunStatus;
use App\Models\Enums\FlowStatus;
use App\Models\Enums\FlowTriggerType;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\FlowRun;
use App\Models\FlowRunEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Flujos demo: uno en borrador y uno activo con su grafo de nodos, una
 * ejecución en curso y otra completada con sus eventos.
 */
class DemoFlowsSeeder extends Seeder
{
    private const ENTRY_NODE_KEY = 'start';

    public function run(Account $team, User $owner): void
    {
        Flow::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Catálogo Express'],
            [
                'user_id' => $owner->id,
                'trigger_type' => FlowTriggerType::Keyword->value,
                'trigger_config' => ['keyword' => 'catalogo'],
                'status' => FlowStatus::Draft,
            ],
        );

        $active = Flow::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Confirmar agendamiento'],
            [
                'user_id' => $owner->id,
                'trigger_type' => FlowTriggerType::Keyword->value,
                'trigger_config' => ['keyword' => 'agendar'],
                'status' => FlowStatus::Active,
            ],
        );

        if (FlowNode::where('flow_id', $active->id)->exists()) {
            return;
        }

        $this->createNodes($active);

        $contact = Contact::where('account_id', $team->id)->inRandomOrder()->first();

        if ($contact !== null) {
            $this->createRuns($active, $owner, $contact);
        }
    }

    private function createNodes(Flow $flow): void
    {
        foreach ($this->nodeDefinitions() as $node) {
            FlowNode::create(['flow_id' => $flow->id, ...$node]);
        }

        $flow->update(['entry_node_id' => self::ENTRY_NODE_KEY]);
    }

    /**
     * @return list<array{node_key: string, node_type: FlowNodeType, config: array<string, mixed>, position_x: int, position_y: int}>
     */
    private function nodeDefinitions(): array
    {
        return [
            ['node_key' => self::ENTRY_NODE_KEY, 'node_type' => FlowNodeType::Start, 'config' => [], 'position_x' => 100, 'position_y' => 100],
            ['node_key' => 'msg_greet', 'node_type' => FlowNodeType::SendMessage, 'config' => ['text' => '¡Hola! Vamos a confirmar tu cita.'], 'position_x' => 300, 'position_y' => 100],
            ['node_key' => 'collect_date', 'node_type' => FlowNodeType::CollectInput, 'config' => ['variable' => 'fecha'], 'position_x' => 500, 'position_y' => 100],
            ['node_key' => 'check_date', 'node_type' => FlowNodeType::Condition, 'config' => ['expression' => '{{fecha}} is valid date'], 'position_x' => 700, 'position_y' => 100],
            ['node_key' => 'handoff_human', 'node_type' => FlowNodeType::Handoff, 'config' => ['queue' => 'scheduling'], 'position_x' => 900, 'position_y' => 50],
            ['node_key' => 'msg_confirm', 'node_type' => FlowNodeType::SendMessage, 'config' => ['text' => 'Listo, agendado para {{fecha}}.'], 'position_x' => 900, 'position_y' => 200],
            ['node_key' => 'end', 'node_type' => FlowNodeType::End, 'config' => [], 'position_x' => 1100, 'position_y' => 200],
        ];
    }

    private function createRuns(Flow $flow, User $owner, Contact $contact): void
    {
        $running = FlowRun::create([
            'account_id' => $flow->account_id,
            'flow_id' => $flow->id,
            'user_id' => $owner->id,
            'contact_id' => $contact->id,
            'status' => FlowRunStatus::Active,
            'current_node_key' => 'collect_date',
            'vars' => ['fecha' => null],
            'started_at' => now()->subMinutes(10),
            'last_advanced_at' => now()->subMinutes(2),
        ]);

        $completed = FlowRun::create([
            'account_id' => $flow->account_id,
            'flow_id' => $flow->id,
            'user_id' => $owner->id,
            'contact_id' => $contact->id,
            'status' => FlowRunStatus::Completed,
            'current_node_key' => 'end',
            'vars' => ['fecha' => '2026-08-15'],
            'started_at' => now()->subHours(2),
            'last_advanced_at' => now()->subHour(),
            'ended_at' => now()->subHour(),
            'end_reason' => 'completed',
        ]);

        FlowRunEvent::create([
            'flow_run_id' => $running->id,
            'event_type' => FlowRunEventType::Started,
            'node_key' => self::ENTRY_NODE_KEY,
            'payload' => [],
        ]);

        FlowRunEvent::create([
            'flow_run_id' => $completed->id,
            'event_type' => FlowRunEventType::Started,
            'node_key' => self::ENTRY_NODE_KEY,
            'payload' => [],
        ]);

        FlowRunEvent::create([
            'flow_run_id' => $completed->id,
            'event_type' => FlowRunEventType::Completed,
            'node_key' => 'end',
            'payload' => ['fecha' => '2026-08-15'],
        ]);
    }
}
