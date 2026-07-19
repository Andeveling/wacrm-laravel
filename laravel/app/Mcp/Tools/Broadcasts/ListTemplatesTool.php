<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Broadcasts;

use App\Models\MessageTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista las plantillas de mensaje WhatsApp sincronizadas con Meta.')]
class ListTemplatesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED'])
                ->description('Filtrar por estado de la plantilla.'),
            'limit' => $schema->integer()
                ->description('Máximo de resultados (max 50).')
                ->default(25),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $limit = min($request->integer('limit', 25), 50);

        $query = MessageTemplate::query()->latest();

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        $templates = $query->limit($limit)->get();

        $data = $templates->map(fn (MessageTemplate $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'category' => $t->category,
            'language' => $t->language,
            'status' => $t->status?->value,
            'header_type' => $t->header_type,
            'body_text' => $t->body_text,
            'meta_template_id' => $t->meta_template_id,
            'quality_score' => $t->quality_score,
        ]);

        return Response::structured([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }
}
