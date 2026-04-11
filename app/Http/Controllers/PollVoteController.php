<?php

namespace App\Http\Controllers;

use App\Models\Poll\Poll;
use App\Models\Poll\PollVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollVoteController extends Controller
{
    public function vote(Request $request, Poll $poll): JsonResponse
    {
        // Check if poll is open
        if (!$poll->is_open) {
            return response()->json([
                'message' => 'This poll is closed',
            ], 422);
        }

        $validated = $request->validate([
            'option_ids' => 'required|array',
            'option_ids.*' => 'integer|exists:poll_options,id',
        ]);

        $user = $request->user();

        // Validate option IDs belong to this poll
        $validOptions = $poll->options()->whereIn('id', $validated['option_ids'])->pluck('id')->toArray();
        if (count($validOptions) !== count($validated['option_ids'])) {
            return response()->json([
                'message' => 'Invalid option IDs',
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
            // Remove existing votes from this user for this poll
            PollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->delete();

            // Create new votes
            foreach ($validated['option_ids'] as $optionId) {
                PollVote::create([
                    'poll_id' => $poll->id,
                    'poll_option_id' => $optionId,
                    'user_id' => $user->id,
                ]);
            }

            DB::commit();

            // Reload poll with fresh data
            $poll->load(['creator', 'options', 'votes']);

            return response()->json([
                'message' => 'Vote recorded successfully',
                'poll' => [
                    'id' => $poll->id,
                    'total_votes' => $poll->total_votes,
                    'results' => $poll->results(),
                    'user_votes' => $poll->userVotes($user),
                    'has_voted' => $poll->hasVoted($user),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to record vote',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function unvote(Request $request, Poll $poll): JsonResponse
    {
        $user = $request->user();

        $deletedCount = PollVote::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($deletedCount === 0) {
            return response()->json([
                'message' => 'You have not voted in this poll',
            ], 422);
        }

        // Reload poll with fresh data
        $poll->load(['creator', 'options', 'votes']);

        return response()->json([
            'message' => 'Vote removed successfully',
            'poll' => [
                'id' => $poll->id,
                'total_votes' => $poll->total_votes,
                'results' => $poll->results(),
                'user_votes' => $poll->userVotes($user),
                'has_voted' => $poll->hasVoted($user),
            ],
        ]);
    }
}
