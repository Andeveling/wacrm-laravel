<?php

declare(strict_types=1);

namespace App\Domain\Pipelines\Actions;

use App\Domain\Pipelines\Responders\PipelineRedirectResponder;
use App\Domain\Pipelines\Services\DealWriter;
use App\Http\Requests\Pipelines\StoreDealRequest;
use App\Models\Pipeline;
use Illuminate\Http\RedirectResponse;

final readonly class StoreDeal
{
    public function __construct(
        private DealWriter $deals,
        private PipelineRedirectResponder $responder,
    ) {}

    public function __invoke(StoreDealRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->deals->store($pipeline, $request->validated(), $request->user()->id);

        return $this->responder->success();
    }
}
