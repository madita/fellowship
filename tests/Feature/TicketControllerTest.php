<?php

namespace Tests\Feature;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $admin;
    protected User $user;
    protected TicketType $ticketType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->user = User::factory()->create();
        $this->ticketType = TicketType::factory()->create();
    }

    // ── Index / Pagination ──────────────────────────────

    public function test_per_page_defaults_to_20(): void
    {
        Ticket::factory()->count(25)->createdBy($this->user)->create([
            'ticket_type_id' => $this->ticketType->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tickets');

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
    }

    public function test_per_page_capped_at_100(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tickets?per_page=500');

        $response->assertOk();
        $this->assertEquals(100, $response->json('per_page'));
    }

    public function test_per_page_zero_clamped_to_1(): void
    {
        Ticket::factory()->createdBy($this->user)->create([
            'ticket_type_id' => $this->ticketType->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tickets?per_page=0');

        $response->assertOk();
        $this->assertEquals(1, $response->json('per_page'));
    }

    public function test_negative_per_page_clamped_to_1(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tickets?per_page=-10');

        $response->assertOk();
        $this->assertEquals(1, $response->json('per_page'));
    }

    // ── Create ──────────────────────────────────────────

    public function test_authenticated_user_can_create_ticket(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/tickets', [
                'ticket_type_id' => $this->ticketType->id,
                'title' => 'Test Ticket',
                'description' => 'Test description',
                'priority' => 'normal',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tickets', [
            'title' => 'Test Ticket',
            'status' => 'open',
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_create_ticket(): void
    {
        $response = $this->postJson('/api/tickets', [
            'ticket_type_id' => $this->ticketType->id,
            'title' => 'Test Ticket',
        ]);

        $response->assertStatus(401);
    }

    // ── Assign ──────────────────────────────────────────

    public function test_admin_can_assign_ticket(): void
    {
        $ticket = Ticket::factory()->createdBy($this->user)->create([
            'ticket_type_id' => $this->ticketType->id,
        ]);

        $assignee = User::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/assign", [
                'user_id' => $assignee->id,
            ]);

        $response->assertOk();
        $ticket->refresh();
        $this->assertEquals($assignee->id, $ticket->assigned_to_user_id);
    }

    public function test_non_admin_cannot_assign_ticket(): void
    {
        $ticket = Ticket::factory()->createdBy($this->user)->create([
            'ticket_type_id' => $this->ticketType->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/tickets/{$ticket->id}/assign", [
                'user_id' => $this->admin->id,
            ]);

        $response->assertStatus(403);
    }

    // ── Delete ──────────────────────────────────────────

    public function test_admin_can_delete_ticket(): void
    {
        $ticket = Ticket::factory()->createdBy($this->user)->create([
            'ticket_type_id' => $this->ticketType->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/tickets/{$ticket->id}");

        $response->assertOk();
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }
}
