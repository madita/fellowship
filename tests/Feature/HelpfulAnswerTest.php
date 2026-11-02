<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Models\Status\Status;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Marking the answer that helped.
 *
 * One trait behind two places: a forum reply, which is where the feature
 * started life as `is_solution`, and a comment on anything commentable,
 * which could not be marked at all before.
 */
class HelpfulAnswerTest extends TestCase
{
    use RefreshDatabase;

    protected User $asker;

    protected User $answerer;

    protected User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role('admin', 'Admin');

        $this->asker    = User::factory()->create();
        $this->answerer = User::factory()->create();
        $this->stranger = User::factory()->create();
    }

    // ── A forum reply ───────────────────────────────────────────────

    public function test_whoever_asked_can_mark_the_reply_that_answered_it(): void
    {
        $post = $this->reply();

        $this->actingAs($this->asker, 'sanctum')
            ->postJson("/api/posts/{$post->id}/helpful")
            ->assertOk()
            ->assertJson(['helpful' => true])
            ->assertJsonPath('post.is_helpful', true);

        $post->refresh();

        $this->assertTrue($post->is_helpful);
        // The boolean recorded neither of these
        $this->assertNotNull($post->helpful_at);
        $this->assertSame($this->asker->id, $post->markedHelpfulBy->id);
    }

    public function test_the_same_request_takes_the_mark_away_again(): void
    {
        $post = $this->reply();
        $post->markHelpful($this->asker);

        // The button said "Unmark Helpful" and sent exactly this, which
        // used to set the flag again rather than clear it
        $this->actingAs($this->asker, 'sanctum')
            ->postJson("/api/posts/{$post->id}/helpful")
            ->assertOk()
            ->assertJson(['helpful' => false]);

        $post->refresh();

        $this->assertFalse($post->is_helpful);
        $this->assertNull($post->helpful_at);
        $this->assertNull($post->helpful_by_id);
    }

    public function test_a_thread_has_one_answer_so_marking_another_moves_it(): void
    {
        $thread = $this->thread();
        $first  = $this->reply($thread);
        $second = $this->reply($thread);

        $first->markHelpful($this->asker);
        $second->markHelpful($this->asker);

        $this->assertFalse($first->fresh()->is_helpful);
        $this->assertTrue($second->fresh()->is_helpful);
        $this->assertSame(1, ForumPost::query()->helpful()->count());
    }

    /**
     * The reply that stops being the answer is saved on its own, not swept
     * up in a mass update that fires no model events — the search index and
     * the activity log only hear about saves.
     */
    public function test_the_reply_that_loses_the_mark_is_cleared_properly(): void
    {
        $thread = $this->thread();
        $first  = $this->reply($thread);
        $second = $this->reply($thread);

        $first->markHelpful($this->asker);
        $second->markHelpful($this->asker);

        $this->assertDatabaseHas('forum_posts', [
            'id'            => $first->id,
            'helpful_at'    => null,
            'helpful_by_id' => null,
        ]);
    }

    public function test_a_reply_in_another_thread_keeps_its_mark(): void
    {
        $mine     = $this->reply();
        $elsewhns = $this->reply($this->thread());

        $mine->markHelpful($this->asker);
        $elsewhns->markHelpful($this->asker);

        $this->assertTrue($mine->fresh()->is_helpful, 'another thread took the mark away');
    }

    public function test_a_passer_by_cannot_decide_what_answered_somebody_else(): void
    {
        $post = $this->reply();

        $this->actingAs($this->stranger, 'sanctum')
            ->postJson("/api/posts/{$post->id}/helpful")
            ->assertForbidden();

        $this->assertFalse($post->fresh()->is_helpful);
    }

    public function test_marking_needs_a_login(): void
    {
        $post = $this->reply();

        $this->postJson("/api/posts/{$post->id}/helpful")->assertUnauthorized();
    }

    public function test_whoever_looks_after_the_category_can_mark(): void
    {
        $moderator = User::factory()->create();
        $moderator->assignRole($this->role('keeper', 'Keeper'));

        $category = $this->forumCategory();
        $category->update(['properties' => ['moderate_roles' => ['keeper']]]);

        $post = $this->reply($this->thread($category));

        $this->actingAs($moderator, 'sanctum')
            ->postJson("/api/posts/{$post->id}/helpful")
            ->assertOk();

        $this->assertTrue($post->fresh()->is_helpful);
    }

    public function test_an_admin_can_mark(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->role('admin', 'Admin'));

        $post = $this->reply();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/posts/{$post->id}/helpful")
            ->assertOk();

        $this->assertTrue($post->fresh()->is_helpful);
    }

    // ── A comment on anything ───────────────────────────────────────

    public function test_whoever_the_discussion_is_about_can_mark_a_comment(): void
    {
        $status  = $this->timelinePost();
        $comment = $status->addComment($this->answerer, '<p>Try the other road</p>');

        $this->actingAs($this->asker, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/helpful")
            ->assertOk()
            ->assertJson(['helpful' => true])
            ->assertJsonPath('comment.is_helpful', true);

        $comment->refresh();

        $this->assertTrue($comment->is_helpful);
        $this->assertSame($this->asker->id, $comment->markedHelpfulBy->id);
    }

    public function test_a_passer_by_cannot_mark_a_comment(): void
    {
        $comment = $this->timelinePost()->addComment($this->answerer, '<p>Said</p>');

        $this->actingAs($this->stranger, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/helpful")
            ->assertForbidden();

        $this->assertFalse($comment->fresh()->is_helpful);
    }

    public function test_one_comment_per_discussion_holds_the_mark(): void
    {
        $status = $this->timelinePost();
        $first  = $status->addComment($this->answerer, '<p>One</p>');
        $second = $status->addComment($this->answerer, '<p>Two</p>');

        $this->actingAs($this->asker, 'sanctum')->postJson("/api/comments/{$first->id}/helpful");
        $this->actingAs($this->asker, 'sanctum')->postJson("/api/comments/{$second->id}/helpful");

        $this->assertFalse($first->fresh()->is_helpful);
        $this->assertTrue($second->fresh()->is_helpful);
        $this->assertSame($second->id, $status->fresh()->helpfulComment->id);
    }

    public function test_a_comment_on_something_else_keeps_its_mark(): void
    {
        $mine  = $this->timelinePost()->addComment($this->answerer, '<p>Here</p>');
        $other = $this->timelinePost()->addComment($this->answerer, '<p>There</p>');

        $mine->markHelpful($this->asker);
        $other->markHelpful($this->asker);

        $this->assertTrue($mine->fresh()->is_helpful, 'another discussion took the mark away');
    }

    public function test_the_mark_comes_away_again(): void
    {
        $comment = $this->timelinePost()->addComment($this->answerer, '<p>Said</p>');
        $comment->markHelpful($this->asker);

        $this->actingAs($this->asker, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/helpful")
            ->assertOk()
            ->assertJson(['helpful' => false]);

        $this->assertFalse($comment->fresh()->is_helpful);
    }

    /**
     * A wiki page has no author column of its own — the text, and whoever
     * wrote it, live on the Page behind it.
     */
    public function test_a_wiki_page_is_owned_by_whoever_wrote_the_page(): void
    {
        $wiki    = $this->approvedWiki();
        $comment = $wiki->addComment($this->answerer, '<p>The answer</p>');

        $this->actingAs($this->stranger, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/helpful")
            ->assertForbidden();

        $this->actingAs($this->asker, 'sanctum')
            ->postJson("/api/comments/{$comment->id}/helpful")
            ->assertOk();

        $this->assertTrue($comment->fresh()->is_helpful);
    }

    public function test_the_discussion_says_whether_the_reader_may_mark(): void
    {
        $status = $this->timelinePost();
        $status->addComment($this->answerer, '<p>Said</p>');

        $this->actingAs($this->asker, 'sanctum')
            ->getJson("/api/comments/status/{$status->id}")
            ->assertOk()
            ->assertJsonPath('can_mark_helpful', true);

        $this->actingAs($this->stranger, 'sanctum')
            ->getJson("/api/comments/status/{$status->id}")
            ->assertOk()
            ->assertJsonPath('can_mark_helpful', false);

        // Nobody is logged in, so nobody decides anything
        $this->getJson("/api/comments/status/{$status->id}")
            ->assertOk()
            ->assertJsonPath('can_mark_helpful', false);
    }

    public function test_a_comment_carries_the_answer_to_the_question_it_was_asked(): void
    {
        $comment = $this->timelinePost()->addComment($this->answerer, '<p>Said</p>');

        $this->assertArrayHasKey('is_helpful', $comment->toArray());
        $this->assertFalse($comment->toArray()['is_helpful']);
    }

    /**
     * Being the helpful answer is decided through the endpoint, never by a
     * field smuggled into an ordinary edit.
     */
    public function test_a_comment_cannot_mark_itself_by_being_edited(): void
    {
        $comment = $this->timelinePost()->addComment($this->answerer, '<p>Said</p>');

        $this->actingAs($this->answerer, 'sanctum')
            ->patchJson("/api/comments/{$comment->id}", [
                'content'    => '<p>Edited</p>',
                'helpful_at' => now()->toDateTimeString(),
            ])
            ->assertOk();

        $this->assertFalse($comment->fresh()->is_helpful);
    }

    public function test_deleting_the_member_who_marked_it_leaves_the_mark_standing(): void
    {
        $comment = $this->timelinePost()->addComment($this->answerer, '<p>Said</p>');
        $marker  = User::factory()->create();

        $comment->markHelpful($marker);
        $marker->forceDelete();

        $comment->refresh();

        $this->assertTrue($comment->is_helpful, 'the answer stopped being the answer');
        $this->assertNull($comment->helpful_by_id);
    }

    // ── Fixtures ────────────────────────────────────────────────────

    /**
     * display_name is not nullable here, so the Spatie shorthand will not do.
     */
    private function role(string $name, string $label): Role
    {
        return Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'api'],
            ['display_name' => $label]
        );
    }

    private function thread(?Taxonomy $category = null): ForumThread
    {
        return ForumThread::create([
            'taxonomy_id' => ($category ?? $this->forumCategory())->id,
            'user_id'     => $this->asker->id,
            'title'       => 'Which road',
            'body'        => '<p>Asking</p>',
        ]);
    }

    private function reply(?ForumThread $thread = null): ForumPost
    {
        return ForumPost::create([
            'thread_id' => ($thread ?? $this->thread())->id,
            'user_id'   => $this->answerer->id,
            'body'      => '<p>Answering</p>',
        ]);
    }

    private function forumCategory(): Taxonomy
    {
        return Taxonomy::firstOrCreate(
            ['term_id' => Term::firstOrCreateByTitle('General')->id, 'taxonomy' => 'forum_cat'],
            ['sort' => 0, 'visible' => true, 'searchable' => true, 'properties' => []]
        );
    }

    private function timelinePost(): Status
    {
        return Status::create([
            'user_id' => $this->asker->id,
            'content' => 'Which road do I take?',
        ]);
    }

    private function approvedWiki(): Wiki
    {
        $page = Page::create([
            'title'   => 'Roads',
            'slug'    => 'roads-' . uniqid(),
            'content' => 'Asking',
            'user_id' => $this->asker->id,
            'type'    => 'wiki',
        ]);

        $wiki = Wiki::create([
            'title'         => $page->title,
            'slug'          => $page->slug,
            'status'        => 'published',
            'wikiable_type' => Page::class,
            'wikiable_id'   => $page->id,
        ]);

        $wiki->approve($this->asker);

        return $wiki->fresh();
    }
}
