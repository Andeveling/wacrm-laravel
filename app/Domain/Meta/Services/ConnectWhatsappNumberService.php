<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Domain\Meta\Support\MetaGraphException;
use App\Domain\Meta\Support\WhatsappConnectionAttempt;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ConnectWhatsappNumberService
{
    public function __construct(private MetaGraphClientContract $meta) {}

    public function connect(
        WhatsappConnectionAttempt $attempt,
        ?WhatsappIntegration $integration,
        ?int $createdBy,
        ?string $pin,
        bool $confirmDefault,
    ): WhatsappConnectionResult {
        $this->guardAgainstForeignClaims($attempt);

        try {
            $this->meta->verifyPhoneAndWaba($attempt->phoneNumberId, $attempt->wabaId, $attempt->token);
        } catch (MetaGraphException $exception) {
            [, $connection] = $this->persistVerifiedCredentials($attempt, $integration, $createdBy);
            $connection->readiness = WhatsappConnectionReadiness::AttentionRequired;
            $connection->last_registration_error = $exception->getMessage();
            $connection->save();

            return $this->incompleteResult($exception, $attempt);
        }

        [$waba, $connection] = $this->persistVerifiedCredentials($attempt, $integration, $createdBy);

        $result = $this->subscribeWabaIfNeeded($attempt, $waba, $connection)
            ?? $this->registerPhoneIfNeeded($attempt, $connection, $pin);

        if ($result !== null) {
            return $result;
        }

        $this->markWaitingForWebhook($connection);
        $this->recordDefaultConfirmation($connection, $confirmDefault);

        return WhatsappConnectionResult::success($connection->registered_at === null
            ? 'Credenciales verificadas y WABA suscrito. Ingresa el PIN para registrar el número y empezar a recibir eventos.'
            : 'Número conectado. Meta está verificando la primera entrega del webhook.');
    }

    private function guardAgainstForeignClaims(WhatsappConnectionAttempt $attempt): void
    {
        $foreignPhone = WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScopes()
            ->where('phone_number_id', $attempt->phoneNumberId)
            ->where('account_id', '!=', $attempt->account->id())
            ->where('readiness', '!=', WhatsappConnectionReadiness::Disconnected)
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
            ->whereHas('phoneNumberConnections', function ($query): void {
                $query->withoutGlobalScopes()
                    ->where('readiness', '!=', WhatsappConnectionReadiness::Disconnected);
            })
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
        WhatsappConnectionAttempt $attempt,
        ?WhatsappIntegration $integration,
        ?int $createdBy,
    ): array {
        $this->releaseDisconnectedForeignPhone($attempt->phoneNumberId, $attempt->account->id());
        $this->releaseDisconnectedForeignWaba($attempt->wabaId, $attempt->account->id());

        $integration ??= new WhatsappIntegration;
        $integration->account_id = $attempt->account->id();
        $integration->created_by ??= $createdBy;
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

    private function releaseDisconnectedForeignPhone(string $phoneNumberId, string $accountId): void
    {
        $foreign = WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->where('account_id', '!=', $accountId)
            ->where('readiness', WhatsappConnectionReadiness::Disconnected)
            ->first();

        if (! $foreign instanceof WhatsappPhoneNumberConnection) {
            return;
        }

        $foreign->phone_number_id = null;
        $foreign->is_default = false;
        $foreign->pending_default = false;
        $foreign->save();
    }

    private function releaseDisconnectedForeignWaba(string $wabaId, string $accountId): void
    {
        $foreign = WabaSubscription::query()
            ->withoutGlobalScopes()
            ->where('waba_id', $wabaId)
            ->where('account_id', '!=', $accountId)
            ->first();

        if (! $foreign instanceof WabaSubscription) {
            return;
        }

        $hasActiveNumbers = WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScopes()
            ->where('waba_subscription_id', $foreign->id)
            ->where('readiness', '!=', WhatsappConnectionReadiness::Disconnected)
            ->exists();

        if ($hasActiveNumbers) {
            return;
        }

        $foreign->waba_id = null;
        $foreign->subscribed_apps_at = null;
        $foreign->save();
    }

    private function subscribeWabaIfNeeded(
        WhatsappConnectionAttempt $attempt,
        WabaSubscription $waba,
        WhatsappPhoneNumberConnection $connection,
    ): ?WhatsappConnectionResult {
        if ($waba->subscribed_apps_at === null) {
            try {
                $this->meta->subscribeWaba($attempt->wabaId, $attempt->token);
            } catch (MetaGraphException $exception) {
                $connection->readiness = WhatsappConnectionReadiness::CredentialsVerified;
                $connection->save();

                return $this->incompleteResult($exception, $attempt);
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
        WhatsappConnectionAttempt $attempt,
        WhatsappPhoneNumberConnection $connection,
        ?string $pin,
    ): ?WhatsappConnectionResult {
        if ($connection->registered_at !== null || $pin === null || $pin === '') {
            return null;
        }

        try {
            $this->meta->registerPhoneNumber($attempt->phoneNumberId, $attempt->token, $pin);
        } catch (MetaGraphException $exception) {
            $connection->last_registration_error = $exception->getMessage();
            $connection->readiness = WhatsappConnectionReadiness::AttentionRequired;
            $connection->save();

            return $this->incompleteResult($exception, $attempt);
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

    private function recordDefaultConfirmation(
        WhatsappPhoneNumberConnection $connection,
        bool $confirmDefault,
    ): void {
        if (! $confirmDefault) {
            return;
        }

        $otherDefaultExists = WhatsappPhoneNumberConnection::query()
            ->whereKeyNot($connection->id)
            ->where('is_default', true)
            ->exists();

        if ($otherDefaultExists) {
            return;
        }

        $connection->pending_default = true;
        $connection->save();
    }

    private function incompleteResult(
        MetaGraphException $exception,
        WhatsappConnectionAttempt $attempt,
    ): WhatsappConnectionResult {
        Log::warning('Meta WhatsApp connection step failed', [
            'operation' => $exception->operation,
            'meta_code' => $exception->metaCode,
            'account_id' => $attempt->account->id(),
            'phone_number_id' => $attempt->phoneNumberId,
            'waba_id' => $attempt->wabaId,
        ]);

        return WhatsappConnectionResult::incomplete(
            'Guardamos el número. Meta todavía no lo activó: '.$exception->getMessage(),
        );
    }
}
