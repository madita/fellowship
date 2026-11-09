<?php

use App\Models\User;
use App\Support\FeaturePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The permissions themselves, written from the one list that describes
 * them.
 *
 * Seeding would do this too, and PermissionsTableSeeder does. This is
 * here for the install that is only ever migrated: a deploy runs
 * `migrate` and nothing else, so without this it would come up with a
 * site whose every admin page is shut to everybody but an admin - which
 * is how the gamification, podcast and mailing permissions came to write
 * themselves inside their own table migrations. They no longer do; there
 * is one place a permission row comes from, and it is this one.
 *
 * A name already in the table keeps whoever holds it and has only its
 * wording refreshed, so reading the list again after an edit to it is
 * safe and takes nothing away from anybody.
 *
 * Nobody is granted an action permission here, and that is deliberate.
 * An action is covered by its feature's `manage-` permission at the
 * moment it is checked - FeaturePermissions::satisfies(), applied by the
 * route middleware and by the Gate alike - so whoever could manage the
 * podcast yesterday can still do every part of it today without this
 * writing thirty rows per role to say so.
 *
 * The two grants that are made keep things as they are rather than
 * changing them. Any signed-in member could always write a wiki page and
 * have it held back until it was approved, and could always make a
 * gallery album - CollectionPolicy::create() returned true with a
 * comment saying so. Gating those on `create-wiki` and `create-gallery`
 * without giving them to the `user` role would quietly take both away
 * from every member on the day this lands, so the role is given them.
 *
 * Which turned out to need one more thing: nothing ever gave anybody the
 * `user` role. It was seeded on every install and assigned only to the
 * demo accounts, so a permission handed to it reached nobody at all.
 * Registration and OAuth sign-up assign it now, and backfillMemberRole()
 * gives it to everybody who registered before they did.
 *
 * The podcast is not given away: creating one has always needed
 * manage-podcast, so `create-podcast` starts in nobody's hands and is
 * handed to the members you trust with it - which is the thing this was
 * asked for. Giving it to the `user` role in the access screens opens it
 * to everybody at once, should that be what you want.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('permissions')->where('guard_name', 'api')->pluck('id', 'name');
        $now      = now();

        foreach (FeaturePermissions::all() as $permission) {
            if ($existing->has($permission['name'])) {
                // Already here - keep who holds it, refresh what it says
                DB::table('permissions')
                    ->where('id', $existing[$permission['name']])
                    ->update([
                        'display_name' => $permission['display_name'],
                        'description'  => $permission['description'],
                        'category'     => $permission['category'],
                        'updated_at'   => $now,
                    ]);

                continue;
            }

            $id = DB::table('permissions')->insertGetId([
                'name'         => $permission['name'],
                'guard_name'   => 'api',
                'display_name' => $permission['display_name'],
                'description'  => $permission['description'],
                'category'     => $permission['category'],
                'is_default'   => $permission['is_default'] ? 1 : 0,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $existing[$permission['name']] = $id;
        }

        // What every member could already do, said out loud now that
        // there is a name for it.
        //
        // RolesTableSeeder does the same for a fresh install, where the
        // `user` role does not exist until the seeders run - this is for
        // an install that has been running for a while and will only
        // ever be migrated.
        foreach (FeaturePermissions::member() as $permission) {
            $this->give('user', $permission, $existing);
        }

        // ...which only means anything once somebody holds the role.
        $this->backfillMemberRole();

        // The admin role holds everything there is, including whatever
        // arrived just now
        $adminId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->value('id');

        if ($adminId) {
            foreach ($existing as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id'       => $adminId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Every name this wrote, which is all of them: rolling it back
        // means going back to a site that had no feature permissions at
        // all, and keeping half of them would describe nothing. Who held
        // what goes with them; the seeders put the admin role and the
        // member role back.
        $ids = DB::table('permissions')
            ->where('guard_name', 'api')
            ->whereIn('name', FeaturePermissions::names())
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The `user` role, to everybody who registered before anything
     * handed it out.
     *
     * Only members holding no role at all, so an admin is left exactly
     * as they are and nobody is given a second one.
     */
    private function backfillMemberRole(): void
    {
        $roleId = DB::table('roles')->where('name', 'user')->where('guard_name', 'api')->value('id');

        if ( ! $roleId) {
            return;
        }

        $type = (new User)->getMorphClass();

        $held = DB::table('model_has_roles')->where('model_type', $type)->pluck('model_id');

        DB::table('users')
            ->whereNotIn('id', $held)
            ->orderBy('id')
            ->select('id')
            ->chunk(500, function ($users) use ($roleId, $type): void {
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'role_id'    => $roleId,
                        'model_type' => $type,
                        'model_id'   => $user->id,
                    ];
                }

                DB::table('model_has_roles')->insertOrIgnore($rows);
            });
    }

    /**
     * @param  Collection<string, int>  $existing
     */
    private function give(string $role, string $permission, Collection $existing): void
    {
        $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'api')->value('id');

        if ( ! $roleId || ! $existing->has($permission)) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore([
            'permission_id' => $existing[$permission],
            'role_id'       => $roleId,
        ]);
    }
};
