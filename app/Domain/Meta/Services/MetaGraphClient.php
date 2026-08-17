<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Support\MetaGraphException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Narrow boundary around the Meta Graph calls needed by the connection wizard.
 * No caller receives a raw Graph error or response body: those may contain
 * provider details that are not safe to expose in a tenant-facing response.
 */
final class MetaGraphClient implements MetaGraphClientContract
{
    /**
     * Verify that the token can read the phone and WABA, and that the phone
     * actually belongs to the requested WABA.
     *
     * @return array{id: string, display_phone_number: string|null, verified_name: string|null}
     */
    public function verifyPhoneAndWaba(string $phoneNumberId, string $wabaId, string $token): array
    {
        $this->assertRequiredPermissionFamilies($token);

        $phone = $this->get(
            $phoneNumberId,
            $token,
            ['fields' => 'id,display_phone_number,verified_name'],
            'credentials',
        );

        $this->assertPhoneBelongsToWaba($phoneNumberId, $wabaId, $token);
        $this->get($wabaId, $token, ['fields' => 'id'], 'permissions');

        return [
            'id' => (string) ($phone['id'] ?? $phoneNumberId),
            'display_phone_number' => is_string($phone['display_phone_number'] ?? null)
                ? $phone['display_phone_number']
                : null,
            'verified_name' => is_string($phone['verified_name'] ?? null)
                ? $phone['verified_name']
                : null,
        ];
    }

    /**
     * Graph v21+ no longer exposes `whatsapp_business_account` on a phone node.
     * Membership is the phone id appearing under the WABA's phone_numbers edge.
     */
    private function assertPhoneBelongsToWaba(string $phoneNumberId, string $wabaId, string $token): void
    {
        $listing = $this->get(
            $wabaId.'/phone_numbers',
            $token,
            ['fields' => 'id'],
            'membership',
        );

        $rows = $listing['data'] ?? [];
        $rows = is_array($rows) ? $rows : [];

        $ids = collect($rows)
            ->map(fn (mixed $row): ?string => is_array($row) && is_string($row['id'] ?? null) ? $row['id'] : null)
            ->filter()
            ->values()
            ->all();

        if (! in_array($phoneNumberId, $ids, true)) {
            throw new MetaGraphException(
                'membership',
                'El número no pertenece al WABA indicado.',
            );
        }
    }

    /**
     * Require both WhatsApp permission families before trusting the token for setup.
     */
    private function assertRequiredPermissionFamilies(string $token): void
    {
        if (! $this->canInspectTokenPermissions()) {
            return;
        }

        $response = $this->debugToken($token);

        if (! $response->successful()) {
            $this->throwForResponse($response, 'permissions');
        }

        $scopes = data_get($response->json(), 'data.scopes', data_get($response->json(), 'data.granular_scopes', []));

        if (! is_array($scopes)) {
            throw new MetaGraphException(
                'permissions',
                'El token de Meta no expone los permisos requeridos.',
            );
        }

        if (! $this->hasRequiredPermissionFamilies($scopes)) {
            throw new MetaGraphException(
                'permissions',
                'El token de Meta no tiene las dos familias de permisos requeridas (gestión y mensajería).',
            );
        }
    }

    private function debugToken(string $token): Response
    {
        try {
            return Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->get($this->url('debug_token'), $this->debugTokenQuery($token));
        } catch (ConnectionException) {
            throw new MetaGraphException(
                'permissions',
                'No se pudo contactar a Meta. Revisa la red e intenta de nuevo.',
            );
        }
    }

    private function canInspectTokenPermissions(): bool
    {
        $appId = config('services.meta.app_id');
        $appSecret = config('services.meta.app_secret');

        return is_string($appId) && $appId !== '' && is_string($appSecret) && $appSecret !== '';
    }

