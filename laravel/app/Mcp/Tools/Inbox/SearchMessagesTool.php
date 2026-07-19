<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inbox;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Busca mensajes por texto en todas las conversaciones del account.')]
class SearchMessagesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Texto a buscar en el contenido de los mensajes.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Máximo de resultados (max 50).')
                ->default(20),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $query = $request->string('query')->value();
        $limit = min($request->integer('limit', 20), 50);

        $conversationIds = Conversation::query()->pluck('id');

        $messages = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('content_text', 'ilike', "%{$query}%")
            ->with('conversation:id,contact_id')
            ->latest()
            ->limit($limit)
            ->get();

        $data = $messages->map(fn (Message $m) => [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'sender_type' => $m->sender_type,
            'content_text' => $m->content_text,
            'status' => $m->status->value,
            'created_at' => $m->created_at?->toIso8601String(),
        ]);

        return Response::structured([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }
}
