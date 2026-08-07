<?php

declare(strict_types=1);

namespace App\Domain\Pipelines\Actions;

use App\Domain\Pipelines\Responders\PipelineRedirectResponder;
use App\Models\Deal;
use Illuminate\Http\RedirectResponse;

final readonly class DestroyDeal
{
    public function __construct(private PipelineRedirectResponder $responder) {}

    public function __invoke(Deal $deal): RedirectResponse
    {
        $deal->delete();

        return $this->responder->success();
    }
}
