<?php

namespace App\Http\Controllers\Poll;

use App\Http\Controllers\Controller;
use App\Models\Poll\Poll;
use App\Models\Poll\PollOption;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PollVoteController extends Controller
{
    public function vote(Request $request, Poll $poll): JsonResponse
    {
        // Check if poll is open
        if ( ! $poll->is_open) {
            return response()->json([
                'message' => 'This poll is closed',
            ], 422);
        }

        $validated = $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'integer|exists:poll_options,id',
        ]);

        $user = $request->user();

        // Validate option IDs belong to this poll
        $validOptions = $poll->options()->whereIn('id', $validated['option_ids'])->pluck('id')->toArray();
        if (count($validOptions) !== count($validated['option_ids'])) {
            return response()->json([
                'message' => 'Invalid option IDs - options must belong to this poll',
            ], 422);
        }

        // For single-choice polls, only allow one option
        if ($poll->type === 'single' && count($validated['option_ids']) > 1) {
            return response()->json([
                'message' => 'This poll only allows a single choice',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Clear whatever this member picked before: a vote may be
            // changed while the poll is open, and for a single-choice poll
            // the new pick must replace the old one rather than join it.
            $this->clearVotes($poll, $user->id);

            foreach ($validated['option_ids'] as $optionId) {
                Vote::create([
                    'user_id'       => $user->id,
                    'voteable_type' => PollOption::class,
                    'voteable_id'   => $optionId,
                ]);
            }

            DB::commit();

            // Reload poll with fresh data
            $poll->load(['creator', 'options', 'votes']);

            return response()->json([
                'message' => 'Vote recorded successfully',
                'poll'    => $poll->toPayload($user),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record vote', [
                'poll_id' => $poll->id,
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to record vote. Please try again.',
            ], 500);
        }
    }

    public function unvote(Request $request, Poll $poll): JsonResponse
    {
        // Check if poll is still open for unvoting
        if ( ! $poll->is_open) {
            return response()->json([
                'message' => 'Cannot remove vote from a closed poll',
            ], 422);
        }

        $user = $request->user();

        $deletedCount = $this->clearVotes($poll, $user->id);

        if ($deletedCount === 0) {
            return response()->json([
                'message' => 'You have not voted in this poll',
            ], 422);
        }

        // Reload poll with fresh data
        $poll->load(['creator', 'options', 'votes']);

        return response()->json([
            'message' => 'Vote removed successfully',
            'poll'    => $poll->toPayload($user),
        ]);
    }

    /**
     * Drop every vote this member has cast in this poll, whichever options
     * they picked. Returns how many there were.
     *
     * Votes point at the option chosen rather than the poll, so "this
     * member's votes in this poll" is scoped by the poll's own option ids.
     */
    private function clearVotes(Poll $poll, int $userId): int
    {
        return Vote::where('user_id', $userId)
            ->where('voteable_type', PollOption::class)
            ->whereIn('voteable_id', $poll->options()->select('id'))
            ->delete();
    }
}
