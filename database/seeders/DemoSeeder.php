<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Members to click around as, on a machine where that is wanted.
 *
 * Not part of a normal seed - DatabaseSeeder does not call it. Run it
 * when you want them:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * They share one password, taken from SEED_DEMO_PASSWORD, because the
 * point of them is to be logged into quickly. That is also why this
 * refuses to run on production: accounts whose password is written in a
 * seeder have no business on a live site.
 */
class DemoSeeder extends Seeder
{
    /**
     * username => email local part. Plain members; give one a role in the
     * admin screens if you need to test what a moderator sees.
     */
    private const MEMBERS = [
        'member' => 'member',
        'writer' => 'writer',
        'guest'  => 'guest',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->say('Refusing to create demo members on production.');

            return;
        }

        $host     = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        // Present but empty counts as unset, so an env file copied from
        // the example still gives working accounts
        $password = env('SEED_DEMO_PASSWORD') ?: 'password';

        foreach (self::MEMBERS as $username => $local) {
            $member = User::firstOrCreate(
                ['username' => $username],
                [
                    'email'             => "{$local}@{$host}",
                    'password'          => bcrypt($password),
                    'email_verified_at' => Carbon::now(),
                ]
            );

            $member->assignRole('user');
        }

        $this->say(count(self::MEMBERS) . " demo members, all with the password: {$password}");
    }

    private function say(string $line): void
    {
        $this->command?->getOutput()->writeln($line);
    }
}
