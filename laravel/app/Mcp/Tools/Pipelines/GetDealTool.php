<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Pipelines;

use App\Mcp\Support\GetTool;
use App\Models\Deal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Obtiene el detalle de un deal incluyendo pipeline, stage, contacto y conversación asociada.')]
class GetDealTool extends GetTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'dealId' => $schema->string()
                ->description('UUID del deal.')
                ->required(),
        ];
    }

    protected function idParam(): string
    {
        return 'dealId';
    }

    protected function findQuery(string $id): Builder
    {
        return Deal::query()
            ->with([
                'pipeline:id,name', 'stage:id,name',
                'contact:id,name,phone,email,company',
                'assignee:id,name',
                'conversation:id,contact_id,status,last_message_text,last_message_at',
            ]);
    }

    protected function mapItem(Model $item): array
    {
        /** @var Deal $d */
        $d = $item;

        return [
            'id' => $d->id,
            'title' => $d->title,
            'value' => (float) $d->value,
            'currency' => $d->currency,
            'status' => $d->status->value,
            'notes' => $d->notes,
            'pipeline' => $d->pipeline?->only('id', 'name'),
            'stage' => $d->stage?->only('id', 'name'),
            'contact' => $d->contact?->only('id', 'name', 'phone', 'email', 'company'),
            'assignee' => $d->assignee?->only('id', 'name'),
            'conversation' => $d->conversation ? [
                'id' => $d->conversation->id,
                'status' => $d->conversation->status->value,
                'last_message_text' => $d->conversation->last_message_text,
                'last_message_at' => $d->conversation->last_message_at?->toIso8601String(),
            ] : null,
            'expected_close_date' => $d->expected_close_date?->toDateString(),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }

    protected function notFoundMessage(): string
    {
        return 'Deal no encontrado.';
    }
}
