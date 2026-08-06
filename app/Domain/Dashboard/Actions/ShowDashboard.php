<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Actions;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the dashboard shell. Pure page render with no domain rules,
 * so ADR 0001 rule 4 applies: no Result object, no Responder.
 */
final class ShowDashboard
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard');
    }
}
