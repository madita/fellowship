<?php

namespace Tests\Feature;

use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Sandbox\Sandbox;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Support\Activities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the site records, and who may read it back.
 *
 * There were no tests for any of this before: four activity() calls in
 * three forum controllers, and a public endpoint that served whole post
 * bodies to anyone.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->user = User::factory()->create();
    }

    // ── Writing ─────────────────────────────────────────────────────

    public function test_a_thread_and_its_reply_are_recorded(): void
    {
        $thread = $this->thread();
        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->user->id,
            'body'      => '<p>A reply</p>',
        ]);

        $events = Activity::where('log_name', Activities::FORUM)->pluck('event')->all();

        $this->assertContains('thread_created', $events);
        $this->assertContains('post_created', $events);
    }

    /**
     * Every entry carries a title and a url, which is what lets one feed
     * component render every feature instead of a switch per event.
     */
    public function test_every_entry_carries_a_title_and_a_way_in(): void
    {
        $thread = $this->thread();

        $entry = Activity::where('event', 'thread_created')->firstOrFail();

        $this->assertSame($thread->title, $entry->properties['title']);
        $this->assertStringContainsString($thread->slug, $entry->properties['url']);
    }

    public function test_the_member_behind_it_is_recorded(): void
    {
        $this->thread();

        $entry = Activity::where('event', 'thread_created')->firstOrFail();

        $this->assertSame($this->user->id, (int) $entry->causer_id);
    }

    /**
     * Recording must never break what it is recording — the same bargain
     * the achievements make.
     */
    public function test_a_subject_that_cannot_describe_itself_is_survivable(): void
    {
        Activities::record(Activities::FORUM, 'odd_event', null, $this->user);

        $this->assertSame(0, Activity::where('event', 'odd_event')->count());
    }

    // ── Reading ─────────────────────────────────────────────────────

    public function test_the_feed_can_be_narrowed_to_one_log(): void
    {
        $this->thread();

        $data = $this->getJson('/api/activity?log=' . Activities::FORUM)
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($data);
        $this->assertSame(Activities::FORUM, $data[0]['log']);
    }

    public function test_an_unknown_log_is_refused_rather_than_answered_empty(): void
    {
        $this->getJson('/api/activity?log=not-a-log')
            ->assertStatus(422);
    }

    public function test_the_feed_can_be_narrowed_to_one_subject(): void
    {
        $first  = $this->thread(null, 'The first matter');
        $second = $this->thread(null, 'The second matter');

        $data = $this->getJson('/api/activity?' . http_build_query([
            'subject'    => 'thread',
            'subject_id' => $second->id,
        ]))->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($second->title, $data[0]['title']);
        $this->assertNotSame($first->title, $data[0]['title']);
    }

    /**
     * The subject is asked for by short name, so a class name off the
     * request never reaches a where clause — the same rule the relateable,
     * watchable and commentable registries follow.
     */
    public function test_a_subject_is_asked_for_by_name_not_by_class(): void
    {
        $this->thread();

        $this->getJson('/api/activity?subject=' . urlencode(ForumThread::class))
            ->assertStatus(422);

        $this->getJson('/api/activity?subject=thread')->assertOk();
    }

    /**
     * The endpoint used to eager load the subject and serialise it whole,
     * so an anonymous caller got entire forum post bodies back.
     */
    public function test_the_feed_does_not_hand_out_the_subject_itself(): void
    {
        $thread = $this->thread();
        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->user->id,
            'body'      => '<p>SECRET-BODY-TEXT</p>',
        ]);

        $response = $this->getJson('/api/activity')->assertOk();

        $response->assertJsonMissing(['subject' => []]);
        $this->assertStringNotContainsString('SECRET-BODY-TEXT', $response->getContent());
    }

    public function test_the_causer_is_reduced_to_a_name(): void
    {
        $this->thread();

        $row = $this->getJson('/api/activity')->assertOk()->json('data.0');

        $this->assertSame(['id', 'username'], array_keys($row['causer']));
    }

    // ── Who may know what happened ──────────────────────────────────

    /**
     * A private category's threads were listed, with their titles, to
     * anyone who asked.
     */
    public function test_activity_in_a_private_category_is_not_listed_to_a_stranger(): void
    {
        $thread = $this->thread($this->category(['is_private' => true]));

        $titles = collect($this->getJson('/api/activity')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertFalse($titles->contains($thread->title));
    }

    public function test_its_author_still_sees_it(): void
    {
        $thread = $this->thread($this->category(['is_private' => true]));

        $titles = collect(
            $this->actingAs($this->user, 'sanctum')->getJson('/api/activity')->assertOk()->json('data')
        )->pluck('title');

        $this->assertTrue($titles->contains($thread->title));
    }

    /**
     * A reply inherits the question from the thread it is in — without
     * that, a post in a private category leaked its thread title.
     */
    public function test_a_reply_in_a_private_category_is_hidden_too(): void
    {
        $thread = $this->thread($this->category(['is_private' => true]));
        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->user->id,
            'body'      => '<p>Quiet</p>',
        ]);

        $events = collect($this->getJson('/api/activity')->assertOk()->json('data'))
            ->pluck('event');

        $this->assertFalse($events->contains('post_created'));
    }

    public function test_a_private_sandbox_is_not_listed_to_a_guest(): void
    {
        $sandbox = Sandbox::create([
            'uuid'       => (string) Str::uuid(),
            'user_id'    => $this->user->id,
            'title'      => 'Hidden workshop',
            'content'    => '<p>Draft</p>',
            'visibility' => 'private',
        ]);

        $titles = collect($this->getJson('/api/activity')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertFalse($titles->contains($sandbox->title));
    }

    public function test_an_entry_whose_subject_is_gone_is_dropped(): void
    {
        $thread = $this->thread();

        $thread->forceDelete();

        $titles = collect($this->getJson('/api/activity')->assertOk()->json('data'))
            ->pluck('title');

        $this->assertFalse($titles->contains('Council of Elrond'));
    }

    // ── Fixtures ────────────────────────────────────────────────────

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

    private function thread(?Taxonomy $category = null, string $title = 'Council of Elrond'): ForumThread
    {
        return ForumThread::create([
            'taxonomy_id' => ($category ?? $this->category())->id,
            'user_id'     => $this->user->id,
            'title'       => $title,
            'slug'        => 'council-' . uniqid(),
            'body'        => 'Bring the ring',
        ]);
    }
}
