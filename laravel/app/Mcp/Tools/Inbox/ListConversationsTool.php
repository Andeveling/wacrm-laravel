<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inbox;

use App\Mcp\Support\ListTool;
use App\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Lista las conversaciones del inbox. Filtrable por status y permite paginación.')]
class ListConversationsTool extends ListTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['open', 'closed', 'archived'])
                ->description('Filtrar por estado de la conversación.')
                ->default('open'),
            'limit' => $schema->integer()
                ->description('Resultados por página (max 50).')
                ->default(25),
        ];
    }

    protected function query(Request $request): Builder
    {
        $status = $request->string('status', 'open')->value();

        return Conversation::query()
            ->where('status', $status)
            ->with(['contact:id,name,phone', 'assignedAgent:id,name'])
            ->latest('last_message_at');
    }

    protected function mapItem(Model $item): array
    {
        /** @var Conversation $c */
        $c = $item;

        return [
            'id' => $c->id,
            'status' => $c->status->value,
            'last_message_text' => $c->last_message_text,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => $c->unread_count,
            'contact' => $c->contact ? [
                'id' => $c->contact->id,
                'name' => $c->contact->name,
                'phone' => $c->contact->phone,
            ] : null,
            'assigned_agent' => $c->assignedAgent?->name,
        ];
    }
}
