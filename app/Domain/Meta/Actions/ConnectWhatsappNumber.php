<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Domain\Meta\Services\MetaGraphClientContract;
use App\Domain\Meta\Support\MetaGraphException;
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
        $phoneNumberId = $data['phone_number_id'];
        $wabaId = $data['waba_id'];
        $integration = WhatsappIntegration::query()->first();
        $token = $this->requireToken($data['access_token'] ?: $integration?->access_token);

        $this->guardAgainstForeignClaims($account, $phoneNumberId, $wabaId);

        try {
            $meta->verifyPhoneAndWaba($phoneNumberId, $wabaId, $token);
        } catch (MetaGraphException $exception) {
            return $responder->respond($this->failedResult($exception, $account, $phoneNumberId, $wabaId));
        }

        [$waba, $connection] = $this->persistVerifiedCredentials(
            $request,
            $account,
            $integration,
            $token,
            $phoneNumberId,
            $wabaId,
        );

        $result = $this->subscribeWabaIfNeeded($meta, $account, $waba, $connection, $token, $phoneNumberId, $wabaId)
            ?? $this->registerPhoneIfNeeded($meta, $account, $connection, $data['pin'] ?? null, $token, $phoneNumberId, $wabaId);

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

    private function guardAgainstForeignClaims(CurrentAccount $account, string $phoneNumberId, string $wabaId): void
    {
        $foreignPhone = WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->where('account_id', '!=', $account->id())
            ->exists();

        if ($foreignPhone) {
            throw ValidationException::withMessages([
                'phone_number_id' => 'Este número ya está conectado a otro Account de esta instalación.',
            ]);
        }

        $foreignWaba = WabaSubscription::query()
            ->withoutGlobalScopes()
            ->where('waba_id', $wabaId)
            ->where('account_id', '!=', $account->id())
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
        CurrentAccount $account,
        ?WhatsappIntegration $integration,
        string $token,
        string $phoneNumberId,
        string $wabaId,
    ): array {
        $integration ??= new WhatsappIntegration;
        $integration->account_id = $account->id();
        $integration->created_by ??= $request->user()->id;
        $integration->access_token = $token;
        $integration->save();

        $waba = WabaSubscription::query()->firstOrNew(['waba_id' => $wabaId]);
        $waba->account_id = $account->id();
        $waba->integration_id = $integration->id;
        $waba->save();

        $connection = WhatsappPhoneNumberConnection::query()->firstOrNew([
            'phone_number_id' => $phoneNumberId,
        ]);
        $connection->account_id = $account->id();
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
        CurrentAccount $account,
        WabaSubscription $waba,
        WhatsappPhoneNumberConnection $connection,
        string $token,
        string $phoneNumberId,
        string $wabaId,
    ): ?WhatsappConnectionResult {
        if ($waba->subscribed_apps_at === null) {
            try {
                $meta->subscribeWaba($wabaId, $token);
            } catch (MetaGraphException $exception) {
                $connection->readiness = WhatsappConnectionReadiness::CredentialsVerified;
                $connection->save();

                return $this->failedResult($exception, $account, $phoneNumberId, $wabaId);
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
        CurrentAccount $account,
        WhatsappPhoneNumberConnection $connection,
        mixed $pin,
        string $token,
        string $phoneNumberId,
        string $wabaId,
    ): ?WhatsappConnectionResult {
        if ($connection->registered_at !== null || ! is_string($pin) || $pin === '') {
            return null;
        }

        try {
            $meta->registerPhoneNumber($phoneNumberId, $token, $pin);
        } catch (MetaGraphException $exception) {
            $connection->last_registration_error = $exception->getMessage();
            $connection->save();

            return $this->failedResult($exception, $account, $phoneNumberId, $wabaId);
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
        CurrentAccount $account,
        string $phoneNumberId,
        string $wabaId,
    ): WhatsappConnectionResult {
        $this->logFailure($exception, $account, $phoneNumberId, $wabaId);

        return WhatsappConnectionResult::failure($exception->getMessage());
    }

    private function logFailure(
        MetaGraphException $exception,
        CurrentAccount $account,
        string $phoneNumberId,
        string $wabaId,
    ): void {
        Log::warning('Meta WhatsApp connection step failed', [
            'operation' => $exception->operation,
            'meta_code' => $exception->metaCode,
            'account_id' => $account->id(),
            'phone_number_id' => $phoneNumberId,
            'waba_id' => $wabaId,
        ]);
    }
}
