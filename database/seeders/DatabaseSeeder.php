<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountUser;
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
use App\Models\Enums\BroadcastRecipientStatus;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\ConversationStatus;
use App\Models\Enums\DealStatus;
use App\Models\Enums\FlowNodeType;
use App\Models\Enums\FlowRunEventType;
use App\Models\Enums\FlowRunStatus;
use App\Models\Enums\FlowStatus;
use App\Models\Enums\FlowTriggerType;
use App\Models\Enums\MessageStatus;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\FlowRun;
use App\Models\FlowRunEvent;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Scopes\AccountScope;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo seeder for issue #41. Idempotent by entity: each `firstOrCreate`
 * is keyed on the natural unique constraint of its table, so re-running
 * `db:seed` is safe and only re-creates what was deleted.
 *
 * Produces:
 *  - test@example.com with a Personal account (production invariant).
 *  - A Team "WACRM Demo" populated with realistic WhatsApp CRM data so
 *    devs running the app locally see a coherent scenario.
 *
 * Content is in Spanish (Faker es_ES).
 */
class DatabaseSeeder extends Seeder
{
    private const DEMO_ACCOUNT_NAME = 'WACRM Demo';

    private const TEST_USER_EMAIL = 'test@example.com';

    private const SYNTHETIC_PASSWORD = 'password';

    /**
     * @var array<string, AccountRole>
     */
    private const DEMO_MEMBERS = [
        'admin@demo.test' => AccountRole::Admin,
        'agent@demo.test' => AccountRole::Member,
        'viewer@demo.test' => AccountRole::Viewer,
    ];

    public function run(): void
    {
        config(['app.faker_locale' => 'es_ES']);

        DB::transaction(function (): void {
            $owner = $this->seedTestUserAndPersonalAccount();
            $team = $this->seedDemoTeamAccount();

            // AccountScope is fail-closed (WHERE 1=0) without a tenant
            // binding. Bind the team so all scoped-model queries work.
            app()->instance(AccountScope::CONTAINER_KEY, $team->id);

            try {
                $syntheticUsers = $this->seedSyntheticUsers();
                $this->seedDemoMembers($team, $owner, $syntheticUsers);

                $this->seedDemoCustomFields($team, $owner);
                $this->seedDemoTags($team, $owner);

                $tags = Tag::where('account_id', $team->id)->get();
                $customFields = CustomField::where('account_id', $team->id)->get();

                $contacts = $this->seedDemoContacts($team, $owner, $tags, $customFields);
                $this->seedDemoConversationsAndMessages($team, $owner, $contacts);
                $this->seedDemoPipelinesAndDeals($team, $owner, $contacts);
                $this->seedDemoBroadcasts($team, $owner);
                $this->seedDemoAutomations($team, $owner);
                $this->seedDemoFlows($team, $owner);
            } finally {
                app()->forgetInstance(AccountScope::CONTAINER_KEY);
            }
        });

        $this->printDemoCredentials();
    }

    // ───────────────── accounts + users ─────────────────

