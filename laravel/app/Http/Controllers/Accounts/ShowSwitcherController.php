<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowSwitcherController extends Controller
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
