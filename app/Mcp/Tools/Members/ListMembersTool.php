<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Members;

use App\Models\AccountUser;
use App\Models\Scopes\AccountScope;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista los miembros del account con sus roles.')]
class ListMembersTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $accountId = app(AccountScope::CONTAINER_KEY);

        $memberships = AccountUser::query()
            ->where('account_id', $accountId)
            ->get();

        $memberIds = $memberships->pluck('user_id');
        $users = User::query()->whereIn('id', $memberIds)->get()->keyBy('id');

        $data = $memberships->map(function (AccountUser $m) use ($users) {
            $user = $users->get($m->user_id);

            return [
                'user_id' => $m->user_id,
                'name' => $user?->name,
                'email' => $user?->email,
                'role' => $m->role?->value,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ];
        });

        return Response::structured([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }
}
