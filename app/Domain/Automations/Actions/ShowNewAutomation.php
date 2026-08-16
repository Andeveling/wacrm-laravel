<?php

declare(strict_types=1);

namespace App\Domain\Automations\Actions;

use App\Domain\Meta\Services\ActiveWhatsappConnectionResolver;
use Inertia\Inertia;
use Inertia\Response;

final class ShowNewAutomation
{
    public function __construct(private ActiveWhatsappConnectionResolver $connections) {}

    public function __invoke(): Response
    {
        return Inertia::render('automations/new', [
            'connections' => $this->connections->list()->all(),
        ]);
    }
}
