<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
//        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        DB::table('permissions')->insert([
            [
                'name'         => 'manage-permission',
                'guard_name'   => 'api',
                'display_name' => 'Manage Permission',
                'description'  => null,
                'category'     => 'permission',
                'is_default'   => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'manage-role',
                'guard_name'   => 'api',
                'display_name' => 'Manage Roles',
                'description'  => null,
                'category'     => 'permission',
                'is_default'   => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'manage-user',
                'guard_name'   => 'api',
                'display_name' => 'Manage User',
                'description'  => null,
                'category'     => 'permission',
                'is_default'   => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'manage-page',
                'guard_name'   => 'api',
                'display_name' => 'Manage Page',
                'description'  => null,
                'category'     => 'permission',
                'is_default'   => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'manage-post',
                'guard_name'   => 'api',
                'display_name' => 'Manage Post',
                'description'  => null,
                'category'     => 'permission',
                'is_default'   => 1,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
        ]);
    }
}
