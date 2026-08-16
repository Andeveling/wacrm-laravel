<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\User;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Database\Seeder;

class DemoWhatsappSeeder extends Seeder
{
    private const WABA_ID = '102938475610293';

    private const PHONE_NUMBER_ID = '109283746510928';

    public function run(Account $team, User $owner): void
    {
        $integration = WhatsappIntegration::firstOrCreate(
            ['account_id' => $team->id],
            [
                'created_by' => $owner->id,
                'access_token' => 'demo-access-token',
            ],
        );

        $waba = WabaSubscription::firstOrCreate(
            ['waba_id' => self::WABA_ID],
            [
                'account_id' => $team->id,
                'integration_id' => $integration->id,
                'subscribed_apps_at' => now(),
            ],
        );

        WhatsappPhoneNumberConnection::firstOrCreate(
            ['phone_number_id' => self::PHONE_NUMBER_ID],
            [
                'account_id' => $team->id,
                'waba_subscription_id' => $waba->id,
                'readiness' => WhatsappConnectionReadiness::Active,
                'is_default' => true,
                'connected_at' => now(),
                'registered_at' => now(),
            ],
        );
    }
}
