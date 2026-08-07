<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Actions;

use App\Domain\Dashboard\Services\DashboardDataService;
use App\Support\CurrentAccount;
use Inertia\Inertia;
use Inertia\Response;

final class ShowDashboard
{
    public function __invoke(CurrentAccount $account, DashboardDataService $data): Response
    {
        return Inertia::render('dashboard', [
            'metrics' => $data->metrics(),
            'conversationsSeries' => [
                '7' => $data->conversationsSeries(7),
                '30' => $data->conversationsSeries(30),
                '90' => $data->conversationsSeries(90),
            ],
            'pipeline' => $data->pipeline(),
            'responseTime' => $data->responseTime(),
            'activity' => $data->activity(),
        ]);
    }
}
