<?php

namespace App\Http\Controllers\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxVersion;
use App\Models\User;
use App\Notifications\SandboxNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SandboxController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['show']);
    }

    /**
     * List sandboxes accessible by the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $sandboxes = Sandbox::accessibleBy($user)
            ->with(['owner:id,username', 'lastEditor:id,username'])
            ->withCount('collaborators')
            ->orderBy('last_edited_at', 'desc')
            ->paginate(20);

        return response()->json($sandboxes);
    }

    /**
     * Create a new sandbox.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'visibility' => 'nullable|in:private,members,public',
        ]);

        $sandbox = Sandbox::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => auth()->id(),
            'visibility' => $validated['visibility'] ?? 'private',
            'settings' => [
                'allowComments' => true,
                'showCursors' => true,
                'autoSave' => true,
            ],
        ]);

        $sandbox->load('owner:id,username');

        return response()->json([
            'message' => __('messages.sandbox.created'),
            'sandbox' => $sandbox,
        ], 201);
    }

    /**
     * Show a sandbox (with collaboration state).
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $sandbox = Sandbox::where('uuid', $uuid)
            ->with(['owner:id,username', 'collaborators:users.id,username'])
            ->firstOrFail();

        $user = auth()->user();

        // Check access
        if ($sandbox->visibility === 'private') {
            if (!$user || !$sandbox->canView($user)) {
                return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
            }
        }

        $response = [
            'sandbox' => $sandbox,
            'canEdit' => $user ? $sandbox->canEdit($user) : false,
            'canManage' => $user ? $sandbox->canManage($user) : false,
            'role' => $user ? $sandbox->getUserRole($user) : 'guest',
        ];

        return response()->json($response);
    }

    /**
     * Update sandbox metadata.
     */
    public function update(Request $request, Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canManage($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'visibility' => 'sometimes|in:private,members,public',
            'settings' => 'sometimes|array',
        ]);

        $sandbox->update($validated);

        return response()->json([
            'message' => __('messages.sandbox.updated'),
            'sandbox' => $sandbox->fresh(['owner:id,username']),
        ]);
    }

    /**
     * Delete a sandbox.
     */
    public function destroy(Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if ($sandbox->user_id !== $user->id) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        $sandbox->delete();

        return response()->json(['message' => __('messages.sandbox.deleted')]);
    }

    /**
     * Get sandbox content.
     */
    public function getState(Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        return response()->json([
            'content' => $sandbox->content,
            'lastEditedAt' => $sandbox->last_edited_at,
            'lastEditedBy' => $sandbox->lastEditor?->username,
        ]);
    }

    /**
     * Save sandbox content.
     */
    public function saveState(Request $request, Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canEdit($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string',
            'createVersion' => 'sometimes|boolean',
            'versionTitle' => 'sometimes|string|max:255',
        ]);

        // Create version snapshot if requested
        if ($request->boolean('createVersion')) {
            SandboxVersion::create([
                'sandbox_id' => $sandbox->id,
                'user_id' => $user->id,
                'title' => $validated['versionTitle'] ?? 'Auto-save',
                'content' => $sandbox->content,
            ]);
        }

        $sandbox->update([
            'content' => $validated['content'],
            'last_edited_at' => now(),
            'last_edited_by' => $user->id,
        ]);

        return response()->json(['message' => __('messages.sandbox.saved')]);
    }

    /**
     * Add a collaborator.
     */
    public function addCollaborator(Request $request, Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canManage($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'sometimes|in:viewer,editor,admin',
        ]);

        if ($validated['user_id'] == $sandbox->user_id) {
            return response()->json(['error' => __('messages.sandbox.cannot_add_owner')], 400);
        }

        $role = $validated['role'] ?? 'editor';

        $sandbox->collaborators()->syncWithoutDetaching([
            $validated['user_id'] => [
                'role' => $role,
                'invited_at' => now(),
                'accepted_at' => now(),
            ],
        ]);

        // Notify the invited user
        $invitedUser = User::find($validated['user_id']);
        if ($invitedUser) {
            $invitedUser->notify(new SandboxNotification($sandbox, 'shared', $user, [
                'role' => $role,
            ]));
        }

        return response()->json([
            'message' => __('messages.sandbox.collaborator_added'),
            'collaborators' => $sandbox->collaborators()->get(['users.id', 'username']),
        ]);
    }

    /**
     * Remove a collaborator.
     */
    public function removeCollaborator(Sandbox $sandbox, User $collaborator): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canManage($user) && $user->id !== $collaborator->id) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        $sandbox->collaborators()->detach($collaborator->id);

        // Notify the removed user (unless they removed themselves)
        if ($collaborator->id !== $user->id) {
            $collaborator->notify(new SandboxNotification($sandbox, 'removed', $user));
        }

        return response()->json([
            'message' => __('messages.sandbox.collaborator_removed'),
        ]);
    }

    /**
     * Accept collaboration invite.
     */
    public function acceptInvite(Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        $collaborator = $sandbox->collaborators()
            ->where('user_id', $user->id)
            ->whereNull('accepted_at')
            ->first();

        if (!$collaborator) {
            return response()->json(['error' => __('messages.sandbox.no_invite')], 404);
        }

        $sandbox->collaborators()->updateExistingPivot($user->id, [
            'accepted_at' => now(),
        ]);

        // Notify the sandbox owner
        $sandbox->owner->notify(new SandboxNotification($sandbox, 'invite_accepted', $user));

        return response()->json(['message' => __('messages.sandbox.invite_accepted')]);
    }

    /**
     * Get version history.
     */
    public function versions(Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        $versions = $sandbox->versions()
            ->with('user:id,username')
            ->paginate(20);

        return response()->json($versions);
    }

    /**
     * Restore a version.
     */
    public function restoreVersion(Sandbox $sandbox, SandboxVersion $version): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canEdit($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        // Save current state as a version first
        SandboxVersion::create([
            'sandbox_id' => $sandbox->id,
            'user_id' => $user->id,
            'title' => 'Before restore',
            'content' => $sandbox->content,
        ]);

        // Restore the selected version (clear yjs_state so Yjs doc reinitializes from HTML)
        $sandbox->update([
            'content' => $version->content,
            'yjs_state' => null,
            'last_edited_at' => now(),
            'last_edited_by' => $user->id,
        ]);

        return response()->json([
            'message' => __('messages.sandbox.version_restored'),
            'content' => $version->content,
        ]);
    }
}
