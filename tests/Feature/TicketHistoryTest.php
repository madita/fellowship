<?php

namespace Tests\Feature;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TicketHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create(['username' => 'boss']);
        $this->admin->assignRole('admin');

        $this->member = User::factory()->create(['username' => 'alice']);
    }

    private function createTicket(): Ticket
    {
        $response = $this->actingAs($this->member, 'sanctum')->postJson('/api/tickets', [
            'ticket_type_id' => TicketType::where('slug', 'support')->value('id'),
            'title'          => 'Login broken',
            'description'    => '<p>First</p>',
        ])->assertCreated();

        return Ticket::findOrFail($response->json('id'));
    }

    public function test_history_lists_who_changed_what_newest_first(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/tickets/{$ticket->id}", ['status' => 'in_progress'])->assertOk();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/tickets/{$ticket->id}/assign", ['user_id' => $this->admin->id])->assertOk();
        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/tickets/{$ticket->id}", ['description' => '<p>Second</p>'])->assertOk();

        $entries = $this->actingAs($this->admin, 'sanctum')->getJson("/api/tickets/{$ticket->id}/history")
            ->assertOk()
            ->json('data');

        $this->assertSame(['updated', 'updated', 'updated', 'created'], array_column($entries, 'action'));
        $this->assertSame('boss', $entries[0]['user']['username']);
        $this->assertSame('alice', $entries[3]['user']['username']);

        $this->assertSame(['description'], array_column($entries[0]['changes'], 'field'));
        $this->assertSame('<p>Second</p>', $entries[0]['changes'][0]['new']);

        $assignment = collect($entries[1]['changes'])->firstWhere('field', 'assigned_to_user_id');
        $this->assertNull($assignment['old_display']);
        $this->assertSame('boss', $assignment['new_display']);

        $status = collect($entries[2]['changes'])->firstWhere('field', 'status');
        $this->assertSame(['open', 'in_progress'], [$status['old'], $status['new']]);
    }

    public function test_tickets_without_recorded_history_still_show_their_creation(): void
    {
        $ticket = Ticket::withoutEvents(fn () => Ticket::factory()->createdBy($this->member)->create([
            'ticket_type_id' => TicketType::where('slug', 'support')->value('id'),
        ]));

        $this->actingAs($this->member, 'sanctum')->getJson("/api/tickets/{$ticket->id}/history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'created')
            ->assertJsonPath('data.0.user.username', 'alice');
    }

    public function test_comments_are_part_of_the_activity(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/comments", ['comment' => '<p>Looking into it</p>'])
            ->assertCreated();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/comments", ['comment' => '<p>Secret</p>', 'is_internal' => true])
            ->assertCreated();

        $adminEntries = $this->actingAs($this->admin, 'sanctum')->getJson("/api/tickets/{$ticket->id}/history")->json('data');
        $this->assertSame(['commented', 'commented', 'created'], array_column($adminEntries, 'action'));
        $this->assertSame('Looking into it', $adminEntries[1]['excerpt']);
        $this->assertTrue($adminEntries[0]['is_internal']);

        // The member who opened the ticket does not see internal notes
        $memberEntries = $this->actingAs($this->member, 'sanctum')->getJson("/api/tickets/{$ticket->id}/history")->json('data');
        $this->assertSame(['commented', 'created'], array_column($memberEntries, 'action'));
        $this->assertSame('Looking into it', $memberEntries[0]['excerpt']);
    }

    public function test_dates_carry_their_timezone(): void
    {
        $ticket = $this->createTicket();

        $created = $this->actingAs($this->member, 'sanctum')->getJson("/api/tickets/{$ticket->id}/history")->json('data.0.created_at');

        // Without a zone the browser reads UTC as local time
        $this->assertMatchesRegularExpression('/(Z|[+-]\d{2}:\d{2})$/', $created);
    }

    public function test_other_members_cannot_read_the_history(): void
    {
        $ticket   = $this->createTicket();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')->getJson("/api/tickets/{$ticket->id}/history")->assertForbidden();
    }
}
