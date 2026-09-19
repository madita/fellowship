<?php

namespace Tests\Feature;

use App\Jobs\SendDiscordWebhook;
use App\Models\DiscordWebhook;
use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Models\Wiki;
use App\Support\DiscordEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DiscordWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://discord.com/api/webhooks/123456789/abcdefg-hijklmn';

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

    private function webhook(array $events = [DiscordEvents::TICKET_CREATED]): DiscordWebhook
    {
        return DiscordWebhook::create([
            'name'   => 'Staff channel',
            'url'    => self::URL,
            'events' => $events,
        ]);
    }

    // ── Managing them ───────────────────────────────────

    public function test_members_cannot_manage_webhooks(): void
    {
        $this->actingAs($this->member, 'sanctum')->getJson('/api/admin/discord-webhooks')->assertForbidden();
    }

    public function test_guests_cannot_manage_webhooks(): void
    {
        $this->getJson('/api/admin/discord-webhooks')->assertUnauthorized();
    }

    public function test_an_admin_adds_a_webhook_and_the_address_stays_secret(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/discord-webhooks', [
            'name'   => 'Announcements',
            'url'    => self::URL,
            'events' => [DiscordEvents::WIKI_PAGE_APPROVED, DiscordEvents::TICKET_CREATED],
        ])->assertCreated();

        $this->assertArrayNotHasKey('url', $response->json());
        $response->assertJsonPath('url_hint', '…/123456789');

        $webhook = DiscordWebhook::firstOrFail();
        $this->assertSame(self::URL, $webhook->url);
        // Stored encrypted, not in the clear
        $this->assertStringNotContainsString('discord.com', $webhook->getRawOriginal('url'));
        $this->assertSame($this->admin->id, (int) $webhook->created_by_user_id);
    }

    public function test_an_address_that_is_not_a_discord_webhook_is_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/discord-webhooks', [
            'name'   => 'Somewhere else',
            'url'    => 'https://example.com/hook',
            'events' => [DiscordEvents::TICKET_CREATED],
        ])->assertUnprocessable()->assertJsonValidationErrors('url');
    }

    public function test_unknown_events_are_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/discord-webhooks', [
            'name'   => 'Anything',
            'url'    => self::URL,
            'events' => ['everything_that_happens'],
        ])->assertUnprocessable()->assertJsonValidationErrors('events.0');
    }

    public function test_editing_without_an_address_keeps_the_stored_one(): void
    {
        $webhook = $this->webhook();

        $this->actingAs($this->admin, 'sanctum')->patchJson("/api/admin/discord-webhooks/{$webhook->id}", [
            'name'   => 'Renamed',
            'url'    => '',
            'events' => [DiscordEvents::FEEDBACK_CREATED],
        ])->assertOk()->assertJsonPath('name', 'Renamed');

        $webhook->refresh();
        $this->assertSame(self::URL, $webhook->url);
        $this->assertSame([DiscordEvents::FEEDBACK_CREATED], $webhook->events);
    }

    public function test_a_test_message_is_delivered_and_the_result_kept(): void
    {
        Http::fake([self::URL => Http::response('', 204)]);
        $webhook = $this->webhook();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/discord-webhooks/{$webhook->id}/test")
            ->assertOk()
            ->assertJsonPath('delivered', true);

        $webhook->refresh();
        $this->assertSame(204, $webhook->last_status);
        $this->assertNotNull($webhook->last_sent_at);
        Http::assertSent(fn ($request) => $request->url() === self::URL && isset($request['embeds'][0]['title']));
    }

    // ── What gets announced ─────────────────────────────

    public function test_a_new_ticket_is_announced_only_to_subscribed_webhooks(): void
    {
        Bus::fake();
        $subscribed = $this->webhook([DiscordEvents::TICKET_CREATED]);
        $this->webhook([DiscordEvents::POST_PUBLISHED]);
        DiscordWebhook::create(['name' => 'Off', 'url' => self::URL, 'events' => [DiscordEvents::TICKET_CREATED], 'is_active' => false]);

        Ticket::factory()->createdBy($this->member)->create([
            'ticket_type_id' => TicketType::where('slug', 'support')->value('id'),
            'title'          => 'Login broken',
        ]);

        Bus::assertDispatchedTimes(SendDiscordWebhook::class, 1);
        Bus::assertDispatched(SendDiscordWebhook::class, fn (SendDiscordWebhook $job) => $job->webhook->is($subscribed)
            && $job->payload['embeds'][0]['title'] === 'Login broken');
    }

    public function test_public_feedback_is_announced_as_feedback_with_its_own_address(): void
    {
        Bus::fake();
        $this->webhook([DiscordEvents::FEEDBACK_CREATED]);

        $response = $this->actingAs($this->member, 'sanctum')->postJson('/api/feedback/tickets', [
            'type'        => 'bug',
            'title'       => 'Upload stops',
            'description' => '<p>At 50 %</p>',
        ])->assertCreated();

        Bus::assertDispatched(SendDiscordWebhook::class, function (SendDiscordWebhook $job) use ($response) {
            $embed = $job->payload['embeds'][0];

            return str_ends_with($embed['url'], "/feedback/{$response->json('id')}")
                && $embed['description'] === 'At 50 %'
                && $embed['author']['name'] === 'alice';
        });
    }

    public function test_a_new_forum_thread_is_announced(): void
    {
        Bus::fake();
        $this->webhook([DiscordEvents::FORUM_THREAD_CREATED]);

        $category = Taxonomy::create([
            'term_id'    => Term::firstOrCreateByTitle('General')->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => ['is_private' => false, 'is_locked' => false],
        ]);

        ForumThread::create([
            'taxonomy_id' => $category->id,
            'user_id'     => $this->member->id,
            'title'       => 'Say hello',
            'body'        => '<p>Welcome everyone</p>',
        ]);

        Bus::assertDispatched(SendDiscordWebhook::class, fn (SendDiscordWebhook $job) => $job->payload['embeds'][0]['title'] === 'Say hello');
    }

    public function test_a_wiki_page_is_announced_when_it_is_approved(): void
    {
        Bus::fake();
        $this->webhook([DiscordEvents::WIKI_PAGE_APPROVED]);

        $this->actingAs($this->member, 'sanctum')->postJson('/api/wiki', [
            'title'   => 'The Fellowship',
            'slug'    => 'the-fellowship',
            'content' => '<p>A long history</p>',
        ])->assertOk();

        // Waiting for review: nothing yet, that is a different event
        Bus::assertNotDispatched(SendDiscordWebhook::class);

        Wiki::where('slug', 'the-fellowship')->firstOrFail()->approve($this->admin);

        Bus::assertDispatched(SendDiscordWebhook::class, function (SendDiscordWebhook $job) {
            $embed = $job->payload['embeds'][0];

            return $embed['title'] === 'The Fellowship' && str_ends_with($embed['url'], '/wiki/the-fellowship');
        });
    }

    public function test_nothing_is_sent_when_no_webhook_wants_the_event(): void
    {
        Bus::fake();
        $this->webhook([DiscordEvents::POST_PUBLISHED]);

        Ticket::factory()->createdBy($this->member)->create([
            'ticket_type_id' => TicketType::where('slug', 'support')->value('id'),
        ]);

        Bus::assertNotDispatched(SendDiscordWebhook::class);
    }
}
