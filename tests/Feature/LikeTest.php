<?php

namespace Tests\Feature;

use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Like;
use App\Models\Status\Status;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one likes table, shared by forum posts and timeline statuses.
 *
 * Neither feature had an endpoint test before this: the forum's like
 * routes were uncovered and status likes had no test at all.
 */
class LikeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ── One table, two kinds ────────────────────────────────────────

    public function test_both_kinds_of_like_live_in_one_table(): void
    {
        $post   = $this->forumPost();
        $status = $this->timelineStatus();

        $post->like($this->user);
        $status->like($this->user);

        $this->assertDatabaseCount('likeable', 2);
        $this->assertSame(
            [ForumPost::class, Status::class],
            Like::query()->pluck('likeable_type')->sort()->values()->all()
        );
    }

    /**
     * forum.post.liked credits whoever wrote the post. The timeline has no
     * achievement of its own, so without a check on the kind a liked
     * status would earn a forum achievement for nobody in particular.
     */
    public function test_a_liked_status_is_not_a_liked_forum_post(): void
    {
        $this->timelineStatus()->like($this->user);

        $this->assertSame(0, Like::where('likeable_type', ForumPost::class)->count());
        $this->assertSame(1, Like::where('likeable_type', Status::class)->count());
    }

    public function test_the_same_thing_cannot_be_liked_twice(): void
    {
        $post = $this->forumPost();

        $post->like($this->user);
        $post->like($this->user);

        $this->assertSame(1, $post->likes()->count());
    }

    // ── The counters, which the two hosts name differently ──────────

    public function test_a_forum_post_keeps_its_count_in_like_count(): void
    {
        $post = $this->forumPost();

        $post->like($this->user);

        $this->assertSame(1, $post->fresh()->like_count);

        $post->unlike($this->user);

        $this->assertSame(0, $post->fresh()->like_count);
    }

    public function test_a_status_keeps_its_count_in_likes_count(): void
    {
        $status = $this->timelineStatus();

        $status->like($this->user);

        $this->assertSame(1, $status->fresh()->likes_count);

        $status->unlike($this->user);

        $this->assertSame(0, $status->fresh()->likes_count);
    }

    /** Liking twice must not count twice, and unliking twice must not go negative. */
    public function test_the_counter_does_not_drift(): void
    {
        $post = $this->forumPost();

        $post->like($this->user);
        $post->like($this->user);

        $this->assertSame(1, $post->fresh()->like_count);

        $post->unlike($this->user);
        $post->unlike($this->user);

        $this->assertSame(0, $post->fresh()->like_count);
    }

    public function test_toggling_reports_and_records_the_new_state(): void
    {
        $post = $this->forumPost();

        $this->assertTrue($post->toggleLike($this->user));
        $this->assertSame(1, $post->fresh()->like_count);

        $this->assertFalse($post->toggleLike($this->user));
        $this->assertSame(0, $post->fresh()->like_count);
    }

    // ── The endpoints, and the keys the client reads ────────────────

    public function test_the_forum_endpoints_like_and_unlike(): void
    {
        $post = $this->forumPost();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/like")
            ->assertOk()
            ->assertJson(['is_liked' => true, 'like_count' => 1]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/posts/{$post->id}/like")
            ->assertOk()
            ->assertJson(['is_liked' => false, 'like_count' => 0]);
    }

    public function test_the_status_endpoint_toggles(): void
    {
        $status = $this->timelineStatus();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/statuses/{$status->id}/like")
            ->assertOk()
            ->assertJson(['liked' => true, 'likes_count' => 1]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/statuses/{$status->id}/like")
            ->assertOk()
            ->assertJson(['liked' => false, 'likes_count' => 0]);
    }

    public function test_liking_needs_a_login(): void
    {
        $post   = $this->forumPost();
        $status = $this->timelineStatus();

        $this->postJson("/api/posts/{$post->id}/like")->assertUnauthorized();
        $this->postJson("/api/statuses/{$status->id}/like")->assertUnauthorized();
    }

    /**
     * A forum post reports `is_liked` and a status `is_liked_by_me`. Two
     * names for one thing, both read by the client, so both stay.
     */
    public function test_a_thread_says_which_posts_the_member_liked(): void
    {
        $post = $this->forumPost();
        $post->like($this->user);

        $thread = $post->thread;
        $slug   = $thread->category->term->slug;

        $posts = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/forums/{$slug}/threads/{$thread->slug}")
            ->assertOk()
            ->json('posts.data');

        $this->assertTrue(collect($posts)->firstWhere('id', $post->id)['is_liked']);
    }

    public function test_a_status_says_whether_the_member_liked_it(): void
    {
        $status = $this->timelineStatus();
        $status->like($this->user);

        $payload = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/statuses')
            ->assertOk()
            ->json('data.0');

        $this->assertTrue($payload['is_liked_by_me']);
        $this->assertSame(1, $payload['likes_count']);
    }

    // ── Cleanup, which a morph has no foreign key for ───────────────

    public function test_a_soft_deleted_post_keeps_its_likes(): void
    {
        $post = $this->forumPost();
        $post->like($this->user);

        $post->delete();

        $this->assertDatabaseCount('likeable', 1);
    }

    public function test_really_deleting_a_post_clears_its_likes(): void
    {
        $post = $this->forumPost();
        $post->like($this->user);

        $post->forceDelete();

        $this->assertDatabaseCount('likeable', 0);
    }

    public function test_deleting_a_member_clears_their_likes(): void
    {
        $post  = $this->forumPost();
        $liker = User::factory()->create();

        $post->like($liker);
        $liker->delete();

        $this->assertDatabaseCount('likeable', 0);
    }

    // ── Fixtures ────────────────────────────────────────────────────

    private function forumPost(): ForumPost
    {
        $category = Taxonomy::create([
            'term_id'    => Term::firstOrCreateByTitle('General ' . uniqid())->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => [],
        ]);

        $thread = ForumThread::create([
            'taxonomy_id' => $category->id,
            'user_id'     => $this->user->id,
            'title'       => 'Council of Elrond',
            'slug'        => 'council-' . uniqid(),
            'body'        => 'Bring the ring',
        ]);

        return ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->user->id,
            'body'      => '<p>A reply</p>',
        ]);
    }

    private function timelineStatus(): Status
    {
        return Status::create([
            'user_id' => $this->user->id,
            'content' => 'Second breakfast',
        ]);
    }
}