    private function seedTestUserAndPersonalAccount(): User
    {
        $user = User::firstOrCreate(
            ['email' => self::TEST_USER_EMAIL],
            [
                'name' => 'Test User',
                'password' => Hash::make(self::SYNTHETIC_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $personal = Account::firstOrCreate(
            ['name' => Account::PERSONAL_NAME, 'type' => AccountType::Personal->value],
        );

        if (! $personal->users()->whereKey($user->id)->exists()) {
            $personal->users()->attach($user->id, [
                'role' => AccountRole::Owner->value,
                'joined_at' => now(),
            ]);
        }

        return $user;
    }

    private function seedDemoTeamAccount(): Account
    {
        return Account::firstOrCreate(
            ['name' => self::DEMO_ACCOUNT_NAME, 'type' => AccountType::Team->value],
        );
    }

    /**
     * @return array<string, User>
     */
    private function seedSyntheticUsers(): array
    {
        $users = [];
        foreach (self::DEMO_MEMBERS as $email => $role) {
            $local = explode('@', $email)[0];
            $users[$email] = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => ucfirst(str_replace('-', ' ', $local)),
                    'password' => Hash::make(self::SYNTHETIC_PASSWORD),
                    'email_verified_at' => now(),
                ],
            );
        }

        return $users;
    }

    /**
     * @param  array<string, User>  $syntheticUsers
     */
    private function seedDemoMembers(Account $team, User $owner, array $syntheticUsers): void
    {
        AccountUser::firstOrCreate(
            ['account_id' => $team->id, 'user_id' => $owner->id],
            ['role' => AccountRole::Owner->value, 'joined_at' => now()->subMonths(6)],
        );

        foreach ($syntheticUsers as $email => $user) {
            AccountUser::firstOrCreate(
                ['account_id' => $team->id, 'user_id' => $user->id],
                ['role' => self::DEMO_MEMBERS[$email]->value, 'joined_at' => now()->subMonths(3)],
            );
        }
    }

    // ───────────────── shared scaffolding ─────────────────

    private function seedDemoCustomFields(Account $team, User $owner): void
    {
        $definitions = [
            ['field_name' => 'industry', 'field_type' => 'text'],
            ['field_name' => 'city', 'field_type' => 'text'],
            ['field_name' => 'birthdate', 'field_type' => 'date'],
        ];

        foreach ($definitions as $def) {
            CustomField::firstOrCreate(
                ['account_id' => $team->id, 'field_name' => $def['field_name']],
                ['user_id' => $owner->id, 'field_type' => $def['field_type']],
            );
        }
    }

    private function seedDemoTags(Account $team, User $owner): void
    {
        $names = ['prospecto', 'cliente', 'VIP'];
        $colors = ['#3b82f6', '#10b981', '#f59e0b'];

        foreach ($names as $i => $name) {
            Tag::firstOrCreate(
                ['account_id' => $team->id, 'name' => $name],
                ['user_id' => $owner->id, 'color' => $colors[$i]],
            );
        }
    }

    /**
     * @param  Collection<int, Tag>  $tags
     * @param  Collection<int, CustomField>  $customFields
     * @return Collection<int, Contact>
     */
    private function seedDemoContacts(
        Account $team,
        User $owner,
        $tags,
        $customFields,
    ) {
        if (Contact::where('account_id', $team->id)->exists()) {
            return Contact::where('account_id', $team->id)->get();
        }

        $spanishNames = [
            'María González', 'Carlos Ramírez', 'Ana Martínez', 'Luis Rodríguez',
            'Sofía Hernández', 'Diego López', 'Valentina Díaz', 'Andrés Pérez',
            'Camila Sánchez', 'Sebastián Castro', 'Isabella Torres', 'Mateo Vargas',
            'Lucía Mendoza', 'Santiago Ruiz', 'Daniela Morales',
        ];

        $cityField = $customFields->firstWhere('field_name', 'city');
        $industryField = $customFields->firstWhere('field_name', 'industry');
        $birthdateField = $customFields->firstWhere('field_name', 'birthdate');

        $contacts = collect();

        foreach ($spanishNames as $i => $name) {
            $contact = Contact::create([
                'account_id' => $team->id,
                'user_id' => $owner->id,
                'phone' => '+57'.fake()->unique()->numerify('310#######'),
                'name' => $name,
                'email' => fake()->safeEmail(),
                'company' => fake()->randomElement([
                    null, 'TechCorp', 'Innovación S.A.', 'Soluciones Ltda.', 'Servicios Colombia',
                ]),
            ]);
            $contacts->push($contact);

            // Tags: 1-2 aleatorios + "VIP" para los últimos 3. Todo contacto
            // demo lleva al menos uno; uno sin etiquetas no muestra nada.
            $tagIds = $tags->shuffle()->take(fake()->numberBetween(1, 2))->pluck('id')->all();

            if ($i >= count($spanishNames) - 3) {
                $vipId = $tags->firstWhere('name', 'VIP')->id;
                if (! in_array($vipId, $tagIds, true)) {
                    $tagIds[] = $vipId;
                }
            }

            foreach ($tagIds as $tagId) {
                ContactTag::create(['contact_id' => $contact->id, 'tag_id' => $tagId]);
            }

            // Custom values: los 3 campos poblados.
            ContactCustomValue::create([
                'contact_id' => $contact->id,
                'custom_field_id' => $cityField->id,
                'value' => fake()->randomElement(['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena']),
            ]);
            ContactCustomValue::create([
                'contact_id' => $contact->id,
                'custom_field_id' => $industryField->id,
                'value' => fake()->randomElement(['Retail', 'Servicios', 'Tecnología', 'Salud', 'Educación']),
            ]);
            ContactCustomValue::create([
                'contact_id' => $contact->id,
                'custom_field_id' => $birthdateField->id,
                'value' => fake()->dateTimeBetween('-30 years', 'now')->format('Y-m-d'),
            ]);
        }

        return $contacts;
    }

    // ───────────────── conversations + messages ─────────────────

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    private function seedDemoConversationsAndMessages(
        Account $team,
        User $owner,
        $contacts,
    ): void {
        if (Conversation::where('account_id', $team->id)->exists()) {
            return;
        }

        $statuses = [
            ConversationStatus::Open,
            ConversationStatus::Open,
            ConversationStatus::Pending,
            ConversationStatus::Closed,
            ConversationStatus::Closed,
            ConversationStatus::Open,
        ];

        foreach ($contacts->take(6) as $i => $contact) {
            $conversation = Conversation::create([
                'account_id' => $team->id,
                'user_id' => $owner->id,
                'contact_id' => $contact->id,
                'status' => $statuses[$i],
                'last_message_at' => now()->subHours($i + 1),
                'unread_count' => $statuses[$i] === ConversationStatus::Open ? fake()->numberBetween(0, 3) : 0,
            ]);

            $this->populateMessages($conversation, $owner, $contact);
        }
    }

    private function populateMessages(Conversation $conversation, User $agent, Contact $contact): void
    {
        $messageCount = fake()->numberBetween(3, 8);

        for ($i = 0; $i < $messageCount; $i++) {
            $isInbound = $i % 2 === 0;
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $isInbound ? 'customer' : 'agent',
                'sender_id' => $isInbound ? null : $agent->id,
                'content_type' => 'text',
                'content_text' => $isInbound
                    ? fake()->randomElement([
                        'Hola, ¿tienen disponibilidad?',
                        'Gracias por la información.',
                        '¿Pueden enviarme más detalles?',
                        'Perfecto, confirmemos para mañana.',
                        '¿A qué hora pueden enviar?',
                        'No me interesa por ahora.',
                    ])
                    : fake()->randomElement([
                        '¡Hola! Sí, tenemos disponibilidad esta semana.',
                        'Con gusto, te envío el catálogo ahora.',
                        '¿Cuál es tu presupuesto aproximado?',
                        'Perfecto, te confirmo el agendamiento.',
                        '¿Te parece bien a las 3 PM?',
                        'Entendido, cualquier cosa me avisas.',
                    ]),
                'status' => $isInbound
                    ? fake()->randomElement([MessageStatus::Delivered, MessageStatus::Read])
                    : MessageStatus::Read,
                'ai_generated' => false,
                'created_at' => now()->subMinutes(($messageCount - $i) * 15),
            ]);

            // Cada 4to inbound, una respuesta del bot ai_generated.
            if ($isInbound && $i > 0 && $i % 4 === 0) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'bot',
                    'content_text' => 'Gracias por tu mensaje. Un agente te responderá pronto.',
                    'content_type' => 'text',
                    'status' => MessageStatus::Read,
                    'ai_generated' => true,
                    'created_at' => $message->created_at->copy()->addMinute(),
                ]);
            }

            // Reacción en algunos mensajes del agente.
            if (! $isInbound && $i % 3 === 0 && $i > 0) {
                MessageReaction::create([
                    'message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'actor_type' => 'customer',
                    'emoji' => '👍',
                ]);
            }
        }
    }

    // ───────────────── pipeline + deals ─────────────────

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    private function seedDemoPipelinesAndDeals(
        Account $team,
        User $owner,
        $contacts,
    ): void {
        if (Pipeline::where('account_id', $team->id)->exists()) {
            return;
        }

        $pipeline = Pipeline::create([
            'account_id' => $team->id,
            'user_id' => $owner->id,
            'name' => 'Ventas Colombia',
        ]);

        $stageDefs = [
            ['name' => 'nuevo', 'position' => 0],
            ['name' => 'calificado', 'position' => 1],
            ['name' => 'propuesta', 'position' => 2],
            ['name' => 'ganado', 'position' => 3],
        ];

        $stages = collect();
        foreach ($stageDefs as $def) {
            $stages->push(PipelineStage::create([
                'pipeline_id' => $pipeline->id,
                'name' => $def['name'],
                'position' => $def['position'],
            ]));
        }

        $dealTitles = [
            'Plan Premium - '.fake()->company(),
            'Renovación anual '.fake()->company(),
            'Migración a plan empresarial',
            'Capacitación equipo comercial',
            'Integración WhatsApp Business',
            'Onboarding '.fake()->company(),
            'Soporte prioritario Q'.fake()->numberBetween(1, 4),
            'Licencias adicionales',
        ];

        $statuses = [
            DealStatus::Open, DealStatus::Open, DealStatus::Open,
            DealStatus::Open, DealStatus::Open, DealStatus::Won,
            DealStatus::Lost, DealStatus::Open,
        ];

        foreach ($dealTitles as $i => $title) {
            $stage = $stages->get(min($i, $stages->count() - 1));
            $contact = $contacts->get($i % $contacts->count());

            Deal::create([
                'account_id' => $team->id,
                'user_id' => $owner->id,
                'assigned_to' => $owner->id,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'contact_id' => $contact->id,
                'title' => $title,
                'value' => fake()->randomFloat(2, 500, 50000),
                'currency' => 'COP',
                'status' => $statuses[$i],
            ]);
        }
    }

    // ───────────────── broadcasts ─────────────────

    private function seedDemoBroadcasts(Account $team, User $owner): void
    {
        Broadcast::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Promoción fin de mes'],
            [
                'user_id' => $owner->id,
                'template_name' => 'promo_mensual',
                'template_language' => 'es',
                'template_variables' => ['descuento' => '20%', 'valido_hasta' => '2026-08-31'],
                'status' => BroadcastStatus::Draft,
            ],
        );

        $sent = Broadcast::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Bienvenida clientes VIP'],
            [
                'user_id' => $owner->id,
                'template_name' => 'bienvenida_vip',
                'template_language' => 'es',
                'template_variables' => ['nombre' => 'cliente'],
                'status' => BroadcastStatus::Sent,
                'total_recipients' => 5,
                'sent_count' => 5,
                'delivered_count' => 4,
                'read_count' => 3,
                'replied_count' => 1,
                'failed_count' => 1,
            ],
        );

        if ($sent->wasRecentlyCreated) {
            $this->populateSentRecipients($sent, $team);
        }
    }

    private function populateSentRecipients(Broadcast $sent, Account $team): void
    {
        $contacts = Contact::where('account_id', $team->id)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $escalation = [
            BroadcastRecipientStatus::Pending,
            BroadcastRecipientStatus::Sent,
            BroadcastRecipientStatus::Delivered,
            BroadcastRecipientStatus::Read,
            BroadcastRecipientStatus::Replied,
        ];

        $rank = [
            'pending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'replied' => 4,
        ];

        foreach ($contacts as $i => $contact) {
            $status = $escalation[$i] ?? BroadcastRecipientStatus::Failed;
            $attributes = [
                'broadcast_id' => $sent->id,
                'contact_id' => $contact->id,
                'status' => $status,
            ];

            $at = $rank[$status->value] ?? 0;
            if ($at >= 1) {
                $attributes['sent_at'] = now()->subHours(2);
            }
            if ($at >= 2) {
                $attributes['delivered_at'] = now()->subHour();
            }
            if ($at >= 3) {
                $attributes['read_at'] = now()->subMinutes(30);
            }
            if ($at >= 4) {
                $attributes['replied_at'] = now()->subMinutes(10);
            }

            if ($status === BroadcastRecipientStatus::Failed) {
                $attributes['error_message'] = 'Recipient phone unreachable.';
            }
            if ($status !== BroadcastRecipientStatus::Pending) {
                $attributes['whatsapp_message_id'] = 'wamid.demo.'.fake()->uuid();
            }

            BroadcastRecipient::create($attributes);
        }
    }

    // ───────────────── automations ─────────────────

    private function seedDemoAutomations(Account $team, User $owner): void
    {
        Automation::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Encuesta post-compra'],
            [
                'user_id' => $owner->id,
                'trigger_type' => 'message_received',
                'is_active' => false,
            ],
        );

        $active = Automation::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Bienvenida primer mensaje'],
            [
                'user_id' => $owner->id,
                'trigger_type' => 'message_received',
                'is_active' => true,
            ],
        );

        if (AutomationStep::where('automation_id', $active->id)->count() === 0) {
            $steps = [
                ['step_type' => 'send_message', 'position' => 0, 'step_config' => ['text' => '¡Hola! Bienvenido a WACRM, ¿en qué te ayudamos?']],
                ['step_type' => 'collect_input', 'position' => 1, 'step_config' => ['variable' => 'motivo', 'timeout_seconds' => 300]],
                ['step_type' => 'condition', 'position' => 2, 'step_config' => ['expression' => '{{motivo}} == "compra"']],
                ['step_type' => 'set_tag', 'position' => 3, 'step_config' => ['tag' => 'lead'], 'branch' => 'yes'],
                ['step_type' => 'handoff', 'position' => 4, 'step_config' => ['queue' => 'sales'], 'branch' => 'no'],
            ];

            foreach ($steps as $step) {
                AutomationStep::create([
                    'automation_id' => $active->id,
                    'parent_step_id' => null,
                    'branch' => $step['branch'] ?? null,
                    'step_type' => $step['step_type'],
                    'step_config' => $step['step_config'],
                    'position' => $step['position'],
                ]);
            }
        }
    }

    // ───────────────── flows ─────────────────

    private function seedDemoFlows(Account $team, User $owner): void
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

        if (FlowNode::where('flow_id', $active->id)->count() === 0) {
            $nodesData = [
                ['node_key' => 'start', 'node_type' => FlowNodeType::Start, 'config' => [], 'position_x' => 100, 'position_y' => 100],
                ['node_key' => 'msg_greet', 'node_type' => FlowNodeType::SendMessage, 'config' => ['text' => '¡Hola! Vamos a confirmar tu cita.'], 'position_x' => 300, 'position_y' => 100],
                ['node_key' => 'collect_date', 'node_type' => FlowNodeType::CollectInput, 'config' => ['variable' => 'fecha'], 'position_x' => 500, 'position_y' => 100],
                ['node_key' => 'check_date', 'node_type' => FlowNodeType::Condition, 'config' => ['expression' => '{{fecha}} is valid date'], 'position_x' => 700, 'position_y' => 100],
                ['node_key' => 'handoff_human', 'node_type' => FlowNodeType::Handoff, 'config' => ['queue' => 'scheduling'], 'position_x' => 900, 'position_y' => 50],
                ['node_key' => 'msg_confirm', 'node_type' => FlowNodeType::SendMessage, 'config' => ['text' => 'Listo, agendado para {{fecha}}.'], 'position_x' => 900, 'position_y' => 200],
                ['node_key' => 'end', 'node_type' => FlowNodeType::End, 'config' => [], 'position_x' => 1100, 'position_y' => 200],
            ];

            foreach ($nodesData as $n) {
                FlowNode::create([
                    'flow_id' => $active->id,
                    ...$n,
                ]);
            }

            $active->update(['entry_node_id' => 'start']);

            $contact = Contact::where('account_id', $team->id)->inRandomOrder()->first();

            $activeRun = FlowRun::create([
                'account_id' => $team->id,
                'flow_id' => $active->id,
                'user_id' => $owner->id,
                'contact_id' => $contact->id,
                'status' => FlowRunStatus::Active,
                'current_node_key' => 'collect_date',
                'vars' => ['fecha' => null],
                'started_at' => now()->subMinutes(10),
                'last_advanced_at' => now()->subMinutes(2),
            ]);

            $completedRun = FlowRun::create([
                'account_id' => $team->id,
                'flow_id' => $active->id,
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
                'flow_run_id' => $activeRun->id,
                'event_type' => FlowRunEventType::Started,
                'node_key' => 'start',
                'payload' => [],
            ]);
            FlowRunEvent::create([
                'flow_run_id' => $completedRun->id,
                'event_type' => FlowRunEventType::Started,
                'node_key' => 'start',
                'payload' => [],
            ]);
            FlowRunEvent::create([
                'flow_run_id' => $completedRun->id,
                'event_type' => FlowRunEventType::Completed,
                'node_key' => 'end',
                'payload' => ['fecha' => '2026-08-15'],
            ]);
        }
    }

    // ───────────────── credentials log ─────────────────

    private function printDemoCredentials(): void
    {
        $command = $this->command;

        // Cuando el seeder corre vía artisan sin un Command explícito
        // (p.ej. desde tests o desde la consola en runtime), $command es
        // null y simplemente no se imprime nada.
        if ($command === null) {
            return;
        }

        $command->info('');
        $command->info('Demo logins (contraseña: '.self::SYNTHETIC_PASSWORD.'):');
        $command->info('  '.self::TEST_USER_EMAIL);
        foreach (self::DEMO_MEMBERS as $email => $role) {
            $command->info("  {$email}  (rol: {$role->value})");
        }
    }
}
