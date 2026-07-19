<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Broadcasts;

use App\Mcp\Support\ListTool;
use App\Models\Broadcast;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Lista los broadcasts del account.')]
class ListBroadcastsTool extends ListTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['draft', 'scheduled', 'sending', 'sent', 'failed'])
                ->description('Filtrar por estado del broadcast.'),
            'limit' => $schema->integer()
                ->description('Resultados por página (max 50).')
                ->default(25),
        ];
    }

    protected function query(Request $request): Builder
    {
        $query = Broadcast::query()->latest();

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        return $query;
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
            'status' => $b->status->value,
            'scheduled_at' => $b->scheduled_at?->toIso8601String(),
            'total_recipients' => $b->total_recipients,
            'sent_count' => $b->sent_count,
            'delivered_count' => $b->delivered_count,
            'read_count' => $b->read_count,
            'replied_count' => $b->replied_count,
            'failed_count' => $b->failed_count,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
