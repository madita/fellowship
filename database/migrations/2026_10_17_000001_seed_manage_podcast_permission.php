<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Running a podcast is not an admin's job.
 *
 * The people who record a show are rarely the people who administer the
 * site, and sending them through the admin panel to publish an episode
 * means handing them the admin panel. This permission lets a show be run
 * from the podcast's own pages instead — the same way the wiki is
 * written from the wiki rather than from a back office.
 *
 * Admins pass without holding it; see EnsureUserHasPermission.
 */
return new class extends Migration
{
    private const NAME = 'manage-podcast';

    public function up(): void
    {
        if ( ! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', self::NAME)->value('id');

        if ( ! $id) {
            $id = DB::table('permissions')->insertGetId([
                'name'         => self::NAME,
                'guard_name'   => 'api',
                'display_name' => 'Manage Podcast',
                'description'  => 'Create shows, write episodes and upload their audio, without the admin panel',
                'category'     => 'podcast',
                'is_default'   => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        $this->giveToAdmins((int) $id);
    }

    /**
     * Admins hold every permission — RolesTableSeeder gives the role
     * Permission::all() — but it does that once, at seed time, so a
     * permission added later never reaches them.
     *
     * It matters here and not for award-achievements, because the client
     * guards these pages too: EnsureUserHasPermission waves an admin
     * through on the API, while the router's permission middleware only
     * looks at the permissions a member actually holds. Without this an
     * admin would be offered the edit button and then bounced off the
     * page it leads to.
     */
    private function giveToAdmins(int $permissionId): void
    {
        if ( ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $adminRoles = DB::table('roles')->where('name', 'admin')->pluck('id');

        foreach ($adminRoles as $roleId) {
            $held = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if ( ! $held) {
                DB::table('role_has_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if ( ! Schema::hasTable('permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', self::NAME)->value('id');

        if ($id && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        }

        DB::table('permissions')->where('name', self::NAME)->delete();
    }
};
