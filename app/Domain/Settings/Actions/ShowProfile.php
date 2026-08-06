<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Show the user's profile settings page. Pure render — ADR 0001 rule 4.
 */
final class ShowProfile
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }
}
