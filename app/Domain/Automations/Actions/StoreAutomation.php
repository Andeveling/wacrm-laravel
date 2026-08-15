<?php

declare(strict_types=1);

namespace App\Domain\Automations\Actions;

use App\Domain\Automations\Services\StoreAutomationService;
use App\Http\Requests\Automations\StoreAutomationRequest;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class StoreAutomation
{
    public function __invoke(StoreAutomationRequest $request, CurrentAccount $account, StoreAutomationService $service): RedirectResponse
    {
        abort_unless($account->isMember(), 403);

        /** @var array{name: string, trigger_type: string, connection_mode: string, connection_id?: string|null, is_active?: bool} $data */
        $data = $request->validated();

        $automation = $service->store($data, $request->user()->id);

        return to_route('automations.edit', $automation);
    }
}
