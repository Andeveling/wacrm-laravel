<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Services\ConnectWhatsappNumberService;
use App\Domain\Meta\Support\WhatsappConnectionAttempt;
use App\Http\Requests\Meta\ConnectWhatsappNumberRequest;
use App\Models\WhatsappIntegration;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class ConnectWhatsappNumber
{
    public function __invoke(
        ConnectWhatsappNumberRequest $request,
        CurrentAccount $account,
        ConnectWhatsappNumberService $service,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        $data = $request->validated();
        $integration = WhatsappIntegration::query()->first();
        $token = $data['access_token'] ?: $integration?->access_token;

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'access_token' => 'Ingresa un token de Meta para iniciar la conexión.',
            ]);
        }

        $attempt = new WhatsappConnectionAttempt(
            $account,
            $data['phone_number_id'],
            $data['waba_id'],
            $token,
        );

        $result = $service->connect(
            $attempt,
            $integration,
            $request->user()?->id,
            is_string($data['pin'] ?? null) ? $data['pin'] : null,
            (bool) ($data['confirm_default'] ?? false),
        );

        return $responder->respond($result);
    }
}
