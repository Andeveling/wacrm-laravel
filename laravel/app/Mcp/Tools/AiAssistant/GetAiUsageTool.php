<?php

declare(strict_types=1);

namespace App\Mcp\Tools\AiAssistant;

use App\Models\AiUsageLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Obtiene el consumo de tokens y costo del AI Assistant en un rango de fechas.')]
class GetAiUsageTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->description('Días hacia atrás para consultar (max 90).')
                ->default(30),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $days = min($request->integer('days', 30), 90);

        $logs = AiUsageLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $totals = [
            'prompt_tokens' => $logs->sum('prompt_tokens'),
            'completion_tokens' => $logs->sum('completion_tokens'),
            'total_tokens' => $logs->sum('total_tokens'),
        ];

        $byProvider = $logs->groupBy(function (AiUsageLog $log) {
            return $log->provider->value;
        })->map(function ($group) {
            return [
                'prompt_tokens' => $group->sum('prompt_tokens'),
                'completion_tokens' => $group->sum('completion_tokens'),
                'total_tokens' => $group->sum('total_tokens'),
                'requests' => $group->count(),
            ];
        });

        $byModel = $logs->groupBy('model')->map(function ($group) {
            return [
                'prompt_tokens' => $group->sum('prompt_tokens'),
                'completion_tokens' => $group->sum('completion_tokens'),
                'total_tokens' => $group->sum('total_tokens'),
                'requests' => $group->count(),
            ];
        });

        return Response::structured([
            'period_days' => $days,
            'totals' => $totals,
            'by_provider' => $byProvider,
            'by_model' => $byModel,
        ]);
    }
}
