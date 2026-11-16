<?php

namespace Tests\Feature;

use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
use App\Models\Setting;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A show gets looked at before the world hears it, and its author can
 * see it while it waits.
 */
class PodcastApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $host;

    protected User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        Permission::findOrCreate('manage-podcast', 'api');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->host = User::factory()->create(['username' => 'host']);
        $this->host->givePermissionTo('manage-podcast');

        $this->stranger = User::factory()->create();

        Setting::clearCache();
    }

    /**
     * Submit a show the way a member does, through the API.
     *
     * Only for the tests about submitting: actingAs() stays in force for
     * the rest of the test, so anything asserting what a *guest* sees
     * has to build its show without it — see pendingShow().
     */
    private function create(User $as, array $attributes = []): Podcast
    {
        $response = $this->actingAs($as, 'sanctum')
            ->postJson('/api/podcasts/manage', array_merge([
                'title'        => 'The Nightly Watch',
                'is_published' => true,
            ], $attributes))
            ->assertCreated();

        return Podcast::find($response->json('podcast.id'));
    }

    /**
     * A show awaiting review, made without signing anybody in — so the
     * tests that follow really are unauthenticated.
     *
     * Creating it with nobody signed in is also what makes it pending:
     * shouldAutoApprove() has no user to check against.
     */
    private function pendingShow(array $attributes = []): Podcast
    {
        $podcast = Podcast::create(array_merge([
            'title'        => 'The Nightly Watch',
            'is_published' => true,
            'created_by'   => $this->host->id,
        ], $attributes));

        $podcast->authors()->sync([$this->host->id]);

        return $podcast->refresh();
    }

    // ----------------------------------------------------------------
    // Submitting
    // ----------------------------------------------------------------

    public function test_a_show_from_a_normal_member_opens_an_approval_ticket(): void
    {
        $podcast = $this->create($this->host);

        $this->assertTrue($podcast->isPending());

        $ticket = Ticket::where('ticketable_type', Podcast::class)
            ->where('ticketable_id', $podcast->id)
            ->first();

        $this->assertNotNull($ticket, 'a podcast_approval ticket should have been opened');
        $this->assertSame('podcast_approval', $ticket->ticketType->slug);
        $this->assertSame('open', $ticket->status);
        // Credited to whoever made the show, not to nobody
        $this->assertSame($this->host->id, $ticket->created_by_user_id);
        $this->assertStringContainsString('The Nightly Watch', $ticket->title);
    }

    /**
     * The migration seeds admin into auto_approve_roles_podcast: "a
     * normal user goes through creation" is the case review is for, and
     * an admin has nobody to ask.
     */
    public function test_an_admin_is_approved_at_once_and_raises_no_ticket(): void
    {
        $podcast = $this->create($this->admin);

        $this->assertTrue($podcast->isApproved());
        $this->assertSame(
            0,
            Ticket::where('ticketable_type', Podcast::class)->where('ticketable_id', $podcast->id)->count()
        );
    }

    public function test_the_auto_approve_roles_setting_decides(): void
    {
        Setting::set('auto_approve_roles_podcast', json_encode([]), 'json');
        Setting::clearCache();

        // With nobody auto-approved, even an admin goes through review
        $this->assertTrue($this->create($this->admin)->isPending());
    }

    // ----------------------------------------------------------------
    // What a pending show is visible to
    // ----------------------------------------------------------------

    public function test_a_pending_show_is_hidden_from_guests_and_strangers(): void
    {
        $podcast = $this->pendingShow();

        $this->getJson('/api/podcasts')->assertOk()->assertJsonCount(0, 'podcasts');
        $this->getJson("/api/podcasts/{$podcast->slug}")->assertNotFound();

        $this->actingAs($this->stranger, 'sanctum')
            ->getJson('/api/podcasts')
            ->assertOk()
            ->assertJsonCount(0, 'podcasts');

        $this->actingAs($this->stranger, 'sanctum')
            ->getJson("/api/podcasts/{$podcast->slug}")
            ->assertNotFound();
    }

    /**
     * The part that was broken: an author could not see their own show
     * while it waited, so there was no way to work on one.
     */
    public function test_an_author_sees_their_own_pending_show(): void
    {
        $podcast = $this->create($this->host);

        $this->actingAs($this->host, 'sanctum')
            ->getJson('/api/podcasts')
            ->assertOk()
            ->assertJsonCount(1, 'podcasts')
            ->assertJsonPath('podcasts.0.is_approved', false);

        $this->actingAs($this->host, 'sanctum')
            ->getJson("/api/podcasts/{$podcast->slug}")
            ->assertOk()
            ->assertJsonPath('podcast.slug', $podcast->slug);
    }

    public function test_a_co_author_sees_it_too(): void
    {
        $cohost = User::factory()->create();
        $podcast = $this->create($this->host, ['author_ids' => [$this->host->id, $cohost->id]]);

        $this->actingAs($cohost, 'sanctum')
            ->getJson('/api/podcasts')
            ->assertOk()
            ->assertJsonCount(1, 'podcasts');
    }

    public function test_an_author_sees_their_own_unpublished_show(): void
    {
        $podcast = $this->pendingShow(['is_published' => false]);
        $podcast->approve($this->admin);

        // Approved, but the author has not published it
        $this->assertTrue($podcast->fresh()->isApproved());

        $this->getJson('/api/podcasts')->assertOk()->assertJsonCount(0, 'podcasts');

        $this->actingAs($this->host, 'sanctum')
            ->getJson('/api/podcasts')
            ->assertOk()
            ->assertJsonCount(1, 'podcasts');
    }

    // ----------------------------------------------------------------
    // Episodes and the feed
    // ----------------------------------------------------------------

    private function episode(Podcast $show, array $attributes = []): PodcastEpisode
    {
        return $show->episodes()->create(array_merge([
            'title'        => 'Pilot',
            'audio_url'    => 'https://example.com/pilot.mp3',
            'is_published' => true,
            'published_at' => now()->subDay(),
            'created_by'   => $this->host->id,
        ], $attributes));
    }

    /**
     * A feed is cached and redistributed by podcast apps, so nothing
     * reaches one before it has been looked at.
     */
    public function test_a_pending_show_has_no_feed_until_it_is_approved(): void
    {
        $podcast = $this->create($this->host);
        $this->episode($podcast);

        $this->get("/podcast/{$podcast->slug}/feed.xml")->assertNotFound();

        $podcast->approve($this->admin);

        $this->get("/podcast/{$podcast->slug}/feed.xml")->assertOk();
    }

    public function test_episodes_of_a_pending_show_stay_out_of_the_dashboard_widget(): void
    {
        $podcast = $this->create($this->host);
        $this->episode($podcast);

        $this->getJson('/api/podcasts/latest-episodes')
            ->assertOk()
            ->assertJsonCount(0, 'episodes');

        $podcast->approve($this->admin);

        $this->getJson('/api/podcasts/latest-episodes')
            ->assertOk()
            ->assertJsonCount(1, 'episodes');
    }

    public function test_a_play_is_not_counted_on_a_show_nobody_may_reach(): void
    {
        $podcast = $this->create($this->host);
        $episode = $this->episode($podcast);

        $this->postJson("/api/podcasts/{$podcast->slug}/episodes/{$episode->slug}/play")
            ->assertNotFound();

        $this->assertSame(0, (int) $episode->fresh()->plays);
    }

    /**
     * An author is shown their own drafts; a listener is not.
     */
    public function test_an_author_sees_draft_episodes_of_their_own_show(): void
    {
        $podcast = $this->pendingShow();
        $podcast->approve($this->admin);
        $this->episode($podcast, ['title' => 'Out now']);
        $this->episode($podcast, ['title' => 'Still writing', 'is_published' => false]);

        $this->getJson("/api/podcasts/{$podcast->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'episodes');

        $this->actingAs($this->host, 'sanctum')
            ->getJson("/api/podcasts/{$podcast->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'episodes');
    }

    public function test_approving_makes_the_show_public(): void
    {
        $podcast = $this->pendingShow();

        $this->getJson('/api/podcasts')->assertOk()->assertJsonCount(0, 'podcasts');

        $podcast->approve($this->admin);

        $this->getJson('/api/podcasts')
            ->assertOk()
            ->assertJsonCount(1, 'podcasts')
            ->assertJsonPath('podcasts.0.is_approved', true);
    }

    /**
     * The admin screen is the moderation queue's neighbour, so it has to
     * keep showing what is waiting.
     */
    public function test_the_manage_list_shows_pending_shows(): void
    {
        $this->create($this->host);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/podcasts/manage')
            ->assertOk()
            ->assertJsonCount(1, 'podcasts')
            ->assertJsonPath('podcasts.0.is_approved', false);
    }
}
