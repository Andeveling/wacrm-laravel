<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Http\Requests\Inbox\StoreInboxConversationRequest;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\ConversationStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StoreInboxConversation
{
    public function __invoke(
        StoreInboxConversationRequest $request,
        CurrentAccount $account,
    ): RedirectResponse {
        abort_unless($account->isMember(), 403);

        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            $contact = Contact::query()->whereKey($data['contact_id'])->first();

            if (! $contact instanceof Contact) {
                throw ValidationException::withMessages([
                    'contact_id' => 'El contacto seleccionado no está disponible.',
                ]);
            }

            $connection = $this->resolveConnection($data['connection_id'] ?? null);

            Conversation::query()->firstOrCreate(
                [
                    'contact_id' => $contact->id,
                    'connection_id' => $connection->id,
                ],
                [
                    'user_id' => $request->user()->id,
                    'status' => ConversationStatus::Open,
                ],
            );
        });

        return to_route('inbox');
    }

    private function resolveConnection(?string $connectionId): WhatsappPhoneNumberConnection
    {
        $query = WhatsappPhoneNumberConnection::query()
            ->where('readiness', WhatsappConnectionReadiness::Active);

        $connection = is_string($connectionId)
            ? $query->whereKey($connectionId)->first()
            : $query->where('is_default', true)->first();

        if (! $connection instanceof WhatsappPhoneNumberConnection) {
            throw ValidationException::withMessages([
                'connection_id' => 'Selecciona una conexión WhatsApp activa.',
            ]);
        }

        return $connection;
    }
}
