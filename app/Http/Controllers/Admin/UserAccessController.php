<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\FeaturePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

/**
 * What one member is allowed, and changing it.
 *
 * Two ways of being allowed something, and both are here: a role, which
 * is a set of permissions with a name, and a permission handed straight
 * to the person. Roles are how it should normally be done - "moderator"
 * says why somebody has what they have, where a list of nineteen
 * permissions does not - but the direct kind is what gets the newsletter
 * written this week without inventing a role for one person.
 *
 * Who may use this: whoever holds manage-user. Which is most of the
 * reason for the rules below. Handing out access is the one piece of
 * admin work that can be used to give yourself more of it, so:
 *
 *  - nobody may edit their own access, admin or not. An admin who wants
 *    their own role changed asks another admin, and loses nothing by it;
 *    without the rule, the one admin who can be talked into it is the
 *    way in.
 *  - only an admin may hand out the admin role, or take it away.
 *  - nobody may hand out a permission they do not hold themselves. A
 *    delegate who looks after the forum cannot make somebody else a
 *    translator, which is the whole point of having given them only the
 *    forum.
 *  - the last admin keeps the role. Not a security rule - a locked door
 *    with the key inside is simply no use to anybody.
 */
class UserAccessController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'permission.any:' . FeaturePermissions::ASSIGN]);
    }

    /**
     * The roles and permissions there are, grouped the way the screen
     * shows them.
     */
    public function catalogue(Request $request): JsonResponse
    {
        return response()->json([
            'roles'       => $this->roles(),
            'permissions' => $this->permissions(),
            'categories'  => FeaturePermissions::categories(),
            // What the person looking may hand out. Everything, for an
            // admin; otherwise only what they hold themselves.
            'grantable'       => $this->grantable($request->user()),
            'may_grant_admin' => $request->user()->isAdmin(),
        ]);
    }

    /**
     * What this member is allowed, and where each of it comes from.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json($this->present($user, $request->user()));
    }

    /**
     * Give this member these roles and these permissions, and nothing
     * else: both lists are the whole of what they should end up with,
     * so a screen that shows checkboxes can send back what it shows.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $editor = $request->user();

        $validated = $request->validate([
            'roles'         => ['present', 'array'],
            'roles.*'       => ['string', Rule::exists('roles', 'name')->where('guard_name', 'api')],
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'api')],
        ]);

        $roles       = array_values(array_unique($validated['roles']));
        $permissions = array_values(array_unique($validated['permissions']));

        if ($editor->id === $user->id) {
            return $this->refuse('messages.access.not_your_own');
        }

        $held = $user->roles->pluck('name')->all();

        // Only an admin may make one, or stop somebody being one
        if ( ! $editor->isAdmin()) {
            $adminBefore = in_array('admin', $held, true);
            $adminAfter  = in_array('admin', $roles, true);

            if ($adminBefore !== $adminAfter) {
                return $this->refuse('messages.access.admin_role_denied');
            }
        }

        // The last admin keeps the role: a site nobody can administer
        // cannot be put right from inside it
        if (in_array('admin', $held, true) && ! in_array('admin', $roles, true)
            && $this->adminCount() <= 1) {
            return $this->refuse('messages.access.last_admin');
        }

        $grantable = $this->grantable($editor);
        $direct    = $user->permissions->pluck('name')->all();

        // Nothing may be handed out, or taken away, that the person
        // doing it does not hold. Taking away counts: otherwise the
        // forum's delegate could quietly strip the translator.
        $touched = array_merge(
            array_diff($permissions, $direct),
            array_diff($direct, $permissions)
        );

        $beyond = array_values(array_diff($touched, $grantable));

        if ($beyond !== []) {
            return $this->refuse('messages.access.beyond_your_own', ['names' => implode(', ', $beyond)]);
        }

        // A role carries permissions with it, so handing one out hands
        // out everything in it
        $rolesBefore  = $held;
        $touchedRoles = array_merge(array_diff($roles, $rolesBefore), array_diff($rolesBefore, $roles));

        foreach ($touchedRoles as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'api')->first();

            if ( ! $role) {
                continue;
            }

            $carried = array_values(array_diff($role->permissions->pluck('name')->all(), $grantable));

            if ($carried !== [] && ! $editor->isAdmin()) {
                return $this->refuse('messages.access.role_beyond_your_own', [
                    'role'  => $role->display_name ?: $role->name,
                    'names' => implode(', ', $carried),
                ]);
            }
        }

        DB::transaction(function () use ($user, $roles, $permissions) {
            $user->syncRoles($roles);
            $user->syncPermissions($permissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(array_merge(
            ['message' => __('messages.access.saved', ['name' => $user->username])],
            $this->present($user->fresh(['roles', 'permissions']), $editor)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, User $editor): array
    {
        $user->loadMissing(['roles.permissions', 'permissions']);

        $direct = $user->permissions->pluck('name')->all();

        // What a role brings with it, so the screen can show a tick that
        // was not put there by hand and say where it came from
        $byRole = [];

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $byRole[$permission->name][] = $role->display_name ?: $role->name;
            }
        }

        $effective = array_values(array_unique(array_merge($direct, array_keys($byRole))));

        // Holding a feature whole answers for each action under it at
        // the moment the action is checked, so the screen is told which
        // of them that applies to rather than being left to work the
        // rule out a second time and get it slightly different.
        $covered = [];

        foreach (FeaturePermissions::umbrella() as $action => $umbrella) {
            if (in_array($umbrella, $effective, true) && ! in_array($action, $effective, true)) {
                $covered[$action] = $umbrella;
            }
        }

        return [
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
            ],
            'roles'       => $user->roles->pluck('name')->all(),
            'permissions' => $direct,
            // Everything they hold, however they came by it
            'effective'  => $effective,
            'from_roles' => $byRole,
            // An action answered for by the feature it belongs to, so
            // the screen can tick it and say which permission covers it
            // instead of showing an empty box beside something the
            // member can in fact do
            'covered'         => $covered,
            'grantable'       => $this->grantable($editor),
            'may_grant_admin' => $editor->isAdmin(),
            'is_self'         => $editor->id === $user->id,
        ];
    }

    /**
     * The names this person may hand out: all of them for an admin, and
     * otherwise what they hold themselves.
     *
     * @return array<int, string>
     */
    private function grantable(User $editor): array
    {
        if ($editor->isAdmin()) {
            return Permission::where('guard_name', 'api')->orderBy('name')->pluck('name')->all();
        }

        $held = $editor->getAllPermissions()->pluck('name')->all();

        // Whoever holds a feature whole may hand out any part of it.
        // Without this, somebody given manage-podcast could do every bit
        // of the work themselves and still not be able to pass the
        // writing of a show to anybody - which is the thing the split
        // was made for.
        foreach (FeaturePermissions::umbrella() as $action => $umbrella) {
            if (in_array($umbrella, $held, true)) {
                $held[] = $action;
            }
        }

        sort($held);

        return array_values(array_unique($held));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function roles(): array
    {
        return Role::with('permissions:id,name')
            ->where('guard_name', 'api')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'name'         => $role->name,
                'display_name' => $role->display_name ?: $role->name,
                'description'  => $role->description,
                'permissions'  => $role->permissions->pluck('name')->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function permissions(): array
    {
        $known = [];

        foreach (FeaturePermissions::all() as $row) {
            $known[$row['name']] = $row;
        }

        // The catalogue's own order, which puts each feature's actions
        // straight after the permission they sit under
        $order = array_flip(array_keys($known));

        return Permission::where('guard_name', 'api')
            ->get()
            ->sortBy(fn (Permission $permission) => $order[$permission->name] ?? PHP_INT_MAX)
            ->map(fn (Permission $permission) => [
                'name'         => $permission->name,
                'display_name' => $permission->display_name ?: $permission->name,
                'description'  => $permission->description,
                'category'     => $permission->category ?: 'other',
                // Which feature this belongs to and which part of it, so
                // the screen can show the parts of the podcast beneath
                // the podcast instead of as five more rows in a list
                'feature'      => $known[$permission->name]['feature'] ?? null,
                'action'       => $known[$permission->name]['action'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function refuse(string $key, array $replace = []): JsonResponse
    {
        return response()->json(['message' => __($key, $replace)], 403);
    }

    private function adminCount(): int
    {
        return User::whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'api'))->count();
    }
}
