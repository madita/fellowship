<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * The two roles every install needs: somebody who can do everything, and
 * everybody else.
 *
 * Admin's permissions are re-synced on every run rather than handed over
 * once. A permission added by a later feature used to reach nobody: the
 * role had been given Permission::all() at seed time and that was that,
 * so an admin was offered pages the router then refused them - which is
 * why two feature migrations had to hand their own permission to the
 * admin role by hand. Re-seeding is the cure now.
 *
 * Roles a community invents for itself - moderators, editors, a committee
 * - are made in the admin screens, not here.
 */
class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api'],
            ['display_name' => 'Admin', 'description' => 'Runs the site']
        );

        // Everything there is, including whatever arrived since last time
        $admin->syncPermissions(Permission::where('guard_name', 'api')->get());

        Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'api'],
            ['display_name' => 'User', 'description' => 'A member, with what any member may do']
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
