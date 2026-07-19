<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Pipelines;

use App\Mcp\Support\ListTool;
use App\Models\Deal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Lista los deals/oportunidades del account. Filtrable por pipeline y status.')]
class ListDealsTool extends ListTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'pipelineId' => $schema->string()
                ->description('UUID del pipeline (opcional).'),
            'status' => $schema->string()
                ->enum(['open', 'won', 'lost'])
                ->description('Filtrar por estado del deal.')
                ->default('open'),
            'limit' => $schema->integer()
                ->description('Resultados por página (max 50).')
                ->default(25),
        ];
    }

    protected function query(Request $request): Builder
    {
        $status = $request->string('status', 'open')->value();

        $query = Deal::query()
            ->with(['pipeline:id,name', 'stage:id,name', 'contact:id,name,phone', 'assignee:id,name'])
            ->where('status', $status)
            ->latest();

        if ($pipelineId = $request->string('pipelineId')->value()) {
            $query->where('pipeline_id', $pipelineId);
        }

        return $query;
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
            'pipeline' => $d->pipeline?->name,
            'stage' => $d->stage?->name,
            'contact' => $d->contact ? [
                'id' => $d->contact->id,
                'name' => $d->contact->name,
                'phone' => $d->contact->phone,
            ] : null,
            'assignee' => $d->assignee?->name,
            'expected_close_date' => $d->expected_close_date?->toDateString(),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
