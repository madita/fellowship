<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        $user = User::create([
            'username'          => 'disorganizer',
            'email'             => 'disorganizer@disorganizer.net',
            'password'          => bcrypt('admin'),
            'email_verified_at' => Carbon::now()
        ]);

        $user->assignRole('admin');
//        $user->roles()->attach(1);

        /* $profile = new \App\Profile;
         $profile->user()->associate($user);
         $profile->first_name = 'Strawberry';
         $profile->last_name = 'M4';
         $profile->save();*/


        $user = User::create([
            'username'          => 'madita',
            'email'             => 'rogi@stadtwache.net',
            'password'          => bcrypt('igorina'),
            'email_verified_at' => Carbon::now()
        ]);

        $user->assignRole('user');

//           $user->roles()->attach(2);
    }
}
