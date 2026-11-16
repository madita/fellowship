<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Services\AchievementService;
use App\Support\Ranks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A member's public page, and the rank shown beside their name in the
 * forum.
 */
class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Ranks::forget();
        $this->member = User::factory()->create(['username' => 'frodo', 'email' => 'frodo@example.test']);
    }

    private function givePoints(int $points): void
    {
        $achievement = Achievement::create([
            'key'       => 'points-' . uniqid(),
            'en'        => ['name' => "Worth {$points}"],
            'points'    => $points,
            'trigger'   => 'manual',
            'threshold' => 1,
        ]);

        app(AchievementService::class)->award($this->member, $achievement);
        Ranks::forget();
    }

    private function forumCategory(): Taxonomy
    {
        return Taxonomy::firstOrCreate(
            ['term_id' => Term::firstOrCreateByTitle('General')->id, 'taxonomy' => 'forum_cat'],
            ['sort' => 0, 'visible' => true, 'searchable' => true, 'properties' => []]
        );
    }

    public function test_anyone_can_read_a_members_page(): void
    {
        $this->givePoints(150);

        $data = $this->getJson('/api/members/frodo')->assertOk()->json('data');

        $this->assertSame('frodo', $data['username']);
        $this->assertSame(150, $data['points']);
        $this->assertSame('Regular', $data['rank']['name']);
        $this->assertNotNull($data['member_since']);
    }

    public function test_a_members_page_does_not_hand_out_their_email(): void
    {
        $body = $this->getJson('/api/members/frodo')->assertOk()->getContent();

        $this->assertStringNotContainsString('frodo@example.test', $body);
    }

    public function test_the_name_is_matched_however_it_is_typed(): void
    {
        $this->getJson('/api/members/FRODO')->assertOk()->assertJsonPath('data.username', 'frodo');
    }

    public function test_a_name_nobody_holds_is_not_found(): void
    {
        $this->getJson('/api/members/nobody')->assertNotFound();
    }

    public function test_a_members_page_counts_what_they_have_written(): void
    {
        $thread = ForumThread::create([
            'taxonomy_id' => $this->forumCategory()->id,
            'user_id'     => $this->member->id,
            'title'       => 'Hello',
            'body'        => '<p>First thread</p>',
        ]);

        ForumPost::create(['thread_id' => $thread->id, 'user_id' => $this->member->id, 'body' => '<p>A reply</p>']);

        $counts = $this->getJson('/api/members/frodo')->assertOk()->json('data.counts');

        $this->assertSame(1, $counts['threads']);
        $this->assertSame(1, $counts['posts']);
    }

    public function test_a_members_page_shows_the_badges_they_earned(): void
    {
        $this->givePoints(10);

        $this->getJson('/api/members/frodo')
            ->assertOk()
            ->assertJsonCount(1, 'data.achievements')
            ->assertJsonPath('data.achievements.0.points', 10);
    }

    /**
     * The forum is where the rank is shown, so the payload has to carry it.
     */
    public function test_a_forum_thread_carries_each_authors_rank(): void
    {
        $this->givePoints(300);

        $thread = ForumThread::create([
            'taxonomy_id' => $this->forumCategory()->id,
            'user_id'     => $this->member->id,
            'title'       => 'Hello',
            'body'        => '<p>First thread</p>',
        ]);

        ForumPost::create(['thread_id' => $thread->id, 'user_id' => $this->member->id, 'body' => '<p>A reply</p>']);

        $body = $this->getJson("/api/forums/{$this->forumCategory()->term->slug}/threads/{$thread->slug}")
            ->assertOk();

        $body->assertJsonPath('posts.data.0.author.rank.name', 'Veteran');
        $body->assertJsonPath('thread.author.rank.name', 'Veteran');
    }

    public function test_a_members_page_shows_the_last_of_each_kind_of_thing_they_made(): void
    {
        $thread = ForumThread::create([
            'taxonomy_id' => $this->forumCategory()->id,
            'user_id'     => $this->member->id,
            'title'       => 'Second breakfast',
            'body'        => '<p>On the subject of</p>',
        ]);

        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->member->id,
            'body'      => '<p>I quite agree</p>',
        ]);

        \App\Models\Status\Status::create(['user_id' => $this->member->id, 'content' => 'Off to Bree']);

        $recent = $this->getJson('/api/members/frodo')->assertOk()->json('data.recent');

        $this->assertSame('Second breakfast', $recent['forum'][0]['title']);
        $this->assertSame('I quite agree', $recent['forum'][0]['excerpt']);
        $this->assertStringContainsString($thread->slug, $recent['forum'][0]['url']);

        $this->assertSame('Off to Bree', $recent['timeline'][0]['excerpt']);

        // Kinds with nothing in them are still there, just empty
        $this->assertSame([], $recent['wiki']);
        $this->assertSame([], $recent['gallery']);
    }

    /**
     * A private forum stays private, whoever wrote in it.
     */
    public function test_a_members_page_leaves_out_posts_from_private_forums(): void
    {
        $private = Taxonomy::create([
            'term_id'    => Term::firstOrCreateByTitle('Council')->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => false,
            'properties' => ['is_private' => true],
        ]);

        $thread = ForumThread::create([
            'taxonomy_id' => $private->id,
            'user_id'     => $this->member->id,
            'title'       => 'Secret council',
            'body'        => '<p>Hush</p>',
        ]);

        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->member->id,
            'body'      => '<p>Nobody must know</p>',
        ]);

        $body = $this->getJson('/api/members/frodo')->assertOk();

        $this->assertSame([], $body->json('data.recent.forum'));
        $this->assertStringNotContainsString('Nobody must know', $body->getContent());
    }

    public function test_a_members_page_leaves_out_wiki_pages_still_waiting_for_approval(): void
    {
        $page = $this->member->pages()->create([
            'title'        => 'Draft page',
            'content'      => 'Not yet',
            'sign_in_only' => 0,
            'published_at' => now(),
        ]);

        $page->wikiable()->save(new \App\Models\Wiki(['title' => 'Draft page', 'slug' => 'draft-page']));

        $body = $this->getJson('/api/members/frodo')->assertOk();

        $this->assertSame([], $body->json('data.recent.wiki'));
        $this->assertSame(0, $body->json('data.counts.wiki_pages'));
    }

    public function test_a_member_with_no_rank_yet_simply_has_none(): void
    {
        // Nothing earned, and the lowest rung asks for more than nothing
        \App\Models\Rank::query()->update(['points_required' => 50]);
        Ranks::forget();

        $this->getJson('/api/members/frodo')->assertOk()->assertJsonPath('data.rank', null);
    }
}
