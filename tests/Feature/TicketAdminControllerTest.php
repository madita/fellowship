<?php

namespace Tests\Feature;

use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TicketAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->user = User::factory()->create();
    }

    private function ticket(array $attributes = []): Ticket
    {
        return Ticket::factory()->createdBy($this->user)->create([
            'ticket_type_id' => TicketType::where('slug', 'support')->value('id'),
            'status'         => 'open',
            'priority'       => 'normal',
            ...$attributes,
        ]);
    }

    public function test_member_cannot_read_ticket_stats(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/admin/tickets/stats')->assertForbidden();
    }

    public function test_queues_and_breakdowns_count_open_tickets(): void
    {
        $this->ticket(['priority' => 'urgent']);
        $this->ticket(['status' => 'in_progress', 'assigned_to_user_id' => $this->admin->id]);
        $this->ticket(['due_date' => now()->subDay()]);
        $this->ticket(['status' => 'closed', 'priority' => 'urgent']);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/tickets/stats');

        $response->assertOk()
            ->assertJsonPath('data.open', 3)
            ->assertJsonPath('data.unassigned', 2)
            ->assertJsonPath('data.overdue', 1)
            ->assertJsonPath('data.urgent', 1)
            ->assertJsonPath('data.by_status.open', 2)
            ->assertJsonPath('data.by_status.in_progress', 1)
            ->assertJsonPath('data.by_status.closed', 1)
            ->assertJsonPath('data.by_priority.urgent', 1)
            ->assertJsonPath('data.by_assignee.0.username', $this->admin->username)
            ->assertJsonPath('data.by_assignee.0.open', 1);

        $support = collect($response->json('data.by_type'))->firstWhere('slug', 'support');
        $this->assertSame(3, $support['open']);
    }

    public function test_trend_and_resolution_time(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));

        $ticket = $this->ticket(['created_at' => now()->subHours(10)]);
        $ticket->update(['status' => 'resolved']);
        $this->ticket(['created_at' => now()->subDays(40)]);

        $response = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/tickets/stats');

        $trend = $response->json('data.trend');
        $this->assertCount(30, $trend);
        $this->assertSame(['date' => now()->toDateString(), 'created' => 1, 'resolved' => 1], end($trend));

        $response->assertJsonPath('data.created_7d', 1)
            ->assertJsonPath('data.resolved_7d', 1)
            ->assertJsonPath('data.avg_resolution_hours', 10);
    }

    public function test_attention_lists_overdue_before_urgent(): void
    {
        $urgent  = $this->ticket(['priority' => 'urgent']);
        $overdue = $this->ticket(['due_date' => now()->subDay()]);
        $this->ticket();

        $ids = array_column(
            $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/tickets/stats')->json('data.attention'),
            'id'
        );

        $this->assertSame([$overdue->id, $urgent->id], $ids);
    }

    public function test_top_feedback_lists_voted_public_feedback(): void
    {
        $bug = $this->ticket([
            'ticket_type_id' => TicketType::where('slug', 'bug')->value('id'),
            'is_public'      => true,
        ]);
        $bug->toggleVote($this->admin);
        $this->ticket(['ticket_type_id' => TicketType::where('slug', 'feature')->value('id'), 'is_public' => true]);

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/tickets/stats')
            ->assertJsonCount(1, 'data.top_feedback')
            ->assertJsonPath('data.top_feedback.0.id', $bug->id)
            ->assertJsonPath('data.top_feedback.0.votes_count', 1);
    }
}
