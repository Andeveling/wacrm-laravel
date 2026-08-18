<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('accounts.{accountId}', function (User $user, string $accountId): bool {
    return $user->accounts()->whereKey($accountId)->exists();
});
