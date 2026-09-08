<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The endpoints feeding the dashboard widgets return live data of the
 * features: upcoming events, recent wiki changes and community stats.
 */
class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->member = User::factory()->create();
    }

    private function createEvent(string $title, string $startDate, ?string $startTime = null, ?string $endDate = null): Event
    {
        $type = EventType::firstOrCreate(['name' => 'Meetup'], ['color' => '#123456', 'options' => '{}']);

        return Event::create([
            'title'         => $title,
            'user_id'       => $this->admin->id,
            'event_type_id' => $type->id,
            'startDate'     => $startDate,
            'startTime'     => $startTime,
            'endDate'       => $endDate ?? $startDate,
        ]);
    }

    private function createWikiPage(string $title, string $slug, User $author, bool $approved = true): Page
    {
        $locale = app()->getLocale();
        $page   = new Page(['user_id' => $author->id, 'slug' => $slug]);
        $page->translateOrNew($locale)->title   = $title;
        $page->translateOrNew($locale)->content = '<p>' . $title . '</p>';
        $page->save();

        $wiki = new Wiki(['slug' => $slug]);
        $wiki->translateOrNew($locale)->title = $title;
        $page->wikiable()->save($wiki);
        if ($approved) {
            $wiki->approve($this->admin);
        }

        return $page;
    }

    public function test_upcoming_events_are_sorted_and_limited(): void
    {
        $this->createEvent('Long ago', now()->subDays(30)->toDateString());
        $this->createEvent('Yesterday', now()->subDay()->toDateString());
        // Started yesterday but still running today.
        $this->createEvent('Festival', now()->subDay()->toDateString(), null, now()->addDay()->toDateString());
        $this->createEvent('Tonight', now()->toDateString(), '19:00:00');
        $this->createEvent('Today all day', now()->toDateString());
        $this->createEvent('Next week', now()->addDays(7)->toDateString());
        $this->createEvent('Next year', now()->addDays(200)->toDateString());

        $data = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/events/upcoming?limit=3')
            ->assertStatus(200)
            ->json('data');

        // Within the default 30-day window: Festival, Today all day, Tonight, Next week.
        $this->assertSame(4, $data['total']);
        $this->assertSame(['Festival', 'Today all day', 'Tonight'], array_column($data['events'], 'title'));
        $this->assertTrue($data['events'][1]['allDay']);
        $this->assertFalse($data['events'][2]['allDay']);
        $this->assertSame('Meetup', $data['events'][0]['type']);
        $this->assertSame('/events/' . $data['events'][0]['id'], $data['events'][0]['url']);

        // A wider window includes next year's event.
        $this->assertSame(5, $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/events/upcoming?days=365')
            ->json('data.total'));
    }

    public function test_recent_wiki_changes_come_from_revisions_of_visible_pages(): void
    {
        $this->actingAs($this->admin);
        $old = $this->createWikiPage('Old page', 'old-page', $this->admin);
        $this->createWikiPage('Pending page', 'pending-page', $this->admin, approved: false);

        $this->actingAs($this->member);
        $this->createWikiPage('Member page', 'member-page', $this->member);
        // Editing produces an "updated" revision that supersedes the creation.
        $old->translateOrNew(app()->getLocale())->content = '<p>Edited</p>';
        $old->touch();
        $old->revisions()->create([
            'revisionable_type' => $old->getTable(),
            'action'            => 'updated',
            'user_id'           => $this->member->id,
            'created_at'        => now()->addMinute(),
        ]);

        $changes = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/wiki/recent-changes')
            ->assertStatus(200)
            ->json('data');

        // One entry per page, newest change first; the unapproved page is hidden.
        $this->assertSame(['Old page', 'Member page'], array_column($changes, 'title'));
        $this->assertSame('updated', $changes[0]['action']);
        $this->assertSame($this->member->username, $changes[0]['author']['username']);
        $this->assertSame('/wiki/old-page', $changes[0]['url']);
        $this->assertSame('created', $changes[1]['action']);

        // Admins also see pending pages.
        $adminChanges = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/recent-changes?limit=2')
            ->json('data');
        $this->assertCount(2, $adminChanges);
        $this->assertContains('Pending page', array_column(
            $this->actingAs($this->admin, 'sanctum')->getJson('/api/wiki/recent-changes')->json('data'),
            'title'
        ));
    }

    public function test_stats_count_live_community_data_and_own_open_tickets(): void
    {
        $this->createEvent('Past', now()->subDays(3)->toDateString());
        $this->createEvent('Soon', now()->addDays(3)->toDateString());
        $this->actingAs($this->admin);
        $this->createWikiPage('Approved', 'approved', $this->admin);
        $this->createWikiPage('Pending', 'pending', $this->admin, approved: false);

        $term     = Term::firstOrCreateByTitle('General');
        $category = Taxonomy::create([
            'term_id'    => $term->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => [],
        ]);
        ForumThread::create([
            'taxonomy_id' => $category->id,
            'user_id'     => $this->member->id,
            'title'       => 'Hello',
            'body'        => '<p>First thread</p>',
        ]);

        $type = TicketType::firstOrCreate(['slug' => 'dashboard-test'], ['name' => 'Dashboard test', 'is_active' => true]);
        foreach ([['open', $this->member], ['resolved', $this->member], ['open', $this->admin]] as [$status, $creator]) {
            Ticket::create([
                'ticket_type_id'     => $type->id,
                'created_by_user_id' => $creator->id,
                'title'              => "Ticket {$status}",
                'status'             => $status,
                'priority'           => 'normal',
            ]);
        }

        $stats = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/account/dashboard/stats')
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(2, $stats['members']);
        $this->assertSame(1, $stats['upcoming_events']);
        $this->assertSame(1, $stats['wiki_pages']);
        $this->assertSame(1, $stats['forum_threads']);
        $this->assertSame(1, $stats['my_open_tickets']);
    }

    public function test_stats_require_authentication(): void
    {
        $this->getJson('/api/account/dashboard/stats')->assertStatus(401);
    }

    private function createTicket(User $creator, array $attributes = []): Ticket
    {
        $type = TicketType::firstOrCreate(['slug' => 'dashboard-test'], ['name' => 'Dashboard test', 'is_active' => true]);

        return Ticket::create(array_merge([
            'ticket_type_id'     => $type->id,
            'created_by_user_id' => $creator->id,
            'title'              => 'Ticket ' . uniqid(),
            'status'             => 'open',
            'priority'           => 'normal',
        ], $attributes));
    }

    public function test_ticket_overview_counts_queues_for_members_and_admins(): void
    {
        $other = User::factory()->create();

        $this->createTicket($this->member);                                                              // mine, open
        $this->createTicket($other, ['assigned_to_user_id' => $this->member->id, 'due_date' => now()->subDay(), 'priority' => 'urgent']); // assigned to me, overdue
        $this->createTicket($other, ['assigned_to_user_id' => $this->member->id, 'due_date' => now()->addDays(3), 'status' => 'in_progress']); // assigned, due soon
        $this->createTicket($this->member, ['status' => 'resolved']);                                    // resolved recently
        $this->createTicket($other, ['status' => 'pending']);                                            // not mine, unassigned
        $this->createTicket($other, ['assigned_to_user_id' => $this->admin->id]);                        // not mine

        $member = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/account/dashboard/tickets')
            ->assertStatus(200)
            ->json('data');

        $this->assertFalse($member['is_admin']);
        $this->assertSame(3, $member['open']);
        $this->assertSame(2, $member['assigned_to_me']);
        $this->assertSame(1, $member['created_by_me']);
        $this->assertNull($member['unassigned']);
        $this->assertSame(1, $member['overdue']);
        $this->assertSame(1, $member['due_this_week']);
        $this->assertSame(1, $member['resolved_7_days']);
        $this->assertSame(['open' => 2, 'in_progress' => 1, 'pending' => 0], $member['by_status']);
        $this->assertSame(1, $member['by_priority']['urgent']);

        $admin = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/account/dashboard/tickets')
            ->json('data');

        $this->assertTrue($admin['is_admin']);
        $this->assertSame(5, $admin['open']);
        $this->assertSame(1, $admin['assigned_to_me']);
        $this->assertSame(0, $admin['created_by_me']);
        $this->assertSame(2, $admin['unassigned']);
        $this->assertSame(['open' => 3, 'in_progress' => 1, 'pending' => 1], $admin['by_status']);
    }

    public function test_members_see_tickets_assigned_to_them_and_can_filter_queues(): void
    {
        $other    = User::factory()->create();
        $assigned = $this->createTicket($other, ['assigned_to_user_id' => $this->member->id, 'title' => 'Assigned to member']);
        $created  = $this->createTicket($this->member, ['title' => 'Created by member', 'due_date' => now()->subDay()]);
        $this->createTicket($other, ['title' => 'Someone else']);

        $this->actingAs($this->member, 'sanctum');

        $titles = fn (array $params) => array_column($this->getJson('/api/tickets?' . http_build_query($params))->assertStatus(200)->json('data'), 'title');

        $this->assertEqualsCanonicalizing(['Assigned to member', 'Created by member'], $titles(['status' => 'open']));
        $this->assertSame(['Assigned to member'], $titles(['status' => 'open', 'assigned_to' => 'me']));
        $this->assertSame(['Created by member'], $titles(['status' => 'open', 'created_by' => 'me']));
        $this->assertSame(['Created by member'], $titles(['status' => 'open', 'due' => 'overdue']));
        $this->assertSame([], $titles(['status' => 'open', 'due' => 'week']));

        $this->getJson('/api/tickets/' . $assigned->id)->assertStatus(200);
        $this->getJson('/api/tickets/' . $created->id)->assertStatus(200);

        // Admins can narrow to their own set with mine=1.
        $this->createTicket($other, ['assigned_to_user_id' => $this->admin->id, 'title' => 'Admin queue']);
        $this->actingAs($this->admin, 'sanctum');
        $this->assertSame(['Admin queue'], $titles(['status' => 'open', 'mine' => 1]));
        $this->assertCount(4, $titles(['status' => 'open']));
    }

    public function test_recent_albums_are_newest_first_with_cover_and_counts(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        foreach (['First album', 'Second album', 'Third album'] as $i => $name) {
            $collection       = new Collection(['user_id' => $this->admin->id]);
            $collection->name = $name;
            $collection->save();
            $collection->created_at = now()->subDays(3 - $i);
            $collection->save();
        }
        $second = Collection::whereTranslation('name', 'Second album')->first();
        $second->addMedia(\Illuminate\Http\UploadedFile::fake()->image('cover.jpg', 40, 30))
            ->withCustomProperties(['is_cover' => true])
            ->toMediaCollection('images');
        $second->addMedia(\Illuminate\Http\UploadedFile::fake()->image('other.jpg', 40, 30))->toMediaCollection('images');

        $response = $this->getJson('/api/collections/recent?limit=2')
            ->assertStatus(200)
            ->json();

        $this->assertSame(3, $response['total']);
        $this->assertSame(['Third album', 'Second album'], array_column($response['data'], 'name'));
        $this->assertNull($response['data'][0]['cover']);
        $this->assertSame(0, $response['data'][0]['media_count']);
        $this->assertSame(2, $response['data'][1]['media_count']);
        $this->assertStringContainsString('cover', $response['data'][1]['cover']);
        $this->assertSame('/gallery/' . $second->slug, $response['data'][1]['url']);
    }
}
