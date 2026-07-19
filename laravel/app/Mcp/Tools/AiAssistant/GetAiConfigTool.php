<?php

declare(strict_types=1);

namespace App\Mcp\Tools\AiAssistant;

use App\Models\AiConfig;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Obtiene la configuración del AI Assistant del account (provider, modelo, auto-reply, handoff). No expone API keys.')]
class GetAiConfigTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $config = AiConfig::query()->first();

        if ($config === null) {
            return Response::error('AI Assistant no configurado para este account.');
        }

        return Response::structured([
            'id' => $config->id,
            'provider' => $config->provider->value,
            'model' => $config->model,
            'is_active' => $config->is_active,
            'auto_reply_enabled' => $config->auto_reply_enabled,
            'auto_reply_max_per_conversation' => $config->auto_reply_max_per_conversation,
            'handoff_agent_id' => $config->handoff_agent_id,
            'has_embeddings_key' => $config->embeddings_api_key !== null,
            'created_at' => $config->created_at?->toIso8601String(),
        ]);
    }
}
