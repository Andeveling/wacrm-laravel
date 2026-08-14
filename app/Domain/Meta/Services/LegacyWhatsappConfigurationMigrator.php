<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Enums\WhatsappLegacyMigrationIssueKind;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Converts the former one-number-per-account configuration into the durable
 * integration/WABA/connection model without deleting the legacy source row.
 *
 * The migrator is deliberately query-builder based. Tenant global scopes must
 * not hide another Account while a deployment is performing this cross-tenant
 * backfill, and no plaintext token is written to the new tables.
 */
final class LegacyWhatsappConfigurationMigrator
{
    /**
     * @phpstan-type LegacyConfig array{
     *     id: string,
     *     account_id: string,
     *     user_id: int|null,
     *     phone_number_id: string|null,
     *     waba_id: string|null,
     *     access_token: string|null,
     *     status: string|null,
     *     connected_at: mixed,
     *     registered_at: mixed,
     *     subscribed_apps_at: mixed,
     *     last_registration_error: string|null
     * }
     */

    /**
     * Run the backfill more than once safely. The legacy ids and issue
     * fingerprints are the idempotency keys; existing CRM rows are only
     * assigned when they are still missing a connection.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->legacyConfigs() as $legacyConfig) {
                $this->migrateConfig($legacyConfig);
            }

            $this->assignConversations();
        });
    }

    /**
     * @return list<array{
     *     id: string,
     *     account_id: string,
     *     user_id: int|null,
     *     phone_number_id: string|null,
     *     waba_id: string|null,
     *     access_token: string|null,
     *     status: string|null,
     *     connected_at: mixed,
     *     registered_at: mixed,
     *     subscribed_apps_at: mixed,
     *     last_registration_error: string|null
     * }>
     */
    private function legacyConfigs(): array
    {
        return array_values(DB::table('whatsapp_config')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'account_id' => (string) $row->account_id,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'phone_number_id' => is_string($row->phone_number_id) ? $row->phone_number_id : null,
                'waba_id' => is_string($row->waba_id) ? $row->waba_id : null,
                'access_token' => is_string($row->access_token) ? $row->access_token : null,
                'status' => is_string($row->status) ? $row->status : null,
                'connected_at' => $row->connected_at,
                'registered_at' => $row->registered_at,
                'subscribed_apps_at' => $row->subscribed_apps_at,
                'last_registration_error' => is_string($row->last_registration_error)
                    ? $row->last_registration_error
                    : null,
            ])
            ->all());
    }

    /**
     * @param array{
     *     id: string,
     *     account_id: string,
     *     user_id: int|null,
     *     phone_number_id: string|null,
     *     waba_id: string|null,
     *     access_token: string|null,
     *     status: string|null,
     *     connected_at: mixed,
     *     registered_at: mixed,
     *     subscribed_apps_at: mixed,
     *     last_registration_error: string|null
     * } $legacy
     */
    private function migrateConfig(array $legacy): void
    {
        $integration = DB::table('whatsapp_integrations')
            ->where('legacy_config_id', $legacy['id'])
            ->first();

        if ($integration === null) {
            $integration = DB::table('whatsapp_integrations')
                ->where('account_id', $legacy['account_id'])
                ->first();
        }

        if ($integration === null) {
            $integrationId = (string) str()->uuid();
            $plainToken = $this->nullableString($legacy['access_token']);

            DB::table('whatsapp_integrations')->insert([
                'id' => $integrationId,
                'account_id' => $legacy['account_id'],
                'created_by' => $legacy['user_id'],
                'access_token' => $plainToken === null ? null : Crypt::encryptString($plainToken),
                'legacy_config_id' => $legacy['id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $integration = [
                'id' => $integrationId,
                'account_id' => $legacy['account_id'],
                'legacy_config_id' => $legacy['id'],
            ];
        } else {
            $integrationId = (string) $integration->id;

            if ($integration->legacy_config_id === null) {
                DB::table('whatsapp_integrations')
                    ->where('id', $integrationId)
                    ->update(['legacy_config_id' => $legacy['id'], 'updated_at' => now()]);
            }

            $integration = [
                'id' => $integrationId,
                'account_id' => (string) $integration->account_id,
                'legacy_config_id' => $legacy['id'],
            ];
        }

        if ($integration['account_id'] !== $legacy['account_id']) {
            // This is only reachable when a pre-existing integration row has
            // been manually corrupted; do not attach legacy data cross-tenant.
            $integration = [
                'id' => (string) $integration['id'],
                'account_id' => $legacy['account_id'],
                'legacy_config_id' => $legacy['id'],
            ];
        }

        if ($integration['legacy_config_id'] === null) {
            DB::table('whatsapp_integrations')
                ->where('id', $integration['id'])
                ->update(['legacy_config_id' => $legacy['id'], 'updated_at' => now()]);
        }

        $waba = $this->migrateWaba($legacy, (string) $integration['id']);
        $this->migratePhoneConnection($legacy, $waba['id'] ?? null);

        $missing = array_values(array_filter([
            $this->nullableString($legacy['access_token']) === null ? 'access_token' : null,
            $this->nullableString($legacy['waba_id']) === null ? 'waba_id' : null,
            $this->nullableString($legacy['phone_number_id']) === null ? 'phone_number_id' : null,
        ]));

        if ($missing !== []) {
            $this->recordIssue(
                accountId: $legacy['account_id'],
                kind: WhatsappLegacyMigrationIssueKind::IncompleteLegacyConfig,
                legacyConfigId: $legacy['id'],
                conversationId: null,
                details: ['missing' => $missing],
            );
        }
    }

    /**
     * @param array{
     *     id: string,
     *     account_id: string,
     *     user_id: int|null,
     *     phone_number_id: string|null,
     *     waba_id: string|null,
     *     access_token: string|null,
     *     status: string|null,
     *     connected_at: mixed,
     *     registered_at: mixed,
     *     subscribed_apps_at: mixed,
     *     last_registration_error: string|null
     * } $legacy
     * @return array<string, mixed>|null
     */
    private function migrateWaba(array $legacy, string $integrationId): ?array
    {
        $wabaId = $this->nullableString($legacy['waba_id']);

        if ($wabaId === null) {
            return null;
        }

        $waba = DB::table('waba_subscriptions')
            ->where('legacy_config_id', $legacy['id'])
            ->first();

        if ($waba === null) {
            $waba = DB::table('waba_subscriptions')
                ->where('waba_id', $wabaId)
                ->first();
        }

        if ($waba !== null) {
            if ($waba->account_id !== $legacy['account_id']) {
                $this->recordIssue(
                    accountId: $legacy['account_id'],
                    kind: WhatsappLegacyMigrationIssueKind::WabaClaimedByAnotherAccount,
                    legacyConfigId: $legacy['id'],
                    conversationId: null,
                    details: ['resource' => 'waba_id', 'action' => 'explicit_reconnect_required'],
                );

                return null;
            }

            return ['id' => (string) $waba->id, 'account_id' => (string) $waba->account_id];
        }

        $wabaRecordId = (string) str()->uuid();

        DB::table('waba_subscriptions')->insert([
            'id' => $wabaRecordId,
            'account_id' => $legacy['account_id'],
            'integration_id' => $integrationId,
            'waba_id' => $wabaId,
            'legacy_config_id' => $legacy['id'],
            'subscribed_apps_at' => $legacy['subscribed_apps_at'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $wabaRecordId, 'account_id' => $legacy['account_id']];
    }

    /**
     * @param array{
     *     id: string,
     *     account_id: string,
     *     user_id: int|null,
     *     phone_number_id: string|null,
     *     waba_id: string|null,
     *     access_token: string|null,
     *     status: string|null,
     *     connected_at: mixed,
     *     registered_at: mixed,
     *     subscribed_apps_at: mixed,
     *     last_registration_error: string|null
     * } $legacy
     */
    private function migratePhoneConnection(array $legacy, ?string $wabaSubscriptionId): void
    {
        $phoneNumberId = $this->nullableString($legacy['phone_number_id']);

        if ($phoneNumberId === null) {
            return;
        }

        $connection = DB::table('whatsapp_phone_number_connections')
            ->where('legacy_config_id', $legacy['id'])
            ->first();

        if ($connection === null) {
            $connection = DB::table('whatsapp_phone_number_connections')
                ->where('phone_number_id', $phoneNumberId)
                ->first();
        }

        if ($connection !== null) {
            if ($connection->account_id !== $legacy['account_id']) {
                $this->recordIssue(
                    accountId: $legacy['account_id'],
                    kind: WhatsappLegacyMigrationIssueKind::PhoneNumberClaimedByAnotherAccount,
                    legacyConfigId: $legacy['id'],
                    conversationId: null,
                    details: ['resource' => 'phone_number_id', 'action' => 'explicit_reconnect_required'],
                );
            }

            return;
        }

        DB::table('whatsapp_phone_number_connections')->insert([
            'id' => (string) str()->uuid(),
            'account_id' => $legacy['account_id'],
            'waba_subscription_id' => $wabaSubscriptionId,
            'phone_number_id' => $phoneNumberId,
            'readiness' => $this->readiness($legacy),
            'is_default' => false,
            'legacy_config_id' => $legacy['id'],
            'connected_at' => $legacy['connected_at'],
            'registered_at' => $legacy['registered_at'],
            'last_registration_error' => $legacy['last_registration_error'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignConversations(): void
    {
        $conversations = DB::table('conversations')
            ->whereNull('connection_id')
            ->orderBy('id')
            ->get(['id', 'account_id']);

        foreach ($conversations as $conversation) {
            $connectionIds = DB::table('whatsapp_phone_number_connections')
                ->where('account_id', $conversation->account_id)
                ->whereNotNull('legacy_config_id')
                ->pluck('id');

            if ($connectionIds->count() === 1) {
                DB::table('conversations')
                    ->where('id', $conversation->id)
                    ->whereNull('connection_id')
                    ->update(['connection_id' => $connectionIds->first(), 'updated_at' => now()]);

                continue;
            }

            $hasLegacyConfig = DB::table('whatsapp_config')
                ->where('account_id', $conversation->account_id)
                ->exists();

            $this->recordIssue(
                accountId: $conversation->account_id,
                kind: $hasLegacyConfig
                    ? WhatsappLegacyMigrationIssueKind::AmbiguousConversationConnection
                    : WhatsappLegacyMigrationIssueKind::MissingLegacyConnection,
                legacyConfigId: null,
                conversationId: $conversation->id,
                details: [
                    'candidate_connections' => $connectionIds->count(),
                    'action' => 'select_connection_explicitly',
                ],
            );
        }
    }

    /**
     * @param array{
     *     status: string|null,
     *     registered_at: mixed,
     *     subscribed_apps_at: mixed
     * } $legacy
     */
    private function readiness(array $legacy): string
    {
        if ($legacy['status'] === 'disconnected') {
            return WhatsappConnectionReadiness::Disconnected->value;
        }

        if ($legacy['registered_at'] !== null && $legacy['subscribed_apps_at'] !== null) {
            // Historical timestamps prove setup steps, not a real routed event.
            return WhatsappConnectionReadiness::WebhookWaiting->value;
        }

        if ($legacy['subscribed_apps_at'] !== null) {
            return WhatsappConnectionReadiness::Subscribed->value;
        }

        return WhatsappConnectionReadiness::CredentialsVerified->value;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordIssue(
        string $accountId,
        WhatsappLegacyMigrationIssueKind $kind,
        ?string $legacyConfigId,
        ?string $conversationId,
        array $details,
    ): void {
        try {
            $encodedDetails = json_encode($details, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $exception;
        }

        $fingerprint = hash('sha256', implode('|', [
            $kind->value,
            $legacyConfigId ?? '',
            $conversationId ?? '',
        ]));

        DB::table('whatsapp_legacy_migration_issues')->updateOrInsert(
            ['fingerprint' => $fingerprint],
            [
                'id' => (string) str()->uuid(),
                'account_id' => $accountId,
                'legacy_config_id' => $legacyConfigId,
                'conversation_id' => $conversationId,
                'kind' => $kind->value,
                'details' => $encodedDetails,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
