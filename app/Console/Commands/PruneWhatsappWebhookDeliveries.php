<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WhatsappWebhookDelivery;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Delete `whatsapp_webhook_deliveries` rows whose `received_at` falls
 * outside the retention window (default 30 days). Retention is anchored
 * on `received_at`, not `created_at`, because the inbox represents
 * real inbound deliveries and that is the timestamp operators care
 * about.
 *
 * The window is parsed the same way `PruneApiKeyAuditLog` does — a
 * trailing `days` suffix keeps the command auditable from a cron
 * command line (`--older-than=30days`).
 *
 * Meant to run daily (see `routes/console.php`).
 */
class PruneWhatsappWebhookDeliveries extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:prune-deliveries {--older-than=30days : Retention window, e.g. 30days}';

    /**
     * @var string
     */
    protected $description = 'Delete webhook deliveries older than the retention window (default 30 days).';

    public function handle(): int
    {
        $days = $this->parseDays((string) $this->option('older-than'));
        $cutoff = now()->subDays($days);

        $deleted = WhatsappWebhookDelivery::query()
            ->where('received_at', '<', $cutoff)
            ->whereDoesntHave('events', function (Builder $query): void {
                $query->whereIn('classification', WhatsappWebhookEvent::classifiableOutcomes());
            })
            ->delete();

        $this->info("Pruned {$deleted} webhook delivery row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }

    private function parseDays(string $window): int
    {
        if (! preg_match('/^(\d+)days$/', $window, $matches)) {
            throw new InvalidArgumentException("Invalid --older-than value [{$window}]. Expected format: <N>days.");
        }

        return (int) $matches[1];
    }
}
