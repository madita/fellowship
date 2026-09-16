<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventLocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;
    protected EventType $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->eventType = EventType::create([
            'name'    => 'Treffen',
            'color'   => '#071CB4',
            'options' => json_encode([
                'location'        => ['custom', 'real', 'virtual'],
                'showAttributtes' => ['endDate'],
            ]),
        ]);

        $this->event = Event::create([
            'title'         => 'Test Event',
            'slug'          => 'test-event',
            'description'   => 'Keep this description',
            'user_id'       => $this->user->id,
            'event_type_id' => $this->eventType->id,
            'startDate'     => now()->format('Y-m-d'),
            'endDate'       => now()->addDays(2)->format('Y-m-d'),
        ]);

        // Simulate a details row written by the legacy migration jobs:
        // options data and coordinates that no event edit may destroy.
        $this->event->details()->create([
            'lat'     => 52.52,
            'lng'     => 13.405,
            'options' => json_encode([
                'albumName'    => 'Legacy Album',
                'creator'      => 'importer',
                'lastEditedBy' => 'importer',
            ]),
        ]);
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'title'         => 'Updated Event',
            'event_type_id' => $this->eventType->id,
        ], $overrides);
    }

    private function detailOptions(): array
    {
        $details = $this->event->details()->first();
        $this->assertNotNull($details, 'The event_details row must never be deleted by an event edit.');

        return json_decode($details->options, true) ?: [];
    }

    /**
     * A user's own joined public channel, valid as an event location.
     */
    private function createOwnChannel(?User $owner = null, array $attributes = []): IrcChannel
    {
        $server = IrcServer::create([
            'name' => 'Test Net',
            'host' => 'irc.example.test',
            'port' => 6667,
        ]);

        $connection = IrcConnection::create([
            'user_id'       => ($owner ?? $this->user)->id,
            'irc_server_id' => $server->id,
            'nickname'      => 'tester',
            'status'        => 'connected',
        ]);

        return IrcChannel::create(array_merge([
            'irc_connection_id' => $connection->id,
            'name'              => '#general',
            'is_joined'         => true,
            'is_private'        => false,
        ], $attributes));
    }

    // --- Data preservation (the former hard-delete / overwrite bug) ---

    public function test_title_only_update_with_empty_location_preserves_details_row(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                // The frontend always sends a (possibly empty) location object.
                'extendedProps' => ['location' => ['type' => null]],
            ]));

        $response->assertStatus(200);

        $options = $this->detailOptions();
        $this->assertSame('Legacy Album', $options['albumName']);
        $this->assertSame('importer', $options['creator']);
        $this->assertEquals(52.52, (float) $this->event->details()->first()->lat);
    }

    public function test_update_without_location_key_leaves_details_untouched(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload())
            ->assertStatus(200);

        $options = $this->detailOptions();
        $this->assertSame('Legacy Album', $options['albumName']);
    }

    public function test_saving_a_location_merges_into_existing_options(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => ['type' => 'custom', 'text' => 'Town square']],
            ]))
            ->assertStatus(200);

        $options = $this->detailOptions();
        $this->assertSame('Legacy Album', $options['albumName'], 'Saving a location must not clobber other options data.');
        $this->assertSame('custom', $options['location']['type']);
        $this->assertSame('Town square', $options['location']['text']);
        $this->assertEquals(52.52, (float) $this->event->details()->first()->lat, 'A non-real location must not null migrated coordinates.');
    }

    public function test_clearing_a_location_removes_only_the_location_key(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => ['type' => 'custom', 'text' => 'Town square']],
            ]))
            ->assertStatus(200);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => ['type' => null]],
            ]))
            ->assertStatus(200);

        $options = $this->detailOptions();
        $this->assertArrayNotHasKey('location', $options);
        $this->assertSame('Legacy Album', $options['albumName']);
    }

    public function test_real_location_with_coordinates_updates_lat_lng(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => [
                    'type'    => 'real',
                    'address' => 'Main St 1',
                    'lat'     => 48.13,
                    'lng'     => 11.57,
                ]],
            ]))
            ->assertStatus(200);

        $details = $this->event->details()->first();
        $this->assertEquals(48.13, (float) $details->lat);
        $this->assertEquals(11.57, (float) $details->lng);
        $this->assertSame('Legacy Album', $this->detailOptions()['albumName']);
    }

    public function test_store_creates_details_row_with_location(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/events', [
                'title'         => 'New Event',
                'event_type_id' => $this->eventType->id,
                'start'         => now()->addDay()->toDateTimeString(),
                'end'           => now()->addDays(2)->toDateTimeString(),
                'extendedProps' => [
                    'event_type_id' => $this->eventType->id,
                    'location'      => ['type' => 'custom', 'text' => 'At the lake'],
                ],
            ]);

        $response->assertStatus(200);

        // title is a translated attribute — locate the new row by id.
        $event = Event::whereKeyNot($this->event->id)->latest('id')->firstOrFail();
        $options = json_decode($event->details()->first()->options, true);
        $this->assertSame('At the lake', $options['location']['text']);
    }

    // --- Partial extendedProps payloads (the former undefined-key 500s) ---

    public function test_update_succeeds_when_extended_props_lacks_event_type_id(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => ['type' => null]],
            ]))
            ->assertStatus(200);

        $this->assertSame($this->eventType->id, $this->event->fresh()->event_type_id);
    }

    public function test_update_preserves_description_when_only_sent_in_extended_props(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['description' => 'From the drawer'],
            ]))
            ->assertStatus(200);

        $this->assertSame('From the drawer', $this->event->fresh()->description);
    }

    public function test_update_keeps_description_when_absent_everywhere(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload())
            ->assertStatus(200);

        $this->assertSame('Keep this description', $this->event->fresh()->description);
    }

    public function test_store_succeeds_when_extended_props_lacks_description(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/events', [
                'title'         => 'Sparse Event',
                'event_type_id' => $this->eventType->id,
                'extendedProps' => ['location' => ['type' => null]],
            ])
            ->assertStatus(200);

        $created = Event::whereKeyNot($this->event->id)->latest('id')->firstOrFail();
        $this->assertSame($this->eventType->id, $created->event_type_id);
    }

    // --- URL scheme enforcement ---

    public function test_javascript_url_location_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => [
                    'type'        => 'virtual',
                    'virtualMode' => 'url',
                    'url'         => 'javascript:alert(document.cookie)',
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extendedProps.location.url']);
    }

    public function test_scheme_less_url_location_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => [
                    'type'        => 'virtual',
                    'virtualMode' => 'url',
                    'url'         => 'example.com/meet',
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extendedProps.location.url']);
    }

    public function test_https_url_location_is_stored(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => [
                    'type'        => 'virtual',
                    'virtualMode' => 'url',
                    'url'         => 'https://meet.example.com/room',
                ]],
            ]))
            ->assertStatus(200);

        $this->assertSame('https://meet.example.com/room', $this->detailOptions()['location']['url']);
    }

    // --- IRC channel ownership ---

    public function test_other_users_channel_is_rejected(): void
    {
        $otherChannel = $this->createOwnChannel(User::factory()->create());

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => [
                    'type'           => 'virtual',
                    'virtualMode'    => 'irc',
                    'irc_channel_id' => $otherChannel->id,
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extendedProps.location.irc_channel_id']);
    }

    public function test_own_private_dm_window_is_rejected(): void
    {
        $dmWindow = $this->createOwnChannel(null, ['name' => 'some_nickname', 'is_private' => true]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => [
                    'type'           => 'virtual',
                    'virtualMode'    => 'irc',
                    'irc_channel_id' => $dmWindow->id,
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extendedProps.location.irc_channel_id']);
    }

    public function test_own_channel_is_accepted_and_resolved_with_its_name(): void
    {
        $channel = $this->createOwnChannel();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => [
                    'type'           => 'virtual',
                    'virtualMode'    => 'irc',
                    'irc_channel_id' => $channel->id,
                ]],
            ]))
            ->assertStatus(200);

        $show = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/events/{$this->event->id}")
            ->assertStatus(200)
            ->json();

        $this->assertSame('#general', $show['event']['location']['irc_channel']);
        $this->assertSame($channel->id, $show['event']['location']['irc_channel_id']);
    }

    public function test_index_returns_resolved_location_objects(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/events/{$this->event->id}", $this->updatePayload([
                'extendedProps' => ['location' => ['type' => 'custom', 'text' => 'Town square']],
            ]))
            ->assertStatus(200);

        $events = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/events')
            ->assertStatus(200)
            ->json('data.events');

        $listed = collect($events)->firstWhere('id', $this->event->id);
        $this->assertSame('custom', $listed['location']['type']);
        $this->assertSame('Town square', $listed['location']['text']);
    }
}
