<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Page;
use App\Models\Status\Status;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The one comments table, shared by wiki discussions and the timeline.
 *
 * Ticket and sandbox comments keep their own tables — see the
 * create_commentable_table migration.
 */
class CommentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ── One table, two kinds ────────────────────────────────────────

    public function test_both_kinds_of_comment_live_in_one_table(): void
    {
        $wiki   = $this->approvedWiki();
        $status = $this->timelinePost();

        $wiki->addComment($this->user, '<p>On the page</p>');
        $status->addComment($this->user, '<p>On the post</p>');

        $this->assertDatabaseCount('commentable', 2);
        $this->assertSame(
            [Status::class, Wiki::class],
            Comment::query()->pluck('commentable_type')->sort()->values()->all()
        );
    }

    /**
     * timeline.comment is a timeline achievement. The wiki has none, so
     * without a check on the kind a wiki comment would earn it.
     */
    public function test_a_wiki_comment_is_not_a_timeline_comment(): void
    {
        $this->approvedWiki()->addComment($this->user, '<p>Hello</p>');

        $this->assertSame(0, Comment::where('commentable_type', Status::class)->count());
        $this->assertSame(1, Comment::where('commentable_type', Wiki::class)->count());
    }

    // ── The kind is an allow-list ───────────────────────────────────

    public function test_a_kind_that_is_not_registered_is_refused(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/comments/user/1', ['content' => '<p>Hi</p>'])
            ->assertNotFound();

        $this->assertDatabaseCount('commentable', 0);
    }

    public function test_a_class_name_is_not_a_kind(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/comments/' . urlencode(Wiki::class) . '/1', ['content' => '<p>Hi</p>'])
            ->assertNotFound();
    }

    public function test_commenting_needs_a_login(): void
    {
        $wiki = $this->approvedWiki();

        $this->postJson("/api/comments/wiki/{$wiki->id}", ['content' => '<p>Hi</p>'])
            ->assertUnauthorized();
    }

    // ── A wiki discussion ───────────────────────────────────────────

    public function test_a_topic_can_be_started_and_replied_to(): void
    {
        $wiki = $this->approvedWiki();

        $topic = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/comments/wiki/{$wiki->id}", ['content' => '<p>Should this be split?</p>'])
            ->assertCreated()
            ->json();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/comments/wiki/{$wiki->id}", [
                'content'   => '<p>I think so</p>',
                'parent_id' => $topic['id'],
            ])
            ->assertCreated();

        $body = $this->getJson("/api/comments/wiki/{$wiki->id}")->assertOk()->json();

        // One topic, carrying its reply — both counted
        $this->assertCount(1, $body['data']);
        $this->assertCount(1, $body['data'][0]['replies']);
        $this->assertSame(2, $body['count']);
    }

    /**
     * A reply has to answer something said in the same discussion, or a
     * client could graft one page's reply onto another's.
     */
    public function test_a_reply_cannot_be_grafted_onto_another_discussion(): void
    {
        $here      = $this->approvedWiki();
        $elsewhere = $this->approvedWiki();

        $theirs = $elsewhere->addComment($this->user, '<p>Over there</p>');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/comments/wiki/{$here->id}", [
                'content'   => '<p>Sneaking in</p>',
                'parent_id' => $theirs->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $here->allComments()->count());
    }

    public function test_the_markup_is_cleaned(): void
    {
        $wiki = $this->approvedWiki();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/comments/wiki/{$wiki->id}", [
                'content' => '<p>Fine</p><script>alert(1)</script>',
            ])
            ->assertCreated();

        $this->assertStringNotContainsString('<script', $wiki->allComments()->first()->content);
    }

    // ── Visibility ──────────────────────────────────────────────────

    /**
     * A page awaiting approval cannot be opened, so it has no discussion
     * a stranger can read or post to.
     */
    public function test_a_pending_page_has_no_discussion_for_a_stranger(): void
    {
        $stranger = User::factory()->create();
        $wiki     = $this->pendingWiki();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/comments/wiki/{$wiki->id}", ['content' => '<p>Hi</p>'])
            ->assertNotFound();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/comments/wiki/{$wiki->id}")
            ->assertNotFound();
    }

    public function test_its_author_can_discuss_a_pending_page(): void
    {
        $wiki = $this->pendingWiki();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/comments/wiki/{$wiki->id}", ['content' => '<p>Mine</p>'])
            ->assertCreated();
    }

    public function test_a_discussion_can_be_read_without_signing_in(): void
    {
        $wiki = $this->approvedWiki();
        $wiki->addComment($this->user, '<p>Public</p>');

        $this->getJson("/api/comments/wiki/{$wiki->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── Editing and deleting ────────────────────────────────────────

    public function test_the_author_may_edit_within_the_window(): void
    {
        $comment = $this->approvedWiki()->addComment($this->user, '<p>First go</p>');

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/comments/{$comment->id}", ['content' => '<p>Second go</p>'])
            ->assertOk();

        $this->assertStringContainsString('Second go', $comment->fresh()->content);
    }

    public function test_the_window_closes(): void
    {
        $comment = $this->approvedWiki()->addComment($this->user, '<p>Old</p>');
        $comment->forceFill(['created_at' => now()->subHour()])->save();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/comments/{$comment->id}", ['content' => '<p>Too late</p>'])
            ->assertForbidden();
    }

    public function test_a_stranger_may_not_edit_or_delete(): void
    {
        $stranger = User::factory()->create();
        $comment  = $this->approvedWiki()->addComment($this->user, '<p>Mine</p>');

        $this->actingAs($stranger, 'sanctum')
            ->patchJson("/api/comments/{$comment->id}", ['content' => '<p>Not yours</p>'])
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertForbidden();
    }

    public function test_an_admin_may_delete_anyone_s_comment(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $comment = $this->approvedWiki()->addComment($this->user, '<p>Mine</p>');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertOk();
    }

    // ── The counter, which the timeline keeps in a column ───────────

    /**
     * It used to be bumped inside Status::addComment() and dropped again
     * in the controller, so a comment created any other way — as the tests
     * did — never counted.
     */
    public function test_the_timeline_count_follows_whatever_route_made_the_comment(): void
    {
        $status = $this->timelinePost();

        $status->addComment($this->user, '<p>Through the model</p>');
        $this->assertSame(1, $status->fresh()->comments_count);

        $status->allComments()->create([
            'user_id' => $this->user->id,
            'content' => '<p>Through the relation</p>',
        ]);
        $this->assertSame(2, $status->fresh()->comments_count);
    }

    public function test_deleting_a_comment_drops_the_count(): void
    {
        $status  = $this->timelinePost();
        $comment = $status->addComment($this->user, '<p>Said</p>');

        $this->assertSame(1, $status->fresh()->comments_count);

        $comment->delete();

        $this->assertSame(0, $status->fresh()->comments_count);
    }

    public function test_a_wiki_keeps_no_counter_and_does_not_mind(): void
    {
        $wiki = $this->approvedWiki();

        $wiki->addComment($this->user, '<p>No column to bump</p>');

        $this->assertSame(1, $wiki->allComments()->count());
    }

    // ── Cleanup, which a morph has no foreign key for ───────────────

    public function test_a_soft_deleted_status_keeps_its_comments(): void
    {
        $status = $this->timelinePost();
        $status->addComment($this->user, '<p>Said</p>');

        $status->delete();

        $this->assertDatabaseCount('commentable', 1);
    }

    public function test_really_deleting_a_status_clears_its_comments(): void
    {
        $status = $this->timelinePost();
        $status->addComment($this->user, '<p>Said</p>');

        $status->forceDelete();

        $this->assertDatabaseCount('commentable', 0);
    }

    public function test_deleting_a_member_clears_their_comments(): void
    {
        $wiki   = $this->approvedWiki();
        $talker = User::factory()->create();

        $wiki->addComment($talker, '<p>Said</p>');
        $talker->delete();

        $this->assertDatabaseCount('commentable', 0);
    }

    // ── Fixtures ────────────────────────────────────────────────────

    private function approvedWiki(): Wiki
    {
        $wiki = $this->pendingWiki();
        $wiki->approve($this->user);

        return $wiki->fresh();
    }

    private function pendingWiki(): Wiki
    {
        $page = Page::create([
            'title'   => 'Rivendell',
            'slug'    => 'rivendell-' . uniqid(),
            'content' => 'A refuge',
            'user_id' => $this->user->id,
            'type'    => 'wiki',
        ]);

        return Wiki::create([
            'title'         => $page->title,
            'slug'          => $page->slug,
            'status'        => 'published',
            'wikiable_type' => Page::class,
            'wikiable_id'   => $page->id,
        ]);
    }

    private function timelinePost(): Status
    {
        return Status::create([
            'user_id' => $this->user->id,
            'content' => 'Second breakfast',
        ]);
    }
}
