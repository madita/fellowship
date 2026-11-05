<?php

use App\Support\FeaturePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * A permission per feature, on an install that is already running.
 *
 * Seeding would do this too, but an install that is only ever migrated
 * would be left with pages nobody but an admin can open - which is how
 * the three feature permissions before these came to write themselves in
 * their own migrations.
 *
 * Nobody loses anything on the way. manage-page and manage-post used to
 * stand for a dozen features between them; every role holding one of
 * them is given the permissions that were carved out of it, so a
 * moderator who could reach the forum yesterday can reach it today. The
 * admin role is re-synced with everything, as the seeder does.
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

        // Whoever could do this work through the coarse permission keeps
        // being able to do it through the one named after the feature
        foreach (FeaturePermissions::inherited() as $name => $inherits) {
            if ( ! $existing->has($name) || ! $existing->has($inherits)) {
                continue;
            }

            $roles = DB::table('role_has_permissions')
                ->where('permission_id', $existing[$inherits])
                ->pluck('role_id');

            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $existing[$name],
                    'role_id'       => $roleId,
                ]);
            }
        }

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
        // Only the ones this added: those that were here before it are
        // not this migration's to remove
        $names = array_values(array_diff(FeaturePermissions::names(), [
            'manage-permission',
            'manage-role',
            'manage-user',
            'manage-page',
            'manage-post',
            'award-achievements',
            'manage-podcast',
            'manage-mailings',
        ]));

        $ids = DB::table('permissions')
            ->where('guard_name', 'api')
            ->whereIn('name', $names)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
