<?php

namespace App\Http\Controllers;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketTag;
use App\Models\Ticket\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * Get all bugs with filtering and sorting.
     */
    public function bugs(Request $request): JsonResponse
    {
        $query = Ticket::with(['ticketType', 'creator', 'tags'])
            ->public()
            ->bugs()
            ->withCount(['votes', 'comments', 'watchers']);

        // Apply filters
        if ($request->filled('status')) {
            $query->bgaStatus($request->status);
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort', 'popular');
        match ($sortBy) {
            'popular' => $query->popular(),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            default => $query->popular(),
        };

        $tickets = $query->paginate(20);

        // Add user's vote status
        if (Auth::check()) {
            $tickets->getCollection()->transform(function ($ticket) {
                $ticket->user_has_voted = $ticket->hasVotedBy(Auth::user());
                $ticket->user_is_watching = $ticket->isWatchedBy(Auth::user());
                return $ticket;
            });
        }

        return response()->json($tickets);
    }

    /**
     * Get all feature requests with filtering and sorting.
     */
    public function features(Request $request): JsonResponse
    {
        $query = Ticket::with(['ticketType', 'creator', 'tags'])
            ->public()
            ->features()
            ->withCount(['votes', 'comments', 'watchers']);

        // Apply filters
        if ($request->filled('status')) {
            $query->bgaStatus($request->status);
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort', 'popular');
        match ($sortBy) {
            'popular' => $query->popular(),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            default => $query->popular(),
        };

        $tickets = $query->paginate(20);

        // Add user's vote status
        if (Auth::check()) {
            $tickets->getCollection()->transform(function ($ticket) {
                $ticket->user_has_voted = $ticket->hasVotedBy(Auth::user());
                $ticket->user_is_watching = $ticket->isWatchedBy(Auth::user());
                return $ticket;
            });
        }

        return response()->json($tickets);
    }

    /**
     * Get a single ticket with all details.
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        // Only show public tickets or if user is admin
        if (!$ticket->isPublic() && (!Auth::check() || !Auth::user()->isAdmin())) {
            abort(404);
        }

        $ticket->load([
            'ticketType',
            'creator',
            'assignee',
            'tags',
            'duplicateOf',
            'duplicates',
            'comments' => function ($query) {
                $query->where('is_internal', false)
                    ->with('user')
                    ->latest();
            },
        ]);

        $ticket->loadCount(['votes', 'watchers', 'comments']);

        // Add user interactions
        if (Auth::check()) {
            $ticket->user_has_voted = $ticket->hasVotedBy(Auth::user());
            $ticket->user_is_watching = $ticket->isWatchedBy(Auth::user());
        }

        return response()->json($ticket);
    }

    /**
     * Create a new bug report.
     */
    public function createBug(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $bugType = TicketType::where('slug', 'bug')->firstOrFail();

        $ticket = Ticket::create([
            'ticket_type_id' => $bugType->id,
            'created_by_user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'open',
            'bga_status' => 'reported',
            'priority' => 'normal',
            'is_public' => true,
        ]);

        // Auto-watch own ticket
        $ticket->toggleWatch(Auth::user());

        return response()->json([
            'message' => 'Bug report submitted successfully!',
            'ticket' => $ticket->load('ticketType', 'creator'),
        ], 201);
    }

    /**
     * Create a new feature request.
     */
    public function createFeature(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $featureType = TicketType::where('slug', 'feature')->firstOrFail();

        $ticket = Ticket::create([
            'ticket_type_id' => $featureType->id,
            'created_by_user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'status' => 'open',
            'bga_status' => 'reported',
            'priority' => 'normal',
            'is_public' => true,
        ]);

        // Auto-watch own ticket
        $ticket->toggleWatch(Auth::user());

        return response()->json([
            'message' => 'Feature request submitted successfully!',
            'ticket' => $ticket->load('ticketType', 'creator'),
        ], 201);
    }

    /**
     * Toggle vote on a ticket.
     */
    public function toggleVote(Ticket $ticket): JsonResponse
    {
        if (!$ticket->isPublic() && (!Auth::check() || !Auth::user()->isAdmin())) {
            abort(404);
        }

        $voted = $ticket->toggleVote(Auth::user());

        return response()->json([
            'voted' => $voted,
            'votes_count' => $ticket->votes_count,
        ]);
    }

    /**
     * Toggle watching a ticket.
     */
    public function toggleWatch(Ticket $ticket): JsonResponse
    {
        if (!$ticket->isPublic() && (!Auth::check() || !Auth::user()->isAdmin())) {
            abort(404);
        }

        $watching = $ticket->toggleWatch(Auth::user());

        return response()->json([
            'watching' => $watching,
            'watchers_count' => $ticket->watchers_count,
        ]);
    }

    /**
     * Add a comment to a ticket.
     */
    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        if (!$ticket->isPublic() && (!Auth::check() || !Auth::user()->isAdmin())) {
            abort(404);
        }

        $request->validate([
            'comment' => 'required|string',
        ]);

        $comment = $ticket->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $request->comment,
            'is_internal' => false,
            'is_official' => Auth::user()->isAdmin(),
        ]);

        // Auto-watch when commenting
        if (!$ticket->isWatchedBy(Auth::user())) {
            $ticket->toggleWatch(Auth::user());
        }

        return response()->json([
            'message' => 'Comment added successfully!',
            'comment' => $comment->load('user'),
        ], 201);
    }

    /**
     * Get all available tags.
     */
    public function tags(): JsonResponse
    {
        $tags = TicketTag::active()
            ->withCount('tickets')
            ->orderBy('name')
            ->get();

        return response()->json($tags);
    }

    /**
     * Get feedback stats.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_bugs' => Ticket::public()->bugs()->count(),
            'total_features' => Ticket::public()->features()->count(),
            'bugs_by_status' => Ticket::public()->bugs()
                ->selectRaw('bga_status, count(*) as count')
                ->groupBy('bga_status')
                ->pluck('count', 'bga_status'),
            'features_by_status' => Ticket::public()->features()
                ->selectRaw('bga_status, count(*) as count')
                ->groupBy('bga_status')
                ->pluck('count', 'bga_status'),
            'top_voted_bugs' => Ticket::public()->bugs()
                ->popular()
                ->limit(5)
                ->get(['id', 'title', 'votes_count']),
            'top_voted_features' => Ticket::public()->features()
                ->popular()
                ->limit(5)
                ->get(['id', 'title', 'votes_count']),
        ]);
    }
}
