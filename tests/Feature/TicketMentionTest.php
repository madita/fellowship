<?php

namespace Tests\Feature;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use App\Notifications\TicketMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TicketMentionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create(['username' => 'boss']);
        $this->admin->assignRole('admin');

        $this->member = User::factory()->create(['username' => 'alice']);
        $this->other  = User::factory()->create(['username' => 'bob']);
    }

    private function mention(User $user): string
    {
        return "<span class=\"mention\" data-user-id=\"{$user->id}\" data-username=\"{$user->username}\">@{$user->username}</span>";
    }

    private function supportTicket(array $attributes = []): Ticket
    {
        return Ticket::factory()->createdBy($this->member)->create([
            'ticket_type_id' => TicketType::where('slug', 'support')->value('id'),
            ...$attributes,
        ]);
    }

    public function test_description_is_cleaned_and_keeps_mentions(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/tickets', [
            'ticket_type_id' => TicketType::where('slug', 'support')->value('id'),
            'title'          => 'Broken',
            'description'    => '<p><strong>Hi</strong> ' . $this->mention($this->member) . '</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ]);

        $response->assertCreated();
        $description = Ticket::findOrFail($response->json('id'))->description;

        $this->assertStringContainsString('<strong>Hi</strong>', $description);
        $this->assertStringContainsString('data-username="alice"', $description);
        $this->assertStringNotContainsString('script', $description);
        $this->assertStringNotContainsString('onerror', $description);
    }

    public function test_admin_can_assign_when_creating(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/tickets', [
            'ticket_type_id'      => TicketType::where('slug', 'support')->value('id'),
            'title'               => 'For bob',
            'assigned_to_user_id' => $this->other->id,
            'due_date'            => '2026-12-24',
        ])->assertCreated()->assertJsonPath('assigned_to_user_id', $this->other->id);
    }

    public function test_empty_comment_markup_is_rejected(): void
    {
        $ticket = $this->supportTicket();

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/comments", ['comment' => '<p> </p><script>x</script>'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    }

    public function test_mention_in_comment_notifies_members_who_can_open_the_ticket(): void
    {
        Notification::fake();
        $ticket = $this->supportTicket();

        // bob cannot open a private support ticket; the admin can
        $this->actingAs($this->member, 'sanctum')->postJson("/api/tickets/{$ticket->id}/comments", [
            'comment' => '<p>' . $this->mention($this->admin) . ' ' . $this->mention($this->other) . '</p>',
        ])->assertCreated();

        Notification::assertSentTo($this->admin, TicketMentionNotification::class,
            fn ($n) => $n->toArray($this->admin)['url'] === "/admin/tickets/{$ticket->id}");
        Notification::assertNotSentTo($this->other, TicketMentionNotification::class);
    }

    public function test_internal_note_mentions_reach_admins_only(): void
    {
        Notification::fake();
        $ticket = $this->supportTicket();

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/tickets/{$ticket->id}/comments", [
            'comment'     => '<p>' . $this->mention($this->member) . '</p>',
            'is_internal' => true,
        ])->assertCreated();

        Notification::assertNotSentTo($this->member, TicketMentionNotification::class);
    }

    public function test_mentioned_watcher_gets_the_mention_only(): void
    {
        Notification::fake();
        $ticket = $this->supportTicket(['ticket_type_id' => TicketType::where('slug', 'bug')->value('id'), 'is_public' => true]);
        $ticket->watch($this->other);

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/tickets/{$ticket->id}/comments", [
            'comment' => '<p>' . $this->mention($this->other) . ' can you check?</p>',
        ])->assertCreated();

        Notification::assertSentTo($this->other, TicketMentionNotification::class,
            fn ($n) => $n->toArray($this->other)['url'] === "/feedback/{$ticket->id}");
        Notification::assertNotSentTo($this->other, TicketActivityNotification::class);
    }

    public function test_editing_a_description_only_notifies_new_mentions(): void
    {
        $ticket = $this->supportTicket(['description' => '<p>' . $this->mention($this->admin) . '</p>']);
        Notification::fake();

        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/tickets/{$ticket->id}", [
            'description' => '<p>' . $this->mention($this->admin) . ' ' . $this->mention($this->member) . '</p>',
            'assigned_to_user_id' => $this->other->id,
        ])->assertOk();

        // The author does not notify themselves; alice is new
        Notification::assertSentTo($this->member, TicketMentionNotification::class,
            fn ($n) => $n->toArray($this->member)['url'] === "/account/tickets/{$ticket->id}");
        Notification::assertNotSentTo($this->admin, TicketMentionNotification::class);
    }

    public function test_feedback_description_and_comments_are_cleaned(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')->postJson('/api/feedback/tickets', [
            'type'        => 'bug',
            'title'       => 'XSS',
            'description' => '<p>Look</p><script>alert(1)</script>',
        ])->assertCreated();

        $id = $response->json('id');
        $this->assertSame('<p>Look</p>', Ticket::findOrFail($id)->description);

        $this->actingAs($this->other, 'sanctum')
            ->postJson("/api/feedback/tickets/{$id}/comments", ['comment' => '<p>Same</p><iframe src="x"></iframe>'])
            ->assertCreated()
            ->assertJsonPath('comment', '<p>Same</p>');
    }
}
