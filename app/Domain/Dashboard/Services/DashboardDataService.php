<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Models\Broadcast;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Message;
use App\Models\PipelineStage;
use App\Support\CurrentAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DashboardDataService
{
    public function __construct(
        private readonly CurrentAccount $account,
    ) {}

    /**
     * Header metrics: active conversations, new contacts, open deals, messages sent.
     *
     * Every MetricDelta carries (current, previous) where both are absolute
     * values and the UI computes current - previous as the change indicator.
     *
     * @return array{activeConversations: array{current: int, previous: int}, newContactsToday: array{current: int, previous: int}, openDealsValue: float, openDealsCount: int, messagesSentToday: array{current: int, previous: int}}
     */
    public function metrics(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        return [
            'activeConversations' => [
                'current' => Conversation::whereIn('status', ['open', 'pending'])->count(),
                'previous' => Conversation::whereIn('status', ['open', 'pending'])
                    ->whereDate('created_at', '<=', $yesterday)
                    ->count(),
            ],
            'newContactsToday' => [
                'current' => Contact::whereDate('created_at', $today)->count(),
                'previous' => Contact::whereDate('created_at', $yesterday)->count(),
            ],
            'openDealsValue' => (float) Deal::where('status', 'open')->sum('value'),
            'openDealsCount' => Deal::where('status', 'open')->count(),
            'messagesSentToday' => [
                'current' => $this->agentMessageCountOn($today),
                'previous' => $this->agentMessageCountOn($yesterday),
            ],
        ];
    }

    private function agentMessageCountOn(Carbon $date): int
    {
        return Message::whereHas('conversation')
            ->where('sender_type', 'agent')
            ->whereDate('created_at', $date)
            ->count();
    }

    /**
     * @param  int<7, 90>  $days
     * @return array<array{day: string, incoming: int, outgoing: int}>
     */
    public function conversationsSeries(int $days): array
    {
        $end = Carbon::today()->endOfDay();
        $start = Carbon::today()->subDays($days - 1)->startOfDay();

        $rows = Message::whereHas('conversation')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, sender_type, COUNT(*) as count')
            ->groupBy(DB::raw('DATE(created_at)'), 'sender_type')
            ->get();

        $byDay = [];
        foreach ($rows as $row) {
            /** @var object{day: string, sender_type: string, count: int} $row */
            $day = $row->day;
            if (! isset($byDay[$day])) {
                $byDay[$day] = ['incoming' => 0, 'outgoing' => 0];
            }
            if ($row->sender_type === 'customer') {
                $byDay[$day]['incoming'] += (int) $row->count;
            } elseif ($row->sender_type === 'agent') {
                $byDay[$day]['outgoing'] += (int) $row->count;
            }
        }

        $series = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $series[] = [
                'day' => $key,
                'incoming' => $byDay[$key]['incoming'] ?? 0,
                'outgoing' => $byDay[$key]['outgoing'] ?? 0,
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Pipeline donut: open deals grouped by stage.
     *
     * PipelineStage is scoped through its pipeline (BelongsToAccount on
     * Pipeline), so whereHas('pipeline') limits results to the current
     * account's pipelines.
     *
     * @return array{stages: array<array{id: string, name: string, color: string, dealCount: int, totalValue: float}>, totalValue: float}
     */
    public function pipeline(): array
    {
        $stages = PipelineStage::with('pipeline')
            ->whereHas('pipeline')
            ->whereHas('deals', fn ($q) => $q->where('status', 'open'))
            ->get()
            ->map(function (PipelineStage $stage): array {
                $openDeals = $stage->deals()->where('status', 'open');

                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'dealCount' => $openDeals->count(),
                    'totalValue' => (float) $openDeals->sum('value'),
                ];
            })
            ->filter(fn (array $s): bool => $s['dealCount'] > 0)
            ->values()
            ->all();

        $totalValue = array_sum(array_column($stages, 'totalValue'));

        return [
            'stages' => $stages,
            'totalValue' => $totalValue,
        ];
    }

    /**
     * Response time: avg minutes to first agent reply per day-of-week,
     * plus this-week and last-week averages.
     *
     * A "first reply" is the gap between the earliest customer message
     * and the earliest subsequent agent message within the same
     * conversation on the same calendar day.
     *
     * @return array{buckets: array<array{dow: int, avgMinutes: float|null, samples: int}>, thisWeekAvg: float|null, lastWeekAvg: float|null}
     */
    public function responseTime(): array
    {
        $accountId = $this->account->id();
        $today = Carbon::today();

        $thisWeekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $thisWeekEnd = $today->copy()->endOfDay();
        $lastWeekStart = $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
        $lastWeekEnd = $today->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $buckets = $this->computeResponseTimeBuckets($accountId, $thisWeekStart, $thisWeekEnd);
        $lastWeekBuckets = $this->computeResponseTimeBuckets($accountId, $lastWeekStart, $lastWeekEnd);

        return [
            'buckets' => $buckets,
            'thisWeekAvg' => $this->computeWeekAverage($buckets),
            'lastWeekAvg' => $this->computeWeekAverage($lastWeekBuckets),
        ];
    }

    /**
     * @return array<array{dow: int, avgMinutes: float|null, samples: int}>
     */
    private function computeResponseTimeBuckets(string $accountId, Carbon $start, Carbon $end): array
    {
        $rows = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.account_id', $accountId)
            ->whereBetween('messages.created_at', [$start, $end])
            ->select('messages.conversation_id')
            ->selectRaw('DATE(messages.created_at) as day')
            ->selectRaw("MIN(CASE WHEN messages.sender_type = 'customer' THEN messages.created_at END) as first_customer_at")
            ->selectRaw("MIN(CASE WHEN messages.sender_type = 'agent' THEN messages.created_at END) as first_agent_at")
            ->groupBy('messages.conversation_id', DB::raw('DATE(messages.created_at)'))
            ->havingRaw("MIN(CASE WHEN messages.sender_type = 'agent' THEN messages.created_at END) IS NOT NULL")
            ->havingRaw("MIN(CASE WHEN messages.sender_type = 'customer' THEN messages.created_at END) IS NOT NULL")
            ->havingRaw("MIN(CASE WHEN messages.sender_type = 'agent' THEN messages.created_at END) > MIN(CASE WHEN messages.sender_type = 'customer' THEN messages.created_at END)")
            ->get();

        $bucketsRaw = [];
        foreach ($rows as $row) {
            $dow = Carbon::parse($row->day)->dayOfWeekIso - 1;

            if (! isset($bucketsRaw[$dow])) {
                $bucketsRaw[$dow] = ['totalMinutes' => 0, 'samples' => 0];
            }

            $diffMinutes = Carbon::parse($row->first_customer_at)
                ->diffInMinutes(Carbon::parse($row->first_agent_at));

            $bucketsRaw[$dow]['totalMinutes'] += $diffMinutes;
            $bucketsRaw[$dow]['samples']++;
        }

        $buckets = [];
        for ($dow = 0; $dow < 7; $dow++) {
            $data = $bucketsRaw[$dow] ?? null;
            $buckets[] = [
                'dow' => $dow,
                'avgMinutes' => $data
                    ? round($data['totalMinutes'] / $data['samples'], 1)
                    : null,
                'samples' => $data ? $data['samples'] : 0,
            ];
        }

        return $buckets;
    }

    /**
     * @param  array<array{dow: int, avgMinutes: float|null, samples: int}>  $buckets
     */
    private function computeWeekAverage(array $buckets): ?float
    {
        $totalMinutes = 0;
        $totalSamples = 0;

        foreach ($buckets as $b) {
            if ($b['avgMinutes'] !== null) {
                $totalMinutes += $b['avgMinutes'] * $b['samples'];
                $totalSamples += $b['samples'];
            }
        }

        return $totalSamples > 0
            ? round($totalMinutes / $totalSamples, 1)
            : null;
    }

    /**
     * Recent activity feed: incoming messages, new contacts, deals, broadcasts.
     *
     * Merged from four sources, sorted by recency descending.
     *
     * @return array<array{id: string, kind: string, text: string, at: string, href?: string}>
     */
    public function activity(int $limit = 50): array
    {
        $messageActivities = Message::whereHas('conversation')
            ->where('sender_type', 'customer')
            ->with('conversation.contact')
            ->latest('created_at')
            ->take($limit)
            ->get()
            ->map(function (Message $m): array {
                $contactName = $m->conversation?->contact->name ?? 'Desconocido';

                return [
                    'id' => 'msg-'.$m->id,
                    'kind' => 'message',
                    'text' => "Nuevo mensaje de {$contactName}",
                    'at' => $m->created_at->toISOString(),
                    'href' => route('inbox'),
                ];
            });

        $contactActivities = Contact::latest('created_at')
            ->take($limit)
            ->get()
            ->map(function (Contact $c): array {
                return [
                    'id' => 'contact-'.$c->id,
                    'kind' => 'contact',
                    'text' => "Nuevo contacto: {$c->name}",
                    'at' => $c->created_at->toISOString(),
                ];
            });

        $dealActivities = Deal::with('stage')
            ->latest('updated_at')
            ->take($limit)
            ->get()
            ->map(function (Deal $d): array {
                $stageName = $d->stage->name ?? 'Sin etapa';

                return [
                    'id' => 'deal-'.$d->id,
                    'kind' => 'deal',
                    'text' => "Negocio \"{$d->title}\" en \"{$stageName}\"",
                    'at' => $d->updated_at->toISOString(),
                ];
            });

        $broadcastActivities = Broadcast::latest('created_at')
            ->take($limit)
            ->get()
            ->map(function (Broadcast $b): array {
                return [
                    'id' => 'bc-'.$b->id,
                    'kind' => 'broadcast',
                    'text' => "Difusión \"{$b->name}\" enviada",
                    'at' => $b->created_at->toISOString(),
                ];
            });

        return $messageActivities
            ->concat($contactActivities)
            ->concat($dealActivities)
            ->concat($broadcastActivities)
            ->sortByDesc('at')
            ->take($limit)
            ->values()
            ->all();
    }
}
