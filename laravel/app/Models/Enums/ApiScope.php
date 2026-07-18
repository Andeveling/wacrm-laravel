<?php

namespace App\Models\Enums;

/**
 * The vocabulary of scopes an API key can carry.
 *
 * Mirrors `docs/public-api.md` exactly — adding a scope here is a breaking
 * change for clients. The DB stores `scopes` as `json` so this is the
 * single source of truth (no Postgres enum to migrate).
 */
enum ApiScope: string
{
    case MessagesSend = 'messages:send';
    case MessagesRead = 'messages:read';
    case ContactsRead = 'contacts:read';
    case ContactsWrite = 'contacts:write';
    case ConversationsRead = 'conversations:read';
    case BroadcastsSend = 'broadcasts:send';
    case WebhooksManage = 'webhooks:manage';
}
