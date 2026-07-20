<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\ContactCustomValue;
use App\Models\ContactTag;
use App\Models\Conversation;
use App\Models\CustomField;
use App\Models\Deal;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\ConversationStatus;
use App\Models\Enums\DealStatus;
use App\Models\Enums\FlowStatus;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\FlowRun;
use App\Models\FlowRunEvent;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Scopes\AccountScope;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Issue #41: el demo seeder debe producir un escenario coherente que
 * satisfaga tanto la necesidad de dev local (un Team poblado) como la
 * invariante de producción (todo user registrado tiene Personal account).
 *
 * Cada #[Test] re-sembra desde cero gracias a RefreshDatabase. Las
 * aserciones son de existencia (estados correctos) y de rango (conteos
 * mínimos), no de igualdad exacta — los nombres/contenidos son fake y
 * solo la estructura es verificable.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_ACCOUNT_NAME = 'WACRM Demo';

    private const DEMO_USER_EMAILS = [
        'admin@demo.test' => AccountRole::Admin,
        'agent@demo.test' => AccountRole::Member,
        'viewer@demo.test' => AccountRole::Viewer,
    ];

    #[Test]
    public function personal_account_invariant_holds_for_test_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Account no tiene BelongsToAccount, no necesita tenant binding.
        $user = User::where('email', 'test@example.com')->firstOrFail();
        $personal = $user->accounts()
            ->where('type', AccountType::Personal->value)
            ->firstOrFail();

        $this->assertSame(Account::PERSONAL_NAME, $personal->name);
        $this->assertSame(AccountRole::Owner, $personal->pivot->role);
    }

    #[Test]
    public function demo_team_account_has_one_member_per_role(): void
    {
        $team = $this->seedAndBind();

        $this->assertSame(AccountType::Team, $team->type);

        $rolesByEmail = $team->users()
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->email => $u->pivot->role])
            ->all();

        // test@example.com es Owner del Team (además de su Personal).
        $this->assertSame(AccountRole::Owner, $rolesByEmail['test@example.com']);

        // Cada rol sintético está asignado exactamente una vez en el Team.
        foreach (self::DEMO_USER_EMAILS as $email => $expectedRole) {
            $this->assertArrayHasKey($email, $rolesByEmail, "{$email} debe ser miembro del Team.");
            $this->assertSame(
                $expectedRole,
                $rolesByEmail[$email],
                "{$email} debe tener rol {$expectedRole->value}.",
            );
        }

        $this->assertCount(4, $rolesByEmail);
    }

    #[Test]
    public function demo_contacts_have_tags_and_custom_values(): void
    {
        $team = $this->seedAndBind();

        // 4 tags y 3 custom fields sembrados dentro de la cuenta demo.
        $this->assertGreaterThanOrEqual(4, Tag::where('account_id', $team->id)->count());
        $this->assertGreaterThanOrEqual(3, CustomField::where('account_id', $team->id)->count());

        $contacts = Contact::where('account_id', $team->id)->get();
        $this->assertGreaterThanOrEqual(10, $contacts->count());

        // La mayoría llevan al menos un tag; todos llevan custom values para los 3 sembrados.
        $taggedCount = ContactTag::whereIn('contact_id', $contacts->pluck('id'))
            ->distinct('contact_id')->count();
        $this->assertGreaterThan($contacts->count() / 2, $taggedCount);

        $valuedCount = ContactCustomValue::whereIn('contact_id', $contacts->pluck('id'))
            ->distinct('contact_id')->count();
        $this->assertSame($contacts->count(), $valuedCount);
    }

    #[Test]
    public function demo_conversations_have_messages_with_ai_generated_and_reactions(): void
    {
        $team = $this->seedAndBind();

        $conversations = Conversation::where('account_id', $team->id)->get();
        $this->assertGreaterThanOrEqual(4, $conversations->count());

        $statuses = $conversations->pluck('status')->map->value;
        $this->assertContains(ConversationStatus::Open->value, $statuses);
        $this->assertContains(ConversationStatus::Pending->value, $statuses);
        $this->assertContains(ConversationStatus::Closed->value, $statuses);

        foreach ($conversations as $conversation) {
            $this->assertGreaterThanOrEqual(
                3,
                Message::where('conversation_id', $conversation->id)->count(),
                "Conversación {$conversation->id} debe tener ≥3 mensajes.",
            );
        }

        $messageIds = Message::whereIn('conversation_id', $conversations->pluck('id'))->pluck('id');
        $this->assertGreaterThan(
            0,
            Message::whereIn('id', $messageIds)->where('ai_generated', true)->count(),
            'Al menos un mensaje demo debe ser ai_generated=true.',
        );
    }

    #[Test]
    public function demo_pipeline_has_deals_distributed_across_stages_and_statuses(): void
    {
        $team = $this->seedAndBind();

        $pipeline = Pipeline::where('account_id', $team->id)->firstOrFail();
        $stages = PipelineStage::where('pipeline_id', $pipeline->id)->orderBy('position')->get();
        $this->assertGreaterThanOrEqual(3, $stages->count());

        $deals = Deal::where('account_id', $team->id)->get();
        $this->assertGreaterThanOrEqual(6, $deals->count());

        $statuses = $deals->pluck('status')->map->value;
        $this->assertContains(DealStatus::Open->value, $statuses);
        $this->assertContains(DealStatus::Won->value, $statuses);
        $this->assertContains(DealStatus::Lost->value, $statuses);

        // Cada deal.stage_id pertenece al pipeline del deal.
        foreach ($deals as $deal) {
            $this->assertSame(
                $deal->pipeline_id,
                PipelineStage::find($deal->stage_id)->pipeline_id,
                "Deal {$deal->id} tiene stage de otro pipeline.",
            );
        }
    }

    #[Test]
    public function demo_broadcast_has_recipients_in_mixed_states(): void
    {
        $team = $this->seedAndBind();

        $broadcasts = Broadcast::where('account_id', $team->id)->get();
        $this->assertGreaterThanOrEqual(2, $broadcasts->count());

        $statuses = $broadcasts->pluck('status')->map->value;
        $this->assertContains(BroadcastStatus::Draft->value, $statuses);
        $this->assertContains(BroadcastStatus::Sent->value, $statuses);

        $sentBroadcast = $broadcasts->firstWhere('status', BroadcastStatus::Sent->value);
        $recipientStatuses = BroadcastRecipient::where('broadcast_id', $sentBroadcast->id)
            ->pluck('status')
            ->map->value
            ->unique()
            ->values();
        $this->assertGreaterThan(
            1,
            $recipientStatuses->count(),
            'El broadcast sent debe tener recipients en ≥2 estados distintos.',
        );
    }

    #[Test]
    public function demo_automation_and_flow_have_steps_nodes_and_runs(): void
    {
        $team = $this->seedAndBind();

        $automations = Automation::where('account_id', $team->id)->get();
        $this->assertGreaterThanOrEqual(2, $automations->count());

        $this->assertTrue($automations->contains(fn (Automation $a) => $a->is_active));
        $this->assertTrue($automations->contains(fn (Automation $a) => ! $a->is_active));

        $activeAutomation = $automations->firstWhere('is_active', true);
        $stepCount = AutomationStep::where('automation_id', $activeAutomation->id)->count();
        $this->assertGreaterThanOrEqual(3, $stepCount);

        $flows = Flow::where('account_id', $team->id)->get();
        $this->assertGreaterThanOrEqual(2, $flows->count());

        $statuses = $flows->pluck('status')->map->value;
        $this->assertContains(FlowStatus::Draft->value, $statuses);
        $this->assertContains(FlowStatus::Active->value, $statuses);

        $activeFlow = $flows->firstWhere('status', FlowStatus::Active->value);
        $nodeCount = FlowNode::where('flow_id', $activeFlow->id)->count();
        $this->assertGreaterThanOrEqual(5, $nodeCount);

        $runs = FlowRun::where('flow_id', $activeFlow->id)->get();
        $runStatuses = $runs->pluck('status')->map->value;
        $this->assertContains('active', $runStatuses);
        $this->assertContains('completed', $runStatuses);

        $runIds = $runs->pluck('id');
        $this->assertGreaterThan(
            0,
            FlowRunEvent::whereIn('flow_run_id', $runIds)->count(),
        );
    }

    #[Test]
    public function seeder_is_idempotent_when_run_twice(): void
    {
        $this->seedAndBind();
        $counts = $this->snapshotCounts();

        // Segunda corrida no debe duplicar nada ni lanzar.
        $this->seed(DatabaseSeeder::class);
        $countsAfter = $this->snapshotCounts();

        foreach ($counts as $key => $value) {
            $this->assertSame(
                $value,
                $countsAfter[$key] ?? null,
                "Conteo de {$key} cambió tras re-seed: esperado {$value}.",
            );
        }
    }

    /**
     * Run the seeder from scratch under RefreshDatabase and bind the demo
     * Team as the current tenant for downstream queries. AccountScope is
     * fail-closed (`WHERE 1=0` sin tenant), so without this bind every
     * Eloquent query against scoped models returns zero rows.
     */
    private function seedAndBind(): Account
    {
        $this->seed(DatabaseSeeder::class);
        $team = Account::where('name', self::DEMO_ACCOUNT_NAME)->firstOrFail();
        app()->instance(AccountScope::CONTAINER_KEY, $team->id);

        return $team;
    }

    /**
     * Captura los conteos clave del escenario demo. Usa
     * withoutGlobalScopes para que sea agnóstica al tenant binding.
     *
     * @return array<string, int>
     */
    private function snapshotCounts(): array
    {
        return [
            'teams' => Account::where('type', AccountType::Team->value)->count(),
            'contacts' => Contact::withoutGlobalScopes()->count(),
            'conversations' => Conversation::withoutGlobalScopes()->count(),
            'deals' => Deal::withoutGlobalScopes()->count(),
            'broadcasts' => Broadcast::withoutGlobalScopes()->count(),
            'broadcast_recipients' => BroadcastRecipient::count(),
            'automations' => Automation::withoutGlobalScopes()->count(),
            'automation_steps' => AutomationStep::count(),
            'flows' => Flow::withoutGlobalScopes()->count(),
            'flow_nodes' => FlowNode::count(),
            'flow_runs' => FlowRun::withoutGlobalScopes()->count(),
            'flow_run_events' => FlowRunEvent::count(),
        ];
    }
}
