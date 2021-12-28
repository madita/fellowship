<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
       $role = Role::create([
           'name' => 'admin',
           'guard_name' => 'api',
           'display_name' => 'Admin',
           'description' => 'Admin'
       ]);

        $role->givePermissionTo(Permission::all());

        Role::create([
            'name' => 'user',
            'guard_name' => 'api',
            'display_name' => 'User',
            'description' => 'User'
        ]);
    }
}
