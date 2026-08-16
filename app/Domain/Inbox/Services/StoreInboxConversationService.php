<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Services;

use App\Domain\Meta\Services\ActiveWhatsappConnectionResolver;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\ConversationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StoreInboxConversationService
{
    public function __construct(private ActiveWhatsappConnectionResolver $connections) {}

    /**
     * @param  array{contact_id: string, connection_id?: string|null}  $data
     */
    public function store(array $data, int $userId): Conversation
    {
        $conversation = DB::transaction(function () use ($data, $userId): Conversation {
            $contact = Contact::query()->whereKey($data['contact_id'])->first();

            if (! $contact instanceof Contact) {
                throw ValidationException::withMessages([
                    'contact_id' => 'El contacto seleccionado no está disponible.',
                ]);
            }

            $connection = $this->connections->find($data['connection_id'] ?? null);

            if ($connection === null) {
                throw ValidationException::withMessages([
                    'connection_id' => 'Selecciona una conexión WhatsApp activa.',
                ]);
            }

            return Conversation::query()->firstOrCreate(
                ['contact_id' => $contact->id, 'connection_id' => $connection->id],
                ['user_id' => $userId, 'status' => ConversationStatus::Open],
            );
        });

        return $conversation;
    }
}
