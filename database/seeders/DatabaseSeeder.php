<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything a fresh install needs to be a working site.
 *
 * `php artisan migrate --seed` gives a site you can sign into and
 * administer: permissions, the two roles, one admin account, the
 * settings, a homepage, a footer, navigation, forum categories and the
 * event shapes.
 *
 * The order matters twice: permissions before roles, because the admin
 * role is given every permission there is, and roles before the admin
 * account, because it is assigned one.
 *
 * All of it can be run again. The seeders that fill in reference data
 * (permissions, roles, settings) add what is missing and leave what is
 * there; the ones that create content (homepage, footer, menus, forum,
 * events) do nothing at all once their content exists, so re-seeding
 * never duplicates a homepage or overwrites an admin's editing.
 *
 * Members to test with are DemoSeeder's job, which is deliberately not
 * called here: php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Who may do what, and somebody to do it
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            UsersTableSeeder::class,

            // How the site behaves and how it looks
            SettingsSeeder::class,
            HomepageSeeder::class,
            FooterSeeder::class,
            MenuSeeder::class,

            // Something in each feature to start from
            ForumSeeder::class,
            EventProfileTableSeeder::class,
            EventTypeTableSeeder::class,
        ]);
    }
}
