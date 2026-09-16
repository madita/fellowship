<?php

namespace App\Http\Controllers;

use App\Models\Event\Event;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data for the user dashboard widgets that has no natural home elsewhere.
 * The other widgets use the feature endpoints directly (events/upcoming,
 * wiki/recent-changes, account/notification, tickets, forums/recent-threads).
 */
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * The user's saved dashboard layout (null until first customised).
     */
    public function layout(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->dashboard_layout]);
    }

    /**
     * Replace the user's dashboard layout: the placed widgets with their
     * type, optional custom title and colour, size, grid position and
     * per-widget settings. An empty list is a valid (empty) dashboard.
     */
    public function saveLayout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout'              => 'present|array|max:30',
            'layout.*.id'         => 'required|string|max:64',
            'layout.*.type'       => 'required|string|alpha_num|max:32',
            'layout.*.title'      => 'nullable|string|max:100',
            'layout.*.color'      => 'nullable|string|max:32',
            'layout.*.size'       => 'required|string|in:small,medium,large,xl',
            'layout.*.position'   => 'required|array',
            'layout.*.position.x' => 'required|integer|min:0|max:20',
            'layout.*.position.y' => 'required|integer|min:0|max:50',
            'layout.*.config'     => 'nullable|array',
        ]);

        // Widget settings are flat key/value pairs (limit, scope, sort …).
        $layout = array_map(function (array $widget) {
            $widget['config'] = array_filter($widget['config'] ?? [], fn ($value) => is_scalar($value) || $value === null);

            return $widget;
        }, $data['layout']);

        $user                   = $request->user();
        $user->dashboard_layout = array_values($layout);
        $user->save();

        return response()->json(['data' => $user->dashboard_layout]);
    }

    /**
     * Ticket counters for the ticket overview widget. Non-admins see their
     * own tickets (created by or assigned to them); admins see everything,
     * plus the unassigned queue.
     */
    public function tickets(Request $request): JsonResponse
    {
        $user    = $request->user();
        $isAdmin = $user->isAdmin();
        $now     = now();

        $visible = fn () => Ticket::query()->when( ! $isAdmin, function ($q) use ($user) {
            $q->where(function ($w) use ($user) {
                $w->where('created_by_user_id', $user->id)
                    ->orWhere('assigned_to_user_id', $user->id);
            });
        });
        $open = fn () => $visible()->open();

        $byStatus   = $open()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $byPriority = $open()->selectRaw('priority, COUNT(*) as total')->groupBy('priority')->pluck('total', 'priority');

        return response()->json([
            'data' => [
                'is_admin'        => $isAdmin,
                'open'            => $open()->count(),
                'assigned_to_me'  => $open()->where('assigned_to_user_id', $user->id)->count(),
                'created_by_me'   => $open()->where('created_by_user_id', $user->id)->count(),
                'unassigned'      => $isAdmin ? $open()->whereNull('assigned_to_user_id')->count() : null,
                'overdue'         => $open()->where('due_date', '<', $now)->count(),
                'due_this_week'   => $open()->whereBetween('due_date', [$now, $now->copy()->addDays(7)])->count(),
                'resolved_7_days' => $visible()->whereIn('status', ['resolved', 'closed'])->where('updated_at', '>=', $now->copy()->subDays(7))->count(),
                'by_status'       => [
                    'open'        => (int) ($byStatus['open'] ?? 0),
                    'in_progress' => (int) ($byStatus['in_progress'] ?? 0),
                    'pending'     => (int) ($byStatus['pending'] ?? 0),
                ],
                'by_priority' => [
                    'urgent' => (int) ($byPriority['urgent'] ?? 0),
                    'high'   => (int) ($byPriority['high'] ?? 0),
                    'normal' => (int) ($byPriority['normal'] ?? 0),
                    'low'    => (int) ($byPriority['low'] ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Live community counters plus the user's own open tickets.
     */
    public function stats(Request $request): JsonResponse
    {
        $user  = $request->user();
        $today = now()->toDateString();

        $myOpenTickets = Ticket::open()
            ->where(function ($q) use ($user) {
                $q->where('created_by_user_id', $user->id)
                    ->orWhere('assigned_to_user_id', $user->id);
            })
            ->count();

        return response()->json([
            'data' => [
                'members'         => User::count(),
                'upcoming_events' => Event::whereDate('startDate', '>=', $today)->count(),
                'wiki_pages'      => Wiki::where('wikiable_type', Page::class)->whereNull('status')->approved()->count(),
                'forum_threads'   => ForumThread::count(),
                'forum_posts'     => ForumPost::count(),
                'my_open_tickets' => $myOpenTickets,
            ],
        ]);
    }
}
