<?php

declare(strict_types=1);

namespace App\Domain\Meta\Responders;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WhatsappConnectionResponder
{
    public function respond(WhatsappConnectionResult $result, Request $request): RedirectResponse
    {
        $redirect = to_route('settings.whatsapp')->with(
            $result->flashKey(),
            $result->message,
        );

        if (! $result->keepsDraft()) {
            return $redirect;
        }

        return $redirect->withInput($request->except('access_token'));
    }
}
