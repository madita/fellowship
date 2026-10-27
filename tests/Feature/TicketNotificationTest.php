<?php

namespace Tests\Feature;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A new ticket reaches the people who handle them, and whoever opened it
 * follows it from the start so the replies reach them too.
 */
class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $otherAdmin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->otherAdmin = User::factory()->create();
        $this->otherAdmin->assignRole('admin');

        $this->member = User::factory()->create();
    }

    private function type(string $slug = 'support'): TicketType
    {
        // The migrations seed support / bug / wiki_approval and the rest
        return TicketType::where('slug', $slug)->firstOrFail();
    }

    private function ticket(array $attributes = []): Ticket
    {
        return Ticket::create(array_merge([
            'ticket_type_id'     => $this->type()->id,
            'created_by_user_id' => $this->member->id,
            'title'              => 'The lights are out',
            'description'        => 'Nothing on the landing page renders.',
            'status'             => 'open',
            'priority'           => 'normal',
        ], $attributes));
    }

    // ----------------------------------------------------------------
    // Telling the people who handle tickets
    // ----------------------------------------------------------------

    public function test_opening_a_ticket_notifies_the_admins(): void
    {
        Notification::fake();

        $this->ticket();

        Notification::assertSentTo(
            [$this->admin, $this->otherAdmin],
            TicketActivityNotification::class
        );
    }

    /**
     * The arrival of your own ticket is not news to you, and you are
     * watching it anyway.
     */
    public function test_the_creator_is_not_told_about_their_own_ticket(): void
    {
        Notification::fake();

        $this->ticket();

        Notification::assertNotSentTo($this->member, TicketActivityNotification::class);
    }

    public function test_an_admin_opening_a_ticket_is_not_told_about_it(): void
    {
        Notification::fake();

        $this->ticket(['created_by_user_id' => $this->admin->id]);

        Notification::assertNotSentTo($this->admin, TicketActivityNotification::class);
        Notification::assertSentTo($this->otherAdmin, TicketActivityNotification::class);
    }

    /**
     * A member who merely *reads* public feedback is not staff: telling
     * everyone who can see a bug report about every bug report would be
     * noise rather than news.
     */
    public function test_an_ordinary_member_is_not_told_about_other_peoples_tickets(): void
    {
        $bystander = User::factory()->create();

        Notification::fake();

        $this->ticket();

        Notification::assertNotSentTo($bystander, TicketActivityNotification::class);
    }

    public function test_the_notification_says_what_kind_of_ticket_and_who_opened_it(): void
    {
        $ticket = $this->ticket();

        $payload = (new TicketActivityNotification($ticket, 'created'))->toArray($this->admin);

        $this->assertSame('ticket_created', $payload['type']);
        $this->assertSame($this->member->username, $payload['creator']);
        $this->assertSame('The lights are out', $payload['ticket_title']);
        $this->assertNotEmpty($payload['ticket_type']);
    }

    /**
     * The link used to be /feedback/{id} for everyone, which is the one
     * page an admin handling a support ticket cannot use.
     */
    public function test_each_recipient_is_sent_the_page_they_can_actually_open(): void
    {
        $ticket = $this->ticket();
        $notification = new TicketActivityNotification($ticket, 'created');

        $this->assertSame(
            "/admin/tickets/{$ticket->id}",
            $notification->toArray($this->admin)['url']
        );

        // Its author reaches it through their own tickets
        $this->assertSame(
            "/account/tickets/{$ticket->id}",
            $notification->toArray($this->member)['url']
        );
    }

    // ----------------------------------------------------------------
    // Watching your own
    // ----------------------------------------------------------------

    public function test_whoever_opens_a_ticket_watches_it(): void
    {
        $ticket = $this->ticket();

        $this->assertTrue($ticket->isWatchedBy($this->member));
    }

    /**
     * The feedback page already did this for its own tickets. A ticket
     * raised anywhere else — the admin screen, an approval ticket a wiki
     * page or a podcast raised on its author's behalf — left its author
     * unsubscribed, so the reply telling them what happens next reached
     * everyone except them.
     */
    public function test_an_approval_ticket_is_watched_by_the_member_it_was_raised_for(): void
    {
        $ticket = $this->ticket([
            'ticket_type_id' => $this->type('wiki_approval')->id,
            'title'          => 'New Wiki Page: The Watch',
        ]);

        $this->assertTrue($ticket->isWatchedBy($this->member));
    }

    public function test_watching_means_the_status_change_reaches_the_author(): void
    {
        $ticket = $this->ticket();

        Notification::fake();

        $ticket->update(['status' => 'resolved']);

        Notification::assertSentTo($this->member, TicketActivityNotification::class);
    }

    public function test_a_ticket_with_no_creator_still_gets_made(): void
    {
        // Nothing to watch it and nobody to leave out of the announcement
        $ticket = $this->ticket(['created_by_user_id' => null]);

        $this->assertNotNull($ticket->id);
        $this->assertSame(0, $ticket->watches()->count());
    }

    /**
     * A ticket is somebody's actual work; announcing it must not be able
     * to take it down with it.
     *
     * Aimed at this one method rather than at creation as a whole: the
     * other things the created hook does — notifyMentions in particular
     * — are not exception-safe, so failing every notification would be
     * testing their behaviour, not this one's.
     */
    public function test_a_failing_announcement_is_swallowed(): void
    {
        $ticket = $this->ticket();

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('mail is down'));

        $ticket->notifyStaffOfNewTicket();

        // Reaching here at all is the assertion: nothing propagated
        $this->assertNotNull($ticket->fresh());
    }
}
