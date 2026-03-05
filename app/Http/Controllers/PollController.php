<?php

namespace App\Http\Controllers;

use App\Models\Poll\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PollController extends Controller
{
    protected const ALLOWED_POLLABLE_TYPES = [
        'page' => \App\Models\Page::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Poll::with(['creator', 'options', 'votes']);

        // Filter by pollable type and ID if provided
        if ($request->has('pollable_type') && $request->has('pollable_id')) {
            $pollableType = self::ALLOWED_POLLABLE_TYPES[$request->pollable_type] ?? null;
            if (!$pollableType) {
                return response()->json([
                    'message' => 'Invalid pollable type',
                ], 422);
            }
            $query->where('pollable_type', $pollableType)
                ->where('pollable_id', (int) $request->pollable_id);
        }

        $polls = $query->latest()->get();

        return response()->json([
            'polls' => $polls->map(function ($poll) use ($request) {
                return $this->formatPoll($poll, $request->user());
            }),
        ]);
    }

    public function show(Request $request, Poll $poll): JsonResponse
    {
        $poll->load(['creator', 'options', 'votes']);

        return response()->json([
            'poll' => $this->formatPoll($poll, $request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pollable_type' => ['required', 'string', Rule::in(array_keys(self::ALLOWED_POLLABLE_TYPES))],
            'pollable_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => ['required', Rule::in(['single', 'multiple'])],
            'anonymous' => 'boolean',
            'closes_at' => 'nullable|date|after:now',
            'options' => 'required|array|min:2|max:50',
            'options.*.option_text' => 'required|string|max:255',
        ]);

        $pollableClass = self::ALLOWED_POLLABLE_TYPES[$validated['pollable_type']];

        if (!$pollableClass::where('id', $validated['pollable_id'])->exists()) {
            return response()->json([
                'message' => 'The specified pollable entity does not exist',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $poll = Poll::create([
                'pollable_type' => $pollableClass,
                'pollable_id' => $validated['pollable_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'type' => $validated['type'],
                'anonymous' => $validated['anonymous'] ?? false,
                'closes_at' => $validated['closes_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['options'] as $index => $option) {
                $poll->options()->create([
                    'option_text' => $option['option_text'],
                    'position' => $index,
                ]);
            }

            DB::commit();

            $poll->load(['creator', 'options']);

            return response()->json([
                'poll' => $this->formatPoll($poll, $request->user()),
                'message' => 'Poll created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create poll', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create poll',
            ], 500);
        }
    }

    public function update(Request $request, Poll $poll): JsonResponse
    {
        // Only creator can update
        if ($poll->created_by !== $request->user()->id) {
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

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => ['sometimes', Rule::in(['single', 'multiple'])],
            'anonymous' => 'sometimes|boolean',
            'closes_at' => 'nullable|date|after:now',
            'options' => 'sometimes|array|min:2|max:50',
            'options.*.option_text' => 'required_with:options|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $poll->update($validated);

            if (isset($validated['options'])) {
                $poll->options()->delete();
                foreach ($validated['options'] as $index => $option) {
                    $poll->options()->create([
                        'option_text' => $option['option_text'],
                        'position' => $index,
                    ]);
                }
            }

            DB::commit();

            $poll->load(['creator', 'options']);

            return response()->json([
                'poll' => $this->formatPoll($poll, $request->user()),
                'message' => 'Poll updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update poll', ['poll_id' => $poll->id, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to update poll',
            ], 500);
        }
    }

    public function destroy(Request $request, Poll $poll): JsonResponse
    {
        // Only creator can delete
        if ($poll->created_by !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this poll',
            ], 403);
        }

        $poll->delete();

        return response()->json([
            'message' => 'Poll deleted successfully',
        ]);
    }

    protected function formatPoll(Poll $poll, $user): array
    {
        return [
            'id' => $poll->id,
            'pollable_type' => $poll->pollable_type,
            'pollable_id' => $poll->pollable_id,
            'title' => $poll->title,
            'description' => $poll->description,
            'type' => $poll->type,
            'anonymous' => $poll->anonymous,
            'closes_at' => $poll->closes_at?->toIso8601String(),
            'is_open' => $poll->is_open,
            'total_votes' => $poll->total_votes,
            'creator' => [
                'id' => $poll->creator->id,
                'name' => $poll->creator->name,
            ],
            'options' => $poll->options->map(function ($option) {
                return [
                    'id' => $option->id,
                    'option_text' => $option->option_text,
                    'position' => $option->position,
                ];
            }),
            'results' => $poll->results(),
            'user_votes' => $poll->userVotes($user),
            'has_voted' => $poll->hasVoted($user),
            'created_at' => $poll->created_at->toIso8601String(),
            'updated_at' => $poll->updated_at->toIso8601String(),
        ];
    }
}
