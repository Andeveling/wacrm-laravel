<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Actions;

use App\Domain\Broadcasts\Support\BroadcastAudience;
use App\Http\Requests\Broadcasts\StoreBroadcastRequest;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\MessageTemplateStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\MessageTemplate;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class StoreBroadcast
{
    public function __construct(private BroadcastAudience $audience) {}

    public function __invoke(StoreBroadcastRequest $request): RedirectResponse
    {
        /** @var array{name: string, template_id: string, connection_id: string, audience_type: 'all'|'tags', tag_ids?: list<string>, template_variables: array<string, string>, scheduled_at?: string|null} $data */
        $data = $request->validated();
        $tagIds = $data['audience_type'] === 'all'
            ? []
            : array_values($data['tag_ids'] ?? []);

        $broadcast = DB::transaction(function () use ($data, $request, $tagIds): Broadcast {
            if (! $this->audience->tagsBelongToCurrentAccount($tagIds)) {
                throw ValidationException::withMessages(['tag_ids' => 'Las etiquetas seleccionadas no están disponibles.']);
            }

            $template = MessageTemplate::query()
                ->whereKey($data['template_id'])
                ->where('status', MessageTemplateStatus::Approved)
                ->first();

            if ($template === null) {
                throw ValidationException::withMessages(['template_id' => 'La plantilla seleccionada no está aprobada o no está disponible.']);
            }

            $connection = WhatsappPhoneNumberConnection::query()
                ->whereKey($data['connection_id'])
                ->where('readiness', WhatsappConnectionReadiness::Active)
                ->first();

            if ($connection === null) {
                throw ValidationException::withMessages(['connection_id' => 'Selecciona una conexión WhatsApp activa.']);
            }

            $contactIds = $this->audience->contacts($tagIds)
                ->lockForUpdate()
                ->pluck('id');

            if ($contactIds->isEmpty()) {
                throw ValidationException::withMessages(['audience' => 'La audiencia seleccionada no tiene contactos.']);
            }

            $broadcast = Broadcast::create([
                'user_id' => $request->user()->id,
                'connection_id' => $connection->id,
                'name' => $data['name'],
                'template_name' => $template->name,
                'template_language' => $template->language ?? 'en_US',
                'template_variables' => $data['template_variables'],
                'audience_filter' => ['type' => $data['audience_type'], 'tag_ids' => $tagIds],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status' => ($data['scheduled_at'] ?? null) === null
                    ? BroadcastStatus::Draft
                    : BroadcastStatus::Scheduled,
                'total_recipients' => $contactIds->count(),
            ]);

            $contactIds->chunk(200)->each(function (Collection $ids) use ($broadcast): void {
                BroadcastRecipient::query()->insert($ids->map(fn (string $contactId): array => [
                    'id' => (string) Str::uuid(),
                    'broadcast_id' => $broadcast->id,
                    'contact_id' => $contactId,
                    'status' => 'pending',
                    'created_at' => now(),
                ])->all());
            });

            return $broadcast;
        });

        return to_route('broadcasts.show', $broadcast);
    }
}
