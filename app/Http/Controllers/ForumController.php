<?php

namespace App\Http\Controllers;

use App\Models\Forum\Forum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForumController extends Controller
{
    /**
     * Get all forums with statistics.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $forums = Forum::whereNull('parent_id')
            ->with(['children', 'lastPost.author'])
            ->orderBy('position')
            ->get()
            ->filter(function ($forum) use ($user) {
                return $forum->canAccess($user);
            })
            ->values();

        return response()->json($forums);
    }

    /**
     * Get a specific forum with its threads.
     */
    public function show(string $slug): JsonResponse
    {
        $user = Auth::user();

        $forum = Forum::where('slug', $slug)
            ->with(['parent', 'children'])
            ->firstOrFail();

        if (!$forum->canAccess($user)) {
            abort(403, 'You do not have permission to access this forum.');
        }

        // Get threads with pagination
        $threads = $forum->threads()
            ->with(['author', 'lastPostUser'])
            ->paginate(20);

        return response()->json([
            'forum' => $forum,
            'threads' => $threads,
        ]);
    }

    /**
     * Create a new forum (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:forums,id',
            'position' => 'nullable|integer',
            'is_private' => 'boolean',
        ]);

        $forum = Forum::create($validated);

        return response()->json($forum, 201);
    }

    /**
     * Update a forum (admin only).
     */
    public function update(Request $request, Forum $forum): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:forums,id',
            'position' => 'integer',
            'is_private' => 'boolean',
            'is_locked' => 'boolean',
        ]);

        $forum->update($validated);

        return response()->json($forum);
    }

    /**
     * Delete a forum (admin only).
     */
    public function destroy(Forum $forum): JsonResponse
    {
        $this->authorize('admin');

        $forum->delete();

        return response()->json(['message' => 'Forum deleted successfully']);
    }
}
