<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowSwitcher
{
    /**
     * Show the account switcher, listing only the accounts the user belongs to.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('accounts/switch', [
            'accounts' => $request->user()->accounts()->get(),
        ]);
    }
}
