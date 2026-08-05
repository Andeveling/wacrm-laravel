<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Settings;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Scopes\AccountScope;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Obtiene la configuración del account (nombre, tipo, API keys activas).')]
class GetAccountSettingsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $accountId = app(AccountScope::CONTAINER_KEY);

        $account = Account::query()
            ->withoutGlobalScope(new AccountScope)
            ->where('id', $accountId)
            ->first();

        if ($account === null) {
            return Response::error('Account no encontrado.');
        }

        $apiKeys = ApiKey::withoutGlobalScope(AccountScope::class)
            ->where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->get()
            ->map(fn ($k) => [
                'id' => $k->id,
                'name' => $k->name,
                'key_prefix' => $k->key_prefix,
                'scopes' => $k->scopes,
                'expires_at' => $k->expires_at?->toIso8601String(),
                'last_used_at' => $k->last_used_at?->toIso8601String(),
            ]);

        $membersCount = AccountUser::query()
            ->where('account_id', $accountId)
            ->count();

        $contactsCount = Contact::query()
            ->where('account_id', $accountId)
            ->count();

        $conversationsOpen = Conversation::query()
            ->where('account_id', $accountId)
            ->where('status', 'open')
            ->count();

        return Response::structured([
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type->value,
            'members_count' => $membersCount,
            'contacts_count' => $contactsCount,
            'conversations_open' => $conversationsOpen,
            'api_keys' => $apiKeys,
            'created_at' => $account->created_at?->toIso8601String(),
        ]);
    }
}
