<?php

declare(strict_types=1);

namespace App\Domain\Pipelines\Responders;

use Illuminate\Http\RedirectResponse;

final readonly class PipelineRedirectResponder
{
    public function success(): RedirectResponse
    {
        return to_route('pipelines');
    }
}
