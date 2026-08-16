<?php

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
use App\Models\Enums\WhatsappConnectionReadiness;
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
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const DEMO_ACCOUNT_NAME = 'WACRM Demo';

const DEMO_USER_EMAILS = [
    'admin@demo.test' => AccountRole::Admin,
    'agent@demo.test' => AccountRole::Member,
    'viewer@demo.test' => AccountRole::Viewer,
];

test('personal account invariant holds for test user', function () {
    $this->seed(DatabaseSeeder::class);

    // Account no tiene BelongsToAccount, no necesita tenant binding.
    $user = User::where('email', 'test@example.com')->firstOrFail();
    $personal = $user->accounts()
        ->where('type', AccountType::Personal->value)
        ->firstOrFail();

    expect($personal->name)->toBe(Account::PERSONAL_NAME);
    expect($personal->pivot->role)->toBe(AccountRole::Owner);
});

test('demo team account has one member per role', function () {
    $team = seedAndBind();

    expect($team->type)->toBe(AccountType::Team);

    $rolesByEmail = $team->users()
        ->get()
        ->mapWithKeys(fn (User $u) => [$u->email => $u->pivot->role])
        ->all();

    // test@example.com es Owner del Team (además de su Personal).
    expect($rolesByEmail['test@example.com'])->toBe(AccountRole::Owner);

    // Cada rol sintético está asignado exactamente una vez en el Team.
    foreach (DEMO_USER_EMAILS as $email => $expectedRole) {
        expect($rolesByEmail)->toHaveKey($email);
        expect($rolesByEmail[$email])->toBe($expectedRole, "{$email} debe tener rol {$expectedRole->value}.");
    }

    expect($rolesByEmail)->toHaveCount(4);
});

test('demo contacts have tags and custom values', function () {
    $team = seedAndBind();

    // 3 tags y 3 custom fields sembrados dentro de la cuenta demo.
    expect(Tag::where('account_id', $team->id)->count())->toBeGreaterThanOrEqual(3);
    expect(CustomField::where('account_id', $team->id)->count())->toBeGreaterThanOrEqual(3);

    $contacts = Contact::where('account_id', $team->id)->get();
    expect($contacts->count())->toBeGreaterThanOrEqual(10);

    // Todos llevan al menos un tag y custom values para los 3 sembrados.
    $taggedCount = ContactTag::whereIn('contact_id', $contacts->pluck('id'))
        ->distinct('contact_id')->count();
    expect($taggedCount)->toBe($contacts->count());

    $valuedCount = ContactCustomValue::whereIn('contact_id', $contacts->pluck('id'))
        ->distinct('contact_id')->count();
    expect($valuedCount)->toBe($contacts->count());
});
test('demo team has a connected default WhatsApp number', function () {
    $team = seedAndBind();

    $integration = WhatsappIntegration::where('account_id', $team->id)->firstOrFail();
    $waba = WabaSubscription::where('account_id', $team->id)->firstOrFail();
    $connection = WhatsappPhoneNumberConnection::where('account_id', $team->id)->firstOrFail();

    expect($waba->integration_id)->toBe($integration->id)
        ->and($connection->waba_subscription_id)->toBe($waba->id)
        ->and($connection->is_default)->toBeTrue()
        ->and($connection->readiness)->toBe(WhatsappConnectionReadiness::Active)
        ->and(Conversation::where('account_id', $team->id)->where('connection_id', $connection->id)->count())
        ->toBeGreaterThanOrEqual(4);
});

test('demo conversations have messages with ai generated and reactions', function () {
    $team = seedAndBind();

    $conversations = Conversation::where('account_id', $team->id)->get();
    expect($conversations->count())->toBeGreaterThanOrEqual(4);

    $statuses = $conversations->pluck('status')->map->value;
    expect($statuses)->toContain(ConversationStatus::Open->value);
    expect($statuses)->toContain(ConversationStatus::Pending->value);
    expect($statuses)->toContain(ConversationStatus::Closed->value);

    foreach ($conversations as $conversation) {
        expect(Message::where('conversation_id', $conversation->id)->count())->toBeGreaterThanOrEqual(3, "Conversación {$conversation->id} debe tener ≥3 mensajes.");
    }

    $messageIds = Message::whereIn('conversation_id', $conversations->pluck('id'))->pluck('id');
    expect(Message::whereIn('id', $messageIds)->where('ai_generated', true)->count())->toBeGreaterThan(0, 'Al menos un mensaje demo debe ser ai_generated=true.');
});

