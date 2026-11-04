<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission the site actually checks.
 *
 * The list is the whole set, not a starting point: the router guards its
 * admin pages against these names (resources/js/router/middleware/
 * permission.js) and the API against the same ones, so a permission that
 * is missing from the database is a page nobody but an admin can open.
 *
 * Three of them - award-achievements, manage-podcast and manage-mailings
 * - are also written by the migration of the feature they belong to, so
 * an install that is migrated without being seeded still has them. They
 * are repeated here
 * because this is the list somebody reads to find out what exists, and a
 * half-list is worse than a repeated row. Everything here is written with
 * firstOrCreate, so running it again changes nothing.
 */
class PermissionsTableSeeder extends Seeder
{
    /**
     * name => [display name, category, default for a new role?, why].
     */
    private const PERMISSIONS = [
        'manage-permission'  => ['Manage Permission', 'permission', true, null],
        'manage-role'        => ['Manage Roles', 'permission', true, null],
        'manage-user'        => ['Manage User', 'permission', true, null],
        'manage-page'        => ['Manage Page', 'permission', true, null],
        'manage-post'        => ['Manage Post', 'permission', true, null],
        'award-achievements' => [
            'Award Achievements', 'achievement', false,
            'Hand achievements to members for things that happened away from the site',
        ],
        'manage-podcast'     => [
            'Manage Podcast', 'podcast', false,
            'Create shows, write episodes and upload their audio, without the admin panel',
        ],
        'manage-mailings'    => [
            'Manage Mailings', 'mailing', false,
            'Write and send mail to the members, and edit the templates the site sends',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => [$display, $category, $isDefault, $description]) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                [
                    'display_name' => $display,
                    'description'  => $description,
                    'category'     => $category,
                    'is_default'   => $isDefault ? 1 : 0,
                ]
            );
        }

        // Spatie keeps the whole set in one cache entry, which would
        // otherwise still be the set from before this ran
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
