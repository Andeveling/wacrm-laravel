<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Contacts;

use App\Domain\Contacts\Support\ContactProjection;
use App\Mcp\Support\GetTool;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Obtiene el detalle completo de un contacto incluyendo tags, notas y conversaciones.')]
class GetContactTool extends GetTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'contactId' => $schema->string()
                ->description('UUID del contacto.')
                ->required(),
        ];
    }

    protected function idParam(): string
    {
        return 'contactId';
    }

    protected function findQuery(string $id): Builder
    {
        return Contact::query()->with([
            ...ContactProjection::RELATIONS,
            'notes',
            'conversations:id,contact_id,status,last_message_text,last_message_at,unread_count',
        ]);
    }

    /**
     * The public shape plus what only the detail view needs. Spreading
     * the projection keeps this tool from re-deriving the base fields.
     */
    protected function mapItem(Model $item): array
    {
        /** @var Contact $c */
        $c = $item;

        return [
            ...ContactProjection::from($c),
            'notes' => $c->notes->map(fn ($n) => [
                'id' => $n->id,
                'content' => $n->note_text,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'conversations' => $c->conversations->map(fn ($conv) => [
                'id' => $conv->id,
                'status' => $conv->status->value,
                'last_message_text' => $conv->last_message_text,
                'last_message_at' => $conv->last_message_at?->toIso8601String(),
                'unread_count' => $conv->unread_count,
            ]),
        ];
    }

    protected function notFoundMessage(): string
    {
        return 'Contacto no encontrado.';
    }
}
