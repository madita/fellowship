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
