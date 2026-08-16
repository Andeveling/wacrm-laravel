<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Domain\Meta\Services\MetaGraphClientContract;
use App\Domain\Meta\Support\MetaGraphException;
use App\Domain\Meta\Support\WhatsappConnectionAttempt;
use App\Http\Requests\Meta\ConnectWhatsappNumberRequest;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ConnectWhatsappNumber
{
    public function __invoke(
        ConnectWhatsappNumberRequest $request,
        CurrentAccount $account,
        MetaGraphClientContract $meta,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        $data = $request->validated();
        $integration = WhatsappIntegration::query()->first();
        $attempt = new WhatsappConnectionAttempt(
            $account,
            $data['phone_number_id'],
            $data['waba_id'],
            $this->requireToken($data['access_token'] ?: $integration?->access_token),
        );

        $this->guardAgainstForeignClaims($attempt);

        try {
            $meta->verifyPhoneAndWaba($attempt->phoneNumberId, $attempt->wabaId, $attempt->token);
        } catch (MetaGraphException $exception) {
            return $responder->respond($this->failedResult($exception, $attempt));
        }

        [$waba, $connection] = $this->persistVerifiedCredentials($request, $attempt, $integration);

        $result = $this->subscribeWabaIfNeeded($meta, $attempt, $waba, $connection)
            ?? $this->registerPhoneIfNeeded($meta, $attempt, $connection, $data['pin'] ?? null);

        if ($result === null) {
            $this->markWaitingForWebhook($connection);
            $result = WhatsappConnectionResult::success($connection->registered_at === null
                ? 'Credenciales verificadas y WABA suscrito. Ingresa el PIN para registrar el número y empezar a recibir eventos.'
                : 'Número conectado. Meta está verificando la primera entrega del webhook.');
        }

        return $responder->respond($result);
    }

    private function requireToken(mixed $token): string
    {
        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'access_token' => 'Ingresa un token de Meta para iniciar la conexión.',
            ]);
        }

        return $token;
    }

    private function guardAgainstForeignClaims(WhatsappConnectionAttempt $attempt): void
    {
        $foreignPhone = WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScopes()
            ->where('phone_number_id', $attempt->phoneNumberId)
            ->where('account_id', '!=', $attempt->account->id())
            ->exists();

        if ($foreignPhone) {
            throw ValidationException::withMessages([
                'phone_number_id' => 'Este número ya está conectado a otro Account de esta instalación.',
            ]);
        }

        $foreignWaba = WabaSubscription::query()
            ->withoutGlobalScopes()
            ->where('waba_id', $attempt->wabaId)
            ->where('account_id', '!=', $attempt->account->id())
            ->exists();

        if ($foreignWaba) {
            throw ValidationException::withMessages([
                'waba_id' => 'Este WABA ya pertenece a otro Account de esta instalación.',
            ]);
        }
    }

    /**
     * @return array{0: WabaSubscription, 1: WhatsappPhoneNumberConnection}
     */
    private function persistVerifiedCredentials(
        ConnectWhatsappNumberRequest $request,
        WhatsappConnectionAttempt $attempt,
        ?WhatsappIntegration $integration,
    ): array {
        $integration ??= new WhatsappIntegration;
        $integration->account_id = $attempt->account->id();
        $integration->created_by ??= $request->user()->id;
        $integration->access_token = $attempt->token;
        $integration->save();

        $waba = WabaSubscription::query()->firstOrNew(['waba_id' => $attempt->wabaId]);
        $waba->account_id = $attempt->account->id();
        $waba->integration_id = $integration->id;
        $waba->save();

        $connection = WhatsappPhoneNumberConnection::query()->firstOrNew([
            'phone_number_id' => $attempt->phoneNumberId,
        ]);
        $connection->account_id = $attempt->account->id();
        $connection->waba_subscription_id = $waba->id;
        $connection->connected_at ??= Carbon::now();
        $connection->last_registration_error = null;
        if ($connection->readiness !== WhatsappConnectionReadiness::Active) {
            $connection->readiness = WhatsappConnectionReadiness::CredentialsVerified;
        }
        $connection->save();

        return [$waba, $connection];
    }

    private function subscribeWabaIfNeeded(
        MetaGraphClientContract $meta,
        WhatsappConnectionAttempt $attempt,
        WabaSubscription $waba,
        WhatsappPhoneNumberConnection $connection,
    ): ?WhatsappConnectionResult {
        if ($waba->subscribed_apps_at === null) {
            try {
                $meta->subscribeWaba($attempt->wabaId, $attempt->token);
            } catch (MetaGraphException $exception) {
                $connection->readiness = WhatsappConnectionReadiness::CredentialsVerified;
                $connection->save();

                return $this->failedResult($exception, $attempt);
            }

            $waba->subscribed_apps_at = Carbon::now();
            $waba->save();
        }

        if ($connection->readiness !== WhatsappConnectionReadiness::Active) {
            $connection->readiness = WhatsappConnectionReadiness::Subscribed;
        }

        return null;
    }

    private function registerPhoneIfNeeded(
        MetaGraphClientContract $meta,
        WhatsappConnectionAttempt $attempt,
        WhatsappPhoneNumberConnection $connection,
        mixed $pin,
    ): ?WhatsappConnectionResult {
        if ($connection->registered_at !== null || ! is_string($pin) || $pin === '') {
            return null;
        }

        try {
            $meta->registerPhoneNumber($attempt->phoneNumberId, $attempt->token, $pin);
        } catch (MetaGraphException $exception) {
            $connection->last_registration_error = $exception->getMessage();
            $connection->save();

            return $this->failedResult($exception, $attempt);
        }

        $connection->registered_at = Carbon::now();

        return null;
    }

    private function markWaitingForWebhook(WhatsappPhoneNumberConnection $connection): void
    {
        if ($connection->registered_at !== null && $connection->readiness !== WhatsappConnectionReadiness::Active) {
            $connection->readiness = WhatsappConnectionReadiness::WebhookWaiting;
        }

        $connection->save();
    }

    private function failedResult(
        MetaGraphException $exception,
        WhatsappConnectionAttempt $attempt,
    ): WhatsappConnectionResult {
        $this->logFailure($exception, $attempt);

        return WhatsappConnectionResult::failure($exception->getMessage());
    }

    private function logFailure(
        MetaGraphException $exception,
        WhatsappConnectionAttempt $attempt,
    ): void {
        Log::warning('Meta WhatsApp connection step failed', [
            'operation' => $exception->operation,
            'meta_code' => $exception->metaCode,
            'account_id' => $attempt->account->id(),
            'phone_number_id' => $attempt->phoneNumberId,
            'waba_id' => $attempt->wabaId,
        ]);
    }
}
