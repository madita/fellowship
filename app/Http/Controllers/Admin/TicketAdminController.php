<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Numbers for the overview of the ticket admin: queues, breakdowns of the
 * open tickets, created vs. resolved per day and the tickets that need
 * attention first.
 */
class TicketAdminController extends Controller
{
    /** Days shown in the created / resolved trend. */
    private const TREND_DAYS = 30;

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    public function stats(): JsonResponse
    {
        $now   = now();
        $open  = fn () => Ticket::query()->open();
        $since = $now->copy()->subDays(self::TREND_DAYS - 1)->startOfDay();

        // "Done" is the moment a ticket was resolved, or closed without being resolved first
        $done = Ticket::query()
            ->where(function ($q) use ($since) {
                $q->where('resolved_at', '>=', $since)
                    ->orWhere(fn ($q) => $q->whereNull('resolved_at')->where('closed_at', '>=', $since));
            })
            ->get(['created_at', 'resolved_at', 'closed_at'])
            ->map(fn (Ticket $ticket) => [
                'created_at' => $ticket->created_at,
                'done_at'    => $ticket->resolved_at ?? $ticket->closed_at,
            ]);

        $created = Ticket::query()->where('created_at', '>=', $since)->pluck('created_at');

        return response()->json([
            'data' => [
                'open'       => $open()->count(),
                'unassigned' => $open()->whereNull('assigned_to_user_id')->count(),
                'overdue'    => $open()->where('due_date', '<', $now)->count(),
                'urgent'     => $open()->where('priority', 'urgent')->count(),

                'created_7d'       => $this->countBetween($created, $now->copy()->subDays(7)),
                'created_prev_7d'  => $this->countBetween($created, $now->copy()->subDays(14), $now->copy()->subDays(7)),
                'resolved_7d'      => $this->countBetween($done->pluck('done_at'), $now->copy()->subDays(7)),
                'resolved_prev_7d' => $this->countBetween($done->pluck('done_at'), $now->copy()->subDays(14), $now->copy()->subDays(7)),

                // Average hours from creation to done, over the trend window
                'avg_resolution_hours' => $done->isEmpty() ? null : round(
                    $done->avg(fn ($ticket) => $ticket['created_at']->diffInMinutes($ticket['done_at']) / 60),
                    1
                ),

                'by_status'    => $this->countsBy(Ticket::query(), 'status', Ticket::STATUSES),
                'by_priority'  => $this->countsBy($open(), 'priority', ['urgent', 'high', 'normal', 'low']),
                'by_type'      => $this->openByType($open()),
                'by_assignee'  => $this->openByAssignee($open()),
                'trend'        => $this->trend($created, $done->pluck('done_at'), $since),
                'attention'    => $this->needsAttention($open(), $now),
                'top_feedback' => $this->topFeedback($open()),
            ],
        ]);
    }

    /**
     * Count of every value of a column, zero for the values without tickets.
     */
    private function countsBy($query, string $column, array $values): array
    {
        $counts = $query->selectRaw("{$column}, count(*) as aggregate")->groupBy($column)->pluck('aggregate', $column);

        return collect($values)->mapWithKeys(fn ($value) => [$value => (int) ($counts[$value] ?? 0)])->all();
    }

    /**
     * Dates from $from (inclusive) up to $to (exclusive), or up to now without $to.
     */
    private function countBetween($dates, Carbon $from, ?Carbon $to = null): int
    {
        return $dates->filter(fn (Carbon $date) => $date->gte($from) && ($to === null || $date->lt($to)))->count();
    }

    /**
     * Open tickets per type: every active type, plus inactive ones that still have open tickets.
     */
    private function openByType($query): array
    {
        $counts = $query->selectRaw('ticket_type_id, count(*) as aggregate')->groupBy('ticket_type_id')->pluck('aggregate', 'ticket_type_id');

        return TicketType::orderBy('position')->get()
            ->filter(fn (TicketType $type) => $type->is_active || isset($counts[$type->id]))
            ->map(fn (TicketType $type) => [
                'slug'  => $type->slug,
                'name'  => $type->name,
                'icon'  => $type->icon,
                'color' => $type->color,
                'open'  => (int) ($counts[$type->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Open tickets per assignee, busiest first (at most six).
     */
    private function openByAssignee($query): array
    {
        $counts = $query->whereNotNull('assigned_to_user_id')
            ->selectRaw('assigned_to_user_id, count(*) as aggregate')
            ->groupBy('assigned_to_user_id')
            ->orderByDesc('aggregate')
            ->limit(6)
            ->pluck('aggregate', 'assigned_to_user_id');

        $users = User::whereIn('id', $counts->keys())->get(['id', 'username', 'name'])->keyBy('id');

        return $counts->map(fn ($count, $userId) => [
            'id'       => (int) $userId,
            'username' => $users->get($userId)?->username,
            'name'     => $users->get($userId)?->name,
            'open'     => (int) $count,
        ])->values()->all();
    }

    /**
     * Tickets created and done per day, oldest day first.
     */
    private function trend($created, $done, Carbon $since): array
    {
        $createdPerDay = $created->countBy(fn (Carbon $date) => $date->toDateString());
        $donePerDay    = $done->countBy(fn (Carbon $date) => $date->toDateString());

        return collect(range(0, self::TREND_DAYS - 1))
            ->map(function (int $offset) use ($since, $createdPerDay, $donePerDay) {
                $day = $since->copy()->addDays($offset)->toDateString();

                return [
                    'date'     => $day,
                    'created'  => (int) ($createdPerDay[$day] ?? 0),
                    'resolved' => (int) ($donePerDay[$day] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Open tickets that are overdue or urgent / high priority: overdue first,
     * then by priority, then the oldest.
     */
    private function needsAttention($query, Carbon $now): array
    {
        return $query->with(['ticketType', 'assignee'])
            ->where(function ($q) use ($now) {
                $q->where('due_date', '<', $now)->orWhereIn('priority', ['urgent', 'high']);
            })
            ->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
            ->oldest()
            ->limit(6)
            ->get()
            ->map(fn (Ticket $ticket) => [
                ...$this->summary($ticket),
                'priority' => $ticket->priority,
                'due_date' => $ticket->due_date,
                'overdue'  => $ticket->due_date !== null && $ticket->due_date->lt($now),
                'assignee' => $ticket->assignee?->username,
            ])
            ->all();
    }

    /**
     * Open public bug reports and feature requests with the most votes.
     */
    private function topFeedback($query): array
    {
        return $query->feedback()
            ->where('is_public', true)
            ->with('ticketType')
            ->whereHas('votes')
            ->withCount('votes')
            ->orderByDesc('votes_count')
            ->oldest()
            ->limit(5)
            ->get()
            ->map(fn (Ticket $ticket) => [
                ...$this->summary($ticket),
                'votes_count' => (int) $ticket->votes_count,
            ])
            ->all();
    }

    private function summary(Ticket $ticket): array
    {
        return [
            'id'         => $ticket->id,
            'title'      => $ticket->title,
            'status'     => $ticket->status,
            'created_at' => $ticket->created_at,
            'type'       => $ticket->ticketType ? [
                'slug'  => $ticket->ticketType->slug,
                'name'  => $ticket->ticketType->name,
                'icon'  => $ticket->ticketType->icon,
                'color' => $ticket->ticketType->color,
            ] : null,
        ];
    }
}
