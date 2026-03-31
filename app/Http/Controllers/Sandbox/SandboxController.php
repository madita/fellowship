<?php

namespace App\Http\Controllers\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\Revision;
use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxVersion;
use App\Models\Setting;
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
     * Get the sandbox limits for a user based on their highest role.
     */
    protected function getUserLimits(User $user): array
    {
        $defaults = ['max_sandboxes' => 0, 'max_collaborators' => 0, 'max_versions' => 0];
        $keys = ['max_sandboxes', 'max_collaborators', 'max_versions'];

        $roleLimits = Setting::get('sandbox_role_limits', '{}');
        if (is_string($roleLimits)) {
            $roleLimits = json_decode($roleLimits, true) ?: [];
        }

        $userRoles = $user->getRoleNames()->toArray();
        $matchedRoles = array_intersect($userRoles, array_keys($roleLimits));

        // If user has no roles with configured limits, use config defaults
        if (empty($matchedRoles)) {
            $configDefaults = config('sandbox.default_role_limits.user', $defaults);
            return array_merge($defaults, $configDefaults);
        }

        // Start with null (unresolved) and merge across matched roles
        // picking the most permissive value (0 = unlimited wins over any number)
        $resolved = ['max_sandboxes' => null, 'max_collaborators' => null, 'max_versions' => null];

        foreach ($matchedRoles as $role) {
            foreach ($keys as $key) {
                $roleValue = (int) ($roleLimits[$role][$key] ?? 0);
                $currentValue = $resolved[$key];

                if ($currentValue === null) {
                    // First matched role — take its value directly
                    $resolved[$key] = $roleValue;
                } elseif ($roleValue === 0 || $currentValue === 0) {
                    // 0 means unlimited — most permissive wins
                    $resolved[$key] = 0;
                } else {
                    // Both are limited — take the higher (more permissive) value
                    $resolved[$key] = max($currentValue, $roleValue);
                }
            }
        }

        // Replace any still-null values with defaults (shouldn't happen, but safety)
        foreach ($keys as $key) {
            if ($resolved[$key] === null) {
                $resolved[$key] = 0;
            }
        }

        return $resolved;
    }

    /**
     * List sandboxes accessible by the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $filter = $request->query('filter', 'all');

        $query = Sandbox::with(['owner:id,username', 'lastEditor:id,username'])
            ->withCount('collaborators');

        // Apply filter at the query level for efficiency
        switch ($filter) {
            case 'owned':
                $query->where('user_id', $user->id);
                break;
            case 'shared':
                $query->where('user_id', '!=', $user->id)
                    ->whereHas('collaborators', function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->whereNotNull('accepted_at');
                    });
                break;
            default: // 'all'
                $query->accessibleBy($user);
                break;
        }

        $sandboxes = $query->orderBy('last_edited_at', 'desc')
            ->paginate(20);

        // Add relationship info to each sandbox
        $collaboratorSandboxIds = $user->belongsToMany(Sandbox::class, 'sandbox_collaborators')
            ->whereNotNull('accepted_at')
            ->pluck('sandboxes.id')
            ->toArray();

        $sandboxes->through(function ($sandbox) use ($user, $collaboratorSandboxIds) {
            if ($sandbox->user_id === $user->id) {
                $sandbox->relationship = 'owner';
            } elseif (in_array($sandbox->id, $collaboratorSandboxIds)) {
                $sandbox->relationship = 'shared';
            } else {
                $sandbox->relationship = $sandbox->visibility; // 'public' or 'members'
            }
            return $sandbox;
        });

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

        $user = auth()->user();
        $limits = $this->getUserLimits($user);

        // Enforce max_sandboxes limit
        if ($limits['max_sandboxes'] > 0) {
            $currentCount = Sandbox::where('user_id', $user->id)->count();
            if ($currentCount >= $limits['max_sandboxes']) {
                return response()->json([
                    'message' => __('messages.sandbox.limit_reached', ['limit' => $limits['max_sandboxes']]),
                ], 403);
            }
        }

        $visibility = $validated['visibility'] ?? 'private';

        // Enforce public sandbox setting
        if ($visibility === 'public' && !filter_var(Setting::get('sandbox_public_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $visibility = 'private';
        }

        $sandbox = Sandbox::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'user_id' => auth()->id(),
            'visibility' => $visibility,
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

        // Enforce public sandbox setting
        if (isset($validated['visibility']) && $validated['visibility'] === 'public'
            && !filter_var(Setting::get('sandbox_public_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $validated['visibility'] = 'private';
        }

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
            // Enforce max_versions limit
            $limits = $this->getUserLimits($user);
            if ($limits['max_versions'] > 0) {
                $currentVersions = $sandbox->versions()->count();
                if ($currentVersions >= $limits['max_versions']) {
                    return response()->json([
                        'message' => __('messages.sandbox.version_limit_reached', ['limit' => $limits['max_versions']]),
                    ], 403);
                }
            }

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

        // Enforce max_collaborators limit
        $limits = $this->getUserLimits($user);
        if ($limits['max_collaborators'] > 0) {
            $currentCollaborators = $sandbox->collaborators()->count();
            if ($currentCollaborators >= $limits['max_collaborators']) {
                return response()->json([
                    'message' => __('messages.sandbox.collaborator_limit_reached', ['limit' => $limits['max_collaborators']]),
                ], 403);
            }
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

        // Enforce max_versions limit before creating backup version
        $limits = $this->getUserLimits($user);
        if ($limits['max_versions'] > 0) {
            $currentVersions = $sandbox->versions()->count();
            if ($currentVersions >= $limits['max_versions']) {
                return response()->json([
                    'message' => __('messages.sandbox.version_limit_reached', ['limit' => $limits['max_versions']]),
                ], 403);
            }
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

    /**
     * Get a single version's content for preview.
     */
    public function showVersion(Sandbox $sandbox, SandboxVersion $version): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        return response()->json([
            'version' => $version->load('user:id,username'),
        ]);
    }

    /**
     * Get revision history (field-level change log).
     */
    public function history(Sandbox $sandbox): JsonResponse
    {
        $user = auth()->user();

        if (!$sandbox->canView($user)) {
            return response()->json(['error' => __('messages.sandbox.unauthorized')], 403);
        }

        $revisions = $sandbox->revisions()
            ->with('executor:id,username')
            ->paginate(30);

        // Map revisions with diff data
        $data = $revisions->through(function ($revision) {
            $diff = $revision->getDiff();
            // Don't send full content blobs in the diff for performance
            unset($diff['content']);

            return [
                'id' => $revision->id,
                'action' => $revision->action,
                'executor' => $revision->executor,
                'diff' => $diff,
                'created_at' => $revision->created_at,
            ];
        });

        return response()->json($data);
    }
}
