<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\FeaturePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission the site actually checks, one per feature.
 *
 * The list itself lives in App\Support\FeaturePermissions, because the
 * same names are needed by the migration that brings an existing install
 * up to date and by the screen that hands them out. This writes them.
 *
 * The set is the whole set, not a starting point: the router guards its
 * admin pages against these names (resources/js/router/middleware/
 * permission.js) and the API against the same ones, so a permission that
 * is missing from the database is a page nobody but an admin can open.
 *
 * Everything is written with firstOrCreate, so running it again changes
 * nothing - except the wording, which is refreshed, so renaming a
 * permission's label does not need the row deleting first.
 */
class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (FeaturePermissions::all() as $permission) {
            $row = Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'api'],
                [
                    'display_name' => $permission['display_name'],
                    'description'  => $permission['description'],
                    'category'     => $permission['category'],
                    'is_default'   => $permission['is_default'] ? 1 : 0,
                ]
            );

            // What it is called and what it says it does are this list's
            // to decide; who holds it is not touched
            $row->update([
                'display_name' => $permission['display_name'],
                'description'  => $permission['description'],
                'category'     => $permission['category'],
            ]);
        }

        // Spatie keeps the whole set in one cache entry, which would
        // otherwise still be the set from before this ran
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
