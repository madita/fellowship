<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Podcast\Podcast;
use App\Models\Role;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Models\Wiki;
use App\Support\Approvals;
use App\Support\FeaturePermissions;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A permission per action, and the queue that goes with it.
 *
 * One permission per feature could say who runs the podcast and nothing
 * else. It could not say "this member may record a show, and somebody
 * else decides whether anybody hears it", which is the shape a community
 * actually wants: the work is done by members and looked at by whoever
 * looks after that corner of the site.
 *
 * So the features members put things into are split by what is being
 * done - view, create, edit, delete, and approve where something really
 * waits - and holding a feature whole still answers for all of it.
 */
class CrudPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsTableSeeder::class);
        $this->seed(RolesTableSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function member(string ...$permissions): User
    {
        $user = User::factory()->create();

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->fresh();
    }

    private function submitShow(User $as, string $title = 'A show'): Podcast
    {
        $this->actingAs($as, 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => $title])
            ->assertCreated();

        return Podcast::where('title', $title)->firstOrFail();
    }

    // ----------------------------------------------------------------
    // The catalogue
    // ----------------------------------------------------------------

    public function test_each_split_feature_has_its_actions(): void
    {
        $split = FeaturePermissions::split();

        $this->assertArrayHasKey('podcast', $split);
        $this->assertArrayHasKey('wiki', $split);

        foreach ($split as $feature => $actions) {
            foreach ($actions as $action) {
                $name = "{$action}-{$feature}";

                $this->assertTrue(FeaturePermissions::has($name), "{$name} is not in the catalogue");

                // Seeded, named and described: the screen that hands
                // these out is long enough without blank rows in it
                $row = collect(FeaturePermissions::all())->firstWhere('name', $name);

                $this->assertNotEmpty($row['display_name'], "{$name} has no name");
                $this->assertNotEmpty($row['description'], "{$name} says nothing about itself");
                $this->assertSame("manage-{$feature}", $row['inherits']);
            }
        }
    }

    public function test_approve_exists_only_where_something_waits(): void
    {
        // A permission nothing checks is decoration. Approving is real
        // for the wiki and the podcast - the two models with a queue
        // behind them - and nowhere else yet.
        $approvable = array_keys(FeaturePermissions::split());

        foreach ($approvable as $feature) {
            $expected = in_array($feature, ['wiki', 'podcast'], true);

            $this->assertSame(
                $expected,
                FeaturePermissions::has("approve-{$feature}"),
                "approve-{$feature} should " . ($expected ? '' : 'not ') . 'exist'
            );
        }

        // And the registry agrees with the catalogue
        $this->assertSame('approve-wiki', Approvals::permissionFor(Wiki::class));
        $this->assertSame('approve-podcast', Approvals::permissionFor(Podcast::class));
    }

    public function test_a_feature_nobody_puts_anything_into_is_held_whole(): void
    {
        // Splitting the settings or the migration tool would mint four
        // names apiece that nothing would ever check
        foreach (['setting', 'migration', 'irc', 'translation', 'rank', 'media'] as $feature) {
            $this->assertTrue(FeaturePermissions::has("manage-{$feature}"));
            $this->assertFalse(FeaturePermissions::has("create-{$feature}"));
            $this->assertFalse(FeaturePermissions::has("view-{$feature}"));
        }
    }

    // ----------------------------------------------------------------
    // The umbrella
    // ----------------------------------------------------------------

    public function test_holding_a_feature_whole_answers_for_each_part_of_it(): void
    {
        $host = $this->member('manage-podcast');

        foreach (['view', 'create', 'edit', 'delete', 'approve'] as $action) {
            $this->assertTrue($host->can("{$action}-podcast"), "manage-podcast did not answer for {$action}-podcast");
        }

        // Not only as an ability - the routes answer the same way, which
        // is the point of applying the rule in one place
        $this->actingAs($host, 'sanctum')->getJson('/api/admin/podcasts')->assertOk();
        $this->actingAs($host, 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => 'Still allowed'])
            ->assertCreated();
    }

    public function test_a_part_does_not_answer_for_the_whole(): void
    {
        $writer = $this->member('create-podcast');

        $this->assertFalse($writer->can('manage-podcast'));
        $this->assertFalse($writer->can('approve-podcast'));
        $this->assertFalse($writer->can('view-podcast'));

        // The whole site's figures belong to whoever looks after the
        // podcast, not to somebody who records one show
        $this->actingAs($writer, 'sanctum')->getJson('/api/admin/podcasts/stats')->assertForbidden();
    }

    public function test_the_umbrella_does_not_reach_across_features(): void
    {
        // The thing the set was split up to undo in the first place:
        // manage-page standing in for the wiki. It stays undone - the
        // live rule is only ever an action and its own feature.
        $editor = $this->member('manage-page');

        $this->assertTrue($editor->can('create-page'));
        $this->assertFalse($editor->can('create-wiki'));
        $this->assertFalse($editor->can('manage-wiki'));
    }

    public function test_one_action_does_not_answer_for_another(): void
    {
        $writer = $this->member('create-page');

        $this->assertTrue($writer->can('create-page'));
        $this->assertFalse($writer->can('delete-page'));

        $page = new Page(['user_id' => $this->admin->id, 'slug' => 'a-page']);
        $page->translateOrNew(app()->getLocale())->title   = 'A page';
        $page->translateOrNew(app()->getLocale())->content = '<p>A page</p>';
        $page->save();

        $this->actingAs($writer, 'sanctum')
            ->deleteJson("/api/datatable/pages/{$page->id}")
            ->assertForbidden();

        // …and the list they were not given either: create-page writes,
        // it does not open the table
        $this->actingAs($writer, 'sanctum')
            ->getJson('/api/datatable/pages')
            ->assertForbidden();

        // …while whoever may read it may read it
        $this->actingAs($this->member('view-page'), 'sanctum')
            ->getJson('/api/datatable/pages')
            ->assertOk();
    }

    // ----------------------------------------------------------------
    // A member records a show, and somebody else decides on it
    // ----------------------------------------------------------------

    public function test_a_trusted_member_submits_a_show_that_then_waits(): void
    {
        $host = $this->member('create-podcast');
        $show = $this->submitShow($host);

        // Made, and not yet anybody's to hear
        $this->assertTrue($show->isPending());

        // …and a ticket was raised for it, which is the queue
        $ticket = Ticket::whereHasMorph('ticketable', [Podcast::class], fn ($q) => $q->where('id', $show->id))->first();

        $this->assertNotNull($ticket, 'submitting a show raised no ticket');
        $this->assertSame('podcast_approval', $ticket->ticketType->slug);
    }

    public function test_a_member_without_create_podcast_is_refused(): void
    {
        $this->actingAs($this->member(), 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => 'Uninvited'])
            ->assertForbidden();

        $this->assertSame(0, Podcast::count());
    }

    public function test_the_author_can_read_their_own_show_while_it_waits(): void
    {
        $host = $this->member('create-podcast');
        $show = $this->submitShow($host);

        // A draft you cannot see is a draft you cannot finish
        $this->assertTrue($show->isVisibleTo($host));
        $this->assertFalse($show->isVisibleTo($this->member()));
        $this->assertFalse($show->isVisibleTo(null));
    }

    public function test_create_podcast_does_not_reach_somebody_elses_show(): void
    {
        $theirs = $this->submitShow($this->member('create-podcast'), 'Theirs');
        $other  = $this->member('create-podcast');

        // Reaching the routes says they may run a podcast, not which
        // one. Without this, handing create-podcast to a member would
        // have handed them everybody's shows.
        $this->actingAs($other, 'sanctum')
            ->patchJson("/api/podcasts/manage/{$theirs->id}", ['title' => 'Mine now'])
            ->assertForbidden();

        $this->assertSame('Theirs', $theirs->fresh()->title);

        // Their own list is their own
        $this->actingAs($other, 'sanctum')
            ->getJson('/api/podcasts/manage')
            ->assertOk()
            ->assertJsonCount(0, 'podcasts');
    }

    public function test_edit_podcast_does_reach_somebody_elses_show(): void
    {
        $theirs = $this->submitShow($this->member('create-podcast'), 'Theirs');

        $this->actingAs($this->member('edit-podcast'), 'sanctum')
            ->patchJson("/api/podcasts/manage/{$theirs->id}", ['title' => 'Tidied up'])
            ->assertOk();

        $this->assertSame('Tidied up', $theirs->fresh()->title);
    }

    // ----------------------------------------------------------------
    // The approver, and the queue they work through
    // ----------------------------------------------------------------

    public function test_the_approver_finds_the_queue_in_the_ticket_list(): void
    {
        $show = $this->submitShow($this->member('create-podcast'));

        $approver = $this->member('approve-podcast');

        // They did not open the ticket and nobody assigned it to them,
        // so before the approve permission the list was empty for them
        // and the permission had nothing behind it
        $titles = $this->actingAs($approver, 'sanctum')
            ->getJson('/api/tickets')
            ->assertOk()
            ->json('data.*.ticketable_id');

        $this->assertContains($show->id, $titles);
    }

    public function test_the_approver_opens_the_ticket_and_lets_the_show_out(): void
    {
        $show   = $this->submitShow($this->member('create-podcast'));
        $ticket = $show->tickets()->firstOrFail();

        $approver = $this->member('approve-podcast');

        $this->actingAs($approver, 'sanctum')->getJson("/api/tickets/{$ticket->id}")->assertOk();

        $this->actingAs($approver, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/approve")
            ->assertOk()
            ->assertJsonPath('is_approved', true);

        $this->assertTrue($show->fresh()->isApproved());
        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    public function test_the_approver_can_read_the_show_while_it_waits(): void
    {
        $show = $this->submitShow($this->member('create-podcast'));

        // Being handed a queue and then refused every item in it was the
        // state of things before approve-podcast read the show itself
        $this->assertTrue($show->isVisibleTo($this->member('approve-podcast')));
    }

    public function test_sending_a_show_back_is_the_same_permission(): void
    {
        $show   = $this->submitShow($this->member('create-podcast'));
        $ticket = $show->tickets()->firstOrFail();

        $this->actingAs($this->member('approve-podcast'), 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/approve")
            ->assertOk();

        $this->actingAs($this->member('approve-podcast'), 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/reject")
            ->assertOk()
            ->assertJsonPath('is_approved', false);

        $this->assertTrue($show->fresh()->isPending());
    }

    public function test_approving_is_refused_without_the_permission(): void
    {
        $show   = $this->submitShow($this->member('create-podcast'));
        $ticket = $show->tickets()->firstOrFail();

        // Recording a show is not deciding on one - which is the whole
        // reason the two are separate permissions
        $this->actingAs($this->member('create-podcast'), 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/approve")
            ->assertForbidden();

        $this->assertTrue($show->fresh()->isPending());
    }

    public function test_an_approver_of_one_thing_does_not_see_another_queue(): void
    {
        $show = $this->submitShow($this->member('create-podcast'));

        $wikiApprover = $this->member('approve-wiki');

        $this->assertSame(
            ['wiki_approval'],
            Approvals::ticketTypesFor($wikiApprover)
        );

        $this->actingAs($wikiApprover, 'sanctum')
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $ticket = $show->tickets()->firstOrFail();

        $this->actingAs($wikiApprover, 'sanctum')->getJson("/api/tickets/{$ticket->id}")->assertForbidden();
        $this->actingAs($wikiApprover, 'sanctum')->postJson("/api/tickets/{$ticket->id}/approve")->assertForbidden();
    }

    public function test_a_member_with_nothing_sees_no_queue_at_all(): void
    {
        $this->submitShow($this->member('create-podcast'));

        $this->assertSame([], Approvals::ticketTypesFor($this->member()));

        $this->actingAs($this->member(), 'sanctum')
            ->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_admin_works_through_every_queue(): void
    {
        $this->assertEqualsCanonicalizing(
            ['wiki_approval', 'podcast_approval'],
            Approvals::ticketTypesFor($this->admin)
        );
    }

    // ----------------------------------------------------------------
    // The wiki, which has worked this way all along
    // ----------------------------------------------------------------

    public function test_approve_wiki_lets_somebody_who_is_not_an_admin_let_a_page_out(): void
    {
        $author = $this->member('create-wiki');

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/wiki', ['title' => 'A page', 'content' => 'Something true'])
            ->assertSuccessful();

        $wiki = Wiki::firstOrFail();
        $this->assertTrue($wiki->isPending());

        $this->actingAs($this->member('create-wiki'), 'sanctum')
            ->postJson("/api/wiki/{$wiki->slug}/approve")
            ->assertForbidden();

        $this->actingAs($this->member('approve-wiki'), 'sanctum')
            ->postJson("/api/wiki/{$wiki->slug}/approve")
            ->assertOk();

        $this->assertTrue($wiki->fresh()->isApproved());
    }

    public function test_a_pending_page_is_readable_by_its_author_and_its_approver(): void
    {
        $author = $this->member('create-wiki');

        $this->actingAs($author, 'sanctum')
            ->postJson('/api/wiki', ['title' => 'Waiting', 'content' => 'Not yet'])
            ->assertSuccessful();

        $wiki = Wiki::firstOrFail();

        $this->assertTrue($wiki->isVisibleTo($author));
        $this->assertTrue($wiki->isVisibleTo($this->member('approve-wiki')));
        $this->assertTrue($wiki->isVisibleTo($this->member('view-wiki')));
        $this->assertFalse($wiki->isVisibleTo($this->member()));
    }

    // ----------------------------------------------------------------
    // Nobody loses anything on the day this lands
    // ----------------------------------------------------------------

    public function test_a_member_keeps_the_wiki_and_the_gallery_they_always_had(): void
    {
        // Any signed-in member could write a wiki page and make an album
        // before this. Both are permissions now, so the role every
        // member holds has to carry them or the split would quietly take
        // them away.
        $held = Role::where('name', 'user')->where('guard_name', 'api')->firstOrFail()
            ->permissions->pluck('name');

        $this->assertTrue($held->contains('create-wiki'));
        $this->assertTrue($held->contains('create-gallery'));

        // …and nothing else: the role is not a way in to the rest
        $this->assertEqualsCanonicalizing(['create-wiki', 'create-gallery'], $held->all());
    }

    public function test_a_new_member_is_given_that_role(): void
    {
        // Which needed fixing too: nothing ever assigned the `user`
        // role, so a permission handed to it reached nobody
        $user = User::factory()->create();
        $user->assignDefaultRole();

        $this->assertTrue($user->fresh()->hasRole('user'));
        $this->assertTrue($user->fresh()->can('create-wiki'));
    }

    public function test_the_admin_role_holds_every_new_name_too(): void
    {
        foreach (FeaturePermissions::names() as $name) {
            $this->assertTrue($this->admin->can($name), "an admin cannot {$name}");
        }
    }
}
