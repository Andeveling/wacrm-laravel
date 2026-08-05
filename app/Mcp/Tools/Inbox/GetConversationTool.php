<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Inbox;

use App\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Obtiene el detalle de una conversación con sus últimos mensajes.')]
class GetConversationTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'conversationId' => $schema->string()
                ->description('UUID de la conversación.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Cantidad de mensajes a incluir (max 100).')
                ->default(20),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $conversationId = $request->string('conversationId')->value();
        $limit = min($request->integer('limit', 20), 100);

        $conversation = Conversation::query()
            ->with(['contact', 'assignedAgent:id,name', 'messages' => function ($q) use ($limit) {
                $q->latest()->limit($limit);
            }])
            ->find($conversationId);

        if ($conversation === null) {
            return Response::error('Conversación no encontrada.');
        }

        return Response::structured([
            'id' => $conversation->id,
            'status' => $conversation->status->value,
            'last_message_text' => $conversation->last_message_text,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $conversation->unread_count,
            'ai_autoreply_disabled' => $conversation->ai_autoreply_disabled,
            'contact' => $conversation->contact ? [
                'id' => $conversation->contact->id,
                'name' => $conversation->contact->name,
                'phone' => $conversation->contact->phone,
                'email' => $conversation->contact->email,
            ] : null,
            'assigned_agent' => $conversation->assignedAgent?->name,
            'messages' => $conversation->messages->reverse()->map(fn ($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'content_text' => $m->content_text,
                'status' => $m->status->value,
                'ai_generated' => $m->ai_generated,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
