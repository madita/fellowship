<?php

namespace Tests\Feature;

use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Models\Watch;
use App\Models\Wiki;
use App\Notifications\WikiUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * Watching, which forum threads and tickets now share.
 */
class WatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ── Toggling ────────────────────────────────────────────────────

    public function test_watching_a_thread_and_stopping_again(): void
    {
        $thread = $this->thread();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/watch/forum-thread/{$thread->id}")
            ->assertOk()
            ->assertJson(['watching' => true, 'watchers_count' => 1]);

        $this->assertDatabaseHas('watches', [
            'user_id'        => $this->user->id,
            'watchable_type' => ForumThread::class,
            'watchable_id'   => $thread->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/watch/forum-thread/{$thread->id}")
            ->assertOk()
            ->assertJson(['watching' => false, 'watchers_count' => 0]);
    }

    public function test_the_same_endpoint_watches_a_ticket(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/watch/ticket/{$ticket->id}")
            ->assertOk()
            ->assertJson(['watching' => true]);

        $this->assertDatabaseHas('watches', [
            'watchable_type' => Ticket::class,
            'watchable_id'   => $ticket->id,
        ]);
    }

    public function test_watching_twice_over_leaves_one_row(): void
    {
        $thread = $this->thread();

        $thread->watch($this->user);
        $thread->watch($this->user);

        $this->assertSame(1, $thread->watches()->count());
    }

    public function test_watching_needs_a_login(): void
    {
        $thread = $this->thread();

        $this->postJson("/api/watch/forum-thread/{$thread->id}")->assertUnauthorized();
        $this->getJson('/api/watching')->assertUnauthorized();
    }

    // ── The kind is an allow-list ───────────────────────────────────

    /**
     * A client that could name the class could hang a watch off any model
     * in the application and read its title back out of the list.
     */
    public function test_a_kind_that_is_not_registered_is_refused(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/watch/user/1')
            ->assertNotFound();

        $this->assertDatabaseCount('watches', 0);
    }

    public function test_a_class_name_is_not_a_kind(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/watch/' . urlencode(User::class) . '/' . $this->user->id)
            ->assertNotFound();

        $this->assertDatabaseCount('watches', 0);
    }

    public function test_watching_something_that_is_gone_is_refused(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/watch/forum-thread/99999')
            ->assertNotFound();
    }

    // ── Visibility ──────────────────────────────────────────────────

    public function test_a_private_thread_cannot_be_watched_by_a_stranger(): void
    {
        $stranger = User::factory()->create();
        $thread   = $this->thread($this->category(['is_private' => true]));

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/watch/forum-thread/{$thread->id}")
            ->assertNotFound();
    }

    public function test_its_author_can_still_watch_a_private_thread(): void
    {
        $thread = $this->thread($this->category(['is_private' => true]));

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/watch/forum-thread/{$thread->id}")
            ->assertOk()
            ->assertJson(['watching' => true]);
    }

    public function test_a_private_ticket_cannot_be_watched_by_a_stranger(): void
    {
        $stranger = User::factory()->create();
        $ticket   = $this->ticket(['is_public' => false]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/watch/ticket/{$ticket->id}")
            ->assertNotFound();
    }

    // ── The list ────────────────────────────────────────────────────

    public function test_the_list_holds_everything_whatever_its_kind(): void
    {
        $thread = $this->thread();
        $ticket = $this->ticket();

        $thread->watch($this->user);
        $ticket->watch($this->user);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/watching')
            ->assertOk();

        $kinds = collect($response->json('data'))->pluck('kind')->sort()->values()->all();

        $this->assertSame(['forum-thread', 'ticket'], $kinds);
    }

    public function test_the_list_carries_a_title_and_a_way_in(): void
    {
        $thread = $this->thread();
        $thread->watch($this->user);

        $row = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/watching')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Council of Elrond', $row['title']);
        $this->assertStringContainsString($thread->slug, $row['url']);
        $this->assertSame($thread->id, $row['watchable_id']);
    }

    public function test_the_list_can_be_narrowed_to_one_kind(): void
    {
        $this->thread()->watch($this->user);
        $this->ticket()->watch($this->user);

        $data = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/watching?kind=ticket')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('ticket', $data[0]['kind']);
    }

    public function test_the_list_is_only_your_own(): void
    {
        $other  = User::factory()->create();
        $thread = $this->thread();

        $thread->watch($other);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/watching')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Losing sight of something you watch should take it off the list, not
     * leave its title showing behind a link that 404s.
     */
    public function test_the_list_leaves_out_what_you_can_no_longer_see(): void
    {
        $category = $this->category();
        $thread   = $this->thread($category);
        $watcher  = User::factory()->create();

        $thread->watch($watcher);

        $this->actingAs($watcher, 'sanctum')
            ->getJson('/api/watching')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // The category is made private after the fact
        $category->update(['properties' => ['is_private' => true]]);

        $this->actingAs($watcher, 'sanctum')
            ->getJson('/api/watching')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Cleanup ─────────────────────────────────────────────────────

    /**
     * A morph has no foreign key to cascade through, so the trait clears
     * the rows itself — but only once the thing is really gone.
     */
    public function test_a_soft_deleted_thread_keeps_its_watchers(): void
    {
        $thread = $this->thread();
        $thread->watch($this->user);

        $thread->delete();

        $this->assertDatabaseCount('watches', 1);
    }

    public function test_really_deleting_a_thread_clears_its_watchers(): void
    {
        $thread = $this->thread();
        $thread->watch($this->user);

        $thread->forceDelete();

        $this->assertDatabaseCount('watches', 0);
    }

    public function test_deleting_a_member_clears_their_watches(): void
    {
        $thread  = $this->thread();
        $watcher = User::factory()->create();

        $thread->watch($watcher);
        $watcher->delete();

        $this->assertDatabaseCount('watches', 0);
    }

    // ── Who gets told ───────────────────────────────────────────────

    public function test_the_member_who_caused_it_is_not_told(): void
    {
        $thread = $this->thread();
        $other  = User::factory()->create();

        $thread->watch($this->user);
        $thread->watch($other);

        $recipients = $thread->watcherRecipients($this->user->id)->pluck('id')->all();

        $this->assertSame([$other->id], $recipients);
    }

    public function test_watchers_who_lost_access_are_not_told(): void
    {
        $category = $this->category(['is_private' => true]);
        $thread   = $this->thread($category);
        $stranger = User::factory()->create();

        // Written straight in: the endpoint would refuse this
        Watch::create([
            'user_id'        => $stranger->id,
            'watchable_type' => ForumThread::class,
            'watchable_id'   => $thread->id,
        ]);

        $this->assertCount(0, $thread->watcherRecipients());
    }

    public function test_a_wiki_page_can_be_watched_through_the_same_endpoint(): void
    {
        $wiki = $this->wikiPage();
        $wiki->approve($this->user);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/watch/wiki/{$wiki->id}")
            ->assertOk()
            ->assertJson(['watching' => true, 'watchers_count' => 1]);

        $this->assertDatabaseHas('watches', [
            'watchable_type' => Wiki::class,
            'watchable_id'   => $wiki->id,
        ]);
    }

    public function test_a_watched_wiki_page_reads_with_its_title_and_address(): void
    {
        $wiki = $this->wikiPage();
        $wiki->approve($this->user);
        $wiki->watch($this->user);

        $row = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/watching?kind=wiki')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('Rivendell', $row['title']);
        $this->assertSame("/wiki/{$wiki->slug}", $row['url']);
    }

    /**
     * A page waiting for approval cannot be opened, so it is not something
     * a stranger can start watching either.
     */
    public function test_a_page_awaiting_approval_cannot_be_watched_by_a_stranger(): void
    {
        $stranger = User::factory()->create();
        $wiki     = $this->wikiPage();

        $this->assertTrue($wiki->isPending());

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/watch/wiki/{$wiki->id}")
            ->assertNotFound();
    }

    public function test_its_author_can_watch_a_page_awaiting_approval(): void
    {
        $wiki = $this->wikiPage();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/watch/wiki/{$wiki->id}")
            ->assertOk()
            ->assertJson(['watching' => true]);
    }

    /**
     * Only the page's owner or an admin may edit it, so the owner does the
     * editing here and a second member does the watching.
     */
    public function test_editing_a_page_tells_the_watchers_but_not_the_editor(): void
    {
        NotificationFacade::fake();

        $wiki = $this->wikiPage();
        $wiki->approve($this->user);

        $watcher = User::factory()->create();
        $wiki->watch($watcher);
        $wiki->watch($this->user);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/wiki/{$wiki->slug}", [
                'title'   => 'Rivendell',
                'content' => 'A refuge, rewritten',
            ])
            ->assertSuccessful();

        NotificationFacade::assertSentTo($watcher, WikiUpdatedNotification::class);
        NotificationFacade::assertNotSentTo($this->user, WikiUpdatedNotification::class);
    }

    public function test_editing_a_page_starts_the_editor_watching_it(): void
    {
        NotificationFacade::fake();

        $wiki = $this->wikiPage();
        $wiki->approve($this->user);

        $this->assertFalse($wiki->isWatchedBy($this->user));

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/wiki/{$wiki->slug}", [
                'title'   => 'Rivendell',
                'content' => 'A refuge, rewritten again',
            ])
            ->assertSuccessful();

        $this->assertTrue($wiki->fresh()->isWatchedBy($this->user));
    }

    private function category(array $properties = []): Taxonomy
    {
        return Taxonomy::create([
            'term_id'    => Term::firstOrCreateByTitle('General ' . uniqid())->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => $properties,
        ]);
    }

    private function thread(?Taxonomy $category = null, ?User $author = null): ForumThread
    {
        return ForumThread::create([
            'taxonomy_id' => ($category ?? $this->category())->id,
            'user_id'     => ($author ?? $this->user)->id,
            'title'       => 'Council of Elrond',
            'slug'        => 'council-of-elrond-' . uniqid(),
            'body'        => 'Bring the ring',
        ]);
    }

    private function ticket(array $overrides = []): Ticket
    {
        $type = TicketType::firstOrCreate(
            ['slug' => 'bug'],
            ['name' => 'Bug', 'is_active' => true]
        );

        return Ticket::create(array_merge([
            'title'               => 'Something is broken',
            'description'         => 'It broke',
            'ticket_type_id'      => $type->id,
            'created_by_user_id'  => $this->user->id,
            'is_public'           => true,
        ], $overrides));
    }

    // ── The wiki, added once the feature was generic ────────────────

    private function wikiPage(array $overrides = []): Wiki
    {
        $page = Page::create(array_merge([
            'title'   => 'Rivendell',
            'slug'    => 'rivendell-' . uniqid(),
            'content' => 'A refuge',
            'user_id' => $this->user->id,
            'type'    => 'wiki',
        ], $overrides));

        return Wiki::create([
            'title'         => $page->title,
            'slug'          => $page->slug,
            'status'        => 'published',
            'wikiable_type' => Page::class,
            'wikiable_id'   => $page->id,
        ]);
    }
}
