<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Contacts;

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
        return Contact::query()
            ->with(['tags:id,name', 'notes', 'conversations:id,contact_id,status,last_message_text,last_message_at,unread_count']);
    }

    protected function mapItem(Model $item): array
    {
        /** @var Contact $c */
        $c = $item;

        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
            'company' => $c->company,
            'avatar_url' => $c->avatar_url,
            'tags' => $c->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
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
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    protected function notFoundMessage(): string
    {
        return 'Contacto no encontrado.';
    }
}