    /**
     * @return array<string, string>
     */
    private function debugTokenQuery(string $token): array
    {
        return [
            'input_token' => $token,
            'access_token' => (string) config('services.meta.app_id').'|'.(string) config('services.meta.app_secret'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $scopes
     */
    private function hasRequiredPermissionFamilies(array $scopes): bool
    {
        $normalized = collect($scopes)
            ->map($this->permissionScopeName(...))
            ->filter()
            ->values()
            ->all();

        foreach (['whatsapp_business_management', 'whatsapp_business_messaging'] as $scope) {
            if (! in_array($scope, $normalized, true)) {
                return false;
            }
        }

        return true;
    }

    private function permissionScopeName(mixed $scope): ?string
    {
        if (is_string($scope)) {
            return $scope;
        }

        if (is_array($scope) && is_string($scope['scope'] ?? null)) {
            return $scope['scope'];
        }

        return null;
    }

    public function subscribeWaba(string $wabaId, string $token): void
    {
        $response = $this->post($wabaId.'/subscribed_apps', $token, [], 'subscription');

        if ($response->successful()) {
            return;
        }

        $errorMessage = strtolower((string) $response->json('error.message', ''));

        if (str_contains($errorMessage, 'already') && str_contains($errorMessage, 'subscribed')) {
            return;
        }

        $this->throwForResponse($response, 'subscription');
    }

    public function unsubscribeWaba(string $wabaId, string $token): void
    {
        $response = $this->delete($wabaId.'/subscribed_apps', $token, 'unsubscription');

        if ($response->successful()) {
            return;
        }

        $errorMessage = strtolower((string) $response->json('error.message', ''));

        if (str_contains($errorMessage, 'not') && str_contains($errorMessage, 'subscribed')) {
            return;
        }

        $this->throwForResponse($response, 'unsubscription');
    }

    public function sendTextMessage(string $phoneNumberId, string $token, string $to, string $body): string
    {
        $response = $this->post(
            $phoneNumberId.'/messages',
            $token,
            [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $body],
            ],
            'send',
        );

        if (! $response->successful()) {
            $this->throwForResponse($response, 'send');
        }

        $whatsappMessageId = $response->json('messages.0.id');

        if (! is_string($whatsappMessageId) || $whatsappMessageId === '') {
            throw new MetaGraphException(
                'send',
                'Meta no devolvió un identificador de mensaje.',
            );
        }

        return $whatsappMessageId;
    }

    public function registerPhoneNumber(string $phoneNumberId, string $token, string $pin): void
    {
        $response = $this->post(
            $phoneNumberId.'/register',
            $token,
            ['messaging_product' => 'whatsapp', 'pin' => $pin],
            'registration',
        );

        if ($response->successful()) {
            return;
        }

        $errorMessage = strtolower((string) $response->json('error.message', ''));

        if (str_contains($errorMessage, 'already') && str_contains($errorMessage, 'registered')) {
            return;
        }

        $this->throwForResponse($response, 'registration');
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function get(string $resource, string $token, array $query, string $operation): array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->get($this->url($resource), $query);
        } catch (ConnectionException) {
            throw new MetaGraphException(
                $operation,
                'No se pudo contactar a Meta. Revisa la red e intenta de nuevo.',
            );
        }

        if (! $response->successful()) {
            $this->throwForResponse($response, $operation);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $resource, string $token, array $payload, string $operation): Response
    {
        try {
            return Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->post($this->url($resource), $payload);
        } catch (ConnectionException) {
            throw new MetaGraphException(
                $operation,
                'No se pudo contactar a Meta. Revisa la red e intenta de nuevo.',
            );
        }
    }

    private function delete(string $resource, string $token, string $operation): Response
    {
        try {
            return Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->delete($this->url($resource));
        } catch (ConnectionException) {
            throw new MetaGraphException(
                $operation,
                'No se pudo contactar a Meta. Revisa la red e intenta de nuevo.',
            );
        }
    }

    private function url(string $resource): string
    {
        return rtrim((string) config('services.meta.graph_api_url'), '/')
            .'/'.trim((string) config('services.meta.graph_api_version'), '/').'/'.ltrim($resource, '/');
    }

    private function throwForResponse(Response $response, string $operation): never
    {
        $code = $response->json('error.code');
        $code = is_int($code) ? $code : null;

        throw new MetaGraphException($operation, $this->messageFor($operation, $code), $code);
    }

    private function messageFor(string $operation, ?int $code): string
    {
        return match ($operation) {
            'credentials' => $this->credentialsMessage($code),
            'permissions' => $this->permissionsMessage($code),
            'subscription' => 'Meta no pudo suscribir el WABA a los webhooks. Revisa los permisos del token.',
            'unsubscription' => 'Meta no pudo desuscribir el WABA. El número ya quedó desconectado.',
            'send' => 'Meta no pudo enviar el mensaje. Revisa la conexión e intenta de nuevo.',
            'registration' => $this->registrationMessage($code),
            default => 'Meta no pudo completar la operación.',
        };
    }

    private function credentialsMessage(?int $code): string
    {
        return match ($code) {
            190 => 'El token de Meta es inválido o expiró.',
            10, 200, 2000 => 'El token de Meta no tiene los permisos necesarios.',
            default => 'Meta rechazó las credenciales. Revisa el token y vuelve a intentar.',
        };
    }

    private function permissionsMessage(?int $code): string
    {
        return match ($code) {
            10, 200, 2000 => 'El token de Meta no tiene permisos para consultar este WABA.',
            default => 'Meta no permitió consultar el WABA. Revisa los permisos del token.',
        };
    }

    private function registrationMessage(?int $code): string
    {
        return match ($code) {
            190 => 'El token de Meta es inválido o expiró.',
            10, 200, 2000 => 'El token de Meta no tiene permisos para registrar el número.',
            100 => 'Meta no pudo registrar el número. Revisa el PIN de verificación en dos pasos.',
            default => 'Meta no pudo registrar el número. Revisa la configuración e intenta de nuevo.',
        };
    }
}
