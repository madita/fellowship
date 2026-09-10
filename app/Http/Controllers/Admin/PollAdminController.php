<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Poll\Poll;
use App\Models\Poll\PollVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin overview of every poll in the system: list with filters, a few
 * numbers, and closing/reopening/deleting polls regardless of creator.
 */
class PollAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'   => 'nullable|in:open,closed',
            'type'     => 'nullable|in:single,multiple',
            'pollable' => 'nullable|in:' . implode(',', array_keys(Poll::POLLABLE_TYPES)),
            'search'   => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page'     => 'nullable|integer|min:1',
        ]);

        $query = Poll::with(['creator', 'options', 'votes', 'pollable']);

        if (($validated['status'] ?? null) === 'open') {
            $query->open();
        } elseif (($validated['status'] ?? null) === 'closed') {
            $query->closed();
        }

        if ( ! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if ( ! empty($validated['pollable'])) {
            $query->where('pollable_type', Poll::POLLABLE_TYPES[$validated['pollable']]);
        }

        if ( ! empty($validated['search'])) {
            $term = '%' . $validated['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        $polls = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20);

        $user = $request->user();

        return response()->json([
            'data' => $polls->getCollection()->map(function (Poll $poll) use ($user) {
                return $poll->toPayload($user) + ['pollable' => $poll->pollableSummary()];
            })->values(),
            'meta' => [
                'current_page' => $polls->currentPage(),
                'last_page'    => $polls->lastPage(),
                'per_page'     => $polls->perPage(),
                'total'        => $polls->total(),
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $weekAgo = now()->subDays(7);

        $byType = Poll::query()
            ->selectRaw('pollable_type, count(*) as aggregate')
            ->groupBy('pollable_type')
            ->pluck('aggregate', 'pollable_type');

        $mostVoted = Poll::with('pollable')
            ->withCount('votes')
            ->orderByDesc('votes_count')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Poll $poll) => [
                'id'          => $poll->id,
                'title'       => $poll->title,
                'total_votes' => (int) $poll->votes_count,
                'pollable'    => $poll->pollableSummary(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'total'      => Poll::count(),
                'open'       => Poll::open()->count(),
                'closed'     => Poll::closed()->count(),
                'votes'      => PollVote::count(),
                'votes_7d'   => PollVote::where('created_at', '>=', $weekAgo)->count(),
                'polls_7d'   => Poll::where('created_at', '>=', $weekAgo)->count(),
                'by_type'    => collect(Poll::POLLABLE_TYPES)->map(fn ($class) => (int) ($byType[$class] ?? 0)),
                'most_voted' => $mostVoted,
            ],
        ]);
    }

    public function close(Request $request, Poll $poll): JsonResponse
    {
        $poll->update(['closes_at' => now()]);

        return response()->json([
            'message' => 'Poll closed',
            'poll'    => $poll->fresh()->toPayload($request->user()),
        ]);
    }

    public function reopen(Request $request, Poll $poll): JsonResponse
    {
        $poll->update(['closes_at' => null]);

        return response()->json([
            'message' => 'Poll reopened',
            'poll'    => $poll->fresh()->toPayload($request->user()),
        ]);
    }

    public function destroy(Poll $poll): JsonResponse
    {
        $poll->delete();

        return response()->json([
            'message' => 'Poll deleted',
        ]);
    }
}
