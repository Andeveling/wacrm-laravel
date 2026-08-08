<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Enums\BroadcastRecipientStatus;
use App\Models\Enums\BroadcastStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Difusiones demo: una en borrador y una enviada con destinatarios en
 * distintos estados de entrega. Depende de DemoContactsSeeder.
 */
class DemoBroadcastsSeeder extends Seeder
{
    /**
     * Un destinatario por estado, para que la vista de detalle muestre
     * toda la escala de entrega.
     *
     * @var list<BroadcastRecipientStatus>
     */
    private const RECIPIENT_STATUSES = [
        BroadcastRecipientStatus::Pending,
        BroadcastRecipientStatus::Sent,
        BroadcastRecipientStatus::Delivered,
        BroadcastRecipientStatus::Read,
        BroadcastRecipientStatus::Replied,
        BroadcastRecipientStatus::Failed,
    ];

    public function run(Account $team, User $owner): void
    {
        Broadcast::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Promoción fin de mes'],
            [
                'user_id' => $owner->id,
                'template_name' => 'promo_mensual',
                'template_language' => 'es',
                'template_variables' => ['descuento' => '20%', 'valido_hasta' => '2026-08-31'],
                'status' => BroadcastStatus::Draft,
            ],
        );

        $sent = Broadcast::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Bienvenida clientes VIP'],
            [
                'user_id' => $owner->id,
                'template_name' => 'bienvenida_vip',
                'template_language' => 'es',
                'template_variables' => ['nombre' => 'cliente'],
                'status' => BroadcastStatus::Sent,
                'total_recipients' => 6,
                'sent_count' => 5,
                'delivered_count' => 3,
                'read_count' => 2,
                'replied_count' => 1,
                'failed_count' => 1,
            ],
        );

        if ($sent->wasRecentlyCreated) {
            $this->populateRecipients($sent, $team);
        }
    }

    private function populateRecipients(Broadcast $sent, Account $team): void
    {
        $contacts = Contact::where('account_id', $team->id)
            ->inRandomOrder()
            ->limit(count(self::RECIPIENT_STATUSES))
            ->get();

        foreach ($contacts as $index => $contact) {
            $status = self::RECIPIENT_STATUSES[$index];

            BroadcastRecipient::create([
                'broadcast_id' => $sent->id,
                'contact_id' => $contact->id,
                'status' => $status,
                ...$this->deliveryTimestamps($status),
                ...$this->deliveryOutcome($status),
            ]);
        }
    }

    /**
     * Marcas de tiempo acumulativas: cada estado conserva las del estado
     * anterior. Un fallo solo alcanzó el intento de envío.
     *
     * @return array<string, CarbonImmutable>
     */
    private function deliveryTimestamps(BroadcastRecipientStatus $status): array
    {
        $timestamps = [
            'sent_at' => now()->subHours(2),
            'delivered_at' => now()->subHour(),
            'read_at' => now()->subMinutes(30),
            'replied_at' => now()->subMinutes(10),
        ];

        return array_slice($timestamps, 0, match ($status) {
            BroadcastRecipientStatus::Pending => 0,
            BroadcastRecipientStatus::Sent, BroadcastRecipientStatus::Failed => 1,
            BroadcastRecipientStatus::Delivered => 2,
            BroadcastRecipientStatus::Read => 3,
            BroadcastRecipientStatus::Replied => 4,
        });
    }

    /**
     * @return array<string, string>
     */
    private function deliveryOutcome(BroadcastRecipientStatus $status): array
    {
        return match ($status) {
            BroadcastRecipientStatus::Pending => [],
            BroadcastRecipientStatus::Failed => [
                'error_message' => 'No se pudo entregar: el teléfono del destinatario no está disponible.',
                'whatsapp_message_id' => 'wamid.demo.'.fake()->uuid(),
            ],
            default => ['whatsapp_message_id' => 'wamid.demo.'.fake()->uuid()],
        };
    }
}
