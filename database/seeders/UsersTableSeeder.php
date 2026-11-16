<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The one account somebody needs to get in and start running the site.
 *
 * It used to create three, with their real addresses and their real
 * passwords written into this file - which meant the password of a live
 * account was in the repository, and that anybody who cloned it and
 * seeded got an admin whose password they already knew.
 *
 * Now there is one account, and it is described by the environment:
 *
 *   SEED_ADMIN_USERNAME=admin
 *   SEED_ADMIN_EMAIL=admin@example.test
 *   SEED_ADMIN_PASSWORD=...
 *
 * With no password given, a random one is generated and printed once -
 * so an install nobody configured is still usable, and still not
 * guessable. An account that already exists is left exactly as it is,
 * password included, so re-seeding never locks anybody out.
 *
 * Members to test with belong in DemoSeeder, which is not part of a
 * normal seed.
 */
class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        // `?:` throughout, not `??` or env()'s own default: a key that is
        // present but empty - SEED_ADMIN_PASSWORD= in an env file copied
        // from the example - means nobody chose one, not that the password
        // is the empty string
        $username = env('SEED_ADMIN_USERNAME') ?: 'admin';
        $email    = env('SEED_ADMIN_EMAIL') ?: "admin@{$host}";
        $password = env('SEED_ADMIN_PASSWORD') ?: null;

        $existing = User::where('username', $username)->orWhere('email', $email)->first();

        if ($existing) {
            // Already here: make sure it can still administer the site,
            // and leave everything else alone
            $existing->assignRole('admin');

            $this->say("Admin {$existing->username} already exists - left as it is.");

            return;
        }

        $generated = $password === null;
        $password ??= Str::password(20);

        $admin = User::create([
            'username'          => $username,
            'email'             => $email,
            'password'          => bcrypt($password),
            'email_verified_at' => Carbon::now(),
        ]);

        $admin->assignRole('admin');

        $this->say("Admin {$username} created.");

        if ($generated) {
            // Printed once, and nowhere else: there is no way to read it
            // back out of the database afterwards
            $this->say("Its password is: {$password}");
            $this->say('Write it down, or set SEED_ADMIN_PASSWORD and seed a fresh database.');
        }
    }

    /**
     * Seeders are also run from tests, where there is no console to
     * write to.
     */
    private function say(string $line): void
    {
        $this->command?->getOutput()->writeln($line);
    }
}
