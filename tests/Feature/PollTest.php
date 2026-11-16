<?php

namespace Tests\Feature;

use App\Models\Forum\ForumThread;
use App\Models\Poll\Poll;
use App\Models\Status\Status;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Polls attached inline to forum threads and timeline statuses, the
 * shared poll rules, voting, the standalone attach endpoint's author
 * check and the admin poll endpoints.
 */
class PollTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $other;

    protected User $admin;

    protected Taxonomy $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->user  = User::factory()->create();
        $this->other = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $term           = Term::firstOrCreateByTitle('General');
        $this->category = Taxonomy::create([
            'term_id'    => $term->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => ['is_private' => false, 'is_locked' => false],
        ]);
    }

    private function pollInput(array $overrides = []): array
    {
        return array_merge([
            'title'       => 'Tabs or spaces?',
            'description' => 'Settle it once and for all',
            'type'        => 'single',
            'anonymous'   => false,
            'closes_at'   => null,
            'options'     => ['Tabs', 'Spaces'],
        ], $overrides);
    }

    private function createThread(?User $author = null): ForumThread
    {
        return ForumThread::create([
            'taxonomy_id' => $this->category->id,
            'user_id'     => ($author ?? $this->user)->id,
            'title'       => 'Existing thread',
            'body'        => '<p>A plain but long enough opening post.</p>',
        ]);
    }

    private function createPollOn($pollable, ?User $creator = null, array $overrides = []): Poll
    {
        $creator = $creator ?? $this->user;
        $poll    = Poll::create([
            'pollable_type' => get_class($pollable),
            'pollable_id'   => $pollable->id,
            'title'         => $overrides['title'] ?? 'Which?',
            'type'          => $overrides['type'] ?? 'single',
            'anonymous'     => false,
            'closes_at'     => $overrides['closes_at'] ?? null,
            'created_by'    => $creator->id,
        ]);
        foreach (['One', 'Two', 'Three'] as $i => $text) {
            $poll->options()->create(['option_text' => $text, 'position' => $i]);
        }

        return $poll;
    }

    // ---- Forum threads -------------------------------------------------

    public function test_thread_can_be_created_with_a_poll_and_show_returns_it(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/forums/{$this->category->id}/threads", [
                'title' => 'Thread with poll',
                'body'  => '<p>Opening post long enough for the spam filter.</p>',
                'poll'  => $this->pollInput(),
            ])
            ->assertStatus(201);

        $poll = $response->json('poll');
        $this->assertSame('Tabs or spaces?', $poll['title']);
        $this->assertSame(ForumThread::class, $poll['pollable_type']);
        $this->assertSame($response->json('id'), $poll['pollable_id']);
        $this->assertCount(2, $poll['options']);
        $this->assertSame(['Tabs', 'Spaces'], array_column($poll['options'], 'option_text'));
        $this->assertSame($this->user->username, $poll['creator']['username']);
        $this->assertTrue($poll['can_edit']);
        $this->assertTrue($poll['can_delete']);
        $this->assertTrue($poll['is_open']);
        $this->assertFalse($poll['has_voted']);
        $this->assertSame(0, $poll['total_votes']);

        $this->assertDatabaseHas('polls', ['pollable_id' => $response->json('id'), 'created_by' => $this->user->id]);

        $slug = $response->json('slug');
        $show = $this->actingAs($this->other, 'sanctum')
            ->getJson("/api/forums/general/threads/{$slug}")
            ->assertStatus(200);

        $this->assertSame($poll['id'], $show->json('thread.poll.id'));
        $this->assertFalse($show->json('thread.poll.can_edit'));
        $this->assertArrayNotHasKey('latest_poll', $show->json('thread'));
    }

    public function test_thread_without_poll_has_null_poll(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/forums/{$this->category->id}/threads", [
                'title' => 'Plain thread',
                'body'  => '<p>Opening post long enough for the spam filter.</p>',
            ])
            ->assertStatus(201);

        $this->assertNull($response->json('poll'));

        $this->getJson("/api/forums/general/threads/{$response->json('slug')}")
            ->assertStatus(200)
            ->assertJsonPath('thread.poll', null);
    }

    public function test_invalid_inline_poll_is_rejected_and_no_thread_is_created(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/forums/{$this->category->id}/threads", [
                'title' => 'Thread with bad poll',
                'body'  => '<p>Opening post long enough for the spam filter.</p>',
                'poll'  => $this->pollInput(['options' => ['Only one']]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['poll.options']);

        $this->assertSame(0, ForumThread::count());
    }

    // ---- Statuses ------------------------------------------------------

    public function test_status_can_be_created_with_a_poll_as_json_string_and_index_carries_it(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->post('/api/statuses', [
                'content' => 'Voting time!',
                'poll'    => json_encode($this->pollInput(['type' => 'multiple', 'options' => ['A', 'B', 'C']])),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $poll = $response->json('poll');
        $this->assertNotNull($poll);
        $this->assertSame('multiple', $poll['type']);
        $this->assertSame(Status::class, $poll['pollable_type']);
        $this->assertSame($response->json('id'), $poll['pollable_id']);
        $this->assertCount(3, $poll['options']);
        $this->assertArrayNotHasKey('latest_poll', $response->json());

        // A second status without a poll
        $this->actingAs($this->user, 'sanctum')
            ->post('/api/statuses', ['content' => 'No poll here'], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('poll', null);

        $index = $this->actingAs($this->other, 'sanctum')->getJson('/api/statuses')->assertStatus(200);
        $byId  = collect($index->json('data'))->keyBy('id');
        $this->assertSame($poll['id'], $byId[$response->json('id')]['poll']['id']);
        $this->assertFalse($byId[$response->json('id')]['poll']['can_edit']);
        $this->assertNull($byId->first(fn ($s) => $s['content'] === 'No poll here')['poll']);

        $this->getJson("/api/statuses/{$response->json('id')}")
            ->assertStatus(200)
            ->assertJsonPath('poll.id', $poll['id']);
    }

    public function test_status_with_invalid_poll_json_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->post('/api/statuses', [
                'content' => 'Broken poll',
                'poll'    => '{not json',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['poll']);

        $this->actingAs($this->user, 'sanctum')
            ->post('/api/statuses', [
                'content' => 'Poll closing in the past',
                'poll'    => json_encode($this->pollInput(['closes_at' => now()->subDay()->toIso8601String()])),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['poll.closes_at']);

        $this->assertSame(0, Status::count());
        $this->assertSame(0, Poll::count());
    }

    // ---- Poll rules ----------------------------------------------------

    public function test_poll_rules_options_count_closes_at_and_type(): void
    {
        $thread = $this->createThread();
        $base   = ['pollable_type' => ForumThread::class, 'pollable_id' => $thread->id];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', $base + $this->pollInput(['options' => ['Just one']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['options']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', $base + $this->pollInput(['options' => array_map(fn ($i) => "Option {$i}", range(1, 11))]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['options']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', $base + $this->pollInput(['options' => ['Fine', '']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['options.1']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', $base + $this->pollInput(['closes_at' => now()->subHour()->toIso8601String()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closes_at']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', $base + $this->pollInput(['type' => 'ranked']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        // Legacy { option_text } objects are still accepted, type defaults to single
        $created = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', $base + [
                'title'   => 'Legacy shape',
                'options' => [['option_text' => 'Yes'], ['option_text' => 'No']],
            ])
            ->assertStatus(201);
        $this->assertSame('single', $created->json('poll.type'));
        $this->assertSame(['Yes', 'No'], array_column($created->json('poll.options'), 'option_text'));
    }

    public function test_single_choice_polls_accept_one_vote_multiple_choice_accept_many(): void
    {
        $thread   = $this->createThread();
        $single   = $this->createPollOn($thread);
        $multiple = $this->createPollOn($thread, null, ['type' => 'multiple']);

        $singleIds = $single->options->pluck('id')->all();
        $multiIds  = $multiple->options->pluck('id')->all();

        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/polls/{$single->id}/vote", ['option_ids' => [$singleIds[0], $singleIds[1]]])
            ->assertStatus(422);

        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/polls/{$single->id}/vote", ['option_ids' => [$singleIds[0]]])
            ->assertStatus(200)
            ->assertJsonPath('poll.has_voted', true);

        $response = $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/polls/{$multiple->id}/vote", ['option_ids' => [$multiIds[0], $multiIds[2]]])
            ->assertStatus(200);
        $this->assertEqualsCanonicalizing([$multiIds[0], $multiIds[2]], $response->json('poll.user_votes'));
        $this->assertSame(2, $response->json('poll.total_votes'));

        // Options from another poll are refused
        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/polls/{$single->id}/vote", ['option_ids' => [$multiIds[0]]])
            ->assertStatus(422);
    }

    public function test_voting_updates_has_voted_and_results(): void
    {
        $thread = $this->createThread();
        $poll   = $this->createPollOn($thread);
        $ids    = $poll->options->pluck('id')->all();

        $vote = $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", ['option_ids' => [$ids[1]]])
            ->assertStatus(200);

        $this->assertTrue($vote->json('poll.has_voted'));
        $this->assertSame([$ids[1]], $vote->json('poll.user_votes'));
        $this->assertSame(1, $vote->json('poll.total_votes'));
        $results = collect($vote->json('poll.results'))->keyBy('id');
        $this->assertSame(1, $results[$ids[1]]['votes']);
        $this->assertSame(100.0, (float) $results[$ids[1]]['percentage']);
        $this->assertSame(0, $results[$ids[0]]['votes']);
        $this->assertArrayHasKey('can_edit', $vote->json('poll'));

        // Changing the vote replaces the previous one
        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", ['option_ids' => [$ids[0]]])
            ->assertStatus(200)
            ->assertJsonPath('poll.user_votes', [$ids[0]])
            ->assertJsonPath('poll.total_votes', 1);

        // Another user's vote shows up in results, the thread payload reflects everything
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", ['option_ids' => [$ids[0]]])
            ->assertStatus(200)
            ->assertJsonPath('poll.total_votes', 2);

        $show = $this->actingAs($this->other, 'sanctum')
            ->getJson("/api/forums/general/threads/{$thread->slug}")
            ->assertStatus(200);
        $this->assertTrue($show->json('thread.poll.has_voted'));
        $this->assertSame(2, $show->json('thread.poll.total_votes'));
        $this->assertSame(100.0, (float) collect($show->json('thread.poll.results'))->keyBy('id')[$ids[0]]['percentage']);

        // Unvote
        $unvote = $this->actingAs($this->other, 'sanctum')
            ->deleteJson("/api/polls/{$poll->id}/vote")
            ->assertStatus(200);
        $this->assertFalse($unvote->json('poll.has_voted'));
        $this->assertSame(1, $unvote->json('poll.total_votes'));

        // Closed polls do not accept votes
        $poll->update(['closes_at' => now()->subMinute()]);
        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/polls/{$poll->id}/vote", ['option_ids' => [$ids[0]]])
            ->assertStatus(422);
    }

    // ---- Standalone attach ---------------------------------------------

    public function test_only_the_author_or_an_admin_can_attach_a_poll_via_polls_endpoint(): void
    {
        $thread = $this->createThread($this->user);
        $status = Status::create(['user_id' => $this->user->id, 'content' => 'Mine']);

        foreach ([[ForumThread::class, $thread->id], [Status::class, $status->id]] as [$type, $id]) {
            $payload = ['pollable_type' => $type, 'pollable_id' => $id] + $this->pollInput();

            $this->actingAs($this->other, 'sanctum')->postJson('/api/polls', $payload)->assertStatus(403);
            $this->actingAs($this->user, 'sanctum')->postJson('/api/polls', $payload)->assertStatus(201);
            $this->actingAs($this->admin, 'sanctum')->postJson('/api/polls', $payload)->assertStatus(201);
        }

        $this->assertSame(4, Poll::count());

        // The removed Forum model is no longer an accepted pollable type
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', ['pollable_type' => 'App\\Models\\Forum\\Forum', 'pollable_id' => 1] + $this->pollInput())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pollable_type']);

        // Non-existent pollable
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/polls', ['pollable_type' => ForumThread::class, 'pollable_id' => 9999] + $this->pollInput())
            ->assertStatus(422);
    }

    public function test_only_creator_or_admin_can_delete_a_poll(): void
    {
        $thread = $this->createThread();
        $poll   = $this->createPollOn($thread, $this->user);

        $this->actingAs($this->other, 'sanctum')->deleteJson("/api/polls/{$poll->id}")->assertStatus(403);
        $this->actingAs($this->user, 'sanctum')->deleteJson("/api/polls/{$poll->id}")->assertStatus(200);
        $this->assertDatabaseMissing('polls', ['id' => $poll->id]);

        $poll = $this->createPollOn($thread, $this->user);
        $this->actingAs($this->admin, 'sanctum')->deleteJson("/api/polls/{$poll->id}")->assertStatus(200);
        $this->assertDatabaseMissing('polls', ['id' => $poll->id]);
    }

    // ---- Admin ---------------------------------------------------------

    public function test_admin_poll_endpoints_require_an_admin(): void
    {
        $poll = $this->createPollOn($this->createThread());

        $this->getJson('/api/admin/polls')->assertStatus(401);
        $this->getJson('/api/admin/polls/stats')->assertStatus(401);
        $this->patchJson("/api/admin/polls/{$poll->id}/close")->assertStatus(401);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/polls')->assertStatus(403);
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/polls/stats')->assertStatus(403);
        $this->actingAs($this->user, 'sanctum')->patchJson("/api/admin/polls/{$poll->id}/close")->assertStatus(403);
        $this->actingAs($this->user, 'sanctum')->patchJson("/api/admin/polls/{$poll->id}/reopen")->assertStatus(403);
        $this->actingAs($this->user, 'sanctum')->deleteJson("/api/admin/polls/{$poll->id}")->assertStatus(403);

        $this->assertDatabaseHas('polls', ['id' => $poll->id]);
    }

    public function test_admin_index_lists_polls_with_pollable_summaries_and_filters(): void
    {
        $thread = $this->createThread();
        $status = Status::create(['user_id' => $this->user->id, 'content' => str_repeat('Long status text ', 10)]);

        $threadPoll = $this->createPollOn($thread, null, ['title' => 'Thread poll']);
        $closedPoll = $this->createPollOn($status, null, ['title' => 'Closed status poll', 'closes_at' => now()->subDay(), 'type' => 'multiple']);

        $index = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls')->assertStatus(200);
        $this->assertSame(2, $index->json('meta.total'));
        $this->assertSame(1, $index->json('meta.current_page'));
        $this->assertArrayHasKey('last_page', $index->json('meta'));
        $this->assertArrayHasKey('per_page', $index->json('meta'));

        // Newest first
        $this->assertSame($closedPoll->id, $index->json('data.0.id'));
        $this->assertSame('status', $index->json('data.0.pollable.type'));
        $this->assertSame('/timeline', $index->json('data.0.pollable.url'));
        // First 80 characters of the status content (plus an ellipsis)
        $this->assertSame(rtrim(mb_substr($status->content, 0, 80)) . '...', $index->json('data.0.pollable.title'));
        $this->assertLessThanOrEqual(83, mb_strlen($index->json('data.0.pollable.title')));
        $this->assertFalse($index->json('data.0.is_open'));

        $this->assertSame('thread', $index->json('data.1.pollable.type'));
        $this->assertSame('Existing thread', $index->json('data.1.pollable.title'));
        $this->assertSame($thread->url, $index->json('data.1.pollable.url'));
        $this->assertTrue($index->json('data.1.can_edit'));

        $open = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls?status=open')->assertStatus(200);
        $this->assertSame([$threadPoll->id], array_column($open->json('data'), 'id'));

        $closed = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls?status=closed')->assertStatus(200);
        $this->assertSame([$closedPoll->id], array_column($closed->json('data'), 'id'));

        $multiple = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls?type=multiple')->assertStatus(200);
        $this->assertSame([$closedPoll->id], array_column($multiple->json('data'), 'id'));

        $threads = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls?pollable=thread')->assertStatus(200);
        $this->assertSame([$threadPoll->id], array_column($threads->json('data'), 'id'));

        $search = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls?search=Closed')->assertStatus(200);
        $this->assertSame([$closedPoll->id], array_column($search->json('data'), 'id'));

        $paged = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls?per_page=1&page=2')->assertStatus(200);
        $this->assertSame([$threadPoll->id], array_column($paged->json('data'), 'id'));
        $this->assertSame(2, $paged->json('meta.last_page'));

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/polls?pollable=forum')->assertStatus(422);
    }

    public function test_admin_stats(): void
    {
        $thread = $this->createThread();
        $status = Status::create(['user_id' => $this->user->id, 'content' => 'Status']);

        $popular = $this->createPollOn($thread, null, ['title' => 'Popular']);
        $quiet   = $this->createPollOn($status, null, ['title' => 'Quiet', 'closes_at' => now()->subDay()]);
        $ids     = $popular->options->pluck('id')->all();

        foreach ([$this->user, $this->other, $this->admin] as $voter) {
            $this->actingAs($voter, 'sanctum')
                ->postJson("/api/polls/{$popular->id}/vote", ['option_ids' => [$ids[0]]])
                ->assertStatus(200);
        }

        $data = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/polls/stats')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(2, $data['total']);
        $this->assertSame(1, $data['open']);
        $this->assertSame(1, $data['closed']);
        $this->assertSame(3, $data['votes']);
        $this->assertSame(3, $data['votes_7d']);
        $this->assertSame(2, $data['polls_7d']);
        $this->assertSame(['thread' => 1, 'status' => 1, 'page' => 0, 'ticket' => 0], $data['by_type']);

        $this->assertCount(2, $data['most_voted']);
        $this->assertSame($popular->id, $data['most_voted'][0]['id']);
        $this->assertSame('Popular', $data['most_voted'][0]['title']);
        $this->assertSame(3, $data['most_voted'][0]['total_votes']);
        $this->assertSame('thread', $data['most_voted'][0]['pollable']['type']);
        $this->assertSame($quiet->id, $data['most_voted'][1]['id']);
        $this->assertSame('status', $data['most_voted'][1]['pollable']['type']);
    }

    public function test_admin_can_close_reopen_and_delete_polls(): void
    {
        $poll = $this->createPollOn($this->createThread());

        $closed = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/polls/{$poll->id}/close")
            ->assertStatus(200);
        $this->assertFalse($closed->json('poll.is_open'));
        $this->assertNotNull($closed->json('poll.closes_at'));
        $this->assertArrayHasKey('message', $closed->json());
        $this->assertFalse($poll->fresh()->is_open);

        $reopened = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/polls/{$poll->id}/reopen")
            ->assertStatus(200);
        $this->assertTrue($reopened->json('poll.is_open'));
        $this->assertNull($reopened->json('poll.closes_at'));
        $this->assertNull($poll->fresh()->closes_at);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/polls/{$poll->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['message']);
        $this->assertDatabaseMissing('polls', ['id' => $poll->id]);
        $this->assertDatabaseMissing('poll_options', ['poll_id' => $poll->id]);
    }
}
