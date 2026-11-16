<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

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
 *
 * There used to be three, all plain members, with a note saying to give
 * one a role in the admin screens if you wanted to see what a moderator
 * sees. Now that every feature has a permission of its own there is
 * something worth seeing, so the set covers the shapes that behave
 * differently: a member with nothing, a role holder, somebody handed a
 * single feature by hand, and the delegate who may hand things on - who
 * is the only way to try the rules that stop them handing on more than
 * they hold.
 *
 * These accounts are described by this file, so running it again puts
 * them back to what it says: a role given to `moderator` by hand is
 * taken off again. Accounts this file does not name are never touched,
 * and neither is anybody's password once it exists.
 */
class DemoSeeder extends Seeder
{
    /**
     * name => [display name, description, permissions].
     *
     * Roles a real community invents for itself are made in the admin
     * screens. These two are here so that there is a role to look at,
     * and so the dialog that hands access out has something whose ticks
     * came from a role rather than by hand - which it shows differently
     * and refuses to untick.
     */
    private const ROLES = [
        'moderator' => [
            'Moderator',
            'Looks after the forum, the tickets and the polls',
            ['manage-forum', 'manage-ticket', 'manage-poll'],
        ],
        'editor' => [
            'Editor',
            'Writes the pages, the posts and the wiki',
            ['manage-page', 'manage-post', 'manage-wiki', 'manage-taxonomy', 'manage-media'],
        ],
    ];

    /**
     * username => [display name, roles, permissions handed over directly].
     *
     * Everybody also gets `user`, because they are all members first.
     */
    private const MEMBERS = [
        // Nothing at all: what the site looks like to a member, and the
        // account to check a screen against when it should refuse
        // somebody
        'member' => ['Demo Member', [], []],

        // A role, and everything it carries: the forum and the tickets,
        // without the wiki or the settings
        'moderator' => ['Demo Moderator', ['moderator'], []],

        // The other role - the content side, without the community one
        'editor' => ['Demo Editor', ['editor'], []],

        // A single feature, handed over by hand rather than through a
        // role, which is the shape the access dialog ticks on its own
        'gallerist'  => ['Demo Gallerist', [], ['manage-gallery']],
        'translator' => ['Demo Translator', [], ['manage-translation']],

        // May hand out access, and holds the forum as well - so the
        // forum is the one thing they can pass on. The account to sign
        // in as to watch handing out more than you hold be refused.
        'delegate' => ['Demo Delegate', [], ['manage-user', 'manage-forum']],

        // Trusted to record a show, and nothing more: what they make
        // waits for somebody else to let it out, and they can only
        // reach their own shows. Sign in as this one to watch a podcast
        // go into the queue.
        'podcaster' => ['Demo Podcaster', [], ['create-podcast']],

        // The other half of that: works through the queue in the ticket
        // list, for the podcast and the wiki, and runs neither of them.
        // The pair of them is the thing one permission per feature
        // could not express.
        'approver' => ['Demo Approver', [], ['approve-podcast', 'approve-wiki']],

        // A role with a feature on top of it, which is how most of these
        // end up looking once a site has been run for a while
        'curator' => ['Demo Curator', ['editor'], ['manage-event']],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->say('Refusing to create demo members on production.');

            return;
        }

        if ( ! Role::where('name', 'user')->where('guard_name', 'api')->exists()) {
            $this->say('There is no `user` role yet - run php artisan db:seed first.');

            return;
        }

        $permissions = $this->permissions();

        if ($permissions === null) {
            return;
        }

        $host     = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        // Present but empty counts as unset, so an env file copied from
        // the example still gives working accounts
        $password = env('SEED_DEMO_PASSWORD') ?: 'password';

        $this->roles($permissions);

        foreach (self::MEMBERS as $username => [$name, $roles, $held]) {
            $member = User::firstOrCreate(
                ['username' => $username],
                [
                    'name'              => $name,
                    'email'             => "{$username}@{$host}",
                    'password'          => bcrypt($password),
                    'email_verified_at' => Carbon::now(),
                ]
            );

            // Synced rather than added to: this file is the description
            // of what each of them holds, so running it again is also
            // the way to put one back after an afternoon of clicking
            // about in the access dialog
            $member->syncRoles(array_merge(['user'], $roles));
            $member->syncPermissions($permissions->whereIn('name', $held));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->report($password);
    }

    /**
     * The permissions these accounts are built out of, or null with
     * something said about it when the database does not have them.
     *
     * Spatie throws on a name it cannot find, which is a stack trace
     * rather than an answer. A missing name here is either a database
     * nobody has seeded or a typo in the lists above, and both are worth
     * naming out loud.
     */
    private function permissions(): ?Collection
    {
        $wanted = collect(self::ROLES)
            ->flatMap(fn ($role) => $role[2])
            ->merge(collect(self::MEMBERS)->flatMap(fn ($member) => $member[2]))
            ->unique()
            ->values();

        $found   = Permission::where('guard_name', 'api')->whereIn('name', $wanted)->get();
        $missing = $wanted->diff($found->pluck('name'));

        if ($missing->isNotEmpty()) {
            $this->say('There is no permission called ' . $missing->implode(', ') . ' - run php artisan db:seed first.');

            return null;
        }

        return $found;
    }

    private function roles(Collection $permissions): void
    {
        foreach (self::ROLES as $name => [$label, $description, $held]) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['display_name' => $label, 'description' => $description]
            );

            $role->syncPermissions($permissions->whereIn('name', $held));
        }
    }

    /**
     * Who they are, printed as a list: the point of the set is that each
     * of them sees something different, which is no use unless the run
     * says which is which.
     */
    private function report(string $password): void
    {
        $this->say(count(self::MEMBERS) . " demo members, all with the password: {$password}");

        foreach (self::MEMBERS as $username => [$name, $roles, $held]) {
            $has = array_merge($roles, $held);

            $this->say('  ' . str_pad($username, 12) . ($has === [] ? 'nothing but being a member' : implode(', ', $has)));
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
