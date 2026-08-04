<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\Forum\ForumThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ForumSubscriptionController extends Controller
{
    /**
     * Subscribe to a thread.
     */
    public function store(ForumThread $thread): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $thread->subscribe($user);

        return response()->json([
            'is_subscribed' => true,
            'message' => 'Subscribed to thread',
        ]);
    }

    /**
     * Unsubscribe from a thread.
     */
    public function destroy(ForumThread $thread): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $thread->unsubscribe($user);

        return response()->json([
            'is_subscribed' => false,
            'message' => 'Unsubscribed from thread',
        ]);
    }
}
