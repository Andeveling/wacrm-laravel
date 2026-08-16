<?php

declare(strict_types=1);

namespace App\Domain\Meta\Responders;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use Illuminate\Http\RedirectResponse;

final class WhatsappConnectionResponder
{
    public function respond(WhatsappConnectionResult $result): RedirectResponse
    {
        return to_route('settings.whatsapp')->with(
            $result->succeeded() ? 'whatsapp_status' : 'whatsapp_error',
            $result->message,
        );
    }
}
