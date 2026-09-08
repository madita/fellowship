<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\Page;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The admin overview endpoint aggregates moderation queues, community
 * and content numbers, system facts and recent items — for admins only.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create(['last_login_at' => now()]);
        $this->admin->assignRole('admin');
        $this->member = User::factory()->create(['email_verified_at' => null, 'last_login_at' => now()->subDays(3)]);
    }

    public function test_only_admins_can_load_the_overview(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
        $this->actingAs($this->member, 'sanctum')->getJson('/api/admin/dashboard')->assertStatus(403);
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/dashboard')->assertStatus(200);
    }

    public function test_overview_counts_queues_community_content_and_recent_items(): void
    {
        $this->actingAs($this->admin);

        // A pending and an approved wiki page.
        foreach ([['Pending page', 'pending-page', false], ['Live page', 'live-page', true]] as [$title, $slug, $approved]) {
            $page = new Page(['user_id' => $this->admin->id, 'slug' => $slug]);
            $page->translateOrNew(app()->getLocale())->title   = $title;
            $page->translateOrNew(app()->getLocale())->content = '<p>x</p>';
            $page->save();
            $wiki = new Wiki(['slug' => $slug]);
            $wiki->translateOrNew(app()->getLocale())->title = $title;
            $page->wikiable()->save($wiki);
            if ($approved) {
                $wiki->approve($this->admin);
            }
        }

        // An upcoming and a past event.
        $type = EventType::create(['name' => 'Meetup', 'color' => '#000', 'options' => '{}']);
        foreach ([now()->addDays(2), now()->subDays(2)] as $date) {
            Event::create([
                'title'         => 'Event',
                'user_id'       => $this->admin->id,
                'event_type_id' => $type->id,
                'startDate'     => $date->toDateString(),
                'endDate'       => $date->toDateString(),
            ]);
        }

        // Tickets: unassigned+overdue, assigned, a legacy claim, a closed one.
        $ticketType = TicketType::firstOrCreate(['slug' => 'dashboard-test'], ['name' => 'Test', 'is_active' => true]);
        $claimType  = TicketType::firstOrCreate(['slug' => 'legacy-account-claim'], ['name' => 'Legacy Account Claim', 'is_active' => true]);
        $base       = ['created_by_user_id' => $this->member->id, 'priority' => 'normal'];
        Ticket::create($base + ['ticket_type_id' => $ticketType->id, 'title' => 'Overdue', 'status' => 'open', 'due_date' => now()->subDay()]);
        Ticket::create($base + ['ticket_type_id' => $ticketType->id, 'title' => 'Assigned', 'status' => 'in_progress', 'assigned_to_user_id' => $this->admin->id]);
        Ticket::create($base + ['ticket_type_id' => $claimType->id, 'title' => 'Claim', 'status' => 'open', 'assigned_to_user_id' => $this->admin->id]);
        Ticket::create($base + ['ticket_type_id' => $ticketType->id, 'title' => 'Closed', 'status' => 'closed']);

        $data = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(1, $data['attention']['pending_wiki']);
        // Creating wiki pages also opens approval tickets (unassigned, open).
        $autoTickets = Ticket::open()->whereNull('assigned_to_user_id')->where('title', 'like', 'New Wiki Page%')->count();
        $this->assertSame(1 + $autoTickets, $data['attention']['unassigned_tickets']);
        $this->assertSame(1, $data['attention']['overdue_tickets']);
        $this->assertSame(1, $data['attention']['legacy_claims']);
        $this->assertSame(1, $data['attention']['unverified_users']);
        $this->assertSame(0, $data['attention']['failed_jobs']);

        $this->assertSame(2, $data['users']['total']);
        $this->assertSame(2, $data['users']['new_7d']);
        $this->assertSame(1, $data['users']['active_24h']);
        $this->assertSame(2, $data['users']['active_7d']);
        $this->assertSame(1, $data['users']['admins']);

        $this->assertSame(1, $data['content']['wiki_pages']);
        $this->assertSame(1, $data['content']['events_upcoming']);
        $this->assertSame(2, $data['content']['events_total']);
        $this->assertSame(3 + $autoTickets, $data['content']['open_tickets']);
        $this->assertSame('0 B', $data['content']['media_size']);

        $this->assertSame(PHP_VERSION, $data['system']['php_version']);
        $this->assertArrayHasKey('cache_driver', $data['system']);
        $this->assertFalse($data['system']['maintenance']);

        $this->assertSame($this->member->username, $data['recent']['users'][0]['username']);
        $this->assertContains('Overdue', array_column($data['recent']['tickets'], 'title'));
        $this->assertNotContains('Assigned', array_column($data['recent']['tickets'], 'title'));
    }
}
