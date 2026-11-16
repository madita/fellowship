<?php

namespace Tests\Feature;

use App\Models\Forum\ForumThread;
use App\Models\Poll\Poll;
use App\Models\Poll\PollOption;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one votes table, shared by poll options and tickets.
 *
 * The per-feature behaviour is covered by PollTest and
 * FeedbackControllerTest; this is about the two of them sharing a table
 * without treading on each other.
 */
class VoteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_the_two_kinds_of_vote_live_in_one_table(): void
    {
        $ticket = $this->ticket();
        $poll   = $this->poll();

        $ticket->toggleVote($this->user);
        $poll->options->first()->vote($this->user);

        $this->assertDatabaseCount('voteable', 2);
        $this->assertSame(
            [PollOption::class, Ticket::class],
            Vote::query()->pluck('voteable_type')->sort()->values()->all()
        );
    }

    /**
     * The achievement for voting in a poll must not fire for a ticket
     * up-vote. Both are rows in the same table now, so only the voteable
     * tells them apart.
     */
    public function test_a_ticket_up_vote_is_not_a_poll_vote(): void
    {
        $ticket = $this->ticket();

        $ticket->toggleVote($this->user);

        $this->assertSame(0, Vote::where('voteable_type', PollOption::class)->count());
        $this->assertSame(1, Vote::where('voteable_type', Ticket::class)->count());
    }

    public function test_a_poll_counts_only_its_own_votes(): void
    {
        $ticket = $this->ticket();
        $poll   = $this->poll();
        $other  = $this->poll();

        $ticket->toggleVote($this->user);
        $other->options->first()->vote($this->user);

        $this->assertSame(0, $poll->fresh()->total_votes);

        $poll->options->first()->vote($this->user);

        $this->assertSame(1, $poll->fresh()->total_votes);
    }

    public function test_a_poll_reports_which_options_a_member_picked(): void
    {
        $poll    = $this->poll();
        $options = $poll->options;

        $options[1]->vote($this->user);

        $this->assertSame([$options[1]->id], $poll->fresh()->userVotes($this->user));
        $this->assertTrue($poll->fresh()->hasVoted($this->user));
    }

    public function test_poll_results_count_per_option(): void
    {
        $poll    = $this->poll();
        $options = $poll->options;
        $second  = User::factory()->create();

        $options[0]->vote($this->user);
        $options[0]->vote($second);
        $options[1]->vote($second);

        $results = collect($poll->fresh()->results())->keyBy('id');

        $this->assertSame(2, $results[$options[0]->id]['votes']);
        $this->assertSame(1, $results[$options[1]->id]['votes']);
    }

    public function test_the_same_thing_cannot_be_voted_for_twice(): void
    {
        $ticket = $this->ticket();

        $ticket->vote($this->user);
        $ticket->vote($this->user);

        $this->assertSame(1, $ticket->votes()->count());
    }

    public function test_voting_toggles_off(): void
    {
        $ticket = $this->ticket();

        $this->assertTrue($ticket->toggleVote($this->user));
        $this->assertFalse($ticket->toggleVote($this->user));
        $this->assertDatabaseCount('voteable', 0);
    }

    // ── Cleanup, which a morph has no foreign key for ───────────────

    public function test_a_soft_deleted_ticket_keeps_its_votes(): void
    {
        $ticket = $this->ticket();
        $ticket->vote($this->user);

        $ticket->delete();

        $this->assertDatabaseCount('voteable', 1);
    }

    public function test_really_deleting_a_ticket_clears_its_votes(): void
    {
        $ticket = $this->ticket();
        $ticket->vote($this->user);

        $ticket->forceDelete();

        $this->assertDatabaseCount('voteable', 0);
    }

    public function test_deleting_a_poll_option_clears_its_votes(): void
    {
        $poll = $this->poll();
        $poll->options->first()->vote($this->user);

        $poll->options->first()->delete();

        $this->assertDatabaseCount('voteable', 0);
    }

    public function test_deleting_a_member_clears_their_votes(): void
    {
        $ticket = $this->ticket();
        $voter  = User::factory()->create();

        $ticket->vote($voter);
        $voter->delete();

        $this->assertDatabaseCount('voteable', 0);
    }

    /**
     * Ticket was always listed as pollable and the endpoint accepted it,
     * but without the trait nothing could read the poll back off a ticket.
     */
    public function test_a_ticket_can_carry_a_poll(): void
    {
        $ticket = $this->ticket();

        $poll = Poll::create([
            'pollable_type' => Ticket::class,
            'pollable_id'   => $ticket->id,
            'title'         => 'Which fix first?',
            'type'          => 'single',
            'created_by'    => $this->user->id,
        ]);

        $this->assertSame($poll->id, $ticket->polls()->first()?->id);
    }

    // ── Fixtures ────────────────────────────────────────────────────

    private function ticket(array $overrides = []): Ticket
    {
        $type = TicketType::firstOrCreate(
            ['slug' => 'bug'],
            ['name' => 'Bug', 'is_active' => true]
        );

        return Ticket::create(array_merge([
            'title'              => 'Something is broken',
            'description'        => 'It broke',
            'ticket_type_id'     => $type->id,
            'created_by_user_id' => $this->user->id,
            'is_public'          => true,
        ], $overrides));
    }

    private function poll(): Poll
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

        $poll = Poll::create([
            'pollable_type' => ForumThread::class,
            'pollable_id'   => $thread->id,
            'title'         => 'When do we leave?',
            'type'          => 'single',
            'created_by'    => $this->user->id,
        ]);

        $poll->options()->create(['option_text' => 'At dawn', 'position' => 0]);
        $poll->options()->create(['option_text' => 'At dusk', 'position' => 1]);

        return $poll->load('options');
    }
}
