<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The accounts to click around as. They are only worth having if each
 * of them really sees something different, so this signs in as them and
 * checks that - rather than counting the rows it wrote.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsTableSeeder::class);
        $this->seed(RolesTableSeeder::class);
    }

    private function member(string $username): User
    {
        $member = User::where('username', $username)->first();

        $this->assertNotNull($member, "there is no demo {$username}");

        return $member;
    }

    public function test_it_creates_members_who_can_sign_in(): void
    {
        $this->seed(DemoSeeder::class);

        $expected = [
            'member', 'moderator', 'editor', 'gallerist', 'translator',
            'delegate', 'curator', 'podcaster', 'approver',
        ];

        foreach ($expected as $username) {
            $member = $this->member($username);

            // Verified, named and a member: an unverified account cannot
            // get past the front door, which would make the whole set
            // useless
            $this->assertNotNull($member->email_verified_at, "{$username} is not verified");
            $this->assertNotEmpty($member->name);
            $this->assertTrue($member->hasRole('user'), "{$username} is not a member");
            $this->assertFalse($member->isAdmin(), "{$username} is an admin");
        }
    }

    public function test_the_plain_member_opens_nothing(): void
    {
        $this->seed(DemoSeeder::class);

        $member = $this->member('member');

        // What any member holds and no more: writing a wiki page and
        // making an album, both of which have always been open to
        // anybody signed in and now have names
        $this->assertEqualsCanonicalizing(
            ['create-wiki', 'create-gallery'],
            $member->getAllPermissions()->pluck('name')->all()
        );

        $this->actingAs($member, 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
        $this->actingAs($member, 'sanctum')->getJson('/api/admin/media')->assertForbidden();
    }

    public function test_a_role_holder_opens_their_own_feature_and_no_other(): void
    {
        $this->seed(DemoSeeder::class);

        $moderator = $this->member('moderator');

        // What the moderator role is for
        $this->actingAs($moderator, 'sanctum')->getJson('/api/admin/tickets/stats')->assertOk();

        // …and the content side, which is the editor's
        $this->actingAs($moderator, 'sanctum')->getJson('/api/admin/media')->assertForbidden();
        $this->actingAs($moderator, 'sanctum')->getJson('/api/admin/translations/locales')->assertForbidden();

        $editor = $this->member('editor');

        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/media')->assertOk();
        $this->actingAs($editor, 'sanctum')->getJson('/api/admin/tickets/stats')->assertForbidden();
    }

    public function test_a_permission_handed_over_by_hand_is_held_by_hand(): void
    {
        $this->seed(DemoSeeder::class);

        $translator = $this->member('translator');

        // Directly, not through a role - which is the shape the access
        // dialog offers to untick
        $this->assertSame(['manage-translation'], $translator->permissions->pluck('name')->all());

        // Not through a role: the `user` role carries what any member
        // may do, and the translations are not that
        $this->assertNotContains(
            'manage-translation',
            $translator->getPermissionsViaRoles()->pluck('name')->all()
        );

        $this->actingAs($translator, 'sanctum')->getJson('/api/admin/translations/locales')->assertOk();
        $this->actingAs($translator, 'sanctum')->getJson('/api/admin/media')->assertForbidden();
    }

    public function test_the_curator_holds_a_role_and_something_on_top_of_it(): void
    {
        $this->seed(DemoSeeder::class);

        $curator = $this->member('curator');

        $this->assertTrue($curator->hasRole('editor'));
        $this->assertSame(['manage-event'], $curator->permissions->pluck('name')->all());

        // Both halves work
        $this->actingAs($curator, 'sanctum')->getJson('/api/admin/media')->assertOk();
        $this->assertTrue($curator->can('manage-event'));
    }

    public function test_the_podcaster_writes_and_the_approver_decides(): void
    {
        $this->seed(DemoSeeder::class);

        $podcaster = $this->member('podcaster');
        $approver  = $this->member('approver');

        // The pair the split exists for: one records a show, the other
        // finds it waiting in the ticket list
        $this->actingAs($podcaster, 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => 'Demo show'])
            ->assertCreated();

        $this->assertFalse($podcaster->can('approve-podcast'));
        $this->assertFalse($approver->can('create-podcast'));

        $this->actingAs($approver, 'sanctum')
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_delegate_can_hand_on_the_forum_and_nothing_else(): void
    {
        $this->seed(DemoSeeder::class);

        $delegate = $this->member('delegate');
        $member   = $this->member('member');

        // The account exists so the escalation rules can be tried from
        // the screen rather than from a test
        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$member->id}/access", [
                'roles'       => [],
                'permissions' => ['manage-forum'],
            ])
            ->assertOk();

        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$member->id}/access", [
                'roles'       => [],
                'permissions' => ['manage-forum', 'manage-translation'],
            ])
            ->assertForbidden();

        // Nor the moderator role, because the tickets and the polls in
        // it were never theirs to give
        $this->actingAs($delegate, 'sanctum')
            ->putJson("/api/admin/users/{$member->id}/access", [
                'roles'       => ['moderator'],
                'permissions' => ['manage-forum'],
            ])
            ->assertForbidden();
    }

    public function test_running_it_again_puts_an_account_back(): void
    {
        $this->seed(DemoSeeder::class);

        $gallerist = $this->member('gallerist');
        $password  = $gallerist->password;

        // An afternoon of clicking about in the dialog
        $gallerist->syncPermissions(['manage-setting']);
        $gallerist->assignRole('moderator');

        $this->seed(DemoSeeder::class);

        $gallerist = $this->member('gallerist')->fresh();

        $this->assertSame(['manage-gallery'], $gallerist->permissions->pluck('name')->all());
        $this->assertFalse($gallerist->hasRole('moderator'));

        // Without a second account, and without a new password: somebody
        // may have changed theirs, and re-seeding is not a way to lock
        // them out of it
        $this->assertSame(1, User::where('username', 'gallerist')->count());
        $this->assertSame($password, $gallerist->password);
    }

    public function test_it_leaves_everybody_else_alone(): void
    {
        $admin = User::factory()->create(['username' => 'the-boss']);
        $admin->assignRole('admin');

        $this->seed(DemoSeeder::class);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_it_refuses_to_run_on_production(): void
    {
        $this->app->instance('env', 'production');

        // Run the seeder itself: db:seed asks before it does anything at
        // all on production, so going through the command would prove
        // the command's point and not this one
        (new DemoSeeder())->run();

        // Accounts sharing one written-down password are a way in, and a
        // live site is exactly where that matters
        $this->assertSame(0, User::whereIn('username', ['member', 'moderator', 'delegate'])->count());
    }

    public function test_it_says_so_rather_than_throwing_on_an_unseeded_database(): void
    {
        // A database with the roles but not the permissions: Spatie
        // throws on a name it cannot find, and a stack trace is not an
        // answer to "why are there no demo members"
        Permission::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(DemoSeeder::class);

        $this->assertSame(0, User::where('username', 'member')->count());
    }
}
