<?php

namespace Tests\Feature;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketTag;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->user  = User::factory()->create();
        $this->other = User::factory()->create();
    }

    /**
     * The migrations seed the bug / feature / support ticket types.
     */
    private function ticket(string $type = 'bug', array $attributes = []): Ticket
    {
        return Ticket::factory()->createdBy($this->user)->create([
            'ticket_type_id' => TicketType::where('slug', $type)->value('id'),
            'is_public'      => true,
            'status'         => 'open',
            ...$attributes,
        ]);
    }

    // ── Visibility ──────────────────────────────────────

    public function test_guest_lists_only_public_tickets_of_the_type(): void
    {
        $bug = $this->ticket('bug');
        $this->ticket('bug', ['is_public' => false]);
        $this->ticket('feature');
        $this->ticket('support');

        $response = $this->getJson('/api/feedback/tickets?type=bug');

        $response->assertOk();
        $this->assertSame([$bug->id], array_column($response->json('data'), 'id'));
    }

    public function test_without_type_both_bugs_and_features_are_listed(): void
    {
        $bug     = $this->ticket('bug');
        $feature = $this->ticket('feature');
        $this->ticket('support');

        $ids = array_column($this->getJson('/api/feedback/tickets')->json('data'), 'id');

        $this->assertEqualsCanonicalizing([$bug->id, $feature->id], $ids);
    }

    public function test_non_feedback_tickets_are_not_reachable(): void
    {
        $support = $this->ticket('support');

        $this->getJson("/api/feedback/tickets/{$support->id}")->assertNotFound();
        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/feedback/tickets/{$support->id}/comments", ['comment' => 'Hi'])
            ->assertNotFound();
    }

    public function test_private_ticket_is_visible_to_creator_and_admin_only(): void
    {
        $ticket = $this->ticket('bug', ['is_public' => false]);

        $this->getJson("/api/feedback/tickets/{$ticket->id}")->assertNotFound();
        $this->actingAs($this->other, 'sanctum')->getJson("/api/feedback/tickets/{$ticket->id}")->assertNotFound();
        $this->actingAs($this->user, 'sanctum')->getJson("/api/feedback/tickets/{$ticket->id}")->assertOk();
        $this->actingAs($this->admin, 'sanctum')->getJson("/api/feedback/tickets/{$ticket->id}")->assertOk();
    }

    public function test_tickets_created_elsewhere_are_private_by_default(): void
    {
        $ticket = Ticket::factory()->create([
            'ticket_type_id' => TicketType::where('slug', 'bug')->value('id'),
        ]);

        $this->assertFalse($ticket->fresh()->is_public);
    }

    public function test_author_data_does_not_expose_the_email(): void
    {
        $ticket = $this->ticket();
        $ticket->comments()->create(['user_id' => $this->other->id, 'comment' => 'Same here']);

        $response = $this->getJson("/api/feedback/tickets/{$ticket->id}");

        $response->assertOk()
            ->assertJsonPath('author.username', $this->user->username)
            ->assertJsonPath('comments.0.author.username', $this->other->username);
        $this->assertStringNotContainsString($this->user->email, $response->getContent());
        $this->assertStringNotContainsString($this->other->email, $response->getContent());
    }

    public function test_internal_comments_are_not_shown(): void
    {
        $ticket = $this->ticket();
        $ticket->comments()->create(['user_id' => $this->admin->id, 'comment' => 'Public', 'is_internal' => false]);
        $ticket->comments()->create(['user_id' => $this->admin->id, 'comment' => 'Secret', 'is_internal' => true]);

        $response = $this->getJson("/api/feedback/tickets/{$ticket->id}");

        $response->assertOk()->assertJsonPath('comments_count', 1);
        $this->assertSame(['Public'], array_column($response->json('comments'), 'comment'));
    }

    // ── Listing ─────────────────────────────────────────

    public function test_open_status_filter_covers_all_open_statuses(): void
    {
        $open       = $this->ticket('bug', ['status' => 'open']);
        $inProgress = $this->ticket('bug', ['status' => 'in_progress']);
        $this->ticket('bug', ['status' => 'resolved']);

        $ids = array_column($this->getJson('/api/feedback/tickets?type=bug&status=open')->json('data'), 'id');

        $this->assertEqualsCanonicalizing([$open->id, $inProgress->id], $ids);
    }

    public function test_popular_sort_orders_by_votes(): void
    {
        $quiet   = $this->ticket();
        $popular = $this->ticket();
        $popular->toggleVote($this->other);
        $popular->toggleVote($this->admin);
        $quiet->toggleVote($this->other);

        $response = $this->actingAs($this->other, 'sanctum')->getJson('/api/feedback/tickets?type=bug&sort=popular');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $popular->id)
            ->assertJsonPath('data.0.votes_count', 2)
            ->assertJsonPath('data.0.user_has_voted', true);
    }

    public function test_tag_filter(): void
    {
        $tag    = TicketTag::where('slug', 'mobile')->first();
        $tagged = $this->ticket();
        $tagged->tags()->attach($tag);
        $this->ticket();

        $ids = array_column($this->getJson('/api/feedback/tickets?type=bug&tag=mobile')->json('data'), 'id');

        $this->assertSame([$tagged->id], $ids);
    }

    // ── Create ──────────────────────────────────────────

    public function test_member_files_a_public_ticket_and_watches_it(): void
    {
        $tag = TicketTag::where('slug', 'performance')->first();

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/feedback/tickets', [
            'type'        => 'feature',
            'title'       => 'Dark mode for the wiki',
            'description' => 'Please.',
            'tag_ids'     => [$tag->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('type.slug', 'feature')
            ->assertJsonPath('user_is_watching', true)
            ->assertJsonPath('tags.0.slug', 'performance');

        $ticket = Ticket::findOrFail($response->json('id'));
        $this->assertTrue($ticket->is_public);
        $this->assertSame($this->user->id, (int) $ticket->created_by_user_id);
    }

    public function test_guest_cannot_file_a_ticket(): void
    {
        $this->postJson('/api/feedback/tickets', ['type' => 'bug', 'title' => 'x', 'description' => 'y'])
            ->assertUnauthorized();
    }

    public function test_only_feedback_types_can_be_filed(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/feedback/tickets', ['type' => 'support', 'title' => 'x', 'description' => 'y'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    // ── Vote / watch / comment ──────────────────────────

    public function test_vote_toggles(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->other, 'sanctum')->postJson("/api/feedback/tickets/{$ticket->id}/vote")
            ->assertOk()->assertJson(['voted' => true, 'votes_count' => 1]);
        $this->actingAs($this->other, 'sanctum')->postJson("/api/feedback/tickets/{$ticket->id}/vote")
            ->assertOk()->assertJson(['voted' => false, 'votes_count' => 0]);
    }

    public function test_watch_toggles(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->other, 'sanctum')->postJson("/api/feedback/tickets/{$ticket->id}/watch")
            ->assertOk()->assertJson(['watching' => true, 'watchers_count' => 1]);
        $this->actingAs($this->other, 'sanctum')->postJson("/api/feedback/tickets/{$ticket->id}/watch")
            ->assertOk()->assertJson(['watching' => false, 'watchers_count' => 0]);
    }

    public function test_commenting_watches_and_notifies_the_other_watchers(): void
    {
        Notification::fake();
        $ticket = $this->ticket();
        $ticket->watch($this->user);

        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/feedback/tickets/{$ticket->id}/comments", ['comment' => 'Happens to me too'])
            ->assertCreated()
            ->assertJsonPath('is_official', false);

        $this->assertTrue($ticket->watchers()->where('user_id', $this->other->id)->exists());
        Notification::assertSentTo($this->user, TicketActivityNotification::class,
            fn ($n) => $n->toArray($this->user)['type'] === 'ticket_comment');
        Notification::assertNotSentTo($this->other, TicketActivityNotification::class);
    }

    public function test_admin_comment_is_official(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/feedback/tickets/{$ticket->id}/comments", ['comment' => 'Fixed next release'])
            ->assertCreated()
            ->assertJsonPath('is_official', true);
    }

    public function test_internal_comment_does_not_notify_watchers(): void
    {
        Notification::fake();
        $ticket = $this->ticket();
        $ticket->watch($this->user);

        $ticket->comments()->create(['user_id' => $this->admin->id, 'comment' => 'Note', 'is_internal' => true]);

        Notification::assertNothingSent();
    }

    // ── Moderation ──────────────────────────────────────

    public function test_status_change_notifies_watchers_and_sets_timestamps(): void
    {
        Notification::fake();
        $ticket = $this->ticket();
        $ticket->watch($this->user);

        // Changed from the ticket admin — the same status the feedback pages show
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->resolved_at);
        Notification::assertSentTo($this->user, TicketActivityNotification::class,
            fn ($n) => $n->toArray($this->user)['status'] === 'resolved');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/tickets/{$ticket->id}", ['status' => 'open'])
            ->assertOk();

        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_watchers_who_lost_access_are_not_notified(): void
    {
        Notification::fake();
        $ticket = $this->ticket('bug', ['is_public' => false]);
        $ticket->watch($this->other);

        $ticket->update(['status' => 'in_progress']);

        Notification::assertNotSentTo($this->other, TicketActivityNotification::class);
    }

    public function test_marking_as_duplicate_closes_the_ticket(): void
    {
        $original  = $this->ticket();
        $duplicate = $this->ticket();

        $this->actingAs($this->admin, 'sanctum')
            // The moderation form always sends the unchanged status along
            ->patchJson("/api/feedback/tickets/{$duplicate->id}", ['status' => 'open', 'duplicate_of_ticket_id' => $original->id])
            ->assertOk()
            ->assertJsonPath('status', 'closed')
            ->assertJsonPath('duplicate_of.id', $original->id);

        $this->getJson("/api/feedback/tickets/{$original->id}")
            ->assertJsonPath('duplicates.0.id', $duplicate->id);
    }

    public function test_admin_can_hide_and_tag_a_ticket(): void
    {
        $ticket = $this->ticket();
        $tag    = TicketTag::where('slug', 'security')->first();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/feedback/tickets/{$ticket->id}", ['is_public' => false, 'tag_ids' => [$tag->id]])
            ->assertOk()
            ->assertJsonPath('is_public', false)
            ->assertJsonPath('tags.0.slug', 'security');
    }

    public function test_ticket_cannot_duplicate_itself(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/feedback/tickets/{$ticket->id}", ['duplicate_of_ticket_id' => $ticket->id])
            ->assertUnprocessable();
    }

    public function test_member_cannot_moderate(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/feedback/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertForbidden();
    }
}
