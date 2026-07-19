<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Broadcasts;

use App\Mcp\Support\GetTool;
use App\Models\Broadcast;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Obtiene el detalle de un broadcast con sus métricas.')]
class GetBroadcastTool extends GetTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'broadcastId' => $schema->string()
                ->description('UUID del broadcast.')
                ->required(),
        ];
    }

    protected function idParam(): string
    {
        return 'broadcastId';
    }

    protected function findQuery(string $id): Builder
    {
        return Broadcast::query();
    }

    protected function mapItem(Model $item): array
    {
        /** @var Broadcast $b */
        $b = $item;

        return [
            'id' => $b->id,
            'name' => $b->name,
            'template_name' => $b->template_name,
            'template_language' => $b->template_language,
            'template_variables' => $b->template_variables,
            'audience_filter' => $b->audience_filter,
            'status' => $b->status->value,
            'scheduled_at' => $b->scheduled_at?->toIso8601String(),
            'metrics' => [
                'total_recipients' => $b->total_recipients,
                'sent_count' => $b->sent_count,
                'delivered_count' => $b->delivered_count,
                'read_count' => $b->read_count,
                'replied_count' => $b->replied_count,
                'failed_count' => $b->failed_count,
            ],
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    protected function notFoundMessage(): string
    {
        return 'Broadcast no encontrado.';
    }
}