test('demo pipeline has deals distributed across stages and statuses', function () {
    $team = seedAndBind();

    $pipeline = Pipeline::where('account_id', $team->id)->firstOrFail();
    $stages = PipelineStage::where('pipeline_id', $pipeline->id)->orderBy('position')->get();
    expect($stages->count())->toBeGreaterThanOrEqual(3);

    $deals = Deal::where('account_id', $team->id)->get();
    expect($deals->count())->toBeGreaterThanOrEqual(6);

    $statuses = $deals->pluck('status')->map->value;
    expect($statuses)->toContain(DealStatus::Open->value);
    expect($statuses)->toContain(DealStatus::Won->value);
    expect($statuses)->toContain(DealStatus::Lost->value);

    // Cada deal.stage_id pertenece al pipeline del deal.
    foreach ($deals as $deal) {
        expect(PipelineStage::find($deal->stage_id)->pipeline_id)->toBe($deal->pipeline_id, "Deal {$deal->id} tiene stage de otro pipeline.");
    }
});

test('demo broadcast has recipients in mixed states', function () {
    $team = seedAndBind();

    $broadcasts = Broadcast::where('account_id', $team->id)->get();
    expect($broadcasts->count())->toBeGreaterThanOrEqual(2);

    $statuses = $broadcasts->pluck('status')->map->value;
    expect($statuses)->toContain(BroadcastStatus::Draft->value);
    expect($statuses)->toContain(BroadcastStatus::Sent->value);

    $sentBroadcast = $broadcasts->firstWhere('status', BroadcastStatus::Sent->value);
    $recipientStatuses = BroadcastRecipient::where('broadcast_id', $sentBroadcast->id)
        ->pluck('status')
        ->map->value
        ->unique()
        ->values();
    expect($recipientStatuses->count())->toBeGreaterThan(1, 'El broadcast sent debe tener recipients en ≥2 estados distintos.');
});

test('demo automation and flow have steps nodes and runs', function () {
    $team = seedAndBind();

    $automations = Automation::where('account_id', $team->id)->get();
    expect($automations->count())->toBeGreaterThanOrEqual(2);

    expect($automations->contains(fn (Automation $a) => $a->is_active))->toBeTrue();
    expect($automations->contains(fn (Automation $a) => ! $a->is_active))->toBeTrue();

    $activeAutomation = $automations->firstWhere('is_active', true);
    $stepCount = AutomationStep::where('automation_id', $activeAutomation->id)->count();
    expect($stepCount)->toBeGreaterThanOrEqual(3);

    $flows = Flow::where('account_id', $team->id)->get();
    expect($flows->count())->toBeGreaterThanOrEqual(2);

    $statuses = $flows->pluck('status')->map->value;
    expect($statuses)->toContain(FlowStatus::Draft->value);
    expect($statuses)->toContain(FlowStatus::Active->value);

    $activeFlow = $flows->firstWhere('status', FlowStatus::Active->value);
    $nodeCount = FlowNode::where('flow_id', $activeFlow->id)->count();
    expect($nodeCount)->toBeGreaterThanOrEqual(5);

    $runs = FlowRun::where('flow_id', $activeFlow->id)->get();
    $runStatuses = $runs->pluck('status')->map->value;
    expect($runStatuses)->toContain('active');
    expect($runStatuses)->toContain('completed');

    $runIds = $runs->pluck('id');
    expect(FlowRunEvent::whereIn('flow_run_id', $runIds)->count())->toBeGreaterThan(0);
});

test('seeder is idempotent when run twice', function () {
    seedAndBind();
    $counts = snapshotCounts();

    // Segunda corrida no debe duplicar nada ni lanzar.
    $this->seed(DatabaseSeeder::class);
    $countsAfter = snapshotCounts();

    foreach ($counts as $key => $value) {
        expect($countsAfter[$key] ?? null)->toBe($value, "Conteo de {$key} cambió tras re-seed: esperado {$value}.");
    }
});

/**
 * Run the seeder from scratch under RefreshDatabase and bind the demo
 * Team as the current tenant for downstream queries. AccountScope is
 * fail-closed (`WHERE 1=0` sin tenant), so without this bind every
 * Eloquent query against scoped models returns zero rows.
 */
function seedAndBind(): Account
{
    test()->seed(DatabaseSeeder::class);
    $team = Account::where('name', DEMO_ACCOUNT_NAME)->firstOrFail();
    app()->instance(AccountScope::CONTAINER_KEY, $team->id);

    return $team;
}

/**
 * Captura los conteos clave del escenario demo. Usa
 * withoutGlobalScopes para que sea agnóstica al tenant binding.
 *
 * @return array<string, int>
 */
function snapshotCounts(): array
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
