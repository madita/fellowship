<?php

namespace App\Http\Controllers;

use App\Models\Poll\Poll;
use App\Services\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PollController extends Controller
{
    /**
     * Allowed pollable model types.
     * Add new pollable models here as they're created.
     */
    protected array $allowedPollableTypes = [
        'App\\Models\\Page',
        'App\\Models\\Forum\\ForumThread',
        'App\\Models\\Status\\Status',
        'App\\Models\\Ticket\\Ticket',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Poll::with(['creator', 'options', 'votes']);

        // Filter by pollable type and ID if provided
        if ($request->has('pollable_type') && $request->has('pollable_id')) {
            // Validate pollable_type is in allowed list
            if ( ! in_array($request->pollable_type, $this->allowedPollableTypes)) {
                return response()->json([
                    'message' => 'Invalid pollable type',
                ], 422);
            }

            $query->where('pollable_type', $request->pollable_type)
                ->where('pollable_id', $request->pollable_id);
        }

        // Add pagination for better performance
        $polls = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'polls' => $polls->getCollection()->map(function (Poll $poll) use ($request) {
                return $poll->toPayload($request->user());
            }),
            'meta' => [
                'current_page' => $polls->currentPage(),
                'last_page'    => $polls->lastPage(),
                'per_page'     => $polls->perPage(),
                'total'        => $polls->total(),
            ],
        ]);
    }

    public function show(Request $request, Poll $poll): JsonResponse
    {
        $poll->load(['creator', 'options', 'votes']);

        return response()->json([
            'poll' => $poll->toPayload($request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(PollService::normalize($request->all()));

        $validated = $request->validate([
            'pollable_type' => 'required|string|in:' . implode(',', $this->allowedPollableTypes),
            'pollable_id'   => 'required|integer',
        ] + PollService::rules());

        // Verify the pollable model exists
        $pollableClass = $validated['pollable_type'];
        $pollable      = class_exists($pollableClass) ? $pollableClass::find($validated['pollable_id']) : null;
        if ( ! $pollable) {
            return response()->json([
                'message' => 'The specified model does not exist',
            ], 422);
        }

        // Only the author of the thread/status/page/ticket (or an admin) may attach a poll
        if ( ! PollService::canAttach($pollable, $request->user())) {
            return response()->json([
                'message' => 'You do not have permission to attach a poll to this item',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $poll = PollService::create($pollable, $request->user(), $validated);

            DB::commit();

            return response()->json([
                'poll'    => $poll->toPayload($request->user()),
                'message' => 'Poll created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create poll', [
                'user_id' => $request->user()->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create poll. Please try again.',
            ], 500);
        }
    }

    public function update(Request $request, Poll $poll): JsonResponse
    {
        // Only creator can update (or admin)
        if ( ! $poll->canManage($request->user())) {
            return response()->json([
                'message' => 'You do not have permission to update this poll',
            ], 403);
        }

        // Can't update if poll has votes
        if ($poll->votes()->exists()) {
            return response()->json([
                'message' => 'Cannot update poll that already has votes',
            ], 422);
        }

        $request->merge(PollService::normalize($request->all()));

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type'        => 'sometimes|in:single,multiple',
            'anonymous'   => 'sometimes|boolean',
            'closes_at'   => 'nullable|date|after:now',
            'options'     => 'sometimes|array|min:' . PollService::MIN_OPTIONS . '|max:' . PollService::MAX_OPTIONS,
            'options.*'   => 'required_with:options|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $options = $validated['options'] ?? null;
            unset($validated['options']);

            $poll->update($validated);

            if ($options !== null) {
                $poll->options()->delete();
                foreach ($options as $index => $text) {
                    $poll->options()->create([
                        'option_text' => $text,
                        'position'    => $index,
                    ]);
                }
            }

            DB::commit();

            $poll->load(['creator', 'options', 'votes']);

            return response()->json([
                'poll'    => $poll->toPayload($request->user()),
                'message' => 'Poll updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update poll', [
                'poll_id' => $poll->id,
                'user_id' => $request->user()->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to update poll. Please try again.',
            ], 500);
        }
    }

    public function destroy(Request $request, Poll $poll): JsonResponse
    {
        // Only creator can delete (or admin)
        if ( ! $poll->canManage($request->user())) {
            return response()->json([
                'message' => 'You do not have permission to delete this poll',
            ], 403);
        }

        $poll->delete();

        return response()->json([
            'message' => 'Poll deleted successfully',
        ]);
    }
}
