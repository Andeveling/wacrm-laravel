<?php

declare(strict_types=1);

namespace App\Domain\Pipelines\Actions;

use App\Domain\Pipelines\Responders\PipelineRedirectResponder;
use App\Domain\Pipelines\Services\DealWriter;
use App\Http\Requests\Pipelines\UpdateDealRequest;
use App\Models\Deal;
use Illuminate\Http\RedirectResponse;

final readonly class UpdateDeal
{
    public function __construct(
        private DealWriter $deals,
        private PipelineRedirectResponder $responder,
    ) {}

    public function __invoke(UpdateDealRequest $request, Deal $deal): RedirectResponse
    {
        $this->deals->update($deal, $request->validated());

        return $this->responder->success();
    }
}
