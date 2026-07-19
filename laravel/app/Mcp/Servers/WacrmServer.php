<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AiAssistant\GetAiConfigTool;
use App\Mcp\Tools\AiAssistant\GetAiUsageTool;
use App\Mcp\Tools\Automations\ListAutomationsTool;
use App\Mcp\Tools\Broadcasts\GetBroadcastTool;
use App\Mcp\Tools\Broadcasts\ListBroadcastsTool;
use App\Mcp\Tools\Broadcasts\ListTemplatesTool;
use App\Mcp\Tools\Contacts\GetContactTool;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Mcp\Tools\Contacts\SearchContactsTool;
use App\Mcp\Tools\Flows\ListFlowsTool;
use App\Mcp\Tools\Inbox\GetConversationTool;
use App\Mcp\Tools\Inbox\ListConversationsTool;
use App\Mcp\Tools\Inbox\SearchMessagesTool;
use App\Mcp\Tools\Members\ListMembersTool;
use App\Mcp\Tools\Pipelines\GetDealTool;
use App\Mcp\Tools\Pipelines\ListDealsTool;
use App\Mcp\Tools\Pipelines\ListPipelinesTool;
use App\Mcp\Tools\Settings\GetAccountSettingsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('wacrm')]
#[Version('1.0.0')]
#[Description('WaCRM API — WhatsApp CRM platform. Gestiona inbox, contactos, pipelines, broadcasts, automatizaciones, flows, AI assistant, settings y miembros.')]
class WacrmServer extends Server
{
    /**
     * The tools registered with this MCP server.
     */
    protected array $tools = [
        // Inbox
        ListConversationsTool::class,
        GetConversationTool::class,
        SearchMessagesTool::class,

        // Contacts
        ListContactsTool::class,
        GetContactTool::class,
        SearchContactsTool::class,

        // Pipelines
        ListPipelinesTool::class,
        ListDealsTool::class,
        GetDealTool::class,

        // Broadcasts
        ListBroadcastsTool::class,
        GetBroadcastTool::class,
        ListTemplatesTool::class,

        // Automations
        ListAutomationsTool::class,

        // Flows
        ListFlowsTool::class,

        // AI Assistant
        GetAiConfigTool::class,
        GetAiUsageTool::class,

        // Members
        ListMembersTool::class,

        // Settings
        GetAccountSettingsTool::class,
    ];
}
